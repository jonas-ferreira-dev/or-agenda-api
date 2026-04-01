<?php

namespace App\Http\Requests\ProfessionalProfile;

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
        $profileId = $this->route('id');

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('professional_profiles', 'slug')->ignore($profileId),
            ],
            'public_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'profile_photo' => ['nullable', 'string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'booking_enabled' => ['nullable', 'boolean'],
        ];
    }
}