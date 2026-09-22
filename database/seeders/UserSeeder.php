<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Audit keamanan (password seeder): mode demo HANYA bila APP_SEED_DEMO_PASSWORDS=true.
     * Produksi (default) memaksa password acak unik per akun, dicetak SEKALI ke terminal
     * saat seeding agar bisa diserahkan ke pemilik akun — bukan tersimpan di repo.
     */
    protected bool $demoPasswords;

    protected array $generatedPasswords = [];

    public function __construct()
    {
        $this->demoPasswords = (bool) env('APP_SEED_DEMO_PASSWORDS', false);
    }

    /**
     * Data pengguna utama (akun inti yang selalu tersedia).
     *
     * Format: [username, name, email, password, role, nowa]
     */
    protected array $mainUsers = [

        // ===========================================
        // SUPERADMIN — akses penuh, tidak bisa dihapus
        // ===========================================
        ['superadmin', 'Super Administrator', 'superadmin@example.com', 'superadmin', 'SUPERADMIN', '081234567890'],

        // ===========================================
        // ADMINISTRATOR
        // ===========================================
        ['admin', 'Administrator', 'admin@example.com', 'admin', 'ADMIN', '081234567891'],

        // ===========================================
        // MANAGER
        // ===========================================
        ['manager', 'Manager Operasional', 'manager@example.com', 'manager', 'MANAGER', '081234567892'],
        ['manager2', 'Manager Keuangan', 'manager.keuangan@example.com', 'password', 'MANAGER', '081234567893'],

        // ===========================================
        // SUPERVISOR
        // ===========================================
        ['supervisor', 'Supervisor Gudang', 'supervisor@example.com', 'password', 'SUPERVISOR', '085711111111'],
        ['supervisor2', 'Supervisor Lapangan', 'supervisor.lapangan@example.com', 'password', 'SUPERVISOR', '085722222222'],

        // ===========================================
        // STAFF
        // ===========================================
        ['staff', 'Staff Operasional', 'staff@example.com', 'staff', 'STAFF', '081234567894'],
        ['staff2', 'Budi Santoso', 'budi@example.com', 'password', 'STAFF', '081234567895'],
        ['staff3', 'Siti Rahmawati', 'siti@example.com', 'password', 'STAFF', '081234567896'],
        ['staff4', 'Ahmad Hidayat', 'ahmad@example.com', 'password', 'STAFF', '081234567897'],
        ['staff5', 'Dewi Lestari', 'dewi@example.com', 'password', 'STAFF', '081234567898'],
        ['staff6', 'Rudi Hartono', 'rudi@example.com', 'password', 'STAFF', '081234567899'],
        ['staff7', 'Rina Marlina', 'rina@example.com', 'password', 'STAFF', '085712345678'],
        ['staff8', 'Eko Prasetyo', 'eko@example.com', 'password', 'STAFF', '085712345679'],
        ['staff9', 'Maya Sari', 'maya@example.com', 'password', 'STAFF', '085712345680'],

        // ===========================================
        // VIEWER (hanya lihat dashboard & report)
        // ===========================================
        ['viewer', 'Viewer Eksternal', 'viewer@example.com', 'password', 'VIEWER', '081298765432'],
        ['viewer2', 'Auditor', 'auditor@example.com', 'password', 'VIEWER', '081298765433'],
    ];

    /**
     * Data pengguna yang di-soft-delete (nonaktif / pernah keluar).
     */
    protected array $deletedUsers = [
        ['karyawan.keluar', 'Karyawan Keluar', 'keluar@example.com', 'password', 'STAFF', '081111111111'],
        ['operator.lama', 'Operator Lama', 'operator.lama@example.com', 'password', 'OPERATOR', '081111111112'],
        ['magang', 'Peserta Magang', 'magang@example.com', 'password', 'VIEWER', '081111111113'],
    ];

    /**
     * Data pengguna tambahan yang di-generate secara deterministik.
     */
    protected function generateAdditionalUsers(int $count): array
    {
        $roles = ['STAFF', 'STAFF', 'STAFF', 'STAFF', 'VIEWER', 'VIEWER'];
        $names = [
            ['agus.wijaya', 'Agus Wijaya'],
            ['sari.dewi', 'Sari Dewi'],
            ['bambang.suprapto', 'Bambang Suprapto'],
            ['rini.kusuma', 'Rini Kusuma'],
            ['doni.prasetyo', 'Doni Prasetyo'],
            ['tina.nurhayati', 'Tina Nurhayati'],
            ['adi.subagio', 'Adi Subagio'],
            ['fitri.handayani', 'Fitri Handayani'],
            ['heru.purnomo', 'Heru Purnomo'],
            ['nina.agustina', 'Nina Agustina'],
            ['yanto.supriyadi', 'Yanto Supriyadi'],
            ['lina.maryati', 'Lina Maryati'],
            ['edi.kurniawan', 'Edi Kurniawan'],
            ['wati.susanti', 'Wati Susanti'],
            ['agus.suharto', 'Agus Suharto'],
            ['slamet.riyadi', 'Slamet Riyadi'],
            ['indah.permatasari', 'Indah Permatasari'],
            ['sutrisno', 'Sutrisno'],
            ['yuni.astuti', 'Yuni Astuti'],
            ['sigit.budiono', 'Sigit Budiono'],
        ];

        $users = [];
        for ($i = 0; $i < min($count, count($names)); $i++) {
            $users[] = [
                $names[$i][0],
                $names[$i][1],
                $names[$i][0].'@baskadrive.com',
                'password',
                $roles[$i % count($roles)],
                '08'.str_pad((string) (8000000000 + $i), 10, '0', STR_PAD_LEFT),
            ];
        }

        return $users;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $created = 0;
        $updated = 0;

        $allUsers = array_merge(
            $this->mainUsers,
            $this->generateAdditionalUsers(20),
        );

        // --- Buat / update semua user aktif ---
        foreach ($allUsers as $row) {
            [$username, $name, $email, $password, $roleName, $nowa] = $row;

            $user = User::withTrashed()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => $now,
                    'password' => $this->resolvePassword($username, $password), // cast 'hashed' meng-hash otomatis
                    'nowa' => $nowa,
                    'deleted_at' => null,
                    'token_2fa' => null,
                    'token_last_request' => null,
                ]
            );

            // Assign role (jika role sudah ada di database)
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $user->syncRoles([$role->id]);
            }

            $user->wasRecentlyCreated ? $created++ : $updated++;
        }

        // --- Buat user yang di-soft-delete ---
        foreach ($this->deletedUsers as $row) {
            [$username, $name, $email, $password, $roleName, $nowa] = $row;

            $user = User::withTrashed()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => null,
                    'password' => $this->resolvePassword($username, $password),
                    'nowa' => $nowa,
                    'deleted_at' => $now->subDays(random_int(30, 180)),
                ]
            );

            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $user->syncRoles([$role->id]);
            }

            $user->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->command->info('UserSeeder selesai. Total: '.($created + $updated)." user (baru: {$created}, update: {$updated}).");

        if ($this->demoPasswords) {
            $this->command->warn('MODE DEMO: password seeder sederhana aktif (APP_SEED_DEMO_PASSWORDS=true). JANGAN pakai di produksi.');
        } else {
            $this->command->warn('=== PASSWORD AKUN (cetak sekali — simpan aman, tidak tersimpan di repo) ===');
            foreach ($this->generatedPasswords as $username => $plain) {
                $this->command->line(sprintf('  %-20s %s', $username, $plain));
            }
            $this->command->warn('=== AKHIR DAFTAR PASSWORD ===');
        }
    }

    /**
     * Password aktual untuk seeding: nilai repo pada mode demo, acak di luar itu.
     * Password acak dibuat SEKALI per username dan diingat agar update-or-create
     * idempoten tidak mengganti password user yang sudah ada di environment lama.
     */
    protected function resolvePassword(string $username, string $demoValue): string
    {
        if ($this->demoPasswords) {
            return $demoValue;
        }

        if (! isset($this->generatedPasswords[$username])) {
            $this->generatedPasswords[$username] = 'Bd-'.bin2hex(random_bytes(9));
        }

        return $this->generatedPasswords[$username];
    }
}
