<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     log_name: ?string,
     *     description: string,
     *     event: ?string,
     *     causer: ?array{id: int, name: string},
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     properties: ?array<string, mixed>,
     *     created_at: ?string,
     * }
     */
    public function toArray(Request $request): array
    {
        $causer = $this->causer;

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'causer' => $causer === null ? null : [
                'id' => $causer->getKey(),
                'name' => $causer->name ?? (string) $causer->getKey(),
            ],
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'properties' => $this->properties,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
