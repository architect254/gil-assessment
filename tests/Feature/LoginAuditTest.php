<?php

namespace Tests\Feature;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_creates_login_activity_record(): void
    {
        $user = User::factory()->create([
            'name' => 'Gate Officer',
            'email' => 'officer@example.com',
        ]);

        $this->assertDatabaseEmpty('login_activities');

        event(new Login('web', $user, false));

        $this->assertDatabaseCount('login_activities', 1);

        $activity = LoginActivity::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->user_id);
        $this->assertNotNull($activity->logged_in_at);
    }

    public function test_multiple_logins_record_distinct_audit_timestamps(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        event(new Login('web', $user1, false));
        event(new Login('web', $user2, false));
        event(new Login('web', $user1, false));

        $this->assertDatabaseCount('login_activities', 3);
        $this->assertSame(2, LoginActivity::query()->where('user_id', $user1->id)->count());
        $this->assertSame(1, LoginActivity::query()->where('user_id', $user2->id)->count());
    }
}
