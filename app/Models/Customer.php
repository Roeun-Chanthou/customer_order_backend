<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $primaryKey = 'cid';

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'photo',
        'gender',
        'otp',
        'otp_verified',
        'is_active',
    ];

    protected $hidden = [
        'password', 'otp'
    ];
}
