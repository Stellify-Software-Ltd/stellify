<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Media extends Model
{
    protected $fillable = [
        'user_id', 
        'uuid', 
        'model_type', 
        'model_id', 
        'collection_name', 
        'name', 
        'file_name', 
        'mime_type', 
        'disk', 
        'conversions_disk', 
        'size', 
        'manipulations', 
        'custom_properties', 
        'generated_conversions', 
        'responsive_images', 
        'order_column'
    ];

    protected $hidden = array('pivot');
}
