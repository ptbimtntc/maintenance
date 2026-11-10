<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class MachineWwd extends Machine
{
    protected $table = 'machines';

    protected static function booted(): void
    {
        static::addGlobalScope('unit', function (Builder $query) {
            $query->where('unit', 'WWD');
        });

        static::creating(function (self $model) {
            $model->unit = 'WWD';
        });
    }
}

