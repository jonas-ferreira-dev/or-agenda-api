<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $services = $request->user()
            ->services()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'message' => 'Serviços listados com sucesso.',
            'data' => $services->items(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $request->user()->services()->create([
            'name' => $request->validated('name'),
            'duration_minutes' => $request->validated('duration_minutes'),
            'price' => $request->validated('price'),
            'description' => $request->validated('description'),
            'active' => $request->validated('active', true),
        ]);

        return response()->json([
            'message' => 'Service created successfully.',
            'data' => $service,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $service = $request->user()->services()->findOrFail($id);

        return response()->json([
            'data' => $service,
        ]);
    }

    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $service = $request->user()->services()->findOrFail($id);

        $service->update($request->validated());

        return response()->json([
            'message' => 'Service updated successfully.',
            'data' => $service->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $service = $request->user()->services()->findOrFail($id);

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }
}