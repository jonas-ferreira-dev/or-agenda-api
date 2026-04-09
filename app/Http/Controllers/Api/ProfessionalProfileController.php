<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalProfile\StoreProfessionalProfileRequest;
use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Models\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $profilePhotoPath = null;

        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $request->file('profile_photo')->store('professional-profiles', 'public');
        }

        $profile = ProfessionalProfile::create([
            'user_id' => $request->user()->id,
            'slug' => $request->slug,
            'public_name' => $request->public_name,
            'bio' => $request->bio,
            'profile_photo' => $profilePhotoPath,
            'is_public' => $request->boolean('is_public', true),
            'booking_enabled' => $request->boolean('booking_enabled', true),
        ]);

        return response()->json([
            'message' => 'Perfil profissional criado com sucesso.',
            'data' => $profile->fresh(),
        ], 201);
    }

    public function update(UpdateProfessionalProfileRequest $request): JsonResponse
    {
        $profile = ProfessionalProfile::where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->only([
            'slug',
            'public_name',
            'bio',
            'is_public',
            'booking_enabled',
        ]);

        if ($request->boolean('remove_profile_photo')) {
            if ($profile->profile_photo) {
                Storage::disk('public')->delete($profile->profile_photo);
            }

            $data['profile_photo'] = null;
        }

        if ($request->hasFile('profile_photo')) {
            if ($profile->profile_photo) {
                Storage::disk('public')->delete($profile->profile_photo);
            }

            $data['profile_photo'] = $request->file('profile_photo')->store('professional-profiles', 'public');
        }

        $profile->update($data);

        return response()->json([
            'message' => 'Perfil profissional atualizado com sucesso.',
            'data' => $profile->fresh(),
        ]);
    }
}