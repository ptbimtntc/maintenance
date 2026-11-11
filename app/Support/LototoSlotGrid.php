<?php

namespace App\Support;

use App\Http\Controllers\LototoBoardSettingsController;
use App\Models\LototoBoard;
use App\Models\LototoBoardSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Where LOTOTO board slots are drawn: a section (band) and a cell (grid_row, grid_column) inside it. The slot's
 * category only says what it holds (and a Tools slot's column span); its position number is its identity.
 *
 * Automatic layout: every section is packed in reading order (row, then column) without gaps, each slot taking
 * its column span (pack / reflow). A Tools slot changed from 1 to 2 columns pushes the slots after it along; from
 * 2 to 1, or a removed slot, lets them close up. Slot numbers and their order never change, only the drawn cells
 * are computed again, and the Edit Mode preview uses the same rule. A section whose stored cells still match the
 * legacy rule (its own category's slots, cell from the number) is "by number" and may be placed by number again.
 * Before the section column exists, a slot's section is its category.
 */
class LototoSlotGrid
{
    private const CATEGORIES = ['technician', 'supervisor', 'spare', 'tool'];

    private static ?bool $stored = null;

    private static ?bool $numbersMayRepeat = null;

    private static ?bool $sections = null;

    /**
     * Whether lototo_board_slots has the grid columns yet. Code may run before the migration (local database not
     * migrated yet, or a deploy ahead of `migrate`); until then nothing is written and the board works as before.
     */
    public static function isStored(): bool
    {
        return self::$stored ??= Schema::hasColumn('lototo_board_slots', 'grid_row');
    }

    /**
     * Whether a category may hold a repeated number: a slot that changes category keeps its number. Needs the
     * cells and the relaxed (non-unique) number index; before those migrations numbers stay unique per category.
     */
    public static function numbersMayRepeat(): bool
    {
        return self::$numbersMayRepeat ??= self::isStored()
            && ! collect(Schema::getIndexes('lototo_board_slots'))->pluck('name')->contains('lototo_slots_board_category_position_unique');
    }

    /** Whether slots have their own section column (else the section is the category). */
    public static function hasSections(): bool
    {
        return self::$sections ??= Schema::hasColumn('lototo_board_slots', 'section');
    }

    /** The band a slot is drawn in. */
    public static function sectionOf(object $slot): string
    {
        return self::hasSections() && filled($slot->section ?? null) ? $slot->section : $slot->category;
    }

    /** For tests that drop and re-add the columns within one process. */
    public static function forgetSchema(): void
    {
        self::$stored = null;
        self::$numbersMayRepeat = null;
        self::$sections = null;
    }

    public static function span(object $slot, string $category): int
    {
        return $category === 'tool' ? max(1, min(3, (int) ($slot->tool_column_span ?? 1))) : 1;
    }

    /**
     * The legacy cell of every number 1..max(highest number, $through): [number => [row, column, span]]. Slots are
     * taken by number (the first one when a number repeats); a missing number takes one column, as on the board.
     */
    public static function legacyCells(Collection $slots, string $category, int $columns, int $through = 0): array
    {
        $byNumber = $slots->sortBy('id')->unique(fn ($slot) => (int) $slot->position)->keyBy(fn ($slot) => (int) $slot->position);
        $last = max((int) $byNumber->keys()->max(), $through);
        $cells = [];
        if ($category !== 'tool') {
            foreach (range(1, max(1, $last)) as $number) {
                $cells[$number] = [intdiv($number - 1, $columns) + 1, (($number - 1) % $columns) + 1, 1];
            }

            return $last > 0 ? $cells : [];
        }

        $row = 1;
        $column = 1;
        foreach ($last > 0 ? range(1, $last) : [] as $number) {
            $span = $byNumber->has($number) ? self::span($byNumber->get($number), 'tool') : 1;
            if ($column + $span - 1 > $columns) {
                $row++;
                $column = 1;
            }
            $cells[$number] = [$row, $column, $span];
            $column += $span;
            if ($column > $columns) {
                $row++;
                $column = 1;
            }
        }

        return $cells;
    }

    /** [slot id => [row, column]] as the legacy rule places these slots (kept for the Tahap A callers and tests). */
    public static function cells(Collection $slots, string $category, int $columns): array
    {
        $legacy = self::legacyCells($slots, $category, $columns);

        return $slots->mapWithKeys(fn ($slot) => [$slot->id => array_slice($legacy[(int) $slot->position], 0, 2)])->all();
    }

    /**
     * Messages for cells that leave the section or overlap. $items: [label, row, column, span] per slot.
     */
    public static function conflicts(string $categoryLabel, iterable $items, int $columns): array
    {
        $messages = [];
        $taken = [];
        foreach ($items as [$label, $row, $column, $span]) {
            if ($row < 1 || $column < 1) {
                $messages[] = "{$label}: baris atau kolom tidak valid.";

                continue;
            }
            if ($column + $span - 1 > $columns) {
                $messages[] = $span > 1
                    ? "{$label} selebar {$span} kolom mulai kolom {$column} melewati batas {$categoryLabel} ({$columns} kolom). Section tidak dilebarkan otomatis."
                    : "{$label} berada di kolom {$column}, di luar lebar {$categoryLabel} ({$columns} kolom). Section tidak dilebarkan otomatis.";

                continue;
            }
            for ($offset = 0; $offset < $span; $offset++) {
                $cell = $row.':'.($column + $offset);
                if (isset($taken[$cell])) {
                    $messages[] = "{$label} bertabrakan dengan {$taken[$cell]} pada baris {$row} kolom ".($column + $offset)." di {$categoryLabel}. Tidak ada slot yang dipindahkan.";

                    continue 2;
                }
            }
            for ($offset = 0; $offset < $span; $offset++) {
                $taken[$row.':'.($column + $offset)] = $label;
            }
        }

        return $messages;
    }

    /**
     * [section => true] for every section still placed by number: only its own category's slots, no repeated
     * number, every cell where the legacy rule puts it (or no cells yet).
     */
    public static function byNumber(LototoBoard $board): array
    {
        if (! self::isStored()) {
            return [];
        }
        $columns = LototoBoardSettingsController::layoutColumns($board);
        $slots = self::boardSlots($board);
        $result = [];
        foreach (self::CATEGORIES as $section) {
            $sectionSlots = $slots->filter(fn ($slot) => self::sectionOf($slot) === $section);
            if ($sectionSlots->contains(fn ($slot) => $slot->grid_row === null || $slot->grid_column === null)) {
                $result[$section] = true;

                continue;
            }
            if ($sectionSlots->contains(fn ($slot) => $slot->category !== $section) || $sectionSlots->pluck('position')->duplicates()->isNotEmpty()) {
                $result[$section] = false;

                continue;
            }
            $legacy = self::legacyCells($sectionSlots, $section, $columns[$section]);
            $result[$section] = $sectionSlots->every(fn ($slot) => [$slot->grid_row, $slot->grid_column] === array_slice($legacy[(int) $slot->position], 0, 2));
        }

        return $result;
    }

    /**
     * Stores the cells after a change. Sections listed in $reflow (taken with byNumber() before the change) are
     * placed by number again, as the old pages always did. Every other section is packed (pack): slots keep their
     * reading order and close every gap, a slot without a cell comes last. A slot without a section gets its
     * category. A result where slots would overlap or leave their section is refused, so the caller's transaction
     * rolls back. Call inside that transaction.
     */
    public static function sync(LototoBoard|int $board, array $reflow = []): void
    {
        if (! self::isStored()) {
            return;
        }
        $board = $board instanceof LototoBoard ? $board->fresh() : LototoBoard::query()->find($board);
        if (! $board) {
            return;
        }
        $columns = LototoBoardSettingsController::layoutColumns($board);
        $slots = self::boardSlots($board);
        $target = [];
        $messages = [];
        foreach (self::CATEGORIES as $section) {
            $sectionSlots = $slots->filter(fn ($slot) => self::sectionOf($slot) === $section)->values();
            if ($sectionSlots->isEmpty()) {
                continue;
            }
            $width = $columns[$section];
            $label = LototoBoardSettingsController::categoryLabel($section);
            $pure = $sectionSlots->every(fn ($slot) => $slot->category === $section);
            if (($reflow[$section] ?? false) && $pure && $sectionSlots->pluck('position')->duplicates()->isEmpty()) {
                $legacy = self::legacyCells($sectionSlots, $section, $width);
                $cells = $sectionSlots->mapWithKeys(fn ($slot) => [$slot->id => array_slice($legacy[(int) $slot->position], 0, 2)])->all();
            } else {
                $cells = self::pack($sectionSlots, $section, $width);
            }
            $target += $cells;
            $messages = [...$messages, ...self::conflicts($label, $sectionSlots->map(fn ($slot) => [
                ($slot->category === $section ? $label : LototoBoardSettingsController::categoryLabel($slot->category))." {$slot->position}",
                $cells[$slot->id][0], $cells[$slot->id][1], self::span($slot, $slot->category),
            ]), $width)];
        }
        if ($messages !== []) {
            throw ValidationException::withMessages(['slots' => array_values(array_unique($messages))]);
        }

        if (self::hasSections()) {
            $unsectioned = $slots->filter(fn ($slot) => blank($slot->section));
            foreach ($unsectioned as $slot) {
                LototoBoardSlot::query()->whereKey($slot->id)->update(['section' => $slot->category]);
            }
        }
        $changed = $slots->filter(fn ($slot) => [$slot->grid_row, $slot->grid_column] !== $target[$slot->id]);
        if ($changed->isEmpty()) {
            return;
        }
        // Clear first so moving cells never meet the unique cell index halfway.
        LototoBoardSlot::query()->whereKey($changed->modelKeys())->update(['grid_row' => null, 'grid_column' => null]);
        foreach ($changed as $slot) {
            [$row, $column] = $target[$slot->id];
            LototoBoardSlot::query()->whereKey($slot->id)->update(['grid_row' => $row, 'grid_column' => $column]);
        }
    }

    /**
     * Packs one section's slots into $columns in reading order (row, then column, as they are drawn now): from the
     * top left, row by row, without gaps. A tool wider than what is left of a row starts the next row. Slots
     * without a cell come last, in the order given. $items: [key => [row|null, column|null, span]]; returns
     * [key => [row, column]]. The Edit Mode preview (lototo-board-draft.js reflowSection) uses the same rule.
     */
    public static function reflow(array $items, int $columns): array
    {
        $order = array_keys($items);
        usort($order, function ($a, $b) use ($items, $order) {
            $rank = fn ($key) => [$items[$key][0] ?? PHP_INT_MAX, $items[$key][1] ?? PHP_INT_MAX, array_search($key, $order, true)];

            return $rank($a) <=> $rank($b);
        });
        $cells = [];
        $row = 1;
        $column = 1;
        foreach ($order as $key) {
            $span = max(1, min($columns, (int) $items[$key][2]));
            if ($column + $span - 1 > $columns) {
                $row++;
                $column = 1;
            }
            $cells[$key] = [$row, $column];
            $column += $span;
        }

        return $cells;
    }

    /**
     * The board's one column count ("Kolom per baris — Seluruh Board"), set from Settings or Edit Mode: every band
     * takes it and every section's slots are packed into it in reading order (reflow). Slots keep their id,
     * category, number and content. Call inside the caller's transaction.
     */
    public static function applyBoardColumns(LototoBoard $board, int $columns): void
    {
        $board->update(['layout' => array_merge($board->layout ?? [], array_fill_keys(
            ['board_columns', 'technician_columns', 'supervisor_columns', 'spare_columns', 'tool_columns'], $columns,
        ))]);
        if (! self::isStored()) {
            return;
        }
        $slots = self::boardSlots($board);
        $target = [];
        foreach (self::CATEGORIES as $section) {
            $sectionSlots = $slots->filter(fn ($slot) => self::sectionOf($slot) === $section);
            $target += self::reflow($sectionSlots->mapWithKeys(fn ($slot) => [$slot->id => [
                $slot->grid_row === null ? null : (int) $slot->grid_row,
                $slot->grid_column === null ? null : (int) $slot->grid_column,
                self::span($slot, $slot->category),
            ]])->all(), $columns);
        }
        $changed = $slots->filter(fn ($slot) => [$slot->grid_row === null ? null : (int) $slot->grid_row, $slot->grid_column === null ? null : (int) $slot->grid_column] !== $target[$slot->id]);
        if ($changed->isEmpty()) {
            return;
        }
        LototoBoardSlot::query()->whereKey($changed->modelKeys())->update(['grid_row' => null, 'grid_column' => null]);
        foreach ($changed as $slot) {
            [$row, $column] = $target[$slot->id];
            LototoBoardSlot::query()->whereKey($slot->id)->update(['grid_row' => $row, 'grid_column' => $column]);
        }
    }

    private static function boardSlots(LototoBoard $board): Collection
    {
        return LototoBoardSlot::query()->where('board_id', $board->id)->orderBy('id')
            ->get(['id', 'category', ...(self::hasSections() ? ['section'] : []), 'position', 'tool_column_span', 'grid_row', 'grid_column']);
    }

    /**
     * Automatic layout of one section: its slots packed in their current reading order (stored cell, row then
     * column; a slot without a cell by its legacy cell, else last), each taking its span, without gaps (reflow).
     * A wider or narrower Tools slot, a removed slot or a slot changing category moves the slots after it; numbers
     * and order never change. The Edit Mode preview (lototo-board-draft.js packDraft) uses the same rule.
     */
    public static function pack(Collection $slots, string $section, int $columns): array
    {
        $legacy = self::legacyCells($slots->where('category', $section), $section, $columns);
        $items = [];
        foreach ($slots->values() as $slot) {
            [$row, $column] = $slot->grid_row !== null && $slot->grid_column !== null
                ? [(int) $slot->grid_row, (int) $slot->grid_column]
                : ($slot->category === $section && isset($legacy[(int) $slot->position]) ? array_slice($legacy[(int) $slot->position], 0, 2) : [null, null]);
            $items[$slot->id] = [$row, $column, self::span($slot, $slot->category)];
        }

        return self::reflow($items, $columns);
    }

    /**
     * What the board draws in one section: its slots (any category) plus empty placeholders for numbers of the
     * section's own category that have no slot, each with grid_row / grid_column set, in reading order. Stored
     * cells are used when every slot of the section has one; otherwise the legacy rule places the whole section,
     * exactly as before the cells existed. A placeholder only takes its legacy cell when nothing is drawn there.
     */
    public static function drawn(string $section, Collection $slots, int $columns, int $boardId, int $through = 0): array
    {
        $placeholder = fn (int $number) => new LototoBoardSlot(['board_id' => $boardId, 'category' => $section, 'position' => $number, 'is_enabled' => true]);
        $native = $slots->filter(fn ($slot) => $slot->category === $section);
        $legacy = self::legacyCells($native, $section, $columns, $through);
        $useCells = self::isStored() && $slots->isNotEmpty()
            && $slots->every(fn ($slot) => $slot->grid_row !== null && $slot->grid_column !== null);
        $drawn = collect();
        $taken = [];
        $occupy = function (int $row, int $column, int $span) use (&$taken) {
            for ($offset = 0; $offset < $span; $offset++) {
                $taken[$row.':'.($column + $offset)] = true;
            }
        };

        if ($useCells) {
            foreach ($slots as $slot) {
                $drawn->push($slot);
                $occupy((int) $slot->grid_row, (int) $slot->grid_column, self::span($slot, $slot->category));
            }
        } else {
            $byNumber = $native->keyBy(fn ($slot) => (int) $slot->position);
            foreach ($legacy as $number => [$row, $column]) {
                if ($byNumber->has($number)) {
                    $slot = $byNumber->get($number);
                    $slot->setAttribute('grid_row', $row);
                    $slot->setAttribute('grid_column', $column);
                    $drawn->push($slot);
                    $occupy($row, $column, self::span($slot, $section));
                }
            }
        }
        $numbers = $native->pluck('position')->map(fn ($number) => (int) $number)->flip();
        // With stored cells the board draws exactly its slots: a slot removed in Edit Mode does not come back as an
        // empty placeholder.
        foreach ($useCells ? [] : $legacy as $number => [$row, $column]) {
            if ($numbers->has($number) || isset($taken[$row.':'.$column]) || $column > $columns) {
                continue;
            }
            $slot = $placeholder($number);
            $slot->setAttribute('grid_row', $row);
            $slot->setAttribute('grid_column', $column);
            $drawn->push($slot);
            $occupy($row, $column, 1);
        }
        $drawn = $drawn->sortBy(fn ($slot) => [(int) $slot->grid_row, (int) $slot->grid_column])->values();

        return ['slots' => $drawn, 'rows' => max(1, (int) $drawn->max('grid_row'))];
    }
}
