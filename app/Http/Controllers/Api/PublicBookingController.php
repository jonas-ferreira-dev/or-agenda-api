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
        $profile = ProfessionalProfile::with('user:id,name')
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
                'booking_enabled' => (bool) $profile->booking_enabled,
            ],
        ]);
    }

    public function services(string $slug): JsonResponse
    {
        $profile = $this->findBookableProfile($slug);

        $services = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'duration_minutes',
                'price',
                'description',
            ]);

        return response()->json([
            'message' => 'Serviços públicos listados com sucesso.',
            'data' => $services,
        ]);
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'service_id' => ['required', 'integer'],
        ]);

        $profile = $this->findBookableProfile($slug);

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        $busyAppointments = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $validated['date'])
            ->whereNotIn('status', ['cancelled'])
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
                'date' => $validated['date'],
                'service_id' => $service->id,
                'duration_minutes' => $service->duration_minutes,
                'available_slots' => $availableSlots,
            ],
        ]);
    }

    public function store(StorePublicAppointmentRequest $request, string $slug): JsonResponse
    {
        $validated = $request->validated();

        $profile = $this->findBookableProfile($slug);

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        $endTime = $this->calculateEndTime($validated['start_time'], $service->duration_minutes);

        $hasConflict = Appointment::where('user_id', $profile->user_id)
            ->whereDate('appointment_date', $validated['appointment_date'])
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($query) use ($validated, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Já existe um agendamento nesse intervalo.',
            ], 422);
        }

        $client = $this->findOrCreatePublicClient($profile->user_id, $validated);

        $appointment = Appointment::create([
            'user_id' => $profile->user_id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $endTime,
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Agendamento público criado com sucesso.',
            'data' => [
                'id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'start_time' => $appointment->start_time,
                'end_time' => $appointment->end_time,
                'status' => $appointment->status,
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                ],
                'client' => [
                    'name' => $client->name,
                    'phone' => $client->phone,
                ],
            ],
        ], 201);
    }

    private function findBookableProfile(string $slug): ProfessionalProfile
    {
        return ProfessionalProfile::where('slug', $slug)
            ->where('is_public', true)
            ->where('booking_enabled', true)
            ->firstOrFail();
    }

    private function findOrCreatePublicClient(int $userId, array $validated): Client
    {
        $client = Client::where('user_id', $userId)
            ->where('phone', $validated['phone'])
            ->first();

        if (! $client && ! empty($validated['email'])) {
            $client = Client::where('user_id', $userId)
                ->where('email', $validated['email'])
                ->first();
        }

        if ($client) {
            $client->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? $client->email,
                'phone' => $validated['phone'],
            ]);

            return $client->fresh();
        }

        return Client::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'notes' => null,
        ]);
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