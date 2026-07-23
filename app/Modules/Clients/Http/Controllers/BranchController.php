<?php

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Http\Requests\StoreBranchRequest;
use App\Modules\Clients\Http\Requests\UpdateBranchRequest;
use App\Modules\Clients\Http\Resources\BranchResource;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;

class BranchController extends Controller
{
    public function store(StoreBranchRequest $request, Client $client): BranchResource
    {
        $data = $request->validated();
        $data['status'] ??= EntityStatus::Active->value;

        return new BranchResource($client->branches()->create($data));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }
}
