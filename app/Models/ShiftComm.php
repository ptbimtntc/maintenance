<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['comm_date', 'shift', 'team', 'machine_no', 'problem', 'is_torsion_shaft', 'progress', 'torsion_relay_status', 'construction', 'length_m', 'remark', 'created_by'])]
class ShiftComm extends Model
{
    /** Shift digit used inside the ID => label. The plant runs 3 shifts a day (defaulted by input time, see defaultShiftFor). */
    public const SHIFTS = [1 => 'Shift 1', 2 => 'Shift 2', 3 => 'Shift 3'];

    /** Teams rotate through the shifts (off-day system); NS is the Day team. */
    /** Estafet (hand-over) status of a Torsion Shaft problem. */
    public const TORSION_RELAY_STATUSES = [
        'not_started' => 'Belum dikerjakan',
        'in_progress' => 'Sedang dikerjakan',
        'next_shift' => 'Estafet shift berikutnya',
        'done' => 'Selesai',
    ];

    public const TEAMS = ['A', 'B', 'C', 'D', 'NS'];


    protected function casts(): array
    {
        return [
            'comm_date' => 'date',
            'shift' => 'integer',
            'is_torsion_shaft' => 'boolean',
            'length_m' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function torsionRelayLabel(): ?string
    {
        return self::TORSION_RELAY_STATUSES[$this->torsion_relay_status] ?? null;
    }

    public function shiftName(): string
    {
        return self::SHIFTS[$this->shift] ?? (string) $this->shift;
    }

    /** 08:00-16:00 => 1, 16:00-24:00 => 2, 00:00-08:00 => 3. */
    public static function defaultShiftFor(Carbon $at): int
    {
        return match (true) {
            $at->hour >= 8 && $at->hour < 16 => 1,
            $at->hour >= 16 => 2,
            default => 3,
        };
    }

    public static function defaultTeamFor(?Employee $employee): ?string
    {
        // The employee master "Shift" is really the team: named A, B, C, D or NS.
        $name = strtoupper(trim((string) $employee?->shift?->name));

        return in_array($name, self::TEAMS, true) ? $name : null;
    }

    /** Most-used constructions first, for the autocomplete suggestions. */
    public static function constructionSuggestions(int $limit = 300): array
    {
        return static::query()
            ->whereNotNull('construction')
            ->where('construction', '!=', '')
            ->select('construction', DB::raw('count(*) as uses'))
            ->groupBy('construction')
            ->orderByDesc('uses')
            ->orderBy('construction')
            ->limit($limit)
            ->pluck('construction')
            ->all();
    }

    /** Creates a record with the next running number for its date + shift. */
    public static function createNumbered(array $data): self
    {
        $prefix = Carbon::parse($data['comm_date'])->format('ymd').$data['shift'];

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $prefix) {
                    $last = static::query()->where('comm_number', 'like', $prefix.'__%')->max('comm_number');
                    $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

                    $comm = new static($data);
                    $comm->comm_number = $prefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
                    $comm->save();

                    return $comm;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Another entry took the number between the read and the write.
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }
}
