<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['machine_no', 'func_location', 'machine_type'])]
class Machine extends Model
{
    /**
     * Cached lookup dictionary: machine_no => machine_type.
     *
     * @return array<string, string>
     */
    public static function lookupCatalog(): array
    {
        try {
            return static::query()
                ->orderBy('machine_no')
                ->pluck('machine_type', 'machine_no')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}

