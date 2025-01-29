<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'project_id', 'path', 'type', 'method', 'data', 'subview', 'ssr', 'redirectUrl', 'statusCode'
    ];
    protected $hidden = array('pivot');
}
