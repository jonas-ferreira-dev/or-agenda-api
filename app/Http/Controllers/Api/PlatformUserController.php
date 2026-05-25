<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformUserRequest;
use App\Http\Requests\Platform\UpdatePlatformUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'is_platform_admin',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('is_active', $validated), function ($query) use ($validated) {
                $query->where('is_active', $validated['is_active']);
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'message' => 'Usuários listados com sucesso.',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        ]);
    }

    public function store(StorePlatformUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_platform_admin' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Usuário criado com sucesso.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->is_platform_admin,
                'is_active' => $user->is_active,
            ],
        ], 201);
    }

    public function update(UpdatePlatformUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return response()->json([
            'message' => 'Usuário atualizado com sucesso.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->is_platform_admin,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}