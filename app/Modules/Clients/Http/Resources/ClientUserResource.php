<?php

namespace App\Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'client_id' => $this->client_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
