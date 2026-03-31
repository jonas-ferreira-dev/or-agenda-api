<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clients = $request->user()
            ->clients()
            ->latest()
            ->get();

        return response()->json([
            'data' => $clients,
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $request->user()->clients()->create($request->validated());

        return response()->json([
            'message' => 'Client created successfully.',
            'data' => $client,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $client = $request->user()->clients()->findOrFail($id);

        return response()->json([
            'data' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = $request->user()->clients()->findOrFail($id);

        $client->update($request->validated());

        return response()->json([
            'message' => 'Client updated successfully.',
            'data' => $client->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $client = $request->user()->clients()->findOrFail($id);

        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully.',
        ]);
    }
}