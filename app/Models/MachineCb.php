<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class MachineCb extends Machine
{
    protected $table = 'machines';

    protected static function booted(): void
    {
        static::addGlobalScope('unit', function (Builder $query) {
            $query->where('unit', 'CB');
        });

        static::creating(function (self $model) {
            $model->unit = 'CB';
        });
    }
}
