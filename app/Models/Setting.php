<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'ws_host',
        'ws_port',
        'ws_secure',
        'ws_password',
        'auto_connect',
    ];

    protected $casts = [
        'ws_port' => 'integer',
        'ws_secure' => 'boolean',
        'auto_connect' => 'boolean',
    ];

    /**
     * Get the singleton settings instance.
     */
    public static function instance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'ws_host'      => '127.0.0.1',
                'ws_port'      => 4455,
                'ws_secure'    => false,
                'ws_password'  => null,
                'auto_connect' => true,
            ]
        );
    }

    /**
     * Set encrypted password.
     */
    public function setWsPasswordAttribute(?string $value): void
    {
        $this->attributes['ws_password'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get decrypted password.
     */
    public function getWsPasswordAttribute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}

