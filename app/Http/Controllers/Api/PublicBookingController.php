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
use App\Services\PublicAvailableSlotsService;
use App\Models\ProfessionalAvailability;
use App\Mail\NewPublicAppointmentMail;
use Illuminate\Support\Facades\Mail;

class PublicBookingController extends Controller
{
    public function showProfessional(string $slug): JsonResponse
    {
        $profile = ProfessionalProfile::with('user:id,name')
            ->where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $availableWeekdays = ProfessionalAvailability::query()
            ->where('user_id', $profile->user_id)
            ->where('is_active', true)
            ->distinct()
            ->orderBy('weekday')
            ->pluck('weekday')
            ->values();

        return response()->json([
            'message' => 'Perfil público encontrado com sucesso.',
            'data' => [
                'slug' => $profile->slug,
                'public_name' => $profile->public_name ?? $profile->user->name,
                'bio' => $profile->bio,
                'profile_photo' => $profile->profile_photo,
                'booking_enabled' => (bool) $profile->booking_enabled,
                'available_weekdays' => $availableWeekdays,
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

    public function availability(
        Request $request,
        string $slug,
        PublicAvailableSlotsService $availableSlotsService
    ): JsonResponse {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'service_id' => ['required', 'integer'],
        ]);

        $profile = $this->findBookableProfile($slug);

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        $slots = $availableSlotsService->getAvailableSlots(
            professional: $profile->user,
            service: $service,
            date: $validated['date'],
        );

        return response()->json([
            'message' => 'Disponibilidade carregada com sucesso.',
            'data' => [
                'date' => $validated['date'],
                'service_id' => $service->id,
                'duration_minutes' => $service->duration_minutes,
                'available_slots' => $slots,
            ],
        ]);
    }

     public function store(
        StorePublicAppointmentRequest $request,
        string $slug,
        PublicAvailableSlotsService $availableSlotsService
    ): JsonResponse {
        $validated = $request->validated();

        $profile = $this->findBookableProfile($slug);

        $service = Service::where('user_id', $profile->user_id)
            ->where('active', true)
            ->findOrFail($validated['service_id']);

        $availableSlots = $availableSlotsService->getAvailableSlots(
            professional: $profile->user,
            service: $service,
            date: $validated['appointment_date'],
        );

        $selectedSlot = collect($availableSlots)->firstWhere(
            'start_time',
            $validated['start_time']
        );

        if (! $selectedSlot) {
            return response()->json([
                'message' => 'Horário indisponível para agendamento.',
                'errors' => [
                    'start_time' => ['Horário indisponível para agendamento.'],
                ],
            ], 422);
        }

        $endTime = $selectedSlot['end_time'];

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
        ])->load(['client', 'service']);

        Mail::to($profile->user->email)->send(
            new NewPublicAppointmentMail($appointment)
        );

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
         return ProfessionalProfile::with('user')
            ->where('slug', $slug)
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

    

    
}