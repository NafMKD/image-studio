<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReceipt extends Model
{
    protected $guarded = [];

    protected function cast (): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
