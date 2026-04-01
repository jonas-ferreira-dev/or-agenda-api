<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalProfile\StoreProfessionalProfileRequest;
use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Models\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = ProfessionalProfile::where('user_id', $request->user()->id)->first();

        if (! $profile) {
            return response()->json([
                'message' => 'Perfil profissional ainda não cadastrado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Perfil profissional encontrado com sucesso.',
            'data' => $profile,
        ]);
    }

    public function store(StoreProfessionalProfileRequest $request): JsonResponse
    {
        $existing = ProfessionalProfile::where('user_id', $request->user()->id)->first();

        if ($existing) {
            return response()->json([
                'message' => 'Este usuário já possui um perfil profissional.',
            ], 422);
        }

        $profile = ProfessionalProfile::create([
            'user_id' => $request->user()->id,
            'slug' => $request->slug,
            'public_name' => $request->public_name,
            'bio' => $request->bio,
            'profile_photo' => $request->profile_photo,
            'is_public' => $request->boolean('is_public', true),
            'booking_enabled' => $request->boolean('booking_enabled', true),
        ]);

        return response()->json([
            'message' => 'Perfil profissional criado com sucesso.',
            'data' => $profile,
        ], 201);
    }

    public function update(UpdateProfessionalProfileRequest $request): JsonResponse
    {
        $profile = ProfessionalProfile::where('user_id', $request->user()->id)->firstOrFail();

        $profile->update($request->only([
            'slug',
            'public_name',
            'bio',
            'profile_photo',
            'is_public',
            'booking_enabled',
        ]));

        return response()->json([
            'message' => 'Perfil profissional atualizado com sucesso.',
            'data' => $profile->fresh(),
        ]);
    }
}