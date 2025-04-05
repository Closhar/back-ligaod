<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryAdminImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image' => url('/storage/' . $this->image),
            'thmb' => url('/storage/' . preg_replace('~^(.*/)([^/]+)$~', '$1thmb_$2', $this->image)),
            'position' => $this->position
        ];
    }
}
