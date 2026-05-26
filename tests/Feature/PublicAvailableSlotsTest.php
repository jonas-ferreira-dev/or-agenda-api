<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\User;
use App\Services\PublicAvailableSlotsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_slots_inside_professional_availability(): void
    {
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([
            [
                'start_time' => '10:00',
                'end_time' => '11:00',
            ],
            [
                'start_time' => '11:00',
                'end_time' => '12:00',
            ],
        ], $slots);
    }

    public function test_it_generates_slots_from_multiple_blocks_in_same_day(): void
    {
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([
            ['start_time' => '10:00', 'end_time' => '11:00'],
            ['start_time' => '11:00', 'end_time' => '12:00'],
            ['start_time' => '13:00', 'end_time' => '14:00'],
            ['start_time' => '14:00', 'end_time' => '15:00'],
        ], $slots);
    }

    public function test_it_does_not_return_slots_without_availability(): void
    {
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([], $slots);
    }

    public function test_it_ignores_inactive_availability_blocks(): void
    {
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => false,
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([], $slots);
    }

    public function test_it_removes_slots_that_overlap_existing_appointments(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
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

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([
            ['start_time' => '10:00', 'end_time' => '11:00'],
            ['start_time' => '12:00', 'end_time' => '13:00'],
        ], $slots);
    }

    public function test_cancelled_appointments_do_not_block_slots(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([
            ['start_time' => '10:00', 'end_time' => '11:00'],
            ['start_time' => '11:00', 'end_time' => '12:00'],
        ], $slots);
    }

    public function test_confirmed_appointments_block_slots(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'duration_minutes' => 60,
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'appointment_date' => '2026-05-25',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        $slots = app(PublicAvailableSlotsService::class)->getAvailableSlots(
            professional: $user,
            service: $service,
            date: '2026-05-25',
        );

        $this->assertEquals([
            ['start_time' => '11:00', 'end_time' => '12:00'],
        ], $slots);
    }
}