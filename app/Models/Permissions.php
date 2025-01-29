<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permissions extends Model
{
    protected $fillable = [
        'id', 'slug', 'project_id', 'super', 'read', 'write', 'execute'
    ];
    protected $hidden = array('pivot');

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'slug', 'slug');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'slug')->select(['slug', 'name']);
    }
}