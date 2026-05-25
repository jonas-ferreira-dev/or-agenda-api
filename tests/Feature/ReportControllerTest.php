<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_revenue_report(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'price' => 50,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
            'appointment_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/revenue?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Relatório de faturamento gerado com sucesso.',
                'data' => [
                    'summary' => [
                        'total_revenue' => 50,
                        'appointments_count' => 1,
                        'average_ticket' => 50,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'summary' => [
                        'total_revenue',
                        'appointments_count',
                        'average_ticket',
                    ],
                    'services',
                    'appointments',
                ],
            ]);
    }

    public function test_user_can_generate_appointments_report(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'appointment_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/appointments?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Relatório de agendamentos gerado com sucesso.',
                'data' => [
                    'summary' => [
                        'total' => 2,
                        'scheduled' => 1,
                        'confirmed' => 0,
                        'completed' => 1,
                        'cancelled' => 0,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'summary' => [
                        'total',
                        'scheduled',
                        'confirmed',
                        'completed',
                        'cancelled',
                    ],
                    'statuses',
                    'appointments',
                ],
            ]);
    }

    public function test_user_can_filter_appointments_report_by_status(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
            'appointment_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/appointments?start_date=2026-05-01&end_date=2026-05-31&status=cancelled'
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'summary' => [
                        'total' => 1,
                        'scheduled' => 0,
                        'confirmed' => 0,
                        'completed' => 0,
                        'cancelled' => 1,
                    ],
                ],
            ]);
    }

    public function test_user_can_generate_cancellations_report(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
            'appointment_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/cancellations?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Relatório de cancelamentos gerado com sucesso.',
                'data' => [
                    'summary' => [
                        'total_appointments' => 2,
                        'cancelled_count' => 1,
                        'cancellation_rate' => 50,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'summary' => [
                        'total_appointments',
                        'cancelled_count',
                        'cancellation_rate',
                    ],
                    'clients',
                    'services',
                    'appointments',
                ],
            ]);
    }

    public function test_user_can_generate_clients_report(): void
    {
        $user = User::factory()->create();

        $clientInPeriod = Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Cliente Um',
            'created_at' => '2026-05-10 10:00:00',
        ]);

        Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Cliente Dois',
            'created_at' => '2026-04-10 10:00:00',
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $clientInPeriod->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'appointment_date' => '2026-05-15',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/clients?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Relatório de clientes gerado com sucesso.',
                'data' => [
                    'summary' => [
                        'total_clients' => 2,
                        'new_clients_in_period' => 1,
                        'clients_with_appointments' => 1,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'summary' => [
                        'total_clients',
                        'new_clients_in_period',
                        'clients_with_appointments',
                    ],
                    'clients',
                ],
            ]);
    }

    public function test_reports_are_isolated_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'price' => 50,
        ]);

        $otherClient = Client::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $otherService = Service::factory()->create([
            'user_id' => $otherUser->id,
            'price' => 999,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'completed',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Appointment::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => $otherClient->id,
            'service_id' => $otherService->id,
            'status' => 'completed',
            'appointment_date' => '2026-05-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/revenue?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'summary' => [
                        'total_revenue' => 50,
                        'appointments_count' => 1,
                    ],
                ],
            ]);
    }

    public function test_guest_cannot_access_reports(): void
    {
        $response = $this->getJson(
            '/api/reports/revenue?start_date=2026-05-01&end_date=2026-05-31'
        );

        $response->assertUnauthorized();
    }

    public function test_report_requires_valid_period(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/reports/revenue?start_date=2026-05-31&end_date=2026-05-01'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }
}