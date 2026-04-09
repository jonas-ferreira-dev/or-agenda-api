<?php

namespace App\Http\Requests\ProfessionalProfile;

use App\Models\ProfessionalProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = ProfessionalProfile::where('user_id', $this->user()->id)->value('id');

        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('professional_profiles', 'slug')->ignore($profileId),
            ],
            'public_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'booking_enabled' => ['nullable', 'boolean'],
        ];
    }
}