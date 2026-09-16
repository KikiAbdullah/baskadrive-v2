<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class UserLogSeeder extends Seeder
{
    /**
     * Template pesan log yang realistis, sesuai format LogHelper.
     */
    protected array $templates = [
        'add' => [
            'user <b>{name}</b> melakukan <b>Penambahan</b> data di menu <b>{menu}</b> dan id/nomor <b>{id}</b>',
            'user <b>{name}</b> menambahkan data baru pada modul <b>{menu}</b>',
        ],
        'edit' => [
            'user <b>{name}</b> melakukan <b>Perubahan</b> data di menu <b>{menu}</b> dan id/nomor <b>{id}</b>',
            'user <b>{name}</b> memperbarui informasi pada modul <b>{menu}</b>',
        ],
        'delete' => [
            'user <b>{name}</b> melakukan <b>Penghapusan</b> data di menu <b>{menu}</b> dan id/nomor <b>{id}</b>',
            'user <b>{name}</b> menghapus data pada modul <b>{menu}</b>',
        ],
        'approve' => [
            'user <b>{name}</b> melakukan <b>Approve</b> data di menu <b>{menu}</b> dan id/nomor <b>{id}</b>',
        ],
        'reject' => [
            'user <b>{name}</b> melakukan <b>Reject</b> data di menu <b>{menu}</b> dan id/nomor <b>{id}</b>',
        ],
        'login' => [
            'user <b>{name}</b> berhasil login ke dalam sistem',
        ],
        'logout' => [
            'user <b>{name}</b> keluar dari sistem',
        ],
    ];

    /**
     * Daftar menu yang tersedia.
     */
    protected array $menus = [
        'Permission', 'Role', 'User', 'Dashboard', 'Log Viewer', 'Settings', 'Report',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all(['id', 'name']);

        if ($users->isEmpty()) {
            $this->command->warn('UserLogSeeder dilewati: belum ada user.');

            return;
        }

        $now = Carbon::now();
        $created = 0;

        // Log login/logout untuk semua user aktif dalam 30 hari terakhir
        foreach ($users as $user) {
            $loginCount = random_int(2, 8);

            for ($i = 0; $i < $loginCount; $i++) {
                $timestamp = $now->copy()->subDays(random_int(0, 30))->subMinutes(random_int(0, 600));

                $type = ($i % 5 === 0) ? 'logout' : 'login';

                UserLog::create([
                    'user_id' => $user->id,
                    'action' => $type, // 'login'/'logout' lowercase (mutator UserLog menormalisasi sisanya)
                    'menu' => null,
                    'message' => $this->renderMessage($type, $user->name, null, $user->id),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $created++;
            }
        }

        // Log aktivitas CRUD untuk user tertentu (superadmin, admin, manager)
        $activeUsers = User::whereIn('username', ['superadmin', 'admin', 'manager', 'manager2', 'supervisor'])
            ->get(['id', 'name']);

        foreach ($activeUsers as $user) {
            $actionCount = random_int(5, 15);

            for ($i = 0; $i < $actionCount; $i++) {
                $type = $this->randomType();
                $menu = $this->menus[array_rand($this->menus)];
                $id = random_int(1, 100);
                $timestamp = $now->copy()->subDays(random_int(0, 30))->subMinutes(random_int(0, 600));

                UserLog::create([
                    'user_id' => $user->id,
                    'action' => $this->actionLabel($type),
                    'menu' => $menu,
                    'message' => $this->renderMessage($type, $user->name, $menu, $id),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $created++;
            }
        }

        $this->command->info("UserLogSeeder selesai: {$created} log berhasil dibuat.");
    }

    protected function randomType(): string
    {
        $types = ['add', 'edit', 'edit', 'delete', 'approve', 'reject'];

        return $types[array_rand($types)];
    }

    protected function actionLabel(string $type): string
    {
        return match ($type) {
            'add' => 'create',
            'edit' => 'update',
            'delete' => 'delete',
            'approve' => 'approve',
            'reject' => 'reject',
            default => 'login',
        };
    }

    protected function renderMessage(string $type, string $name, ?string $menu, $id): string
    {
        $templates = $this->templates[$type] ?? $this->templates['login'];
        $template = $templates[array_rand($templates)];

        return str_replace(
            ['{name}', '{menu}', '{id}'],
            [$name, $menu ?? '-', $id],
            $template
        );
    }
}
