<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonilManagementTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): string
    {
        return 'Bearer '.$user->createToken('test')->plainTextToken;
    }

    public function test_admin_sistem_can_create_and_list_personil(): void
    {
        $admin = User::factory()->adminSistem()->create();

        $create = $this->withHeader('Authorization', $this->bearer($admin))
            ->postJson('/api/personil', [
                'name' => 'Petugas Unit Kerja',
                'email' => 'unit.petugas@example.com',
                'password' => 'Password#123',
                'password_confirmation' => 'Password#123',
                'role' => 'unit_kerja',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'unit_kerja');

        $this->assertDatabaseHas('users', [
            'email' => 'unit.petugas@example.com',
            'role' => 'unit_kerja',
        ]);

        $this->withHeader('Authorization', $this->bearer($admin))
            ->getJson('/api/personil?per_page=10&status=all')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_non_admin_sistem_cannot_access_personil_management(): void
    {
        $pengelola = User::factory()->pengelola()->create();

        $this->withHeader('Authorization', $this->bearer($pengelola))
            ->getJson('/api/personil')
            ->assertStatus(403);

        $this->withHeader('Authorization', $this->bearer($pengelola))
            ->postJson('/api/personil', [
                'name' => 'Tidak Boleh',
                'email' => 'tidak.boleh@example.com',
                'password' => 'Password#123',
                'password_confirmation' => 'Password#123',
                'role' => 'unit_kerja',
            ])
            ->assertStatus(403);
    }

    public function test_admin_sistem_cannot_deactivate_or_demote_own_account(): void
    {
        $admin = User::factory()->adminSistem()->create([
            'name' => 'Admin Sistem',
            'email' => 'admin.sistem@example.com',
        ]);

        $this->withHeader('Authorization', $this->bearer($admin))
            ->deleteJson('/api/personil/'.$admin->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->withHeader('Authorization', $this->bearer($admin))
            ->putJson('/api/personil/'.$admin->id, [
                'name' => 'Admin Sistem',
                'email' => 'admin.sistem@example.com',
                'role' => 'pengelola_aplikasi',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_sistem_can_restore_inactive_personil(): void
    {
        $admin = User::factory()->adminSistem()->create();
        $target = User::factory()->create([
            'role' => 'tim_implementasi_aplikasi',
        ]);
        $target->delete();

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->withHeader('Authorization', $this->bearer($admin))
            ->postJson('/api/personil/'.$target->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_sistem_can_permanently_delete_personil(): void
    {
        $admin = User::factory()->adminSistem()->create();
        $target = User::factory()->create([
            'name' => 'Backend Developer',
            'email' => 'backend.developer@example.com',
            'role' => 'tim_implementasi_aplikasi',
        ]);
        $target->delete();

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->withHeader('Authorization', $this->bearer($admin))
            ->deleteJson('/api/personil/'.$target->id.'/force')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
            'email' => 'backend.developer@example.com',
        ]);
    }
}
