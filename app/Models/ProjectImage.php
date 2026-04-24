<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectImage extends Model
{
    use HasUuids;

    protected $fillable = ['project_id', 'image_url', 'display_order'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
