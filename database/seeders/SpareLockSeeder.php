<?php

namespace Database\Seeders;

use App\Models\LototoBoardSlot;
use App\Models\SpareLock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SpareLockSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 20) as $number) {
            $spareLock = SpareLock::query()->firstOrCreate(
                ['code' => sprintf('SP-%02d', $number)],
                ['is_active' => true],
            );

            if ($spareLock->wasRecentlyCreated && Schema::hasTable('lototo_board_slots')) {
                LototoBoardSlot::query()
                    ->where('category', 'spare')
                    ->where('position', $number)
                    ->whereNull('spare_lock_id')
                    ->update(['spare_lock_id' => $spareLock->id]);
            }
        }
    }
}
