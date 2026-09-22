<?php

namespace Database\Seeders;

use App\Models\AppSetting as AppSettingModel;
use App\Support\AppSettings;
use Arr;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 *  RENTAL ERP — COMPREHENSIVE SEEDER
 * ============================================================================
 *  Seed SELURUH tabel dengan data operasional 12 bulan yang sangat realistis.
 *
 *  Volume data:
 *  - 10 brand, 34 model, 46 unit kendaraan
 *  - 48 pelanggan (36 individu + 12 korporat)
 *  - 16 sopir, 14 karyawan, 8 lokasi, 10 bengkel, 6 tipe maintenance, 10 promo
 *  - COA lengkap standar akuntansi Indonesia
 *  - ~150 sewa dalam 12 bulan (completed/ongoing/reserved/cancelled/terlambat)
 *    dengan detail add-on, perpanjangan, pengembalian, kerusakan + foto,
 *    klaim asuransi, maintenance, denda
 *  - Invoice, pembayaran (multi-metode), refund — konsisten dgn status sewa
 *  - Jurnal umum balanced (double-entry) utk setiap transaksi keuangan
 *  - Log aktivitas & setting aplikasi
 * ============================================================================
 */
class RentalErpCompleteSeeder extends Seeder
{
    protected array $map = [];       // peta id: brands, models, vehicles, dst.

    protected Carbon $now;

    protected array $maleFirst = ['Budi', 'Ahmad', 'Agus', 'Bambang', 'Joko', 'Hendra', 'Rizky', 'Bayu', 'Doni', 'Fajar', 'Yoga', 'Eko', 'Andi', 'Rudi', 'Gilang', 'Dimas', 'Arif', 'Taufik', 'Wahyu', 'Adi', 'Dwi', 'Rendra', 'Ferry', 'Ilham', 'Reza', 'Galih', 'Angga', 'Bagus', 'Dedi', 'Hari'];

    protected array $femaleFirst = ['Siti', 'Dewi', 'Citra', 'Rina', 'Fitri', 'Wulan', 'Maya', 'Indah', 'Sari', 'Lestari', 'Putri', 'Ratna', 'Ayu', 'Dian', 'Novi', 'Yuni', 'Rina', 'Endang', 'Tuti', 'Nurul', 'Anisa', 'Melati', 'Kartika', 'Sri'];

    protected array $lastNames = ['Santoso', 'Wijaya', 'Pratama', 'Maharani', 'Kusuma', 'Saputra', 'Utami', 'Hidayat', 'Nugraha', 'Setiawan', 'Wibowo', 'Handayani', 'Rahayu', 'Firmansyah', 'Purnama', 'Siregar', 'Simanjuntak', 'Halim', 'Permana', 'Susanto', 'Nurhayati', 'Maulana', 'Anggraini', 'Kurniawan'];

    public function run(): void
    {
        $this->now = Carbon::now();
        $this->command->info('=== RentalErpCompleteSeeder: mulai seeding 12 bulan data operasional ===');

        // Bersihkan data transaksi & master modul rental (seeder lama dijalankan
        // sebelum seeder ini; kita ganti dengan dataset yang lebih lengkap).
        // FK aman karena urutan child -> parent.
        $this->command->info('... membersihkan data rental ERP lama');
        $isMysql = DB::getDriverName() === 'mysql';
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
        foreach ([
            'tr_journal_detail', 'tr_journal', 'tr_refund', 'tr_payment', 'tr_invoice', 'tr_fine',
            'tr_maintenance', 'tr_insurance_claim', 'tr_damage_photo', 'tr_damage_report', 'tr_return',
            'tr_rental_extension', 'tr_rental_detail', 'tr_rental', 'rental_inspections', 'vehicle_location_histories',
            'm_coa', 'm_promo', 'm_maintenance_type', 'm_workshop', 'm_location', 'm_driver',
            'm_customer', 'm_vehicle', 'm_vehicle_model', 'm_brand', 'm_employee',
        ] as $table) {
            DB::table($table)->truncate();
        }
        if ($isMysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->seedAppSettings();
        $this->seedBrandsAndModels();
        $this->seedLocations();
        $this->seedWorkshops();
        $this->seedMaintenanceTypes();
        $this->seedCustomers();
        $this->seedDrivers();
        $this->seedVehicles();
        $this->seedPromos();
        $this->seedCoa();
        $this->seedEmployees();
        $this->seedRentals();        // rental + detail + extension
        $this->syncVehicleStatuses(); // SED-07: status unit mengikuti sewa aktif
        $this->seedVehicleHistories();
        $this->seedInspections();
        $this->seedReturns();        // return + mileage progression
        $this->seedDamagesAndClaims();
        $this->seedMaintenances();
        $this->seedFines();
        $this->seedInvoicesPaymentsRefunds();
        $this->seedUserLogs();

        $this->command->info('=== Selesai! Semua tabel terisi data realistis ===');
    }

    /* =========================================================
       MASTER DATA
    ========================================================= */

    protected function seedAppSettings(): void
    {
        AppSettings::setMany(AppSettings::defaults());
        AppSettingModel::query()->update(['created_at' => $this->now->copy()->subDays(365)]);
        $this->command->info('✓ app_settings (default aplikasi)');
    }

    protected function seedBrandsAndModels(): void
    {
        $brands = ['Toyota', 'Honda', 'Suzuki', 'Mitsubishi', 'Daihatsu', 'Nissan', 'Hyundai', 'Kia', 'Mazda', 'Isuzu'];
        foreach ($brands as $b) {
            DB::table('m_brand')->insert(['brand_name' => $b, 'created_at' => $this->now->copy()->subDays(370)]);
        }
        $brandIds = DB::table('m_brand')->pluck('brand_id', 'brand_name');

        // [brand, model, kategori, bahan bakar, transmisi, kursi, harga/hari, rate asuransi %, deposit]
        $models = [
            ['Toyota', 'Avanza 1.5 G', 'MPV', 'Petrol', 'Manual', 7, 350000, 5.0, 500000],
            ['Toyota', 'Avanza 1.5 CVT', 'MPV', 'Petrol', 'Automatic', 7, 385000, 5.0, 500000],
            ['Toyota', 'Innova Zenix', 'MPV', 'Petrol', 'Automatic', 7, 675000, 5.5, 900000],
            ['Toyota', 'Fortuner VRZ', 'SUV', 'Diesel', 'Automatic', 7, 1250000, 7.0, 2000000],
            ['Toyota', 'Rush GR Sport', 'SUV', 'Petrol', 'Automatic', 7, 475000, 5.5, 700000],
            ['Toyota', 'HiAce Premio', 'Van', 'Diesel', 'Manual', 14, 950000, 6.0, 1500000],
            ['Toyota', 'Alphard Hybrid', 'Van', 'Hybrid', 'Automatic', 7, 2500000, 8.0, 5000000],
            ['Toyota', 'Calya 1.2 G', 'MPV', 'Petrol', 'Manual', 7, 275000, 4.5, 400000],
            ['Honda', 'Brio Satya E', 'Hatchback', 'Petrol', 'Manual', 5, 275000, 4.0, 350000],
            ['Honda', 'Brio RS CVT', 'Hatchback', 'Petrol', 'Automatic', 5, 325000, 4.5, 400000],
            ['Honda', 'Jazz RS', 'Hatchback', 'Petrol', 'Automatic', 5, 425000, 5.0, 600000],
            ['Honda', 'BR-V Prestige', 'SUV', 'Petrol', 'Automatic', 7, 465000, 5.5, 650000],
            ['Honda', 'CR-V Prestige', 'SUV', 'Petrol', 'Automatic', 5, 775000, 6.5, 1200000],
            ['Honda', 'Mobilio E', 'MPV', 'Petrol', 'Manual', 7, 360000, 4.5, 500000],
            ['Honda', 'Civic RS', 'Sedan', 'Petrol', 'Automatic', 5, 875000, 7.0, 1500000],
            ['Suzuki', 'Ertiga Hybrid', 'MPV', 'Petrol', 'Manual', 7, 375000, 4.5, 500000],
            ['Suzuki', 'APV Luxury', 'Van', 'Petrol', 'Manual', 11, 550000, 5.0, 800000],
            ['Suzuki', 'XL7 Alpha', 'SUV', 'Petrol', 'Automatic', 7, 425000, 5.0, 600000],
            ['Suzuki', 'Baleno Hatchback', 'Hatchback', 'Petrol', 'Automatic', 5, 435000, 5.0, 600000],
            ['Mitsubishi', 'Xpander Ultimate', 'MPV', 'Petrol', 'Automatic', 7, 450000, 5.0, 650000],
            ['Mitsubishi', 'Xpander Cross', 'SUV', 'Petrol', 'Automatic', 7, 495000, 5.5, 700000],
            ['Mitsubishi', 'Pajero Sport Dakar', 'SUV', 'Diesel', 'Automatic', 7, 1350000, 7.5, 2500000],
            ['Mitsubishi', 'Outlander PHEV', 'SUV', 'Hybrid', 'Automatic', 5, 1150000, 7.0, 1800000],
            ['Daihatsu', 'Xenia 1.3 X', 'MPV', 'Petrol', 'Manual', 7, 325000, 4.5, 450000],
            ['Daihatsu', 'Terios X', 'SUV', 'Petrol', 'Manual', 7, 460000, 5.0, 650000],
            ['Daihatsu', 'Sigra R', 'MPV', 'Petrol', 'Manual', 7, 315000, 4.0, 400000],
            ['Nissan', 'Livina VL', 'MPV', 'Petrol', 'Automatic', 7, 425000, 5.0, 600000],
            ['Nissan', 'X-Trail e-Power', 'SUV', 'Hybrid', 'Automatic', 5, 895000, 6.5, 1400000],
            ['Hyundai', 'Stargazer Prime', 'MPV', 'Petrol', 'Automatic', 7, 445000, 5.0, 650000],
            ['Hyundai', 'Tucson Signature', 'SUV', 'Petrol', 'Automatic', 5, 925000, 6.5, 1500000],
            ['Kia', 'Seltos EX', 'SUV', 'Petrol', 'Automatic', 5, 685000, 6.0, 1000000],
            ['Kia', 'Carnival 11 Seat', 'Van', 'Petrol', 'Automatic', 11, 1450000, 7.0, 2500000],
            ['Mazda', 'CX-5 Touring', 'SUV', 'Petrol', 'Automatic', 5, 825000, 6.0, 1200000],
            ['Isuzu', 'TRAGA Pickup', 'Pickup', 'Diesel', 'Manual', 2, 400000, 4.5, 600000],
        ];

        $rows = [];
        foreach ($models as $i => $m) {
            $rows[] = [
                'brand_id' => $brandIds[$m[0]],
                'model_name' => $m[1],
                'category' => $m[2],
                'fuel_type' => $m[3],
                'transmission' => $m[4],
                'seat_capacity' => $m[5],
                'base_price_per_day' => $m[6],
                'base_price_per_km' => 1200,
                'insurance_rate' => $m[7],
                'deposit_amount' => $m[8],
                'is_active' => true,
                'created_at' => $this->now->copy()->subDays(370),
            ];
            $this->map['models'][$m[1]] = ['id' => $i + 1, 'price' => $m[6], 'rate' => $m[7], 'deposit' => $m[8]];
        }
        DB::table('m_vehicle_model')->insert($rows);
        $this->command->info('✓ m_brand (10) + m_vehicle_model (34)');
    }

    protected function seedVehicles(): void
    {
        // [model, warna, tahun, kota-plate, jumlah unit]
        $plan = [
            ['Avanza 1.5 G', 'Putih', 2022, 'B', 4],
            ['Avanza 1.5 CVT', 'Silver', 2023, 'B', 2],
            ['Innova Zenix', 'Hitam', 2023, 'B', 2],
            ['Fortuner VRZ', 'Abu-abu', 2023, 'B', 1],
            ['Rush GR Sport', 'Hitam', 2022, 'B', 2],
            ['HiAce Premio', 'Putih', 2022, 'B', 1],
            ['Alphard Hybrid', 'Hitam', 2024, 'B', 1],
            ['Calya 1.2 G', 'Kuning', 2021, 'B', 2],
            ['Brio Satya E', 'Merah', 2022, 'B', 3],
            ['Brio RS CVT', 'Oranye', 2023, 'B', 1],
            ['Jazz RS', 'Biru', 2023, 'B', 1],
            ['BR-V Prestige', 'Hitam', 2023, 'B', 1],
            ['CR-V Prestige', 'Silver', 2022, 'B', 1],
            ['Mobilio E', 'Abu-abu', 2021, 'B', 1],
            ['Civic RS', 'Biru', 2023, 'B', 1],
            ['Ertiga Hybrid', 'Putih', 2022, 'D', 3],
            ['APV Luxury', 'Silver', 2021, 'D', 1],
            ['XL7 Alpha', 'Hitam', 2022, 'D', 1],
            ['Baleno Hatchback', 'Merah', 2022, 'D', 1],
            ['Xpander Ultimate', 'Hitam', 2023, 'L', 2],
            ['Xpander Cross', 'Abu-abu', 2023, 'L', 1],
            ['Pajero Sport Dakar', 'Putih', 2023, 'L', 1],
            ['Outlander PHEV', 'Biru', 2024, 'L', 1],
            ['Xenia 1.3 X', 'Silver', 2022, 'AB', 2],
            ['Terios X', 'Hitam', 2022, 'AB', 1],
            ['Sigra R', 'Putih', 2021, 'AB', 1],
            ['Livina VL', 'Putih', 2022, 'H', 1],
            ['X-Trail e-Power', 'Hitam', 2024, 'H', 1],
            ['Stargazer Prime', 'Biru', 2023, 'AB', 1],
            ['Tucson Signature', 'Abu-abu', 2023, 'B', 1],
            ['Seltos EX', 'Merah', 2022, 'B', 1],
            ['Carnival 11 Seat', 'Hitam', 2023, 'B', 1],
            ['CX-5 Touring', 'Merah', 2023, 'D', 1],
            ['TRAGA Pickup', 'Putih', 2021, 'B', 1],
            ['Avanza 1.5 G', 'Abu-abu', 2021, 'DK', 2],
            ['Xenia 1.3 X', 'Putih', 2023, 'DK', 2],
            ['Brio Satya E', 'Kuning', 2023, 'DK', 2],
            ['Innova Zenix', 'Silver', 2024, 'DK', 1],
        ];

        $cities = [
            'B' => ['DKI Jakarta', 'Jl. Sudirman Kav. 21'],
            'D' => ['Jawa Barat', 'Jl. Asia Afrika No. 8'],
            'L' => ['Jawa Timur', 'Jl. Pemuda No. 27'],
            'AB' => ['DI Yogyakarta', 'Jl. Malioboro No. 52'],
            'H' => ['Jawa Tengah', 'Jl. Pandanaran No. 18'],
            'DK' => ['Bali', 'Jl. Sunset Road No. 88'],
        ];

        $letters = ['AA', 'AB', 'AC', 'AD', 'BA', 'BB', 'BC', 'BD', 'CA', 'CB', 'DA', 'DB', 'EA', 'EB', 'FA', 'GA'];
        $rows = [];
        $seq = 0;
        $colorsSet = ['Putih', 'Silver', 'Hitam', 'Abu-abu', 'Merah', 'Biru', 'Kuning', 'Oranye'];

        foreach ($plan as [$model, $color, $year, $city, $qty]) {
            for ($u = 1; $u <= $qty; $u++) {
                $seq++;
                $plate = $city.' '.(1000 + $seq * 37) % 9000 .' '.$letters[$seq % count($letters)];
                $this->map['vehicles'][] = [
                    'id' => $seq,
                    'plate' => $plate,
                    'model' => $model,
                    'city' => $city,
                    'km' => random_int(8000, 48000),
                    'year' => $year,
                    'status' => 'available',
                ];
                $rows[] = [
                    'license_plate' => $plate,
                    'vin' => 'MH'.strtoupper(Str::random(2)).random_int(100000000, 999999999),
                    'model_id' => $this->map['models'][$model]['id'],
                    'location_id' => random_int(1, 8),
                    'color' => $color === '-' ? $colorsSet[$seq % count($colorsSet)] : $color,
                    'year' => $year,
                    'mileage' => $this->map['vehicles'][$seq - 1]['km'],
                    'status' => 'available',
                    'purchase_date' => Carbon::create($year, random_int(1, 12), random_int(1, 28))->toDateString(),
                    'purchase_price' => $this->map['models'][$model]['price'] * 480,
                    'current_value' => $this->map['models'][$model]['price'] * 380,
                    'engine_number' => 'EN'.random_int(1000000, 9999999),
                    'photo_url' => null,
                    'notes' => null,
                    'created_at' => Carbon::create($year, random_int(1, 6), 1)->toDateString(),
                    'updated_at' => $this->now,
                ];
            }
        }
        DB::table('m_vehicle')->insert($rows);
        $this->command->info('✓ m_vehicle ('.count($rows).' unit)');
    }

    protected function seedCustomers(): void
    {
        $cities = [
            ['Jakarta Selatan', 'DKI Jakarta', '12190', '3171'],
            ['Jakarta Barat', 'DKI Jakarta', '11220', '3173'],
            ['Bandung', 'Jawa Barat', '40115', '3273'],
            ['Surabaya', 'Jawa Timur', '60271', '3578'],
            ['Yogyakarta', 'DI Yogyakarta', '55223', '3471'],
            ['Semarang', 'Jawa Tengah', '50132', '3374'],
            ['Denpasar', 'Bali', '80361', '5171'],
        ];
        $streets = ['Jl. Kemang Raya No. %d', 'Jl. Tebet Raya No. %d', 'Jl. Cikini Raya No. %d', 'Jl. Gatot Subroto Kav. %d', 'Jl. Dago No. %d', 'Jl. Dipatiukur No. %d', 'Jl. Raya Darmo No. %d', 'Jl. Malioboro No. %d', 'Jl. Kaliurang KM %d', 'Jl. Pandanaran No. %d', 'Jl. Sunset Road No. %d', 'Jl. Teuku Umar No. %d'];

        $rows = [];
        $seq = 0;

        // 12 korporat
        $companies = [
            ['PT Maju Jaya Transport', 'Andi Kurniawan'],
            ['PT Sinar Abadi Logistik', 'Bambang Sutrisno'],
            ['CV Berkah Trans Nusantara', 'Candra Wijaya'],
            ['PT Karya Wisata Mandiri', 'Dian Prasasti'],
            ['PT Duta Ekspedisi Prima', 'Eko Purnomo'],
            ['CV Samudra Perkasa', 'Ferry Gunawan'],
            ['PT Global Tour & Travel', 'Gita Hapsari'],
            ['PT Aneka Usaha Mandiri', 'Hariyanto'],
            ['PT Bumi Rancang Baru', 'Indra Kusnadi'],
            ['CV Mitra Sejati', 'Joko Susanto'],
            ['PT Sentosa Holiday', 'Kartika Sari'],
            ['PT Nusantara Tour', 'Lukman Hakim'],
        ];
        foreach ($companies as $i => [$co, $pic]) {
            $seq++;
            [$city, $prov, $zip, $nikPrefix] = $cities[$i % count($cities)];
            $parts = explode(' ', $pic, 2);
            $rows[] = $this->customerRow($seq, 'corporate', $parts[0], $parts[1] ?? '-', $co, $city, $prov, $zip, $nikPrefix, $streets);
        }

        // 36 individu
        $usedNames = [];
        while ($seq < 48) {
            $first = $this->pick($this->femaleFirst);
            $last = $this->pick($this->lastNames);
            if (isset($usedNames[$first.$last])) {
                continue;
            }
            $usedNames[$first.$last] = true;
            $seq++;
            [$city, $prov, $zip, $nikPrefix] = $cities[$seq % count($cities)];
            $rows[] = $this->customerRow($seq, 'individual', $first, $last, null, $city, $prov, $zip, $nikPrefix, $streets);
        }

        DB::table('m_customer')->insert($rows);
        foreach ($rows as $r) {
            $this->map['customers'][] = $r['customer_id'];
        }
        $this->command->info('✓ m_customer (48: 12 korporat + 36 individu)');
    }

    protected function customerRow(int $seq, string $type, string $first, string $last, ?string $company, string $city, string $prov, string $zip, string $nikPrefix, array $streets): array
    {
        $slug = strtolower(str_replace(' ', '.', $company ?: ($first.' '.$last)));
        $phone = $type === 'corporate' ? '021'.random_int(50000000, 79999999) : '08'.random_int(1111111111, 8999999999);
        $dob = Carbon::create(random_int(1975, 2004), random_int(1, 12), random_int(1, 28));
        $blacklisted = $this->chance(7);

        return [
            'customer_id' => $seq,
            'customer_type' => $type,
            'first_name' => $first,
            'last_name' => $last,
            'company_name' => $company,
            'email' => str_replace('..', '.', $slug).'@'.$this->pick(['gmail.com', 'yahoo.co.id', 'outlook.com', 'mail.com']),
            'phone' => $phone,
            'address' => sprintf($this->pick($streets), random_int(1, 120)),
            'city' => $city,
            'province' => $prov,
            'postal_code' => $zip,
            'country' => 'Indonesia',
            'driver_license_number' => 'SIM-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'driver_license_expiry' => $this->now->copy()->addYears(random_int(1, 4))->toDateString(),
            'driver_license_photo' => null,
            'id_card_number' => $type === 'individual' ? $nikPrefix.$dob->format('dmy').str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT) : null,
            'id_card_photo' => null,
            'date_of_birth' => $dob->toDateString(),
            'is_verified' => $this->chance(85),
            'is_blacklisted' => $blacklisted,
            'blacklist_reason' => $blacklisted ? $this->pick(['Sering telat pengembalian', 'Merusak kendaraan', 'Tidak membayar denda', 'Dokumen palsu']) : null,
            'notes' => null,
            'created_at' => $this->now->copy()->subDays(random_int(30, 400)),
            'updated_at' => $this->now,
        ];
    }

    protected function seedDrivers(): void
    {
        $rows = [];
        for ($i = 1; $i <= 16; $i++) {
            $first = $i % 3 === 0 ? $this->pick($this->femaleFirst) : $this->pick($this->maleFirst);
            $last = $this->pick($this->lastNames);
            $rows[] = [
                'driver_id' => $i,
                'first_name' => $first,
                'last_name' => $last,
                'license_number' => 'SIM-A-'.random_int(10000000, 99999999),
                'license_expiry' => $this->now->copy()->addYears(random_int(1, 4))->toDateString(),
                'phone' => '0857'.random_int(10000000, 99999999),
                'is_active' => $this->chance(90),
                'notes' => null,
                'created_at' => $this->now->copy()->subDays(random_int(200, 500)),
            ];
            $this->map['drivers'][] = $i;
        }
        DB::table('m_driver')->insert($rows);
        $this->command->info('✓ m_driver (16)');
    }

    protected function seedLocations(): void
    {
        $locations = [
            ['Kantor Pusat Jakarta', 'Jl. Sudirman Kav. 21', 'Jakarta Pusat', 'DKI Jakarta', '021-5711234', '08:00-17:00', -6.2088, 106.8456],
            ['Cabang Jakarta Selatan', 'Jl. TB Simatupang No. 15', 'Jakarta Selatan', 'DKI Jakarta', '021-7591234', '08:00-20:00', -6.2615, 106.8106],
            ['Cabang Jakarta Barat', 'Jl. Daan Mogot KM 8', 'Jakarta Barat', 'DKI Jakarta', '021-5551234', '08:00-17:00', -6.1683, 106.7584],
            ['Cabang Bandung', 'Jl. Asia Afrika No. 8', 'Bandung', 'Jawa Barat', '022-4211234', '08:00-17:00', -6.9147, 107.6098],
            ['Cabang Surabaya', 'Jl. Pemuda No. 27', 'Surabaya', 'Jawa Timur', '031-5311234', '08:00-17:00', -7.2575, 112.7521],
            ['Cabang Semarang', 'Jl. Pandanaran No. 18', 'Semarang', 'Jawa Tengah', '024-3511234', '08:30-17:30', -6.9932, 110.4203],
            ['Cabang Yogyakarta', 'Jl. Malioboro No. 52', 'Yogyakarta', 'DI Yogyakarta', '0274-551234', '08:00-17:00', -7.7956, 110.3695],
            ['Cabang Denpasar', 'Jl. Sunset Road No. 88', 'Denpasar', 'Bali', '0361-891234', '08:00-17:00', -8.6705, 115.2126],
        ];
        $rows = [];
        foreach ($locations as $i => $l) {
            $rows[] = [
                'location_id' => $i + 1,
                'location_name' => $l[0],
                'address' => $l[1],
                'city' => $l[2],
                'province' => $l[3],
                'contact_phone' => $l[4],
                'opening_hours' => $l[5],
                'latitude' => $l[6],
                'longitude' => $l[7],
                'is_active' => true,
                'created_at' => $this->now->copy()->subDays(400),
            ];
            $this->map['locations'][] = $i + 1;
        }
        DB::table('m_location')->insert($rows);
        $this->command->info('✓ m_location (8) + GPS & jam operasional');
    }

    protected function seedWorkshops(): void
    {
        $workshops = [
            ['Auto2000 Jakarta Pusat', 'Jl. Gunung Sahari No. 45', '021-6412345', 'Rudi Hartanto', 4.8, 'Mesin & Tune Up'],
            ['Daihatsu Service Bandung', 'Jl. Soekarno Hatta No. 210', '022-7322345', 'Yusuf Maulana', 4.5, 'Body Repair'],
            ['Bengkel Jaya Mobil', 'Jl. Raya Bogor KM 22', '021-8712345', 'Slamet Widodo', 4.2, 'General Service'],
            ['Pertamina Servis Surabaya', 'Jl. Raya Gubeng No. 44', '031-5022345', 'Agus Wibisono', 4.6, 'Ganti Oli & Filter'],
            ['TirePlus Yogyakarta', 'Jl. Ring Road Utara', '0274-481234', 'Bayu Nugraha', 4.7, 'Ban & Spooring'],
            ['Oto2000 Denpasar', 'Jl. By Pass Ngurah Rai', '0361-701234', 'Wayan Sudira', 4.4, 'AC & Elektrikal'],
            ['Bengkel Prima Motor', 'Jl. Cikarang Barat No. 9', '021-8991234', 'Dedi Supriadi', 4.0, 'Kaki-kaki'],
            ['Servis Bersama Motor', 'Jl. Ahmad Yani No. 77', '024-761234', 'Fajar Ramadhan', 4.3, 'Rem & Suspensi'],
            ['BMW Astra Semarang', 'Jl. MT Haryono No. 300', '024-651234', 'Gunawan Saputra', 4.9, 'Premium & Body Repair'],
            ['Toyota Nasmoco Medan', 'Jl. Gatot Subroto No. 199', '061-451234', 'Halim Tanjung', 4.5, 'General Service'],
        ];
        $rows = [];
        foreach ($workshops as $i => $w) {
            $rows[] = [
                'workshop_id' => $i + 1,
                'name' => $w[0],
                'address' => $w[1],
                'phone' => $w[2],
                'contact_person' => $w[3],
                'rating' => $w[4],
                'specialization' => $w[5],
                'is_active' => $this->chance(90),
                'created_at' => $this->now->copy()->subDays(400),
            ];
        }
        DB::table('m_workshop')->insert($rows);
        $this->map['workshops'] = range(1, 10);
        $this->command->info('✓ m_workshop (10) + rating & spesialisasi');
    }

    protected function seedMaintenanceTypes(): void
    {
        $types = [
            ['Servis Berkala 10.000 KM', 10000, 6, 'Servis rutin mesin & filter'],
            ['Ganti Oli Mesin', 5000, 3, 'Penggantian oli mesin & filter oli'],
            ['Spooring & Balancing', 20000, 12, 'Penyetelan kaki-kaki & balancing ban'],
            ['Ganti Ban 4 Unit', 40000, 24, 'Penggantian seluruh ban'],
            ['Servis AC Kendaraan', 15000, 9, 'Cuci evaporator & isi freon'],
            ['Service Rem', 20000, 12, 'Penggantian kampas & minyak rem'],
        ];
        $rows = [];
        foreach ($types as $i => $t) {
            $rows[] = [
                'type_id' => $i + 1,
                'type_name' => $t[0],
                'interval_km' => $t[1],
                'interval_months' => $t[2],
                'description' => $t[3],
                'is_active' => true,
                'created_at' => $this->now->copy()->subDays(400),
            ];
        }
        DB::table('m_maintenance_type')->insert($rows);
        $this->map['mtypes'] = range(1, 6);
        $this->command->info('✓ m_maintenance_type (6)');
    }

    protected function seedPromos(): void
    {
        $promos = [
            ['WELCOME10', 'Diskon 10% untuk pelanggan baru', 'percentage', 10, 2, 365],
            ['LEBARAN2026', 'Promo mudik lebaran', 'percentage', 15, 4, 90],
            ['KORPORAT15', 'Diskon khusus perusahaan', 'percentage', 15, 5, 365],
            ['WEEKEND20', 'Diskon weekend 20%', 'percentage', 20, 2, 180],
            ['MONTHLY30', 'Sewa bulanan hemat 30%', 'percentage', 30, 30, 365],
            ['HARIBANGGA', 'Diskon nominal 250rb', 'fixed_amount', 250000, 3, 120],
            ['EARLYBIRD50', 'Diskon early bird', 'fixed_amount', 50000, 1, 90],
            ['MERDEKA26', 'Promo kemerdekaan', 'percentage', 17, 3, 60],
            ['BUNDLINGGPS', 'Paket GPS hemat', 'fixed_amount', 100000, 2, 365],
            ['REFERRAL25', 'Referral pelanggan', 'percentage', 25, 3, 365],
        ];
        $rows = [];
        $cats = [['SUV', 'MPV'], ['Hatchback', 'MPV'], ['SUV', 'Van'], ['MPV', 'Sedan']];
        foreach ($promos as $i => $p) {
            $start = $this->now->copy()->subDays(random_int(60, 300));
            $applicable = $this->chance(35) ? json_encode($this->pick($cats)) : null;
            $rows[] = [
                'promo_id' => $i + 1,
                'promo_code' => $p[0],
                'description' => $p[1],
                'discount_type' => $p[2],
                'discount_value' => $p[3],
                'min_rental_days' => $p[4],
                'valid_from' => $start->toDateString(),
                'valid_to' => $start->copy()->addDays($p[5])->toDateString(),
                'max_usage' => random_int(50, 500),
                'usage_count' => random_int(0, 40),
                'applicable_categories' => $applicable,
                'is_active' => $this->chance(85),
                'created_at' => $start->toDateString(),
            ];
            $this->map['promos'][$p[0]] = $i + 1;
        }
        DB::table('m_promo')->insert($rows);
        $this->command->info('✓ m_promo (10) + kategori terbatas');
    }

    protected function seedCoa(): void
    {
        $coa = [
            ['1-1000', 'Kas & Bank', 'asset', null],
            ['1-1100', 'Kas Besar', 'asset', '1-1000'],
            ['1-1200', 'Rekening Bank', 'asset', '1-1000'],
            ['1-2000', 'Piutang', 'asset', null],
            ['1-2100', 'Piutang Sewa', 'asset', '1-2000'],
            ['1-3000', 'Inventaris & Armada', 'asset', null],
            ['1-3100', 'Kendaraan', 'asset', '1-3000'],
            ['1-3200', 'Akumulasi Depresiasi', 'asset', '1-3000'],
            ['1-4000', 'Aset Lainnya', 'asset', null],
            ['2-1000', 'Utang Usaha', 'liability', null],
            ['2-2000', 'Utang Jangka Panjang', 'liability', null],
            ['2-3000', 'Pendapatan Diterima Dimuka', 'liability', null],
            ['2-4000', 'Kewajiban Lainnya', 'liability', null],
            ['3-1000', 'Modal', 'equity', null],
            ['3-2000', 'Laba Ditahan', 'equity', null],
            ['4-1000', 'Pendapatan Sewa', 'income', null],
            ['4-1100', 'Pendapatan Sewa Dasar', 'income', '4-1000'],
            ['4-1200', 'Pendapatan Sewa Tambahan', 'income', '4-1000'],
            ['4-2000', 'Pendapatan Denda', 'income', null],
            ['4-3000', 'Pendapatan Klaim Asuransi', 'income', null],
            ['5-1000', 'Beban Operasional', 'expense', null],
            ['5-1100', 'Beban Gaji', 'expense', '5-1000'],
            ['5-1200', 'Beban BBM & Tol', 'expense', '5-1000'],
            ['5-2000', 'Beban Maintenance & Repair', 'expense', null],
            ['5-2100', 'Beban Servis', 'expense', '5-2000'],
            ['5-2200', 'Beban Suku Cadang', 'expense', '5-2000'],
            ['5-3000', 'Beban Depresiasi', 'expense', null],
            ['5-4000', 'Beban Asuransi', 'expense', null],
        ];
        $ids = [];
        $rows = [];
        foreach ($coa as $i => $c) {
            $ids[$c[0]] = $i + 1;
            $rows[] = [
                'account_id' => $i + 1,
                'account_code' => $c[0],
                'account_name' => $c[1],
                'account_type' => $c[2],
                'parent_id' => $c[3] ? $ids[$c[3]] : null,
                'is_active' => true,
                'created_at' => $this->now->copy()->subDays(400),
            ];
        }
        DB::table('m_coa')->insert($rows);
        $this->map['coa'] = $ids;
        $this->command->info('✓ m_coa ('.count($coa).' akun)');
    }

    protected function seedEmployees(): void
    {
        $employees = [
            ['admin.gudang', 'Adi', 'Subagio', 'Admin Gudang', 'admin.gudang'],
            ['kasir1', 'Maya Sari', 'Anggraini', 'Kasir', 'kasir1'],
            ['kasir2', 'Dedi Kurniawan', 'Setiawan', 'Kasir', 'kasir2'],
            ['kasir3', 'Nina Agustina', 'Wibowo', 'Kasir', 'kasir3'],
            ['akuntan', 'Rina Maryati', 'Halim', 'Akuntan', 'akuntan'],
            ['mekanik1', 'Sutrisno', 'Pamungkas', 'Mekanik Senior', 'mekanik1'],
            ['mekanik2', 'Wati Susanti', 'Rahayu', 'Mekanik', 'mekanik2'],
            ['supervisor.ops', 'Yanto Supriyadi', 'Firmansyah', 'Supervisor Operasional', 'supervisor.ops'],
            ['cs1', 'Lina Maryati', 'Siregar', 'Customer Service', 'cs1'],
            ['cs2', 'Edi Prasetyo', 'Nugraha', 'Customer Service', 'cs2'],
            ['purchasing', 'Sigit Budiono', 'Santoso', 'Staf Pembelian', 'purchasing'],
            ['hrd', 'Yuni Astuti', 'Kusuma', 'HRD', 'hrd'],
            ['spv.lapangan', 'Galih Permana', 'Saputra', 'Supervisor Lapangan', 'spv.lapangan'],
            ['manajer.ops', 'Indah Permatasari', 'Hidayat', 'Manajer Operasional', 'manajer.ops'],
        ];
        $rows = [];
        foreach ($employees as $i => $e) {
            $rows[] = [
                'employee_id' => $i + 1,
                'first_name' => $e[1],
                'last_name' => $e[2],
                'email' => $e[0].'@baskadrive.com',
                'phone' => '0812'.random_int(10000000, 99999999),
                'position' => $e[3],
                'hire_date' => $this->now->copy()->subDays(random_int(200, 1000))->toDateString(),
                'username' => $e[4],
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'role' => match (true) {
                    str_contains($e[3], 'Manajer') => 'manager',
                    str_contains($e[3], 'Supervisor') => 'manager',
                    str_contains($e[3], 'Akuntan') => 'accountant',
                    str_contains($e[3], 'Kasir') => 'cashier',
                    str_contains($e[3], 'Mekanik') => 'mechanic',
                    default => 'cashier',
                },
                'is_active' => true,
                'created_at' => $this->now->copy()->subDays(random_int(200, 1000)),
            ];
            $this->map['employees'][$e[0]] = $i + 1;
        }
        DB::table('m_employee')->insert($rows);
        $this->command->info('✓ m_employee (14)');
    }

    /* =========================================================
       TRANSAKSI SEWA (12 BULAN)
    ========================================================= */

    protected function seedRentals(): void
    {
        $cashiers = ['kasir1', 'kasir2', 'kasir3', 'supervisor.ops'];
        $this->map['rentals'] = [];       // id per index
        $this->map['rentalMeta'] = [];    // meta utk seeder lanjutan
        $rentalRows = [];
        $detailRows = [];
        $extensionRows = [];
        $seq = 0;
        $km = array_column($this->map['vehicles'], 'km', 'id');

        foreach ($this->map['vehicles'] as $v) {
            $cursor = $this->now->copy()->subDays(365)->subDays(random_int(0, 15));
            $units = 0;
            $hasActive = false;

            while ($cursor->lessThan($this->now) && $units < 10) {
                $gap = random_int(3, 40);
                $start = $cursor->copy()->addDays($gap);
                $days = $this->weightedDays();
                $end = $start->copy()->addDays($days);

                // window melewati hari ini → ongoing (berjalan sekarang)
                if ($end->greaterThan($this->now) && $start->lessThan($this->now)) {
                    $status = 'ongoing';
                    $hasActive = true;
                } else {
                    $status = $this->chance(8) ? 'cancelled' : 'completed';
                }

                $seq++;
                $model = $this->map['models'][$v['model']];
                $customer = $this->pick($this->map['customers']);
                $custType = DB::table('m_customer')->where('customer_id', $customer)->value('customer_type');
                $isCorporate = $custType === 'corporate';

                // ===== STATUS berdasarkan posisi tanggal =====
                if ($end->lessThan($this->now)) {
                    $status = $this->chance(8) ? 'cancelled' : 'completed';
                } elseif ($start->greaterThan($this->now)) {
                    $status = 'reserved';
                } else {
                    $status = 'ongoing';
                }

                // ===== EKONOMI (SED-02: gross-up — subtotal bruto sebelum diskon) =====
                $withDriver = $this->chance($isCorporate ? 55 : 35);
                $driverId = $withDriver ? $this->pick($this->map['drivers']) : null;
                $driverFee = $withDriver ? random_int(13, 20) * 10000 : 0;

                $promoId = $this->chance(22) ? $this->pick(array_values($this->map['promos'])) : null;
                $discount = 0;
                if ($promoId) {
                    $promo = DB::table('m_promo')->where('promo_id', $promoId)->first();
                    if ($promo && $days >= $promo->min_rental_days) {
                        $discount = $promo->discount_type === 'percentage'
                            ? round(($model['price'] * $days) * $promo->discount_value / 100)
                            : (int) $promo->discount_value;
                    } else {
                        $promoId = null;
                    }
                }

                $totalBase = $model['price'] * $days;
                $insuranceFee = (int) round($totalBase * $model['rate'] / 100);
                $driverTotal = $withDriver ? $driverFee * $days : 0;
                $youngDriverFee = $this->chance(6) ? random_int(1, 3) * 50000 : 0;

                // ===== PPN: per sewa =====
                $taxPercent = $isCorporate && $this->chance(30) ? 0 : ($this->chance(8) ? 5 : 11);
                $subtotal = $totalBase + $insuranceFee + $driverTotal + $youngDriverFee;   // bruto sebelum diskon
                $taxAmount = (int) round(($subtotal - $discount) * $taxPercent / 100);
                $total = $subtotal - $discount + $taxAmount;

                // ===== DEPOSIT: per sewa =====
                $deposit = $this->chance(12) ? 0 : $model['deposit'] - ($isCorporate ? random_int(0, 2) * 100000 : 0);
                $deposit = max(0, min($deposit, (int) $total));   // SED-04: deposit ≤ nilai sewa

                // ===== LOKASI =====
                $pickup = $this->pick($this->map['locations']);
                $returnLoc = $this->chance(12) ? $this->pick($this->map['locations']) : $pickup;

                // ===== STATUS berdasarkan posisi tanggal =====
                if ($status === 'completed') {
                    $pay = $this->chance(85) ? 'paid' : ($this->chance(60) ? 'partial' : 'unpaid');
                } elseif ($status === 'ongoing') {
                    $pay = $this->chance(40) ? 'partial' : ($this->chance(50) ? 'paid' : 'unpaid');
                } elseif ($status === 'reserved') {
                    $pay = $this->chance(45) ? 'partial' : 'unpaid';
                } else {
                    $pay = 'refunded';
                }

                $createdAt = $start->copy()->subDays(random_int(1, 5))->setTime(random_int(8, 20), random_int(0, 59));
                $rentalRows[] = [
                    'rental_id' => $seq,
                    'rental_code' => 'RNT-'.$start->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $customer,
                    'vehicle_id' => $v['id'],
                    'employee_id' => $this->map['employees'][$this->pick($cashiers)],
                    'pickup_location_id' => $pickup,
                    'return_location_id' => $returnLoc,
                    'promo_id' => $promoId,
                    'rental_start_date' => $start->copy()->setTime(random_int(7, 10), random_int(0, 59)),
                    'rental_end_date' => $end->copy()->setTime(random_int(7, 10), random_int(0, 59)),
                    'actual_return_date' => $status === 'completed' ? $end->copy()->addHours(random_int(-3, 20)) : null,
                    'rental_days' => $days,
                    'is_with_driver' => $withDriver,
                    'driver_id' => $driverId,
                    'base_rate_per_day' => $model['price'],
                    'total_base_price' => $totalBase,
                    'insurance_fee' => $insuranceFee,
                    'driver_fee' => $driverTotal,
                    'young_driver_fee' => $youngDriverFee,
                    'discount_amount' => $discount,
                    'tax_amount' => $taxAmount,
                    'tax_percent' => $taxPercent,
                    'deposit_amount' => $deposit,
                    'total_amount' => $total,
                    'status' => $status,
                    'payment_status' => $pay,
                    'notes' => $this->chance(15) ? $this->pick(['Lunas di muka via transfer', 'Repeat order pelanggan', 'Unit dicek bersama pelanggan', null, null]) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $rid = $seq;
                $this->map['rentals'][$seq] = $rid;
                $this->map['rentalMeta'][$seq] = [
                    'id' => $rid, 'code' => 'RNT-'.$start->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'status' => $status, 'pay' => $pay, 'total' => $total, 'subtotal' => $subtotal,
                    'tax' => $taxAmount, 'discount' => $discount, 'deposit' => $deposit,
                    'days' => $days, 'start' => $start, 'end' => $end, 'created' => $createdAt,
                    'customer' => $customer, 'vehicle' => $v['id'], 'with_driver' => $withDriver,
                    'driver_id' => $driverId, 'driver_total' => $driverTotal, 'return_loc' => $returnLoc,
                    'is_corporate' => $isCorporate, 'deposit_total' => $deposit,
                ];

                // ===== DETAIL ADD-ON (30%) =====
                if ($this->chance(30)) {
                    $addons = [
                        ['GPS Tracker', 'gps', 25000, $days],
                        ['Kursi Bayi', 'child_seat', 40000, $days],
                        ['Charger & Kabel Data', 'other', 25000, 1],
                        ['WiFi Portable', 'other', 60000, $days],
                        ['Antar-Jemput Bandara', 'extra_driver', 150000, 1],
                        ['Sewa Dalam Kota', 'extra_driver', 100000, random_int(1, 3)],
                    ];
                    $n = random_int(1, 2);
                    foreach ((array) array_rand($addons, $n) as $idx) {
                        [$name, $type, $price, $qty] = $addons[$idx];
                        $detailRows[] = [
                            'rental_id' => $rid,
                            'item_type' => $type,
                            'item_name' => $name,
                            'quantity' => $qty,
                            'unit_price' => $price,
                            'total_price' => $price * $qty,
                            'created_at' => $createdAt,
                        ];
                    }
                }

                // ===== PERPANJANGAN (12% completed/ongoing) =====
                if (in_array($status, ['completed', 'ongoing']) && $this->chance(12)) {
                    $extDays = random_int(1, 4);
                    $extBase = $model['price'] * $extDays;
                    $extTax = (int) round($extBase * $taxPercent / 100);
                    $extensionRows[] = [
                        'rental_id' => $rid,
                        'old_end_date' => $end->copy(),
                        'new_end_date' => $end->copy()->addDays($extDays),
                        'extended_days' => $extDays,
                        'additional_base_price' => $extBase,
                        'additional_tax' => $extTax,
                        'additional_total' => $extBase + $extTax,
                        'status' => $status === 'completed' ? 'approved' : $this->pick(['approved', 'pending']),
                        'approved_by' => $this->map['employees']['manajer.ops'],
                        'approved_at' => $end->copy()->subDays(1),
                        'notes' => null,
                        'created_at' => $end->copy()->subDays(2),
                    ];
                    // perpanjangan menambah pendapatan sewa (meta total tidak diubah — realistis, diterbitkan invoice tambahan)
                }

                // ===== progres kilometer =====
                $km[$v['id']] += $days * random_int(80, 220);

                $units++;
                $cursor = $end;

                if ($status === 'reserved' || $hasActive) {
                    break;
                }
            }

            // ===== SEWA AKTIF SAAT INI (65% kendaraan punya) =====
            if (! $hasActive && $this->chance(65) && $this->now->copy()->subDays(330)->lessThan($cursor)) {
                $roll = $this->weightedPick(['ongoing' => 32, 'overdue' => 8, 'reserved' => 25]);
                $seq++;

                $model = $this->map['models'][$v['model']];
                $customer = $this->pick($this->map['customers']);
                $isCorporate = DB::table('m_customer')->where('customer_id', $customer)->value('customer_type') === 'corporate';
                $days = $this->weightedDays();
                $withDriver = $this->chance($isCorporate ? 55 : 35);
                $driverId = $withDriver ? $this->pick($this->map['drivers']) : null;
                $driverFee = $withDriver ? random_int(13, 20) * 10000 : 0;

                $promoId = $this->chance(15) ? $this->pick(array_values($this->map['promos'])) : null;
                $discount = 0;
                if ($promoId) {
                    $promo = DB::table('m_promo')->where('promo_id', $promoId)->first();
                    if ($promo && $days >= $promo->min_rental_days) {
                        $discount = $promo->discount_type === 'percentage'
                            ? round(($model['price'] * $days) * $promo->discount_value / 100)
                            : (int) $promo->discount_value;
                    } else {
                        $promoId = null;
                    }
                }

                $totalBase = $model['price'] * $days;
                $insuranceFee = (int) round($totalBase * $model['rate'] / 100);
                $driverTotal = $withDriver ? $driverFee * $days : 0;
                $taxPercent = $isCorporate && $this->chance(30) ? 0 : 11;
                $subtotal = $totalBase + $insuranceFee + $driverTotal;   // bruto sebelum diskon (SED-02)
                $taxAmount = (int) round(($subtotal - $discount) * $taxPercent / 100);
                $total = $subtotal - $discount + $taxAmount;
                $deposit = $this->chance(12) ? 0 : min($model['deposit'], (int) $total);   // SED-04: deposit ≤ nilai sewa
                $pickup = $this->pick($this->map['locations']);
                $createdAt = $this->now->copy()->subDays(random_int(1, 8));

                if ($roll === 'reserved') {
                    $start = $this->now->copy()->addDays(random_int(2, 30));
                    $end = $start->copy()->addDays($days);
                    $status = 'reserved';
                    $pay = $this->chance(45) ? 'partial' : 'unpaid';
                } elseif ($roll === 'overdue') {
                    $start = $this->now->copy()->subDays(random_int(8, 22));
                    $end = $this->now->copy()->subDays(random_int(1, 6));
                    $status = 'ongoing';   // app: ongoing + end < now = Terlambat
                    $pay = 'unpaid';
                } else {
                    $start = $this->now->copy()->subDays(random_int(1, $days - 1));
                    $end = $start->copy()->addDays($days);
                    $status = 'ongoing';
                    $pay = $this->chance(40) ? 'partial' : ($this->chance(50) ? 'paid' : 'unpaid');
                }

                $rentalRows[] = [
                    'rental_id' => $seq,
                    'rental_code' => 'RNT-'.$start->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $customer,
                    'vehicle_id' => $v['id'],
                    'employee_id' => $this->map['employees'][$this->pick($cashiers)],
                    'pickup_location_id' => $pickup,
                    'return_location_id' => $pickup,
                    'promo_id' => $promoId,
                    'rental_start_date' => $start->copy()->setTime(random_int(7, 10), random_int(0, 59)),
                    'rental_end_date' => $end->copy()->setTime(random_int(7, 10), random_int(0, 59)),
                    'actual_return_date' => null,
                    'rental_days' => $days,
                    'is_with_driver' => $withDriver,
                    'driver_id' => $driverId,
                    'base_rate_per_day' => $model['price'],
                    'total_base_price' => $totalBase,
                    'insurance_fee' => $insuranceFee,
                    'driver_fee' => $driverTotal,
                    'young_driver_fee' => 0,
                    'discount_amount' => $discount,
                    'tax_amount' => $taxAmount,
                    'tax_percent' => $taxPercent,
                    'deposit_amount' => $deposit,
                    'total_amount' => $total,
                    'status' => $status,
                    'payment_status' => $pay,
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $this->map['rentalMeta'][$seq] = [
                    'id' => $seq, 'code' => 'RNT-'.$start->format('Y').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                    'status' => $status, 'pay' => $pay, 'total' => $total, 'subtotal' => $subtotal,
                    'tax' => $taxAmount, 'discount' => $discount, 'deposit' => $deposit,
                    'days' => $days, 'start' => $start, 'end' => $end, 'created' => $createdAt,
                    'customer' => $customer, 'vehicle' => $v['id'], 'with_driver' => $withDriver,
                    'driver_id' => $driverId, 'driver_total' => $driverTotal, 'return_loc' => $pickup,
                    'is_corporate' => $isCorporate, 'deposit_total' => $deposit,
                ];

                if ($status === 'ongoing') {
                    $km[$v['id']] += random_int(100, 1200);
                }
            }
        }

        // bulk insert dalam chunk
        foreach (array_chunk($rentalRows, 100) as $chunk) {
            DB::table('tr_rental')->insert($chunk);
        }
        if ($detailRows) {
            foreach (array_chunk($detailRows, 100) as $chunk) {
                DB::table('tr_rental_detail')->insert($chunk);
            }
        }
        if ($extensionRows) {
            foreach (array_chunk($extensionRows, 50) as $chunk) {
                DB::table('tr_rental_extension')->insert($chunk);
            }
        }

        foreach ($this->map['vehicles'] as $i => $v) {
            $this->map['vehicles'][$i]['km'] = $km[$v['id']];
        }

        // SED-04: rental berdeposit akan ditahan sebagai payment terpisah
        // (allocation=deposit, lawan 2-3000) — invoice sewa hanya memuat pokok sewa.
        $this->map['depositPayments'] = [];
        foreach ($this->map['rentalMeta'] as $meta) {
            if ($meta['deposit'] > 0) {
                $this->map['depositPayments'][$meta['id']] = true;
            }
        }

        $counts = array_count_values(array_column($this->map['rentalMeta'], 'status'));
        $this->command->info('✓ tr_rental ('.count($rentalRows).'): '.http_build_query($counts ?: [], '', ', '));
        $this->command->info('✓ tr_rental_detail ('.count($detailRows).') + tr_rental_extension ('.count($extensionRows).')');
    }

    protected function syncVehicleStatuses(): void
    {
        // SED-07: status unit mengikuti sewa aktif — ongoing/overdue → rented,
        // reserved → reserved. Sebelumnya semua unit dibiarkan 'available'.
        $statusByVehicle = [];
        foreach ($this->map['rentalMeta'] as $meta) {
            if ($meta['status'] === 'ongoing') {
                $statusByVehicle[$meta['vehicle']] = 'rented';
            } elseif ($meta['status'] === 'reserved') {
                $statusByVehicle[$meta['vehicle']] ??= 'reserved';
            }
        }

        if ($statusByVehicle) {
            foreach ($statusByVehicle as $vehicleId => $status) {
                DB::table('m_vehicle')->where('vehicle_id', $vehicleId)
                    ->update(['status' => $status, 'updated_at' => $this->now]);
            }
        }
        $this->command->info('✓ sinkronisasi status unit ('.count($statusByVehicle).' rented/reserved)');
    }

    protected function seedReturns(): void
    {
        $rows = [];
        $seq = 0;
        $kmByVehicle = array_column($this->map['vehicles'], 'km', 'id');

        foreach ($this->map['rentalMeta'] as $meta) {
            if ($meta['status'] !== 'completed') {
                continue;
            }

            $seq++;
            $condition = $this->weightedPick(['excellent' => 30, 'good' => 46, 'fair' => 15, 'damaged' => 9]);
            $extra = match ($condition) {
                'fair' => random_int(100, 500) * 1000,
                'damaged' => random_int(5, 15) * 100000,
                default => $this->chance(12) ? random_int(1, 4) * 50000 : 0,
            };
            $deposit = $meta['deposit'];
            $refund = $condition === 'good' ? $deposit : max(0, $deposit - $extra);
            if ($meta['pay'] === 'refunded') {
                $refund = 0;
            }

            $returnDate = $meta['end']->copy()->addHours(random_int(0, 20));

            $rows[] = [
                'return_id' => $seq,
                'rental_id' => $meta['id'],
                'return_date' => $returnDate,
                'return_mileage' => $kmByVehicle[$meta['vehicle']] ?? random_int(20000, 80000),
                'fuel_level' => $this->weightedPick(['full' => 45, 'three_quarter' => 30, 'half' => 15, 'quarter' => 7, 'empty' => 3]),
                'vehicle_condition' => $condition,
                'damage_description' => $condition === 'damaged' ? $this->pick(['Bumper depan penyok & cat terkelupas', 'Pintu sisi kanan lecet parah', 'Lampu belakang pecah', 'Bemor depan baret luas']) : ($condition === 'fair' ? $this->pick(['Baret kecil di pintu', 'Velg tergores trotoar', 'Spion retak']) : null),
                'repair_cost_estimate' => $extra,
                'extra_charge' => $extra,
                'deposit_refund' => $refund,
                'created_at' => $returnDate,
            ];

            $this->map['returns'][$meta['id']] = ['id' => $seq, 'condition' => $condition, 'extra' => $extra, 'date' => $returnDate];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('tr_return')->insert($chunk);
        }
        $this->command->info('✓ tr_return ('.count($rows).')');
    }

    protected function seedDamagesAndClaims(): void
    {
        $damageRows = $photoRows = $claimRows = [];
        $dSeq = $pSeq = $cSeq = 0;
        $this->map['claims'] = [];
        $this->map['damages'] = [];
        $providers = ['ACSA Insurance', 'Jasa Raharja Putera', 'Adira Autocall', 'Astra Buana', 'Zurich Takaful', 'Sinarmas MSIG'];

        foreach ($this->map['rentalMeta'] as $meta) {
            $ret = $this->map['returns'][$meta['id']] ?? null;
            if (! $ret || in_array($ret['condition'], ['excellent', 'good'])) {
                continue;
            }

            $dSeq++;
            $severity = $ret['condition'] === 'damaged' ? $this->pick(['severe', 'moderate']) : 'minor';
            $reportedAt = $ret['date']->copy()->addDays(random_int(0, 2));

            $damageRows[] = [
                'damage_id' => $dSeq,
                'rental_id' => $meta['id'],
                'vehicle_id' => $meta['vehicle'],
                'return_id' => $ret['id'],
                'reported_date' => $reportedAt,
                'damage_type' => $this->weightedPick(['exterior' => 50, 'glass' => 15, 'tire' => 12, 'interior' => 10, 'electrical' => 7, 'mechanical' => 6]),
                'severity' => $severity,
                'location' => $this->pick(['Bumper depan', 'Pintu kanan', 'Pintu kiri', 'Kap mesin', 'Kaca depan', 'Velg', 'Spion kiri', 'Bagian belakang', 'Interior kursi']),
                'description' => $this->pick(['Kerusakan akibat tabrakan ringan', 'Terkena object saat parkir', 'Kecelakaan kecil di jalan tol', 'Kelalaian saat berkendara', 'Benda jatuh mengenai bodi']),
                'repair_cost_estimate' => $ret['extra'],
                'actual_repair_cost' => $this->chance(75) ? (int) round($ret['extra'] * (random_int(85, 120) / 100)) : 0,
                'status' => $this->weightedPick(['repaired' => 45, 'claimed_insurance' => 25, 'repair_in_progress' => 15, 'assessment' => 10, 'inspected' => 5]),
                'inspected_by' => $this->map['employees']['mekanik1'],
                'inspected_at' => $reportedAt->copy()->addDays(1),
                'notes' => null,
                'created_at' => $reportedAt,
                'updated_at' => $reportedAt,
            ];

            // SED-06: identitas kerusakan utk penautan denda (FLE-07)
            $this->map['damages'][$meta['id']] = [
                'id' => $dSeq,
                'code' => 'DMG-'.str_pad((string) $dSeq, 5, '0', STR_PAD_LEFT),
                'actual' => $damageRows[$dSeq - 1]['actual_repair_cost'],
                'estimate' => $ret['extra'],
            ];

            // foto 1-4 per kerusakan
            foreach (range(1, random_int(1, 4)) as $n) {
                $pSeq++;
                $photoRows[] = [
                    'photo_id' => $pSeq,
                    'damage_id' => $dSeq,
                    'photo_url' => 'damage-photos/damage-'.$dSeq.'-view-'.$n.'.jpg',
                    'caption' => 'Foto kerusakan sudut '.$n,
                    'uploaded_at' => $reportedAt,
                ];
            }

            // klaim untuk severe/moderate (60%)
            if (in_array($severity, ['severe', 'moderate']) && $this->chance(60)) {
                $cSeq++;
                $claimAmount = (int) round($ret['extra'] * random_int(70, 100) / 100);
                $claimStatus = $this->weightedPick(['approved' => 40, 'paid' => 25, 'under_review' => 20, 'submitted' => 15]);
                $approvedAmount = $this->chance(70) ? (int) round($claimAmount * random_int(70, 100) / 100) : 0;
                $claimRows[] = [
                    'claim_id' => $cSeq,
                    'rental_id' => $meta['id'],
                    'damage_id' => $dSeq,
                    'claim_number' => 'CLM-'.$reportedAt->format('Y').'-'.str_pad((string) $cSeq, 5, '0', STR_PAD_LEFT),
                    'insurance_provider' => $this->pick($providers),
                    'policy_number' => 'POL-'.random_int(100000, 999999),
                    'claim_date' => $reportedAt->copy()->addDays(random_int(1, 5)),
                    'claim_amount' => $claimAmount,
                    'approved_amount' => $approvedAmount,
                    'status' => $claimStatus,
                    'approved_date' => $this->chance(65) ? $reportedAt->copy()->addDays(random_int(6, 25)) : null,
                    'notes' => null,
                    'created_at' => $reportedAt,
                ];

                // SED-05: metadata klaim utk jurnal pencairan (FLE-09)
                if ($claimStatus === 'paid' && $approvedAmount > 0) {
                    $paidAt = $reportedAt->copy()->addDays(random_int(26, 60));
                    if ($paidAt->greaterThan($this->now)) {
                        $paidAt = $this->now->copy()->subDays(random_int(0, 5));
                    }
                    $this->map['claims'][] = [
                        'number' => 'CLM-'.$reportedAt->format('Y').'-'.str_pad((string) $cSeq, 5, '0', STR_PAD_LEFT),
                        'provider' => $claimRows[$cSeq - 1]['insurance_provider'] ?? 'Asuransi',
                        'approved' => $approvedAmount,
                        'paid_at' => $paidAt->toDateString(),
                    ];
                }
            }
        }

        if ($damageRows) {
            foreach (array_chunk($damageRows, 100) as $ch) {
                DB::table('tr_damage_report')->insert($ch);
            }
        }
        if ($photoRows) {
            foreach (array_chunk($photoRows, 100) as $ch) {
                DB::table('tr_damage_photo')->insert($ch);
            }
        }
        if ($claimRows) {
            foreach (array_chunk($claimRows, 50) as $ch) {
                DB::table('tr_insurance_claim')->insert($ch);
            }
        }

        $this->command->info("✓ tr_damage_report ($dSeq) + tr_damage_photo ($pSeq) + tr_insurance_claim ($cSeq)");
    }

    protected function seedMaintenances(): void
    {
        $typeCosts = [1 => [350, 800], 2 => [180, 400], 3 => [450, 700], 4 => [2400, 3200], 5 => [750, 1200], 6 => [400, 900]];
        $descriptions = [1 => 'Servis rutin: ganti oli, filter udara & filter oli', 2 => 'Ganti oli mesin + filter oli', 3 => 'Spooring, balancing & penyetelan toe', 4 => 'Ganti 4 ban new Bridgestone', 5 => 'Cuci evaporator, isi freon R134a', 6 => 'Ganti kampas rem depan + minyak rem DOT4'];

        $rows = [];
        $seq = 0;
        foreach ($this->map['vehicles'] as $v) {
            $n = random_int(2, 4);
            for ($i = 0; $i < $n; $i++) {
                $seq++;
                $typeId = $this->pick($this->map['mtypes']);
                $scheduled = $this->now->copy()->subDays(random_int(10, 350));
                $isFuture = $this->chance(8);
                if ($isFuture) {
                    $scheduled = $this->now->copy()->addDays(random_int(5, 40));
                }
                $cost = random_int($typeCosts[$typeId][0], $typeCosts[$typeId][1]) * 1000;
                $workshop = $this->pick($this->map['workshops']);

                // SED-08: odometer wajar — tidak negatif, tidak melebihi km tercatat
                $currentKm = max(500, $v['km'] - random_int(500, 15000));

                $rows[] = [
                    'maintenance_id' => $seq,
                    'vehicle_id' => $v['id'],
                    'workshop_id' => $workshop,
                    'maintenance_type_id' => $typeId,
                    'scheduled_date' => $scheduled->toDateString(),
                    'actual_date' => $isFuture ? null : $scheduled->copy()->addDays(random_int(0, 2))->toDateString(),
                    'current_mileage' => $currentKm,
                    'cost' => $cost,
                    'description' => $descriptions[$typeId],
                    'status' => $isFuture ? 'scheduled' : ($this->chance(10) ? 'in_progress' : 'completed'),
                    'next_maintenance_km' => $v['km'] + 10000,
                    'notes' => null,
                    'created_at' => $scheduled->copy()->subDays(random_int(3, 14)),
                    'updated_at' => $scheduled,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $ch) {
            DB::table('tr_maintenance')->insert($ch);
        }
        $this->map['maintenances'] = $rows;
        $this->command->info('✓ tr_maintenance ('.$seq.')');
    }

    protected function seedFines(): void
    {
        $rows = [];
        $seq = 0;
        $fineTypes = [
            ['late_return', 'Keterlambatan pengembalian unit', [50000, 400000]],
            ['fuel', 'Kekurangan bahan bakar dari level awal', [50000, 200000]],
            ['other', 'Tilang ETLE pelanggaran lalu lintas', [250000, 750000]],
            ['other', 'Bukti merokok di dalam kendaraan', [150000, 300000]],
            ['cleaning', 'Kendaraan kotor berat saat dikembalikan', [100000, 250000]],
            ['lost_item', 'Kehilangan aksesori kendaraan', [150000, 600000]],
        ];
        $officers = ['kasir1', 'kasir2', 'spv.lapangan'];

        foreach ($this->map['rentalMeta'] as $meta) {
            if (! in_array($meta['status'], ['completed', 'ongoing'])) {
                continue;
            }

            $officer = $this->map['employees'][$this->pick($officers)];

            // ===== Denda kerusakan (SED-06): tertaut damage_id, jalur akrual FLE-08 =====
            $damage = $this->map['damages'][$meta['id']] ?? null;
            if ($damage && $this->chance(45)) {
                $seq++;
                $amount = max(100000, (int) round(($damage['actual'] ?: $damage['estimate']) * random_int(30, 60) / 100));
                $status = $this->weightedPick(['paid' => 60, 'unpaid' => 30, 'waived' => 10]);
                $issuedAt = $meta['end']->copy()->addDays(random_int(0, 3));
                if ($issuedAt->greaterThan($this->now)) {
                    // Denda tidak boleh diterbitkan di masa depan.
                    $issuedAt = $this->now->copy()->subDays(random_int(0, 2));
                }
                $paidDate = $issuedAt->copy()->addDays(random_int(0, 10));
                $waiveDate = $issuedAt->copy()->addDays(random_int(1, 7));
                if ($status === 'paid' && $paidDate->greaterThan($this->now)) {
                    $status = 'unpaid';
                }
                if ($status === 'waived' && $waiveDate->greaterThan($this->now)) {
                    $waiveDate = $this->now->copy()->subDays(random_int(0, 2));
                }

                $rows[] = [
                    'fine_id' => $seq,
                    'rental_id' => $meta['id'],
                    'return_id' => $this->map['returns'][$meta['id']]['id'] ?? null,
                    'damage_id' => $damage['id'],
                    'fine_type' => 'damage',
                    'description' => 'Tagihan kerusakan '.$damage['code'].' — bagian tanggungan penyewa di luar polis',
                    'amount' => $amount,
                    'status' => $status,
                    'issued_date' => $issuedAt,
                    'paid_date' => $status === 'paid' ? $paidDate : null,
                    'paid_by' => $status === 'paid' ? $officer : null,
                    'waived_by' => $status === 'waived' ? $this->map['employees']['manajer.ops'] : null,
                    'waived_at' => $status === 'waived' ? $waiveDate : null,
                    'issued_by' => $officer,
                    'notes' => null,
                ];
                $rows[$seq - 1]['code'] = 'FND-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
            }

            // ===== Denda operasional lain =====
            if (! $this->chance(20)) {
                continue;
            }

            $seq++;
            [$type, $desc, $range] = $this->pick($fineTypes);
            $issuedAt = $meta['end']->copy();
            if ($issuedAt->greaterThan($this->now)) {
                // Sewa ongoing berakhir di masa depan — denda operasional diterbitkan hari ini.
                $issuedAt = $this->now->copy()->subDays(random_int(0, 3));
            }
            $status = $this->chance(68) ? 'paid' : ($this->chance(75) ? 'unpaid' : 'waived');
            $paidDate = $issuedAt->copy()->addDays(random_int(0, 10));
            $waiveDate = $issuedAt->copy()->addDays(random_int(1, 7));
            if ($status === 'paid' && $paidDate->greaterThan($this->now)) {
                $status = 'unpaid';
            }
            if ($status === 'waived' && $waiveDate->greaterThan($this->now)) {
                $waiveDate = $this->now->copy()->subDays(random_int(0, 2));
            }

            $rows[] = [
                'fine_id' => $seq,
                'rental_id' => $meta['id'],
                'return_id' => $this->map['returns'][$meta['id']]['id'] ?? null,
                'damage_id' => null,
                'fine_type' => $type,
                'description' => $desc,
                'amount' => random_int($range[0], $range[1]),
                'status' => $status,
                'issued_date' => $issuedAt,
                'paid_date' => $status === 'paid' ? $paidDate : null,
                'paid_by' => $status === 'paid' ? $officer : null,
                'waived_by' => $status === 'waived' ? $this->map['employees']['manajer.ops'] : null,
                'waived_at' => $status === 'waived' ? $waiveDate : null,
                'issued_by' => $officer,
                'notes' => null,
                'code' => 'FND-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            ];
        }

        // 'code' hanya metadata internal utk deskripsi jurnal — bukan kolom tabel.
        $fineRows = array_map(fn (array $r) => Arr::except($r, ['code']), $rows);
        foreach (array_chunk($fineRows, 50) as $ch) {
            DB::table('tr_fine')->insert($ch);
        }
        $this->map['fines'] = $rows;
        $this->command->info('✓ tr_fine ('.count($rows).', termasuk denda kerusakan tertaut damage_id)');
    }

    /* =========================================================
       KEUANGAN: INVOICE, PAYMENT, REFUND, JURNAL
    ========================================================= */

    protected function seedInvoicesPaymentsRefunds(): void
    {
        $invoiceRows = $paymentRows = $refundRows = $journalRows = $journalDetailRows = [];
        $iSeq = $pSeq = $rSeq = $jSeq = 0;
        $accountant = $this->map['employees']['akuntan'];
        $coa = $this->map['coa'];
        $kas = $coa['1-1100'];
        $bank = $coa['1-1200'];
        $piutang = $coa['1-2100'];
        $pendSewa = $coa['4-1100'];
        $pendDenda = $coa['4-2000'];
        $pendKlaim = $coa['4-3000'];
        $uangMuka = $coa['2-3000'];
        $bebanServis = $coa['5-2100'];
        $bebanSukucadang = $coa['5-2200'];
        $bebanOperasional = $coa['5-1000'];

        $methods = [
            'bank_transfer' => ['weight' => 35, 'ref' => 'TRF'],
            'cash' => ['weight' => 30, 'ref' => null],
            'e_wallet' => ['weight' => 15, 'ref' => 'EW'],
            'credit_card' => ['weight' => 10, 'ref' => 'CC'],
            'debit_card' => ['weight' => 10, 'ref' => 'DB'],
        ];

        $journals = function (string $date, string $type, array $entries) use (&$jSeq, &$journalRows, &$journalDetailRows, $accountant) {
            $jSeq++;
            $jid = $jSeq;
            $journalRows[] = [
                'journal_id' => $jid,
                'transaction_date' => $date,
                'reference_number' => 'JRNL-'.Carbon::parse($date)->format('Ym').'-'.str_pad((string) $jSeq, 5, '0', STR_PAD_LEFT),
                'description' => $entries['desc'],
                'journal_type' => $type,
                'created_by' => $accountant,
                'created_at' => $date,
            ];
            foreach ($entries['lines'] as $line) {
                $journalDetailRows[] = [
                    'journal_id' => $jid,
                    'account_id' => $line['account'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['desc'],
                ];
            }
        };

        // ===== TAHANAN DEPOSIT (SED-04): Dr Kas/Bank, Cr Uang Muka Pelanggan =====
        // Dibuat untuk SEMUA rental berdeposit (termasuk cancelled — deposit
        // tetap tertahan dan dapat dikembalikan lewat refund pembatalan).
        foreach ($this->map['rentalMeta'] as $meta) {
            if ($meta['deposit_total'] <= 0) {
                continue;
            }
            $pSeq++;
            $depMethod = $this->chance(70) ? 'bank_transfer' : 'cash';
            $depDate = $meta['start']->copy()->subDays(random_int(0, 2));
            if ($depDate->greaterThan($this->now)) {
                // Rental reserved: deposit diterima saat booking (hari ini atau lebih awal).
                $depDate = $this->now->copy()->subDays(random_int(0, 3));
            }

            $paymentRows[] = [
                'payment_id' => $pSeq,
                'invoice_id' => null,
                'rental_id' => $meta['id'],
                'payment_date' => $depDate->toDateString(),
                'amount' => $meta['deposit_total'],
                'payment_method' => $depMethod,
                'reference_number' => $depMethod === 'bank_transfer' ? 'DEP-'.random_int(100000, 999999) : null,
                'status' => 'completed',
                'allocation' => 'deposit',
                'notes' => 'Tahanan deposit sewa '.$meta['code'],
                'created_at' => $depDate,
            ];

            $journals($depDate->toDateString(), 'payment', [
                'desc' => 'Penerimaan deposit sewa '.$meta['code'],
                'lines' => [
                    ['account' => $depMethod === 'cash' ? $kas : $bank, 'debit' => $meta['deposit_total'], 'credit' => 0, 'desc' => 'Kas diterima (deposit)'],
                    ['account' => $uangMuka, 'debit' => 0, 'credit' => $meta['deposit_total'], 'desc' => 'Uang muka pelanggan'],
                ],
            ]);
        }

        // ===== INVOICE utk semua sewa non-cancelled =====
        // Kontrak FIN-07 (SED-02): total = sub_total (bruto) - discount + tax.
        // Deposit (SED-04) ditahan terpisah sbg payment allocation=deposit (2-3000)
        // sehingga invoice hanya memuat pokok sewa.
        foreach ($this->map['rentalMeta'] as $meta) {
            if ($meta['status'] === 'cancelled') {
                continue;
            }

            $iSeq++;
            $issue = $meta['start']->copy()->subDays(random_int(0, 2));
            if ($issue->greaterThan($this->now)) {
                // Booking masa depan: invoice konfirmasi diterbitkan hari ini.
                $issue = $this->now->copy()->subDays(random_int(0, 2));
            }
            $due = $issue->copy()->addDays(7);
            $paid = $meta['pay'];

            $status = match ($paid) {
                'paid' => $due->lessThan($this->now) ? 'paid' : ($this->chance(80) ? 'paid' : 'sent'),
                'partial' => $due->lessThan($this->now) ? 'overdue' : 'partially_paid',
                'unpaid' => $due->lessThan($this->now) ? 'overdue' : 'sent',
                default => 'sent',
            };
            if ($status === 'draft' || $meta['status'] === 'reserved') {
                $status = $this->chance(50) ? 'draft' : 'sent';
            }

            $paidAmount = $paid === 'paid' ? $meta['total'] : ($paid === 'partial' ? (int) round($meta['total'] * random_int(30, 60) / 100) : 0);

            $invoiceRows[] = [
                'invoice_id' => $iSeq,
                'rental_id' => $meta['id'],
                'invoice_number' => 'INV-'.$issue->format('Y').'-'.str_pad((string) $iSeq, 5, '0', STR_PAD_LEFT),
                'issue_date' => $issue->toDateString(),
                'due_date' => $due->toDateString(),
                'sub_total' => $meta['subtotal'],
                'tax' => $meta['tax'],
                'discount' => $meta['discount'],
                'total_amount' => $meta['total'],
                'paid_amount' => $paidAmount,
                'status' => $status,
                'notes' => $meta['deposit_total'] > 0 ? 'Deposit Rp '.number_format($meta['deposit_total'], 0, ',', '.').' ditahan terpisah (tidak termasuk tagihan).' : null,
                'created_at' => $issue,
                'updated_at' => $issue,
            ];
            $this->map['invoices'][$meta['id']] = ['id' => $iSeq, 'total' => $meta['total'], 'paid' => $paidAmount, 'issue' => $issue, 'status' => $status, 'code' => 'INV-'.$issue->format('Y').'-'.str_pad((string) $iSeq, 5, '0', STR_PAD_LEFT)];

            // Jurnal akrual piutang: Dr Piutang Sewa, Cr Pendapatan Sewa
            $journals($issue->toDateString(), 'rental', [
                'desc' => 'Akrual piutang invoice '.'INV-'.$issue->format('Y').'-'.str_pad((string) $iSeq, 5, '0', STR_PAD_LEFT),
                'lines' => [
                    ['account' => $piutang, 'debit' => $meta['total'], 'credit' => 0, 'desc' => 'Piutang sewa '.$meta['code']],
                    ['account' => $pendSewa, 'debit' => 0, 'credit' => $meta['total'], 'desc' => 'Pendapatan sewa'],
                ],
            ]);

            // ===== PAYMENT POKOK SEWA =====
            if ($paidAmount > 0) {
                $pSeq++;
                $method = $this->weightedPick(array_column($methods, 'weight'));
                $methodNames = array_keys($methods);
                $methodName = $methodNames[$method];
                $ref = $methods[$methodName]['ref'] ? $methods[$methodName]['ref'].'-'.random_int(100000, 999999) : null;
                $payDate = $issue->copy()->addDays(random_int(0, 6));
                if ($payDate->greaterThan($this->now)) {
                    // Rental reserved/ongoing: pembayaran DP terjadi saat booking (hari ini atau lebih awal).
                    $payDate = $this->now->copy()->subDays(random_int(0, 5));
                }

                $paymentRows[] = [
                    'payment_id' => $pSeq,
                    'invoice_id' => $iSeq,
                    'rental_id' => $meta['id'],
                    'payment_date' => $payDate->toDateString(),
                    'amount' => $paidAmount,
                    'payment_method' => $methodName,
                    'reference_number' => $ref,
                    'status' => 'completed',
                    'allocation' => 'rental',
                    'notes' => null,
                    'created_at' => $payDate,
                ];

                $journals($payDate->toDateString(), 'payment', [
                    'desc' => 'Pembayaran invoice '.$meta['code'],
                    'lines' => [
                        ['account' => $methodName === 'cash' ? $kas : $bank, 'debit' => $paidAmount, 'credit' => 0, 'desc' => 'Penerimaan pembayaran'],
                        ['account' => $piutang, 'debit' => 0, 'credit' => $paidAmount, 'desc' => 'Pelunasan piutang'],
                    ],
                ]);
            }
        }

        // ===== REFUND (SED-04) =====
        foreach ($this->map['rentalMeta'] as $meta) {
            $ret = $this->map['returns'][$meta['id']] ?? null;
            $hasDepositPayment = isset($this->map['depositPayments'][$meta['id']]);
            $depositCut = $ret ? (int) $ret['extra'] : 0;
            $refundDeposit = $hasDepositPayment && $meta['status'] === 'completed' && $meta['deposit_total'] > 0 && $this->chance(70);
            $cancelRefund = $meta['status'] === 'cancelled' && $meta['pay'] === 'refunded' && $hasDepositPayment;

            if ($refundDeposit || $cancelRefund) {
                $rSeq++;
                $date = ($ret['date'] ?? $meta['end'])->copy()->addDays(random_int(1, 5));
                if ($date->greaterThan($this->now)) {
                    // Pembatalan booking masa depan: refund sudah diproses hari ini.
                    $date = $this->now->copy()->subDays(random_int(0, 3));
                }

                if ($cancelRefund) {
                    // Pembatalan sewa: deposit yang tertahan dikembalikan penuh.
                    $amount = $meta['deposit_total'];
                    $note = 'Pengembalian deposit pembatalan sewa';
                } else {
                    // Deposit kembali dikurangi extra charge; potongan jadi pendapatan sewa.
                    $amount = max(0, $meta['deposit_total'] - $depositCut);
                    $note = $depositCut > 0
                        ? 'Pengembalian deposit dikurangi biaya kerusakan Rp '.number_format($depositCut, 0, ',', '.')
                        : 'Pengembalian deposit';
                }

                if ($amount <= 0 && ! $cancelRefund) {
                    continue;   // deposit ludes tertelan biaya — tidak ada refund
                }

                $refundRows[] = [
                    'refund_id' => $rSeq,
                    'payment_id' => null,
                    'rental_id' => $meta['id'],
                    'refund_date' => $date->toDateString(),
                    'amount' => $amount,
                    'refund_type' => $cancelRefund ? 'cancellation' : 'deposit_return',
                    'status' => 'processed',
                    'reference_number' => 'RFD-'.random_int(100000, 999999),
                    'notes' => $note,
                ];

                $lines = [
                    ['account' => $uangMuka, 'debit' => $meta['deposit_total'], 'credit' => 0, 'desc' => 'Pelunasan kewajiban uang muka'],
                ];
                if ($depositCut > 0 && ! $cancelRefund) {
                    $lines[] = ['account' => $pendSewa, 'debit' => 0, 'credit' => $depositCut, 'desc' => 'Pendapatan dari potongan deposit'];
                }
                $lines[] = ['account' => $bank, 'debit' => 0, 'credit' => $amount, 'desc' => 'Transfer deposit kembali'];

                $journals($date->toDateString(), 'refund', [
                    'desc' => 'Refund '.($cancelRefund ? 'pembatalan' : 'deposit').' '.$meta['code'],
                    'lines' => $lines,
                ]);
            }
        }

        // ===== JURNAL MAINTENANCE =====
        foreach ($this->map['maintenances'] as $m) {
            if ($m['status'] !== 'completed' || $m['cost'] <= 0) {
                continue;
            }
            $journals($m['scheduled_date'], 'maintenance', [
                'desc' => 'Biaya maintenance kendaraan #'.$m['vehicle_id'],
                'lines' => [
                    ['account' => $this->chance(60) ? $bebanServis : $bebanSukucadang, 'debit' => $m['cost'], 'credit' => 0, 'desc' => 'Beban servis'],
                    ['account' => $kas, 'debit' => 0, 'credit' => $m['cost'], 'desc' => 'Pembayaran bengkel'],
                ],
            ]);
        }

        // ===== JURNAL DENDA (SED-06: akrual + settlement/waive sesuai kontrak FLE-08) =====
        foreach ($this->map['fines'] as $f) {
            $isDamage = $f['fine_type'] === 'damage' && $f['damage_id'] !== null;

            if ($isDamage) {
                // Akrual saat denda diterbitkan: Dr Piutang Sewa, Cr Pendapatan Denda
                $journals($f['issued_date']->toDateString(), 'fine', [
                    'desc' => 'Akrual piutang denda kerusakan '.$f['code'],
                    'lines' => [
                        ['account' => $piutang, 'debit' => $f['amount'], 'credit' => 0, 'desc' => 'Piutang denda kerusakan'],
                        ['account' => $pendDenda, 'debit' => 0, 'credit' => $f['amount'], 'desc' => 'Pendapatan denda (akrual)'],
                    ],
                ]);
            }

            if ($f['status'] === 'paid') {
                if ($isDamage) {
                    // Settlement piutang: Dr Kas/Bank, Cr Piutang Sewa (bukan pendapatan ganda)
                    $journals($f['paid_date']->toDateString(), 'fine', [
                        'desc' => 'Pelunasan piutang denda kerusakan '.$f['code'],
                        'lines' => [
                            ['account' => $kas, 'debit' => $f['amount'], 'credit' => 0, 'desc' => 'Denda diterima'],
                            ['account' => $piutang, 'debit' => 0, 'credit' => $f['amount'], 'desc' => 'Pelunasan piutang denda'],
                        ],
                    ]);
                } else {
                    // Denda non-damage langsung kas (kontrak FIN-03)
                    $journals($f['paid_date']->toDateString(), 'fine', [
                        'desc' => 'Penerimaan denda sewa',
                        'lines' => [
                            ['account' => $kas, 'debit' => $f['amount'], 'credit' => 0, 'desc' => 'Denda diterima'],
                            ['account' => $pendDenda, 'debit' => 0, 'credit' => $f['amount'], 'desc' => 'Pendapatan denda'],
                        ],
                    ]);
                }
            } elseif ($f['status'] === 'waived') {
                if ($isDamage) {
                    // Waive membalik akrual: Dr Pendapatan Denda, Cr Piutang Sewa
                    $journals($f['waived_at']->toDateString(), 'fine', [
                        'desc' => 'Penghapusan piutang denda kerusakan (waive) '.$f['code'],
                        'lines' => [
                            ['account' => $pendDenda, 'debit' => $f['amount'], 'credit' => 0, 'desc' => 'Reversal pendapatan denda'],
                            ['account' => $piutang, 'debit' => 0, 'credit' => $f['amount'], 'desc' => 'Hapus piutang denda'],
                        ],
                    ]);
                }
                // Denda non-damage waived tidak berdampak jurnal (belum pernah diakui).
            }
        }

        // ===== JURNAL KLAIM ASURANSI CAIR (SED-05, FLE-09): Dr Bank, Cr Pendapatan Klaim =====
        // map['claims'] hanya berisi klaim berstatus paid dengan approved > 0.
        foreach ($this->map['claims'] ?? [] as $c) {
            $journals($c['paid_at'], 'insurance', [
                'desc' => 'Pencairan klaim asuransi '.$c['number'],
                'lines' => [
                    ['account' => $bank, 'debit' => $c['approved'], 'credit' => 0, 'desc' => 'Klaim diterima dari '.$c['provider']],
                    ['account' => $pendKlaim, 'debit' => 0, 'credit' => $c['approved'], 'desc' => 'Pendapatan klaim asuransi'],
                ],
            ]);
        }

        // ===== beberapa jurnal adjustment manual (SED-03: bernilai nyata) =====
        for ($i = 0; $i < 4; $i++) {
            $date = $this->now->copy()->subDays(random_int(5, 100));
            $amount = random_int(12, 85) * 1000 + 500;
            $journals($date->toDateString(), 'adjustment', [
                'desc' => 'Penyesuaian selisih kas akhir bulan',
                'lines' => [
                    ['account' => $bebanOperasional, 'debit' => $amount, 'credit' => 0, 'desc' => 'Selisih kas tidak terjelaskan'],
                    ['account' => $kas, 'debit' => 0, 'credit' => $amount, 'desc' => 'Penyesuaian saldo kas'],
                ],
            ]);
        }

        foreach (array_chunk($invoiceRows, 100) as $ch) {
            DB::table('tr_invoice')->insert($ch);
        }
        foreach (array_chunk($paymentRows, 100) as $ch) {
            DB::table('tr_payment')->insert($ch);
        }
        foreach (array_chunk($refundRows, 50) as $ch) {
            DB::table('tr_refund')->insert($ch);
        }
        foreach (array_chunk($journalRows, 100) as $ch) {
            DB::table('tr_journal')->insert($ch);
        }
        foreach (array_chunk($journalDetailRows, 200) as $ch) {
            DB::table('tr_journal_detail')->insert($ch);
        }

        $this->command->info("✓ tr_invoice ($iSeq) + tr_payment ($pSeq) + tr_refund ($rSeq)");
        $this->command->info("✓ tr_journal ($jSeq) + tr_journal_detail (".count($journalDetailRows).') — balanced');
    }

    protected function seedUserLogs(): void
    {
        $menus = [
            ['create', 'rental.create', 'Membuat sewa baru melalui wizard'],
            ['update', 'rental.detail', 'Memperbarui data transaksi sewa'],
            ['update', 'rental.detail', 'Konfirmasi penjemputan sewa'],
            ['create', 'fleet.damage', 'Menambahkan laporan kerusakan'],
            ['update', 'fleet.damage', 'Mengubah status perbaikan'],
            ['create', 'fleet.insurance-claim', 'Mengajukan klaim asuransi'],
            ['create', 'fleet.maintenance', 'Menjadwalkan maintenance kendaraan'],
            ['update', 'fleet.maintenance', 'Menandai maintenance selesai'],
            ['create', 'finance.invoice', 'Menerbitkan invoice sewa'],
            ['update', 'finance.fine', 'Menandai denda lunas'],
            ['create', 'finance.payment', 'Mencatat pembayaran pelanggan'],
            ['create', 'accounting.manual-journal', 'Posting jurnal manual'],
            ['update', 'system.settings', 'Memperbarui pengaturan umum'],
            ['delete', 'master.vehicle', 'Menghapus data unit kendaraan'],
        ];

        // SED-09: pakai user aktual (bukan rent angka) agar log valid bila
        // jumlah user berubah.
        $userIds = DB::table('users')->pluck('id')->all();
        if (! $userIds) {
            $this->command->warn('seedUserLogs: tidak ada user — log dilewati');

            return;
        }

        $rows = [];
        for ($i = 0; $i < 90; $i++) {
            [$action, $menu, $msg] = $this->pick($menus);
            $rows[] = [
                'user_id' => $this->pick($userIds),
                'action' => $action,
                'menu' => $menu,
                'message' => $msg,
                'created_at' => $this->now->copy()->subDays(random_int(0, 360))->setTime(random_int(7, 21), random_int(0, 59)),
                'updated_at' => $this->now,
            ];
        }
        foreach (array_chunk($rows, 50) as $ch) {
            DB::table('user_logs')->insert($ch);
        }
        $this->command->info('✓ user_logs (90)');
    }

    protected function seedVehicleHistories(): void
    {
        $rows = [];
        foreach ($this->map['vehicles'] as $v) {
            if ($this->chance(30)) {
                $veh = DB::table('m_vehicle')->where('vehicle_id', $v['id'])->first();
                $current = $veh->location_id ?? $this->pick($this->map['locations']);
                $candidates = array_values(array_filter($this->map['locations'], fn ($id) => $id !== $current));
                $newLoc = $candidates ? $this->pick($candidates) : $current;
                if ($newLoc && $current) {
                    $rows[] = [
                        'vehicle_id' => $v['id'],
                        'from_location_id' => $current,
                        'to_location_id' => $newLoc,
                        'notes' => $this->pick(['Mutasi cabang operasional', 'Rotasi armada', 'Penyesuaian kebutuhan cabang', 'Pindah pool']),
                        'created_by' => $this->pick(array_values($this->map['employees'])),
                        'created_at' => $this->now->copy()->subDays(random_int(10, 200)),
                        'updated_at' => $this->now->copy()->subDays(random_int(10, 200)),
                    ];
                }
            }
        }
        if ($rows) {
            DB::table('vehicle_location_histories')->insert($rows);
        }
        $this->command->info('✓ vehicle_location_histories ('.count($rows).')');
    }

    protected function seedInspections(): void
    {
        $rows = [];
        // SED-08: odometer inspeksi konsisten dengan km unit dan
        // handover_in ≥ handover_out utk rental yang sama.
        $kmByVehicle = array_column($this->map['vehicles'], 'km', 'id');

        foreach ($this->map['rentalMeta'] as $meta) {
            $baseKm = $kmByVehicle[$meta['vehicle']] ?? random_int(8000, 48000);

            if ($this->chance(80)) {
                $outKm = max(500, $baseKm - random_int(200, 3000));
                $rows[] = [
                    'rental_id' => $meta['id'],
                    'vehicle_id' => $meta['vehicle'],
                    'inspection_type' => 'handover_out',
                    'odometer' => $outKm,
                    'fuel_level' => $this->pick(['full', 'three_quarter', 'half', 'quarter']),
                    'body_damage_points' => json_encode($this->chance(70) ? [] : [['x' => random_int(10, 90), 'y' => random_int(10, 90), 'note' => 'Baret halus']]),
                    'checklist' => json_encode(['stnk' => true, 'dongkrak' => true, 'ban_serep' => $this->chance(90), 'segitiga' => true, 'p3k' => true]),
                    'exterior_notes' => $this->chance(20) ? 'Baret kecil pintu kanan' : null,
                    'interior_notes' => null,
                    'notes' => 'Serah terima awal',
                    'created_by' => $this->pick(array_values($this->map['employees'])),
                    'created_at' => $meta['start']->copy()->subHours(1),
                    'updated_at' => $meta['start']->copy()->subHours(1),
                ];
            }
            if ($meta['status'] === 'completed' && $this->chance(70)) {
                $rows[] = [
                    'rental_id' => $meta['id'],
                    'vehicle_id' => $meta['vehicle'],
                    'inspection_type' => 'handover_in',
                    'odometer' => $baseKm + random_int(50, 2500),
                    'fuel_level' => $this->pick(['full', 'three_quarter', 'half', 'quarter', 'empty']),
                    'body_damage_points' => json_encode([]),
                    'checklist' => json_encode(['stnk' => true, 'dongkrak' => true, 'ban_serep' => true, 'segitiga' => true, 'p3k' => true]),
                    'exterior_notes' => null,
                    'interior_notes' => null,
                    'notes' => 'Pengembalian diperiksa',
                    'created_by' => $this->pick(array_values($this->map['employees'])),
                    'created_at' => $meta['end']->copy()->addHours(1),
                    'updated_at' => $meta['end']->copy()->addHours(1),
                ];
            }
        }
        if ($rows) {
            DB::table('rental_inspections')->insert($rows);
        }
        $this->command->info('✓ rental_inspections ('.count($rows).')');
    }

    /* =========================================================
       UTIL
    ========================================================= */

    protected function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    protected function chance(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }

    protected function weightedPick(array $weights): mixed
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        foreach ($weights as $key => $w) {
            $rand -= $w;
            if ($rand <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    protected function weightedDays(): int
    {
        return (int) $this->weightedPick([2 => 18, 3 => 24, 4 => 20, 5 => 14, 6 => 8, 7 => 8, 8 => 4, 9 => 2, 12 => 1, 14 => 1]);
    }
}
