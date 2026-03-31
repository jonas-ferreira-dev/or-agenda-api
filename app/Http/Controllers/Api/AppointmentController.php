<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::where('user_id', $request->user()->id)
            ->with(['client', 'service'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        return response()->json($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $user = $request->user();

        $client = Client::where('user_id', $user->id)->findOrFail($request->client_id);
        $service = Service::where('user_id', $user->id)->findOrFail($request->service_id);

        $hasConflict = Appointment::where('user_id', $user->id)
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
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => $request->appointment_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => $request->status ?? 'scheduled',
            'notes' => $request->notes,
        ]);

        return response()->json(
            $appointment->load(['client', 'service']),
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('user_id', $request->user()->id)
            ->with(['client', 'service'])
            ->findOrFail($id);

        return response()->json($appointment);
    }

    public function update(UpdateAppointmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $appointment = Appointment::where('user_id', $user->id)->findOrFail($id);

        if ($request->filled('client_id')) {
            Client::where('user_id', $user->id)->findOrFail($request->client_id);
        }

        if ($request->filled('service_id')) {
            Service::where('user_id', $user->id)->findOrFail($request->service_id);
        }

        $appointmentDate = $request->appointment_date ?? $appointment->appointment_date->format('Y-m-d');
        $startTime = $request->start_time ?? $appointment->start_time;
        $endTime = $request->end_time ?? $appointment->end_time;

        if ($endTime <= $startTime) {
            return response()->json([
                'message' => 'O horário final deve ser maior que o horário inicial.',
                'errors' => [
                    'end_time' => ['O horário final deve ser maior que o horário inicial.'],
                ],
            ], 422);
        }

        $hasConflict = Appointment::where('user_id', $user->id)
            ->whereDate('appointment_date', $appointmentDate)
            ->where('id', '!=', $appointment->id)
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
            'service_id' => $request->service_id ?? $appointment->service_id,
            'appointment_date' => $appointmentDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $request->status ?? $appointment->status,
            'notes' => $request->notes ?? $appointment->notes,
        ]);

        return response()->json($appointment->load(['client', 'service']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('user_id', $request->user()->id)->findOrFail($id);

        $appointment->delete();

        return response()->json([
            'message' => 'Agendamento removido com sucesso.',
        ]);
    }
}