<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Meta extends Model
{
    protected $fillable = [
        'user_id', 'project_id', 'name', 'type', 'slug', 'data', 'updated_at'
    ];

    protected $hidden = array('pivot');
}
