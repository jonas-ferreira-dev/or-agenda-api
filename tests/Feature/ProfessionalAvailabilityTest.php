<?php

namespace Tests\Feature;

use App\Models\ProfessionalAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_availabilities(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        ProfessionalAvailability::factory()->create([
            'user_id' => $otherUser->id,
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/professional-availabilities');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Horários de disponibilidade listados com sucesso.',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $user->id);
    }

    public function test_user_can_create_availability(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/professional-availabilities', [
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Horário de disponibilidade criado com sucesso.',
                'data' => [
                    'user_id' => $user->id,
                    'weekday' => 1,
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('professional_availabilities', [
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);
    }

    public function test_user_can_create_multiple_blocks_in_same_day_without_overlap(): void
    {
        $user = User::factory()->create();

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/professional-availabilities', [
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '19:00',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('professional_availabilities', [
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '19:00',
        ]);
    }

    public function test_user_cannot_create_overlapping_availability(): void
    {
        $user = User::factory()->create();

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/professional-availabilities', [
            'weekday' => 1,
            'start_time' => '11:00',
            'end_time' => '13:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Já existe um horário de disponibilidade nesse intervalo.',
            ]);
    }

    public function test_user_can_create_adjacent_availability_without_overlap(): void
    {
        $user = User::factory()->create();

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/professional-availabilities', [
            'weekday' => 1,
            'start_time' => '12:00',
            'end_time' => '14:00',
        ]);

        $response->assertCreated();
    }

    public function test_user_cannot_create_availability_with_end_time_before_start_time(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/professional-availabilities', [
            'weekday' => 1,
            'start_time' => '14:00',
            'end_time' => '10:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_user_can_update_own_availability(): void
    {
        $user = User::factory()->create();

        $availability = ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/professional-availabilities/{$availability->id}", [
            'weekday' => 2,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Horário de disponibilidade atualizado com sucesso.',
                'data' => [
                    'id' => $availability->id,
                    'user_id' => $user->id,
                    'weekday' => 2,
                    'start_time' => '13:00:00',
                    'end_time' => '18:00:00',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('professional_availabilities', [
            'id' => $availability->id,
            'weekday' => 2,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
            'is_active' => false,
        ]);
    }

    public function test_user_cannot_update_availability_to_overlap_another_block(): void
    {
        $user = User::factory()->create();

        ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $availability = ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
            'weekday' => 1,
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/professional-availabilities/{$availability->id}", [
            'weekday' => 1,
            'start_time' => '11:00',
            'end_time' => '14:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Já existe um horário de disponibilidade nesse intervalo.',
            ]);
    }

    public function test_user_cannot_update_other_users_availability(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $availability = ProfessionalAvailability::factory()->create([
            'user_id' => $otherUser->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/professional-availabilities/{$availability->id}", [
            'weekday' => 2,
            'start_time' => '13:00',
            'end_time' => '18:00',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_availability(): void
    {
        $user = User::factory()->create();

        $availability = ProfessionalAvailability::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/professional-availabilities/{$availability->id}");

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Horário de disponibilidade removido com sucesso.',
            ]);

        $this->assertDatabaseMissing('professional_availabilities', [
            'id' => $availability->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_availability(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $availability = ProfessionalAvailability::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/professional-availabilities/{$availability->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('professional_availabilities', [
            'id' => $availability->id,
        ]);
    }

    public function test_guest_cannot_manage_availabilities(): void
    {
        $response = $this->getJson('/api/professional-availabilities');

        $response->assertUnauthorized();
    }
}