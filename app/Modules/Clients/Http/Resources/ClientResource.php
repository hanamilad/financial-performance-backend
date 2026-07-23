<?php

namespace App\Modules\Clients\Http\Resources;

use App\Modules\Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status->value,
            'branches_count' => $this->whenCounted('branches'),
            'users_count' => $this->whenCounted('users'),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'users' => ClientUserResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at,
        ];
    }
}
