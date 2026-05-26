<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory; 

    protected $fillable = [
        'user_id',
        'client_id',
        'service_id',
        'appointment_date',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'start_time',
        'end_time',
        'status',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}