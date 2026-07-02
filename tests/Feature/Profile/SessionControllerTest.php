<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for the profile session-revocation endpoints:
 *   DELETE /profile/sessions/{id}   → profile.sessions.revoke
 *   DELETE /profile/sessions/all    → profile.sessions.revoke-all
 */
class SessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['status' => 'active']);
        $this->user->assignRole('student');
    }

    // ── Guest guard ───────────────────────────────────────────────────────────

    public function test_guest_cannot_revoke_a_session(): void
    {
        $this->delete(route('profile.sessions.revoke', 'some-session-id'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_guest_cannot_revoke_all_sessions(): void
    {
        $this->delete(route('profile.sessions.revoke-all'))
            ->assertRedirect(route('auth.login'));
    }

    // ── Single revoke ─────────────────────────────────────────────────────────

    public function test_user_can_revoke_another_session(): void
    {
        $other = $this->createSession($this->user, 'other-session-id');

        $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke', $other->session_id))
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseMissing('user_sessions', ['session_id' => 'other-session-id']);
    }

    public function test_user_cannot_revoke_their_current_session(): void
    {
        $sessionId = $this->app->make('session')->getId();

        $response = $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke', $sessionId));

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('error');
    }

    public function test_revoking_a_non_existent_session_returns_error(): void
    {
        $response = $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke', 'does-not-exist'));

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('error');
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $otherSession = $this->createSession($other, 'other-user-session');

        $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke', $otherSession->session_id))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('user_sessions', ['session_id' => 'other-user-session']);
    }

    // ── Revoke all ────────────────────────────────────────────────────────────

    public function test_revoke_all_removes_other_sessions_but_keeps_current(): void
    {
        $this->createSession($this->user, 'session-a');
        $this->createSession($this->user, 'session-b');

        $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke-all'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('user_sessions', ['session_id' => 'session-a']);
        $this->assertDatabaseMissing('user_sessions', ['session_id' => 'session-b']);
    }

    public function test_revoke_all_with_no_other_sessions_shows_success(): void
    {
        $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke-all'))
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');
    }

    public function test_revoke_all_does_not_affect_other_users_sessions(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $this->createSession($other, 'other-user-session');

        $this->actingAs($this->user)
            ->delete(route('profile.sessions.revoke-all'));

        $this->assertDatabaseHas('user_sessions', ['session_id' => 'other-user-session']);
    }

    // ── JSON responses ────────────────────────────────────────────────────────

    public function test_revoke_returns_json_when_requested(): void
    {
        $session = $this->createSession($this->user, 'json-session-id');

        $this->actingAs($this->user)
            ->deleteJson(route('profile.sessions.revoke', $session->session_id))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_revoke_all_returns_json_when_requested(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('profile.sessions.revoke-all'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_revoking_non_existent_session_via_json_returns_404(): void
    {
        $this->actingAs($this->user)
            ->deleteJson(route('profile.sessions.revoke', 'does-not-exist'))
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createSession(User $user, string $sessionId): UserSession
    {
        return UserSession::create([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'created_at' => now(),
        ]);
    }
}
