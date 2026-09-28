<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    }

    // --- register ---

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jean Dupont',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
                 ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'jean@example.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $this->postJson('/api/auth/register', [
            'name'                  => 'Jean Dupont',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('email');
    }

    public function test_register_fails_with_mismatched_passwords(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Jean Dupont',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('password');
    }

    // --- login ---

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'mauvais_mot_de_passe',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('email');
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'inconnu@example.com',
            'password' => 'secret123',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('email');
    }

    public function test_login_revokes_previous_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $user->createToken('auth_token'); // token existant

        $this->assertCount(1, $user->tokens);

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertCount(1, $user->fresh()->tokens); // toujours 1 : l'ancien révoqué, nouveau créé
    }

    // --- me ---

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/auth/me')
             ->assertOk()
             ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_unauthenticated_cannot_get_profile(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    // --- logout ---

    public function test_authenticated_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
             ->postJson('/api/auth/logout')
             ->assertOk()
             ->assertJsonFragment(['message' => 'Déconnecté.']);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    // --- pin/set ---

    public function test_user_can_set_a_new_pin(): void
    {
        $user = User::factory()->create(['order_pin' => null]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/set', ['pin' => '1234'])
             ->assertOk()
             ->assertJsonFragment(['message' => 'Code PIN défini avec succès.']);

        $this->assertTrue(Hash::check('1234', $user->fresh()->order_pin));
    }

    public function test_user_can_change_pin_with_correct_current_pin(): void
    {
        $user = User::factory()->create(['order_pin' => Hash::make('0000')]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/set', ['pin' => '9999', 'current_pin' => '0000'])
             ->assertOk();

        $this->assertTrue(Hash::check('9999', $user->fresh()->order_pin));
    }

    public function test_set_pin_fails_with_wrong_current_pin(): void
    {
        $user = User::factory()->create(['order_pin' => Hash::make('0000')]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/set', ['pin' => '9999', 'current_pin' => 'mauvais'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('current_pin');
    }

    public function test_set_pin_requires_digits_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/set', ['pin' => 'abcd'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('pin');
    }

    // --- pin/verify ---

    public function test_user_can_verify_correct_pin(): void
    {
        $user = User::factory()->create(['order_pin' => Hash::make('1234')]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/verify', ['pin' => '1234'])
             ->assertOk()
             ->assertJson(['verified' => true]);
    }

    public function test_verify_returns_false_for_wrong_pin(): void
    {
        $user = User::factory()->create(['order_pin' => Hash::make('1234')]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/verify', ['pin' => '9999'])
             ->assertOk()
             ->assertJson(['verified' => false]);
    }

    public function test_verify_fails_when_no_pin_defined(): void
    {
        $user = User::factory()->create(['order_pin' => null]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/auth/pin/verify', ['pin' => '1234'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('pin');
    }
}
