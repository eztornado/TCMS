<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'users_count' => $this->whenCounted('users'),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])),
        ];
    }
}
