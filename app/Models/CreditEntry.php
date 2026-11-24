<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function cast (): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
