<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_appointment(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = Service::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'notes' => 'Primeiro atendimento',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
    }

    public function test_user_cannot_create_appointment_with_client_from_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $client = Client::factory()->create(['user_id' => $otherUser->id]);
        $service = Service::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertNotFound();
    }

    public function test_user_cannot_create_appointment_with_service_from_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = Service::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertNotFound();
    }

    public function test_user_cannot_create_appointment_with_conflicting_time(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = Service::factory()->create(['user_id' => $user->id]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:30',
            'end_time' => '11:30',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Já existe um agendamento nesse intervalo.',
            ]);
    }

    public function test_user_lists_only_own_appointments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Appointment::factory()->create(['user_id' => $user->id]);
        Appointment::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/appointments');

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_user_can_update_own_appointment(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = Service::factory()->create(['user_id' => $user->id]);

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/appointments/{$appointment->id}", [
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
            ]);
    }

    public function test_user_can_delete_own_appointment(): void
    {
        $user = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('appointments', [
            'id' => $appointment->id,
        ]);
    }
}