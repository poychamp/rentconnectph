<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property User $resource
 */
class FieldLoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid'  => $this->uuid,
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => AppRole::field()->value,
        ];
    }
}
