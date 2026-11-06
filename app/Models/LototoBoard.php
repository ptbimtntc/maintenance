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
     * The division part of new spare lock codes: the division name in capitals, other characters as single
     * hyphens (DRAMIX, STEEL-CORD). When an older board already has the same division, the Business Unit code is
     * added (MAINTENANCE-HP), so two boards never draw from the same series and a board keeps its series when a
     * later board of the same division is added.
     */
    public function spareCodePrefix(): string
    {
        $division = self::codePart($this->division_name) ?: 'BOARD';
        $shared = self::query()->where('id', '<', $this->id)->pluck('division_name')
            ->contains(fn ($name) => self::codePart($name) === $division);
        if ($shared) {
            $unit = $this->businessUnit()->first();
            $unitPart = self::codePart(preg_replace('/^BU[\s_-]+/i', '', (string) ($unit?->code ?: $unit?->name)));
            $division .= '-'.($unitPart ?: $this->id);
        }

        return $division;
    }

    /**
     * The next $count unused spare lock codes for this board: "{prefix}-SP-001", "-002", ... unique across all boards.
     * The codes of that series are locked, so two requests at once cannot take the same code (the unique index on
     * spare_locks.code is the last guard). Call inside a transaction.
     */
    public function nextSpareCodes(int $count): array
    {
        $prefix = $this->spareCodePrefix().'-SP-';
        $used = SpareLock::query()->where('code', 'like', $prefix.'%')->lockForUpdate()->pluck('code')
            ->map(fn ($code) => strtoupper($code))
            ->flip();
        $codes = [];
        $number = 0;
        while (count($codes) < $count) {
            $code = sprintf('%s%03d', $prefix, ++$number);
            if (! $used->has($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private static function codePart(?string $value): string
    {
        $ascii = strtoupper(Str::ascii((string) $value));

        return rtrim(substr(trim((string) preg_replace('/[^A-Z0-9]+/', '-', $ascii), '-'), 0, 20), '-');
    }
}
