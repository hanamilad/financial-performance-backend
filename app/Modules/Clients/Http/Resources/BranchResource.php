<?php

namespace App\Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
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
