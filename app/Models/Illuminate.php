<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Illuminate extends Model
{
    protected $fillable = [
        'name', 'type', 'slug', 'data'
    ];

    protected $hidden = array('pivot');
}
