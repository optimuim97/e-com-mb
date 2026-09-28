<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class AuthOtp extends Model
{
    protected $fillable = [
        'identifier', 'purpose', 'code_hash',
        'attempts', 'expires_at', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public const PURPOSE_PASSWORD = 'password';
    public const PURPOSE_PIN      = 'pin';
}
