<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'name'        => $this->name,
            'description' => $this->description,
            'icon_url'    => $this->icon_url,
            'base_price'  => $this->base_price !== null ? (float) $this->base_price : null,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'price'       => $this->whenPivotLoaded('provider_services', fn () => (float) $this->pivot->price),
            'created_at'  => $this->created_at,
        ];
    }
}
