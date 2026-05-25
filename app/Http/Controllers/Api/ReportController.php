<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function revenue(Request $request): JsonResponse
    {
        $validated = $this->validatePeriodFilters($request);

        $query = Appointment::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->with(['client:id,name,phone,email', 'service:id,name,price,duration_minutes'])
            ->whereBetween('appointment_date', [
                $validated['start_date'],
                $validated['end_date'],
            ]);

        if (! empty($validated['service_id'])) {
            $query->where('service_id', $validated['service_id']);
        }

        $appointments = $query
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        $totalRevenue = $appointments->sum(
            fn ($appointment) => (float) ($appointment->service?->price ?? 0)
        );

        $appointmentsCount = $appointments->count();

        $servicesSummary = $appointments
            ->groupBy('service_id')
            ->map(function ($items) {
                $service = $items->first()->service;

                return [
                    'service_id' => $service?->id,
                    'service_name' => $service?->name ?? 'Serviço removido',
                    'appointments_count' => $items->count(),
                    'total_revenue' => round(
                        $items->sum(fn ($appointment) => (float) ($appointment->service?->price ?? 0)),
                        2
                    ),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Relatório de faturamento gerado com sucesso.',
            'data' => [
                'summary' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'appointments_count' => $appointmentsCount,
                    'average_ticket' => $appointmentsCount > 0
                        ? round($totalRevenue / $appointmentsCount, 2)
                        : 0,
                ],
                'services' => $servicesSummary,
                'appointments' => $appointments->map(fn ($appointment) => [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'start_time' => $appointment->start_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'client' => $appointment->client ? [
                        'id' => $appointment->client->id,
                        'name' => $appointment->client->name,
                        'phone' => $appointment->client->phone,
                        'email' => $appointment->client->email,
                    ] : null,
                    'service' => $appointment->service ? [
                        'id' => $appointment->service->id,
                        'name' => $appointment->service->name,
                        'price' => (float) $appointment->service->price,
                        'duration_minutes' => $appointment->service->duration_minutes,
                    ] : null,
                ]),
            ],
        ]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $validated = $this->validatePeriodFilters($request, [
            'status' => [
                'nullable',
                'string',
                Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled']),
            ],
        ]);

        $query = Appointment::query()
            ->where('user_id', $request->user()->id)
            ->with(['client:id,name,phone,email', 'service:id,name,price,duration_minutes'])
            ->whereBetween('appointment_date', [
                $validated['start_date'],
                $validated['end_date'],
            ]);

        if (! empty($validated['service_id'])) {
            $query->where('service_id', $validated['service_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $appointments = $query
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        $statusSummary = $appointments
            ->groupBy('status')
            ->map(fn ($items, $status) => [
                'status' => $status,
                'count' => $items->count(),
            ])
            ->values();

        return response()->json([
            'message' => 'Relatório de agendamentos gerado com sucesso.',
            'data' => [
                'summary' => [
                    'total' => $appointments->count(),
                    'scheduled' => $appointments->where('status', 'scheduled')->count(),
                    'confirmed' => $appointments->where('status', 'confirmed')->count(),
                    'completed' => $appointments->where('status', 'completed')->count(),
                    'cancelled' => $appointments->where('status', 'cancelled')->count(),
                ],
                'statuses' => $statusSummary,
                'appointments' => $appointments->map(fn ($appointment) => [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'start_time' => $appointment->start_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'client' => $appointment->client ? [
                        'id' => $appointment->client->id,
                        'name' => $appointment->client->name,
                        'phone' => $appointment->client->phone,
                        'email' => $appointment->client->email,
                    ] : null,
                    'service' => $appointment->service ? [
                        'id' => $appointment->service->id,
                        'name' => $appointment->service->name,
                        'price' => (float) $appointment->service->price,
                    ] : null,
                ]),
            ],
        ]);
    }

    public function cancellations(Request $request): JsonResponse
    {
        $validated = $this->validatePeriodFilters($request);

        $allAppointmentsQuery = Appointment::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('appointment_date', [
                $validated['start_date'],
                $validated['end_date'],
            ]);

        if (! empty($validated['service_id'])) {
            $allAppointmentsQuery->where('service_id', $validated['service_id']);
        }

        $totalAppointments = (clone $allAppointmentsQuery)->count();

        $cancelledAppointments = (clone $allAppointmentsQuery)
            ->where('status', 'cancelled')
            ->with(['client:id,name,phone,email', 'service:id,name,price,duration_minutes'])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        $clientsSummary = $cancelledAppointments
            ->groupBy('client_id')
            ->map(function ($items) {
                $client = $items->first()->client;

                return [
                    'client_id' => $client?->id,
                    'client_name' => $client?->name ?? 'Cliente removido',
                    'cancellations_count' => $items->count(),
                ];
            })
            ->sortByDesc('cancellations_count')
            ->values();

        $servicesSummary = $cancelledAppointments
            ->groupBy('service_id')
            ->map(function ($items) {
                $service = $items->first()->service;

                return [
                    'service_id' => $service?->id,
                    'service_name' => $service?->name ?? 'Serviço removido',
                    'cancellations_count' => $items->count(),
                ];
            })
            ->sortByDesc('cancellations_count')
            ->values();

        return response()->json([
            'message' => 'Relatório de cancelamentos gerado com sucesso.',
            'data' => [
                'summary' => [
                    'total_appointments' => $totalAppointments,
                    'cancelled_count' => $cancelledAppointments->count(),
                    'cancellation_rate' => $totalAppointments > 0
                        ? round(($cancelledAppointments->count() / $totalAppointments) * 100, 2)
                        : 0,
                ],
                'clients' => $clientsSummary,
                'services' => $servicesSummary,
                'appointments' => $cancelledAppointments->map(fn ($appointment) => [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'start_time' => $appointment->start_time,
                    'end_time' => $appointment->end_time,
                    'status' => $appointment->status,
                    'client' => $appointment->client ? [
                        'id' => $appointment->client->id,
                        'name' => $appointment->client->name,
                        'phone' => $appointment->client->phone,
                        'email' => $appointment->client->email,
                    ] : null,
                    'service' => $appointment->service ? [
                        'id' => $appointment->service->id,
                        'name' => $appointment->service->name,
                    ] : null,
                ]),
            ],
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $validated = $this->validatePeriodFilters($request, [
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $clientsQuery = Client::query()
            ->where('user_id', $request->user()->id)
            ->withCount('appointments')
            ->withMax('appointments', 'appointment_date')
            ->whereBetween('created_at', [
                $this->startDateTime($validated['start_date']),
                $this->endDateTime($validated['end_date']),
            ]);

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $clientsQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $clientsQuery
            ->orderByDesc('appointments_count')
            ->orderBy('name')
            ->get();

        $totalClients = Client::query()
            ->where('user_id', $request->user()->id)
            ->count();

        return response()->json([
            'message' => 'Relatório de clientes gerado com sucesso.',
            'data' => [
                'summary' => [
                    'total_clients' => $totalClients,
                    'new_clients_in_period' => $clients->count(),
                    'clients_with_appointments' => $clients->where('appointments_count', '>', 0)->count(),
                ],
                'clients' => $clients->map(fn ($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'appointments_count' => $client->appointments_count,
                    'last_appointment_date' => $client->appointments_max_appointment_date,
                    'created_at' => $client->created_at,
                ]),
            ],
        ]);
    }

    private function validatePeriodFilters(Request $request, array $extraRules = []): array
    {
        return $request->validate(array_merge([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'service_id' => ['nullable', 'integer'],
        ], $extraRules));
    }

    private function startDateTime(string $date): string
    {
        return "{$date} 00:00:00";
    }

    private function endDateTime(string $date): string
    {
        return "{$date} 23:59:59";
    }
}