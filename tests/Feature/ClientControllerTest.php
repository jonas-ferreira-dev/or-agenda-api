<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_client(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/clients', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'phone' => '11999999999',
            'notes' => 'Cliente recorrente',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Maria Silva',
                'email' => 'maria@example.com',
            ]);

        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_user_lists_only_own_clients(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Client::factory()->create(['user_id' => $user->id, 'name' => 'Cliente A']);
        Client::factory()->create(['user_id' => $otherUser->id, 'name' => 'Cliente B']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/clients');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Cliente A'])
            ->assertJsonMissing(['name' => 'Cliente B']);
    }

    public function test_user_cannot_view_client_from_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $client = Client::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clients/{$client->id}");

        $response->assertNotFound();
    }

    public function test_user_can_update_own_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/clients/{$client->id}", [
            'name' => 'Nome Novo',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Nome Novo',
            ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_user_can_delete_own_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/clients/{$client->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('clients', [
            'id' => $client->id,
        ]);
    }
}