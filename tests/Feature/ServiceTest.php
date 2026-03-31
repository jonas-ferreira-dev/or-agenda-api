<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_service(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/services', [
            'name' => 'Corte Masculino',
            'duration_minutes' => 45,
            'price' => 35.90,
            'description' => 'Serviço de corte tradicional.',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Corte Masculino');

        $this->assertDatabaseHas('services', [
            'user_id' => $user->id,
            'name' => 'Corte Masculino',
            'duration_minutes' => 45,
        ]);
    }

    public function test_guest_cannot_create_service(): void
    {
        $response = $this->postJson('/api/services', [
            'name' => 'Corte Masculino',
            'duration_minutes' => 45,
            'price' => 35.90,
            'active' => true,
        ]);

        $response->assertUnauthorized();
    }

    public function test_service_creation_requires_valid_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/services', [
            'name' => '',
            'duration_minutes' => 0,
            'price' => -10,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'duration_minutes',
                'price',
            ]);
    }

    public function test_authenticated_user_sees_only_their_own_services(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Service::factory()->create([
            'user_id' => $user->id,
            'name' => 'Meu Serviço',
        ]);

        Service::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Serviço de Outro Usuário',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/services');

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Meu Serviço'])
            ->assertJsonMissing(['name' => 'Serviço de Outro Usuário']);
    }

    public function test_user_can_update_their_own_service(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
            'name' => 'Corte Simples',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/services/{$service->id}", [
            'name' => 'Corte Premium',
            'duration_minutes' => 60,
            'price' => 55.50,
            'description' => 'Atualizado',
            'active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Corte Premium');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Corte Premium',
            'duration_minutes' => 60,
        ]);
    }

    public function test_user_cannot_update_service_from_another_user(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/services/{$service->id}", [
            'name' => 'Tentativa Indevida',
            'duration_minutes' => 60,
            'price' => 50,
            'description' => 'Teste',
            'active' => true,
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_delete_their_own_service(): void
    {
         /** @var User $user */
        $user = User::factory()->create();

        $service = Service::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/services/{$service->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }
}