<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Creates a Spare slot plus its own physical spare lock for every given position. Codes continue from the
     * first free number for the prefix on this board only, e.g. SPH-01, SPH-02, ... Existing locks and codes are
     * never touched. Call inside a transaction.
     */
    public function provisionSpareLocks(array $positions, string $prefix): void
    {
        if ($positions === []) {
            return;
        }

        $usedCodes = SpareLock::query()->where('board_id', $this->id)->lockForUpdate()->pluck('code')
            ->map(fn ($code) => strtoupper($code))
            ->flip();
        $number = 0;

        foreach ($positions as $position) {
            do {
                $code = sprintf('%s-%02d', $prefix, ++$number);
            } while ($usedCodes->has($code));
            $usedCodes->put($code, true);

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
    }

    /** The prefix most of this board's spare codes already use (SPH for SPH-01), so new locks continue that series. */
    public function defaultSparePrefix(): string
    {
        return SpareLock::query()->where('board_id', $this->id)->pluck('code')
            ->map(fn ($code) => preg_match('/^(.+)-\d+$/', $code, $matches) === 1 ? strtoupper($matches[1]) : null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? 'SP';
    }
}
