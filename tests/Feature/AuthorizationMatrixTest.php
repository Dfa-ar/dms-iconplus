<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matches_role_matrix(): void
    {
        $users = $this->usersByRole();

        $this->actingAs($users['admin'])
            ->get(route('dashboard'))
            ->assertOk();
        $this->actingAs($users['petugas'])
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($users['admin'])
            ->get(route('admin.petugas.index'))
            ->assertOk();
        $this->actingAs($users['petugas'])
            ->get(route('admin.petugas.index'))
            ->assertForbidden();

        $this->actingAs($users['petugas'])
            ->get(route('petugas.tasks.index'))
            ->assertOk();
        $this->actingAs($users['admin'])
            ->get(route('petugas.tasks.index'))
            ->assertForbidden();
    }

    public function test_gate_abilities_match_role_matrix(): void
    {
        $users = $this->usersByRole();

        $expected = [
            'admin' => [
                'viewDashboard' => true,
                'exportReport' => true,
                'uploadPa' => true,
            ],
            'petugas' => [
                'viewDashboard' => false,
                'exportReport' => false,
                'uploadPa' => false,
            ],
        ];

        foreach ($expected as $role => $abilities) {
            foreach ($abilities as $ability => $allowed) {
                $this->assertSame(
                    $allowed,
                    Gate::forUser($users[$role])->allows($ability),
                    "Unexpected {$ability} result for {$role}."
                );
            }
        }
    }

    /** @return array<string, User> */
    private function usersByRole(): array
    {
        $users = [];

        foreach (['admin', 'petugas'] as $roleName) {
            $role = Role::create(['name' => $roleName]);
            $users[$roleName] = User::factory()->create([
                'role_id' => $role->id,
                'is_active' => true,
            ]);
        }

        return $users;
    }
}
