<?php

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clients\Http\Requests\StoreClientUserRequest;
use App\Modules\Clients\Http\Requests\UpdateClientUserRequest;
use App\Modules\Clients\Http\Resources\ClientUserResource;
use App\Modules\Clients\Models\Client;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Support\Facades\Hash;

class ClientUserController extends Controller
{
    public function store(StoreClientUserRequest $request, Client $client): ClientUserResource
    {
        $user = $client->users()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => UserRole::ClientUser,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new ClientUserResource($user);
    }

    public function update(UpdateClientUserRequest $request, User $clientUser): ClientUserResource
    {
        // This endpoint manages client users only; it must never edit an admin.
        abort_unless($clientUser->role === UserRole::ClientUser, 403);

        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $clientUser->update($data);

        return new ClientUserResource($clientUser);
    }
}
