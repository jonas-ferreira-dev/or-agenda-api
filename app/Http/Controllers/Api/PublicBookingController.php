<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StorePublicAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function showProfessional(string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::with('user')
            ->where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        return response()->json([
            'slug' => $profile->slug,
            'public_name' => $profile->public_name ?? $profile->user->name,
            'bio' => $profile->bio,
            'profile_photo' => $profile->profile_photo,
            'booking_enabled' => $profile->booking_enabled,
        ]);
    }

    public function services(string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $services = Service::where('user_id', $profile->user_id)
            ->latest()
            ->get();

        return response()->json($services);
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $appointments = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $request->date)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        return response()->json([
            'date' => $request->date,
            'busy_slots' => $appointments,
        ]);
    }

    public function store(StorePublicAppointmentRequest $request, string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $service = Service::where('user_id', $profile->user_id)
            ->findOrFail($request->service_id);

        $client = Client::where('user_id', $profile->user_id)
            ->where('phone', $request->phone)
            ->first();

        if (!$client && $request->filled('email')) {
            $client = Client::where('user_id', $profile->user_id)
                ->where('email', $request->email)
                ->first();
        }

        if (!$client) {
            $client = Client::create([
                'user_id' => $profile->user_id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'notes' => null,
            ]);
        }

        $hasConflict = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $request->appointment_date)
            ->where(function ($query) use ($request) {
                $query->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Já existe um agendamento nesse intervalo.',
            ], 422);
        }

        $appointment = Appointment::create([
            'user_id' => $profile->user_id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => $request->appointment_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'scheduled',
            'notes' => $request->notes,
        ]);

        return response()->json(
            $appointment->load(['client', 'service']),
            201
        );
    }
}