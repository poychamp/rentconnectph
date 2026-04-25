<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'verified' => ListingCardResource::collection($this->resource['verified']),
            'recently' => ListingCardResource::collection($this->resource['recently']),
        ];
    }
}
