<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Style extends Model
{
    protected $fillable = [
        'project_id', 'name', 'data'
    ];
}
