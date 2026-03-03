<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotkeyAction extends Model
{
    protected $fillable = [
        'hotkey_id',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function hotkey(): BelongsTo
    {
        return $this->belongsTo(Hotkey::class);
    }
}

