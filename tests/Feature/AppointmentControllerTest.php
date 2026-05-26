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
        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/appointments', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00',
            'notes' => 'Primeiro atendimento',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Agendamento criado com sucesso.')
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '11:00:00');

        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
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
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_update_own_appointment(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2025-09-10',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/appointments/{$appointment->id}", [
            'start_time' => '11:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Agendamento atualizado com sucesso.')
            ->assertJsonPath('data.start_time', '11:00')
            ->assertJsonPath('data.end_time', '12:00:00');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
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

        public function test_user_can_cancel_own_appointment_with_reason(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Profissional indisponível neste horário.',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Agendamento cancelado com sucesso.',
                'data' => [
                    'id' => $appointment->id,
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Profissional indisponível neste horário.',
                    'cancelled_by' => 'professional',
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Profissional indisponível neste horário.',
            'cancelled_by' => 'professional',
        ]);

        $this->assertNotNull(
            Appointment::find($appointment->id)->cancelled_at
        );
    }

    public function test_user_cannot_cancel_other_users_appointment(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Teste de cancelamento.',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_cancel_appointment_requires_reason(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cancellation_reason']);
    }

    public function test_user_cannot_cancel_already_cancelled_appointment(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        $appointment = Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Cancelado anteriormente.',
            'cancelled_at' => now(),
            'cancelled_by' => 'professional',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Tentando cancelar novamente.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Este agendamento já está cancelado.',
            ]);
    }
}