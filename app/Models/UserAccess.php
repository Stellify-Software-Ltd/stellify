<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccess extends Model
{
    protected $fillable = [
        'ip_address', 'user_id', 'visits', 'last_accessed_at', 'blocked', 'tracked'
    ];
}
