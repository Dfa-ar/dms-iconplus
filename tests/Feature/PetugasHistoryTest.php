<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use App\Models\KendalaReason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PetugasHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_without_officer_profile_sees_empty_history(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_DONE,
            'current_officer_id' => null,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('petugas.tasks.history'));

        $response->assertOk();
        $response->assertViewHas('history', function ($history) {
            return $history->count() === 0;
        });
    }

    public function test_petugas_cannot_complete_pa_that_belongs_to_another_officer(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $owner = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        \App\Models\Officer::factory()->create([
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $otherOfficer = \App\Models\Officer::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $otherOfficer->id,
            'assigned_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->post(route('petugas.tasks.complete', $paOrder), []);

        $response->assertForbidden();
    }

    public function test_petugas_is_redirected_to_their_task_dashboard_after_login(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'email' => 'petugas@idms.test',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'identifier' => 'petugas@idms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('petugas.tasks.index'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'email' => 'inactive@idms.test',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $identifier = 'rate-limit-'.uniqid().'@idms.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.submit'), [
                    'identifier' => $identifier,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.submit'), [
                'identifier' => $identifier,
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_petugas_can_complete_task_without_phase_two_evidence(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
            'assigned_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('petugas.tasks.complete', $paOrder), [
                'receiver_name' => 'Penerima Test',
            ])
            ->assertRedirect(route('petugas.tasks.index'));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_DONE,
        ]);
        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_kendala_notes_are_required_only_for_lainnya(): void
    {
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
        ]);
        $reason = KendalaReason::create(['label' => 'Lainnya', 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('petugas.tasks.kendala', $paOrder), [
                'kendala_reason_id' => $reason->id,
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
    }
}
