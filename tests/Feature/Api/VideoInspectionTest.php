<?php

namespace Tests\Feature\Api;

use App\Models\Rental;
use App\Models\RentalInspection;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inspeksi video (praktik lapangan): rekam seluruh sisi + interior saat
 * pengambilan & pengembalian.
 * - Multipart `videos[]` di handover-out & return.
 * - Tersimpan di disk `public` folder `inspection-videos/`.
 * - Path tercatat di `rental_inspections.videos` (JSON) + total durasi.
 * - Idempotent bersama `client_uuid` inspeksi.
 */
class VideoInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        config(['field.inspection_video_max_files' => 3]);
        config(['field.inspection_video_max_kb' => 51200]);
    }

    protected function login(): string
    {
        $operator = User::where('username', 'staff')->firstOrFail();
        $res = $this->postJson('/api/auth/login', [
            'username' => $operator->username,
            'password' => 'staff',
        ]);

        return 'Bearer '.$res->json('data.access_token');
    }

    /** File video palsu (mimetype video) untuk multipart. */
    protected function fakeVideo(string $name = 'inspeksi.mp4', int $kb = 64): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'video/mp4');
    }

    public function test_handover_out_stores_inspection_videos(): void
    {
        // Pilih reservasi yang BELUM punya inspeksi awal (jalur tulis baru).
        $rental = Rental::where('status', 'reserved')
            ->whereNotNull('vehicle_id')
            ->whereDoesntHave('handoverOut')
            ->firstOrFail();

        $res = $this->postJson("/api/v1/field/rentals/{$rental->rental_id}/handover-out", [
            'odometer' => 45000,
            'fuel_level' => 'full',
            'video_total_seconds' => 95,
            'videos' => [$this->fakeVideo(), $this->fakeVideo('interior.mp4')],
        ], ['Authorization' => $this->login()]);

        $res->assertOk()->assertJsonPath('status', true);

        $inspection = RentalInspection::where('rental_id', $rental->rental_id)
            ->where('inspection_type', 'handover_out')->firstOrFail();

        $this->assertCount(2, $inspection->videos);
        $this->assertSame(95, (int) $inspection->video_total_seconds);

        foreach ($inspection->videos as $path) {
            $this->assertStringStartsWith('inspection-videos/', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_return_stores_inspection_videos(): void
    {
        $rental = Rental::where('status', 'ongoing')->whereNotNull('vehicle_id')->firstOrFail();

        $odo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle?->mileage ?? 0);

        $res = $this->postJson("/api/v1/field/rentals/{$rental->rental_id}/return", [
            'odometer_end' => $odo + 100,
            'fuel_level_end' => 'half',
            'video_total_seconds' => 120,
            'videos' => [$this->fakeVideo('kembali.mp4')],
        ], ['Authorization' => $this->login()]);

        $res->assertOk();

        $inspection = RentalInspection::where('rental_id', $rental->rental_id)
            ->where('inspection_type', 'handover_in')->firstOrFail();

        $this->assertCount(1, $inspection->videos);
        Storage::disk('public')->assertExists($inspection->videos[0]);
        $this->assertSame(120, (int) $inspection->video_total_seconds);
    }

    public function test_video_validation_rejects_more_than_limit(): void
    {
        $rental = Rental::where('status', 'reserved')
            ->whereNotNull('vehicle_id')
            ->whereDoesntHave('handoverOut')
            ->firstOrFail();

        $res = $this->postJson("/api/v1/field/rentals/{$rental->rental_id}/handover-out", [
            'odometer' => 45000,
            'fuel_level' => 'full',
            'videos' => [
                $this->fakeVideo('a.mp4'),
                $this->fakeVideo('b.mp4'),
                $this->fakeVideo('c.mp4'),
                $this->fakeVideo('d.mp4'), // melebihi max 3
            ],
        ], ['Authorization' => $this->login()]);

        $res->assertStatus(422)->assertJsonValidationErrors(['videos']);
    }

    public function test_video_validation_rejects_non_video_file(): void
    {
        $rental = Rental::where('status', 'reserved')
            ->whereNotNull('vehicle_id')
            ->whereDoesntHave('handoverOut')
            ->firstOrFail();

        $res = $this->postJson("/api/v1/field/rentals/{$rental->rental_id}/handover-out", [
            'odometer' => 45000,
            'fuel_level' => 'full',
            'videos' => [UploadedFile::fake()->create('bukti.pdf', 64, 'application/pdf')],
        ], ['Authorization' => $this->login()]);

        $res->assertStatus(422)->assertJsonValidationErrors(['videos.0']);
    }
}
