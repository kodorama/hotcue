<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotkey extends Model
{
    protected $fillable = [
        'name',
        'accelerator',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(HotkeyAction::class);
    }

    /**
     * Scope to get only enabled hotkeys.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}

