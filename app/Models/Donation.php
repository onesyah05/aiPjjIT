<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_sandbox' => 'boolean',
            'paid_at' => 'datetime',
            'qr_string' => 'encrypted',
            'total_payment' => 'integer',
            'qris_expires_at' => 'datetime',
        ];
    }
}
