<?php

namespace App\Models;

use App\Support\LototoSlotGrid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['business_unit_id', 'division_name', 'code', 'title', 'subtitle', 'layout', 'component_settings', 'is_active'])]
class LototoBoard extends Model
{
    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'component_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(LototoBoardSlot::class, 'board_id', 'id');
    }

    /**
     * Creates a Spare slot plus its own physical spare lock for every given position, with the next free codes
     * (nextSpareCodes). Existing locks and codes are never touched. Call inside a transaction.
     */
    public function provisionSpareLocks(array $positions): void
    {
        if ($positions === []) {
            return;
        }
        $byNumber = LototoSlotGrid::byNumber($this);

        foreach (array_map(null, $positions, $this->nextSpareCodes(count($positions))) as [$position, $code]) {
            $lock = SpareLock::query()->create([
                'board_id' => $this->id,
                'code' => $code,
                'is_active' => true,
            ]);
            LototoBoardSlot::query()->create([
                'board_id' => $this->id,
                'category' => 'spare',
                'position' => $position,
                'spare_lock_id' => $lock->id,
                'is_enabled' => true,
            ]);
        }
        LototoSlotGrid::sync($this, $byNumber);
    }

    /**
     * The Business Unit initial in new spare lock codes (SP-{initial}-01), from the unit's name: the first letter of
     * each word (HALF PRODUCT -> HP, STEEL CORD -> SC) or the first two letters of a one-word name (DRAMIX -> DR).
     * Business Units with the same initial share one series, so their codes still never repeat.
     */
    public function spareCodeInitial(): string
    {
        $unit = $this->businessUnit()->first();
        $words = array_values(array_filter(explode('-', self::codePart($unit?->name))));
        if (count($words) > 1) {
            return implode('', array_map(fn ($word) => $word[0], $words));
        }

        return substr($words[0] ?? 'BU', 0, 2) ?: 'BU';
    }

    /**
     * The next $count spare lock codes for this board: SP-{initial}-01, -02, ... in its Business Unit's series,
     * continuing after the highest number used so far (a number is never handed out twice, also after a lock was
     * removed), two digits (three from 100). Codes of other formats saved before stay as they are and keep working.
     * The series is locked, so two requests at once cannot take the same code (the unique index on spare_locks.code
     * is the last guard). Call inside a transaction.
     */
    public function nextSpareCodes(int $count): array
    {
        $prefix = 'SP-'.$this->spareCodeInitial().'-';
        $highest = SpareLock::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->pluck('code')
            ->map(fn ($code) => preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/i', (string) $code, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return $count > 0 ? array_map(fn ($offset) => sprintf('%s%02d', $prefix, $highest + $offset), range(1, $count)) : [];
    }

    private static function codePart(?string $value): string
    {
        $ascii = strtoupper(Str::ascii((string) $value));

        return rtrim(substr(trim((string) preg_replace('/[^A-Z0-9]+/', '-', $ascii), '-'), 0, 20), '-');
    }
}
