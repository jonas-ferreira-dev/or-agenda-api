<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = Client::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'notes' => $request->notes,
        ]);

        return response()->json($client, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $client = Client::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json($client);
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = Client::where('user_id', $request->user()->id)->findOrFail($id);

        $client->update($request->only([
            'name',
            'email',
            'phone',
            'notes',
        ]));

        return response()->json($client);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = Client::where('user_id', $request->user()->id)->findOrFail($id);

        $client->delete();

        return response()->json([
            'message' => 'Cliente removido com sucesso.',
        ]);
    }
}