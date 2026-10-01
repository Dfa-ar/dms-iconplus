<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Officer;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_whatsapp_reminder_to_officer(): void
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

        $response = $this->actingAs($admin)
            ->post(route('admin.assignments.remind', $officer), [
                'region_id' => $region->id,
                'target_per_officer' => 5,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $officer->user_id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);

        $this->assertTrue(Notification::query()->where('user_id', $officer->user_id)->exists());
    }
}
