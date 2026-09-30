<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array{name: string, permissions: list<string>, users_count: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()->all(), []),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
