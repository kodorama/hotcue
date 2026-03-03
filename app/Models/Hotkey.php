<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $accelerator  Normalized accelerator string, e.g. "cmd+shift+1".
 * @property bool $enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HotkeyAction> $actions
 */
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

