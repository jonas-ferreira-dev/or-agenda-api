<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_user(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform/users', [
            'name' => 'João Studio',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Usuário criado com sucesso.',
                'data' => [
                    'name' => 'João Studio',
                    'email' => 'joao@example.com',
                    'is_platform_admin' => false,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'João Studio',
            'email' => 'joao@example.com',
            'is_platform_admin' => false,
        ]);

        $createdUser = User::where('email', 'joao@example.com')->first();

        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('password123', $createdUser->password));
    }

    public function test_normal_user_cannot_create_platform_user(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/platform/users', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Acesso permitido apenas para administradores da plataforma.',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'cliente@example.com',
        ]);
    }

    public function test_guest_cannot_create_platform_user(): void
    {
        $response = $this->postJson('/api/platform/users', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('users', [
            'email' => 'cliente@example.com',
        ]);
    }

    public function test_platform_user_creation_requires_valid_data(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform/users', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => 'different',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);
    }

    public function test_platform_user_email_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        User::factory()->create([
            'email' => 'duplicado@example.com',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform/users', [
            'name' => 'Usuário Duplicado',
            'email' => 'duplicado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_public_register_route_is_disabled(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Cadastro Público',
            'email' => 'publico@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('users', [
            'email' => 'publico@example.com',
        ]);
    }

        public function test_platform_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        User::factory()->count(2)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/platform/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_platform_admin',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta',
            ]);
    }

        public function test_normal_user_cannot_list_platform_users(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/platform/users');

        $response->assertForbidden();
    }

        public function test_platform_admin_can_update_user(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $targetUser = User::factory()->create([
            'name' => 'Nome Antigo',
            'email' => 'antigo@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/platform/users/{$targetUser->id}", [
            'name' => 'Nome Novo',
            'email' => 'novo@example.com',
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Usuário atualizado com sucesso.',
                'data' => [
                    'id' => $targetUser->id,
                    'name' => 'Nome Novo',
                    'email' => 'novo@example.com',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Nome Novo',
            'email' => 'novo@example.com',
            'is_active' => false,
        ]);
    }


        public function test_platform_admin_can_update_user_password(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $targetUser = User::factory()->create([
            'password' => 'old-password',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/platform/users/{$targetUser->id}", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertOk();

        $targetUser->refresh();

        $this->assertTrue(Hash::check('new-password123', $targetUser->password));
    }

        public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'bloqueado@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'bloqueado@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Usuário bloqueado. Entre em contato com o suporte.',
            ]);
    }
}