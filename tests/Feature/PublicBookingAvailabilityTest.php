<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class PublicBookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-24 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_public_availability_uses_professional_availability_blocks(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/public/professionals/joao-barber/availability?date=2026-05-25&service_id={$service->id}"
        );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Disponibilidade carregada com sucesso.',
                'data' => [
                    'date' => '2026-05-25',
                    'service_id' => $service->id,
                    'duration_minutes' => 60,
                    'available_slots' => [
                        [
                            'start_time' => '10:00',
                            'end_time' => '11:00',
                        ],
                        [
                            'start_time' => '11:00',
                            'end_time' => '12:00',
                        ],
                    ],
                ],
            ]);
    }

    public function test_public_availability_does_not_show_slots_outside_professional_availability(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'maria-studio',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/public/professionals/maria-studio/availability?date=2026-05-25&service_id={$service->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.available_slots', [
                [
                    'start_time' => '14:00',
                    'end_time' => '15:00',
                ],
                [
                    'start_time' => '15:00',
                    'end_time' => '16:00',
                ],
            ]);
    }

    public function test_public_availability_removes_busy_slots(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'ana-clinic',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'scheduled',
        ]);

        $response = $this->getJson(
            "/api/public/professionals/ana-clinic/availability?date=2026-05-25&service_id={$service->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.available_slots', [
                [
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                ],
                [
                    'start_time' => '12:00',
                    'end_time' => '13:00',
                ],
            ]);
    }

    public function test_public_booking_cannot_create_appointment_outside_availability(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'carlos-barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/carlos-barber/appointments', [
            'name' => 'Cliente Teste',
            'phone' => '21999999999',
            'email' => 'cliente@email.com',
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '13:00',
            'notes' => null,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Horário indisponível para agendamento.',
            ]);

        $this->assertDatabaseMissing('appointments', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '13:00',
        ]);
    }

    public function test_public_booking_can_create_appointment_inside_availability(): void
    {
        $user = User::factory()->create();

        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'bia-nails',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/bia-nails/appointments', [
            'name' => 'Cliente Teste',
            'phone' => '21999999999',
            'email' => 'cliente@email.com',
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'notes' => null,
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Agendamento público criado com sucesso.',
                'data' => [
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                    'status' => 'scheduled',
                    'client' => [
                        'name' => 'Cliente Teste',
                        'phone' => '21999999999',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
        ]);
    }
}