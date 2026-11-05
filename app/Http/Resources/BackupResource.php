<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupResource extends JsonResource
{
    /**
     * @return array{name: string, size: int, created_at: string, created_on: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this['name'],
            'size' => $this['size'],
            'created_at' => $this['created_at'],
            'created_on' => CarbonImmutable::parse($this['created_at'])->toDateString(),
        ];
    }
}
