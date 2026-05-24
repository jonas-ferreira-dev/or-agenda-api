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
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $services = $request->user()
            ->services()
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('active', $validated), function ($query) use ($validated) {
                $query->where('active', $validated['active']);
            })
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
                'from' => $services->firstItem(),
                'to' => $services->lastItem(),
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