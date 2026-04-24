<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'icon_url'    => $this->icon_url,
            'services'    => ServiceResource::collection($this->whenLoaded('services')),
            'created_at'  => $this->created_at,
        ];
    }
}
