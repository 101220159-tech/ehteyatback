<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'icon_url'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
