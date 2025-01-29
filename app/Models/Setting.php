<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id', 'name', 'active_domain', 'data', 'type'
    ];
    protected $hidden = array('pivot');
}
