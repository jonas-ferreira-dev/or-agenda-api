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
        $perPage = min((int) $request->get('per_page', 15), 100);

        $clients = Client::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'message' => 'Clientes listados com sucesso.',
            'data' => $clients->items(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
        ]);
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

        return response()->json([
            'message' => 'Cliente criado com sucesso.',
            'data' => $client,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $client = Client::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json([
            'message' => 'Cliente encontrado com sucesso.',
            'data' => $client,
        ]);
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

        return response()->json([
            'message' => 'Cliente atualizado com sucesso.',
            'data' => $client->fresh(),
        ]);
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