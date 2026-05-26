<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\ProfessionalAvailability;

class PublicBookingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_public_professional_profile(): void
    {
        $user = User::factory()->create(['name' => 'João']);
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'public_name' => 'João Barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        $response = $this->getJson('/api/public/professionals/joao-barber');

        $response->assertOk()
            ->assertJsonFragment([
                'slug' => 'joao-barber',
                'public_name' => 'João Barber',
            ]);
    }

    public function test_can_list_public_services_from_professional(): void
    {
        $user = User::factory()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'is_public' => true,
            'booking_enabled' => true,
        ]);

        Service::factory()->create([
            'user_id' => $user->id,
            'name' => 'Corte',
        ]);

        $response = $this->getJson('/api/public/professionals/joao-barber/services');

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Corte',
            ]);
    }

    public function test_public_user_can_create_appointment_and_client(): void
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
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 5,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'notes' => 'Primeiro atendimento',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'appointment_date' => '2026-04-10T00:00:00.000000Z',
            ]);

        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ]);

        $client = Client::where('user_id', $user->id)
            ->where('phone', '11999999999')
            ->first();

        $this->assertNotNull($client);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    public function test_public_user_reuses_existing_client_by_phone(): void
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
            'duration_minutes' => 60,
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Maria Antiga',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 5,
            'start_time' => '12:00',
            'end_time' => '14:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Maria Nova',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('clients', 1);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    public function test_public_user_cannot_create_conflicting_appointment(): void
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
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 5,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Outra Cliente',
            'email' => 'outra@example.com',
            'phone' => '11888888888',
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '10:30',
            'end_time' => '11:30',
        ]);

        $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Horário indisponível para agendamento.',
        ]);
    }

    public function test_cannot_create_public_appointment_if_booking_is_disabled(): void
    {
        $user = User::factory()->create();
        ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            'slug' => 'joao-barber',
            'is_public' => true,
            'booking_enabled' => false,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/public/professionals/joao-barber/appointments', [
            'name' => 'Maria Silva',
            'phone' => '11999999999',
            'service_id' => $service->id,
            'appointment_date' => '2026-04-10',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertNotFound();
    }
}