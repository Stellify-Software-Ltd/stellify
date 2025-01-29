<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
  protected $fillable = [
    'user_id', 'project_id', 'name', 'slug', 'type', 'data'
  ];
}
