<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
        public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:scheduled,confirmed,completed,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $appointments = Appointment::where('user_id', $request->user()->id)
            ->with(['client', 'service'])
            ->when(! empty($validated['date']), function ($query) use ($validated) {
                $query->whereDate('appointment_date', $validated['date']);
            })
            ->when(! empty($validated['status']), function ($query) use ($validated) {
                $query->where('status', $validated['status']);
            })
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = $validated['search'];

                $query->where(function ($query) use ($search) {
                    $query->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('service', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Agendamentos listados com sucesso.',
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'from' => $appointments->firstItem(),
                'to' => $appointments->lastItem(),
            ],
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)->findOrFail($request->client_id);
        $service = Service::where('user_id', $user->id)->findOrFail($request->service_id);

        $endTime = $this->calculateEndTime($request->start_time, $service->duration_minutes);

        $hasConflict = Appointment::where('user_id', $user->id)
                ->whereDate('appointment_date', $request->appointment_date)
                ->whereNotIn('status', ['cancelled'])
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
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => $request->appointment_date,
            'start_time' => $request->start_time,
            'end_time' => $endTime,
            'status' => $request->status ?? 'scheduled',
            'notes' => $request->notes,
        ])->load(['client', 'service']);

        return response()->json([
            'message' => 'Agendamento criado com sucesso.',
            'data' => $appointment,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('user_id', $request->user()->id)
            ->with(['client', 'service'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Agendamento encontrado com sucesso.',
            'data' => $appointment,
        ]);
    }

    public function update(UpdateAppointmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $appointment = Appointment::where('user_id', $user->id)->findOrFail($id);

        if ($request->filled('client_id')) {
            Client::where('user_id', $user->id)->findOrFail($request->client_id);
        }

        $service = $request->filled('service_id')
            ? Service::where('user_id', $user->id)->findOrFail($request->service_id)
            : Service::where('user_id', $user->id)->findOrFail($appointment->service_id);

        $appointmentDate = $request->appointment_date ?? $appointment->appointment_date->format('Y-m-d');
        $startTime = $request->start_time ?? $appointment->start_time;
        $endTime = $this->calculateEndTime($startTime, $service->duration_minutes);

        $hasConflict = Appointment::where('user_id', $user->id)
            ->whereDate('appointment_date', $appointmentDate)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'Já existe um agendamento nesse intervalo.',
            ], 422);
        }

        $appointment->update([
            'client_id' => $request->client_id ?? $appointment->client_id,
            'service_id' => $service->id,
            'appointment_date' => $appointmentDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $request->status ?? $appointment->status,
            'notes' => $request->notes ?? $appointment->notes,
        ]);

        return response()->json([
            'message' => 'Agendamento atualizado com sucesso.',
            'data' => $appointment->load(['client', 'service']),
        ]);
    }

        public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $appointment = Appointment::where('user_id', $request->user()->id)
            ->with(['client', 'service'])
            ->findOrFail($id);

        if ($appointment->status === 'cancelled') {
            return response()->json([
                'message' => 'Este agendamento já está cancelado.',
            ], 422);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_at' => now(),
            'cancelled_by' => 'professional',
        ]);

        return response()->json([
            'message' => 'Agendamento cancelado com sucesso.',
            'data' => $appointment->fresh()->load(['client', 'service']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('user_id', $request->user()->id)->findOrFail($id);

        $appointment->delete();

        return response()->json([
            'message' => 'Agendamento removido com sucesso.',
        ]);
    }

    private function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        return Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($durationMinutes)
            ->format('H:i:s');
    }
}