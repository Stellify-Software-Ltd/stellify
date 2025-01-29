<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Searchable;

class Short extends Model
{
    use Searchable;

    protected $fillable = [
        'user_id', 'project_id', 'page_id', 'element_id', 'name', 'search_count', 'text'
    ];

    protected $hidden = array('pivot', 'created_at', 'updated_at', 'deleted_at', 'project_id', 'element_id');

    public function toSearchableArray(): array
    {
        return [
            'text' => $this->text
        ];
    }

    public function getPage() {
        return $this->hasOne(Page::class, 'slug', 'page_id');
    }
}
