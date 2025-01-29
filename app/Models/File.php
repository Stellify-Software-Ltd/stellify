<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class File extends Model
{
    protected $fillable = [
        'user_id', 'name', 'type', 'slug', 'project_id', 'data'
    ];

    protected $hidden = array('pivot');
}
