<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;



class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jonas Ferreira',
            'email' => 'jonas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jonas@example.com',
            'name' => 'Jonas Ferreira',
        ]);
    }

    public function test_password_is_hashed_when_user_registers(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Jonas Ferreira',
            'email' => 'jonas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'jonas@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'jonas@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jonas@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jonas@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jonas@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }
}