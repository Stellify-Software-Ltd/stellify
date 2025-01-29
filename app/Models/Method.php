<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Method extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'type', 'name', 'slug', 'data'
    ];

    protected $hidden = array('pivot');
}
