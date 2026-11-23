<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_user_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'o@example.test', 'password' => 'Correct-Horse-9']);

        $this->post(route('login.store'), ['email' => 'O@Example.test', 'password' => 'Correct-Horse-9'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        User::factory()->create(['email' => 'o@example.test', 'password' => 'Correct-Horse-9']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'o@example.test', 'password' => 'nope'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'off@example.test', 'password' => 'Correct-Horse-9']);

        $this->post(route('login.store'), ['email' => 'off@example.test', 'password' => 'Correct-Horse-9'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_failed_logins_do_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'real@example.test', 'password' => 'Correct-Horse-9']);

        $this->post(route('login.store'), ['email' => 'ghost@example.test', 'password' => 'x'])
            ->assertSessionHasErrors('email');
        $unknownMessage = session('errors')->first('email');

        $this->post(route('login.store'), ['email' => 'real@example.test', 'password' => 'x'])
            ->assertSessionHasErrors('email');
        $wrongPasswordMessage = session('errors')->first('email');

        $this->assertNotEmpty($unknownMessage);
        $this->assertSame($unknownMessage, $wrongPasswordMessage);
    }

    public function test_repeated_failures_lock_the_login_temporarily(): void
    {
        User::factory()->create(['email' => 'o@example.test', 'password' => 'Correct-Horse-9']);

        foreach (range(1, 5) as $ignored) {
            $this->post(route('login.store'), ['email' => 'o@example.test', 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post(route('login.store'), ['email' => 'o@example.test', 'password' => 'Correct-Horse-9'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'Plain-Secret-123']);

        $this->assertNotSame('Plain-Secret-123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Plain-Secret-123', $user->getRawOriginal('password')));
    }

    public function test_accounts_with_a_temporary_password_must_change_it_first(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'password' => 'Temp-Pass-123']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get(route('students.index'))->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get(route('password.edit'))->assertOk();
    }

    public function test_changing_the_password_clears_the_forced_change_flag(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'password' => 'Temp-Pass-123']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'Temp-Pass-123',
            'password' => 'Brand-New-Pass-456',
            'password_confirmation' => 'Brand-New-Pass-456',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Brand-New-Pass-456', $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_weak_or_wrong_current_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Temp-Pass-123']);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrong',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['current_password', 'password']);
    }

    public function test_an_account_disabled_mid_session_is_signed_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_responses_carry_security_headers_and_are_not_cached(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
