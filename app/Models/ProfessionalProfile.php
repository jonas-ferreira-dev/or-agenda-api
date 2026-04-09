<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'public_name',
        'bio',
        'profile_photo',
        'is_public',
        'booking_enabled',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'booking_enabled' => 'boolean',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo) {
            return null;
        }

        return asset('storage/' . $this->profile_photo);
    }
}