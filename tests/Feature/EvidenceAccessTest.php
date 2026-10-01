<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_can_view_evidence(): void
    {
        Storage::fake('public');
        [$paOrder, $owner, $other] = $this->paWithPetugas();
        $admin = $this->userWithRole('admin');
        $evidence = Evidence::create([
            'pa_id' => $paOrder->id,
            'type' => 'perangkat',
            'file_path' => 'evidences/test.jpg',
            'uploaded_by' => $owner->id,
            'uploaded_at' => now(),
        ]);
        Storage::disk('public')->put($evidence->file_path, 'image-content');

        foreach ([$admin, $owner] as $user) {
            $response = $this->actingAs($user)->get(route('evidence.show', $evidence));

            $response->assertOk();
            $response->assertHeader('content-type', 'image/jpeg');
        }

        $this->actingAs($other)
            ->get(route('evidence.show', $evidence))
            ->assertForbidden();
    }

    public function test_authorized_user_gets_not_found_when_evidence_file_is_missing(): void
    {
        Storage::fake('public');
        [$paOrder, $owner] = $this->paWithPetugas();
        $evidence = Evidence::create([
            'pa_id' => $paOrder->id,
            'type' => 'perangkat',
            'file_path' => 'evidences/missing.jpg',
            'uploaded_by' => $owner->id,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('evidence.show', $evidence))
            ->assertNotFound();
    }

    /** @return array{0: PaOrder, 1: User, 2: User} */
    private function paWithPetugas(): array
    {
        $owner = $this->userWithRole('petugas');
        $other = $this->userWithRole('petugas');
        $ownerOfficer = Officer::factory()->create([
            'user_id' => $owner->id,
            'is_active' => true,
        ]);
        Officer::factory()->create([
            'user_id' => $other->id,
            'is_active' => true,
        ]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_officer_id' => $ownerOfficer->id,
            'current_status' => PaOrder::STATUS_DONE,
        ]);

        return [$paOrder, $owner, $other];
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
