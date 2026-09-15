<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): \App\Models\User
    {
        return \App\Models\User::where('username', $username)->firstOrFail();
    }

    public function test_admin_can_create_user_with_numeric_role_id(): void
    {
        $admin = $this->user('admin');
        $staffRoleId = Role::where('name', 'STAFF')->value('id');

        $response = $this->actingAs($admin)->post(route('user-setup.user.store'), [
            'username' => 'karyawan_baru',
            'name' => 'Karyawan Baru',
            'email' => 'karyawan@baska.test',
            'nowa' => '08120000111',
            'password' => 'PasswordKuat123',
            'role' => (string) $staffRoleId,
        ]);

        $response->assertSessionHasNoErrors();
        $new = \App\Models\User::where('username', 'karyawan_baru')->first();
        $this->assertNotNull($new, 'user tersimpan');
        $this->assertTrue($new->hasRole('STAFF'), 'role STAFF ter-assign dari ID string');
    }

    public function test_invalid_role_id_is_rejected(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->post(route('user-setup.user.store'), [
            'username' => 'rolegagal',
            'name' => 'Gagal',
            'email' => 'gagal@baska.test',
            'password' => 'PasswordKuat123',
            'role' => '99999',
        ]);
        $response->assertSessionHasErrors('role');
        $this->assertNull(\App\Models\User::where('username', 'rolegagal')->first());
    }

    public function test_weak_password_is_rejected(): void
    {
        $admin = $this->user('admin');
        $roleId = Role::where('name', 'STAFF')->value('id');

        $response = $this->actingAs($admin)->post(route('user-setup.user.store'), [
            'username' => 'lemah',
            'name' => 'Lemah',
            'email' => 'lemah@baska.test',
            'password' => 'abc',
            'role' => $roleId,
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_staff_cannot_create_user(): void
    {
        $response = $this->actingAs($this->user('staff'))->post(route('user-setup.user.store'), [
            'username' => 'naik',
            'name' => 'Naik',
            'email' => 'naik@baska.test',
            'password' => 'PasswordKuat123',
            'role' => Role::where('name', 'STAFF')->value('id'),
        ]);
        $response->assertStatus(403);
    }

    public function test_superadmin_cannot_be_demoted_by_admin(): void
    {
        $admin = $this->user('admin');
        $super = $this->user('superadmin');
        $staffRoleId = Role::where('name', 'STAFF')->value('id');

        $response = $this->actingAs($admin)->put(route('user-setup.user.update', $super->id), [
            'name' => 'Super',
            'email' => $super->email,
            'nowa' => $super->nowa,
            'password' => '',
            'role' => (string) $staffRoleId,
            'deleted_at_baru' => '1',
        ]);
        // Validasi role OK (STAFF id valid) tapi guard SUPERADMIN menolak.
        // NB: pesan detail baru muncul sempurna setelah tiket S-10 (rethrow ValidationException),
        // di sprint ini cukup dipastikan write SUPERADMIN tertolak & 302 redirect dengan error flash.
        $response->assertStatus(302);
        $response->assertSessionHas('errors');
        $super->refresh();
        $this->assertTrue($super->hasRole('SUPERADMIN'), 'SUPERADMIN tidak berubah');
    }
}
