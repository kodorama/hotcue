<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotkey_id
 * @property string $type  Action type slug, e.g. "switch_scene", "toggle_mute".
 * @property array<string, mixed> $payload  JSON column cast to array; structure varies by type.
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Hotkey $hotkey
 */
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

