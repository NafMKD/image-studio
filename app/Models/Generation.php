<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    protected $guarded = [];

    protected function cast (): array 
    {
        return [
            'reference_urls' => 'array',
            'usage' => 'array',
            'completed_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    public function user (): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation (): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
