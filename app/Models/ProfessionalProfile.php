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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}