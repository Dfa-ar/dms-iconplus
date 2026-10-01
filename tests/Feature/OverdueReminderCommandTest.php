<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_assignment_reminder_command_sends_whatsapp_notification(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
            'parent_group' => 'Bandung',
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create([
                'role_id' => $petugasRole->id,
                'is_active' => true,
            ])->id,
            'region_id' => $region->id,
            'phone' => '6281234567890',
            'is_active' => true,
            'daily_target' => 20,
        ]);

        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_officer_id' => $officer->id,
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'assigned_date' => now()->subDays(3)->toDateString(),
            'pa_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->artisan('reminders:wa-overdue')
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $officer->user_id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);

        $this->assertTrue(Notification::query()->where('user_id', $officer->user_id)->exists());
        $this->assertDatabaseHas('pa_orders', ['id' => $paOrder->id, 'current_officer_id' => $officer->id]);
    }
}
