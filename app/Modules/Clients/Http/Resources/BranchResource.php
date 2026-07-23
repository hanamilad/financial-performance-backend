<?php

namespace App\Modules\Clients\Http\Resources;

use App\Modules\Clients\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'code' => $this->code,
            'city' => $this->city,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
