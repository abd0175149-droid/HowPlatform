<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletAccount extends Model
{
    protected $table = 'wallet_accounts';

    protected $fillable = ['user_id', 'balance', 'version'];

    protected $casts = [
        'user_id' => 'int',
        'balance' => 'int',
        'version' => 'int',
    ];
}
