<?php

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Http\Requests\StoreClientRequest;
use App\Modules\Clients\Http\Requests\UpdateClientRequest;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clients = Client::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->enum('status', EntityStatus::class),
                fn ($query, EntityStatus $status) => $query->where('status', $status),
            )
            ->withCount(['branches', 'users'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request): ClientResource
    {
        $data = $request->validated();
        $data['status'] ??= EntityStatus::Active->value;

        return new ClientResource(Client::create($data));
    }

    public function show(Client $client): ClientResource
    {
        $client->load([
            'branches' => fn ($query) => $query->latest(),
            'users' => fn ($query) => $query->latest(),
        ])->loadCount(['branches', 'users']);

        return new ClientResource($client);
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->loadCount(['branches', 'users']));
    }
}
