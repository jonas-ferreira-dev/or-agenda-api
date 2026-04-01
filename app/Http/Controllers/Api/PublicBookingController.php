<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StorePublicAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Carbon\Carbon;
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
            'message' => 'Perfil público encontrado com sucesso.',
            'data' => [
                'slug' => $profile->slug,
                'public_name' => $profile->public_name ?? $profile->user->name,
                'bio' => $profile->bio,
                'profile_photo' => $profile->profile_photo,
                'booking_enabled' => $profile->booking_enabled,
            ],
        ]);
    }

    public function services(string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $services = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Serviços públicos listados com sucesso.',
            'data' => $services,
        ]);
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($request->service_id);

        $busyAppointments = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $request->date)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        $availableSlots = $this->generateAvailableSlots(
            serviceDuration: $service->duration_minutes,
            busyAppointments: $busyAppointments->toArray(),
            startOfDay: '09:00',
            endOfDay: '18:00',
            slotStepMinutes: 30
        );

        return response()->json([
            'message' => 'Disponibilidade carregada com sucesso.',
            'data' => [
                'date' => $request->date,
                'service_id' => $service->id,
                'duration_minutes' => $service->duration_minutes,
                'available_slots' => $availableSlots,
                'busy_slots' => $busyAppointments,
            ],
        ]);
    }

    public function store(StorePublicAppointmentRequest $request, string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($request->service_id);

        $client = Client::where('user_id', $profile->user_id)
            ->where('phone', $request->phone)
            ->first();

        if (! $client && $request->filled('email')) {
            $client = Client::where('user_id', $profile->user_id)
                ->where('email', $request->email)
                ->first();
        }

        if ($client) {
            $client->update([
                'name' => $request->name,
                'email' => $request->email ?? $client->email,
                'phone' => $request->phone,
            ]);
        } else {
            $client = Client::create([
                'user_id' => $profile->user_id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'notes' => null,
            ]);
        }

        $endTime = $this->calculateEndTime($request->start_time, $service->duration_minutes);

        $hasConflict = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $request->appointment_date)
            ->where(function ($query) use ($request, $endTime) {
                $query->where('start_time', '<', $endTime)
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
            'end_time' => $endTime,
            'status' => 'scheduled',
            'notes' => $request->notes,
        ])->load(['client', 'service']);

        return response()->json([
            'message' => 'Agendamento público criado com sucesso.',
            'data' => $appointment,
        ], 201);
    }

    private function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        return Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMinutes)
            ->format('H:i:s');
    }

    private function generateAvailableSlots(
        int $serviceDuration,
        array $busyAppointments,
        string $startOfDay = '09:00',
        string $endOfDay = '18:00',
        int $slotStepMinutes = 30
    ): array {
        $slots = [];

        $cursor = Carbon::createFromFormat('H:i', $startOfDay);
        $dayEnd = Carbon::createFromFormat('H:i', $endOfDay);

        while ($cursor->copy()->addMinutes($serviceDuration) <= $dayEnd) {
            $slotStart = $cursor->format('H:i:s');
            $slotEnd = $cursor->copy()->addMinutes($serviceDuration)->format('H:i:s');

            $hasConflict = collect($busyAppointments)->contains(function ($appointment) use ($slotStart, $slotEnd) {
                return $appointment['start_time'] < $slotEnd
                    && $appointment['end_time'] > $slotStart;
            });

            if (! $hasConflict) {
                $slots[] = $cursor->format('H:i');
            }

            $cursor->addMinutes($slotStepMinutes);
        }

        return $slots;
    }
}