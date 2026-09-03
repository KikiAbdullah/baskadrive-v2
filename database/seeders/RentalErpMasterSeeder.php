<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Location;
use App\Models\MaintenanceType;
use App\Models\Promo;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RentalErpMasterSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ===========================================
        // 1. MEREK
        // ===========================================
        $brands = ['Toyota', 'Honda', 'Suzuki', 'Mitsubishi', 'Daihatsu', 'Nissan', 'Hyundai', 'Kia'];
        $brandMap = [];
        foreach ($brands as $name) {
            $brand = Brand::firstOrCreate(['brand_name' => $name]);
            $brandMap[$name] = $brand->brand_id;
        }

        // ===========================================
        // 2. MODEL KENDARAAN
        // ===========================================
        $models = [
            ['brand_name' => 'Toyota', 'model_name' => 'Avanza', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 7, 'price' => 350000, 'insurance' => 5.00, 'deposit' => 500000],
            ['brand_name' => 'Toyota', 'model_name' => 'Innova', 'category' => 'MPV', 'fuel_type' => 'Diesel', 'transmission' => 'Automatic', 'seat_capacity' => 8, 'price' => 550000, 'insurance' => 5.00, 'deposit' => 750000],
            ['brand_name' => 'Toyota', 'model_name' => 'Fortuner', 'category' => 'SUV', 'fuel_type' => 'Diesel', 'transmission' => 'Automatic', 'seat_capacity' => 7, 'price' => 1200000, 'insurance' => 7.00, 'deposit' => 1500000],
            ['brand_name' => 'Honda', 'model_name' => 'Brio', 'category' => 'Hatchback', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 5, 'price' => 250000, 'insurance' => 4.00, 'deposit' => 350000],
            ['brand_name' => 'Honda', 'model_name' => 'CR-V', 'category' => 'SUV', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'seat_capacity' => 5, 'price' => 650000, 'insurance' => 6.00, 'deposit' => 1000000],
            ['brand_name' => 'Suzuki', 'model_name' => 'Ertiga', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 7, 'price' => 380000, 'insurance' => 5.00, 'deposit' => 500000],
            ['brand_name' => 'Daihatsu', 'model_name' => 'Xenia', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'seat_capacity' => 7, 'price' => 330000, 'insurance' => 5.00, 'deposit' => 450000],
            ['brand_name' => 'Mitsubishi', 'model_name' => 'Pajero Sport', 'category' => 'SUV', 'fuel_type' => 'Diesel', 'transmission' => 'Automatic', 'seat_capacity' => 7, 'price' => 1350000, 'insurance' => 7.00, 'deposit' => 2000000],
        ];

        $modelMap = [];
        foreach ($models as $m) {
            $model = VehicleModel::firstOrCreate(
                ['model_name' => $m['model_name']],
                [
                    'brand_id' => $brandMap[$m['brand_name']],
                    'category' => $m['category'],
                    'fuel_type' => $m['fuel_type'],
                    'transmission' => $m['transmission'],
                    'seat_capacity' => $m['seat_capacity'],
                    'base_price_per_day' => $m['price'],
                    'insurance_rate' => $m['insurance'],
                    'deposit_amount' => $m['deposit'],
                    'is_active' => true,
                ]
            );
            $modelMap[$m['model_name']] = $model->model_id;
        }

        // ===========================================
        // 3. UNIT KENDARAAN
        // ===========================================
        $vehicles = [
            ['plate' => 'B 1234 ABC', 'vin' => 'MH1XX1234567890X', 'model' => 'Avanza', 'color' => 'Putih', 'year' => 2022, 'km' => 25000, 'status' => 'available'],
            ['plate' => 'B 5678 DEF', 'vin' => 'MH1XX9876543210Y', 'model' => 'Innova', 'color' => 'Hitam', 'year' => 2023, 'km' => 12000, 'status' => 'available'],
            ['plate' => 'B 9012 GHI', 'vin' => 'MH1XX5555555555Z', 'model' => 'Brio', 'color' => 'Merah', 'year' => 2021, 'km' => 35000, 'status' => 'rented'],
            ['plate' => 'B 3456 JKL', 'vin' => 'MH1XX7777777777W', 'model' => 'Fortuner', 'color' => 'Abu-abu', 'year' => 2023, 'km' => 8000, 'status' => 'available'],
            ['plate' => 'B 7890 MNO', 'vin' => 'MH1XX3333333333V', 'model' => 'Ertiga', 'color' => 'Silver', 'year' => 2022, 'km' => 28000, 'status' => 'maintenance'],
            ['plate' => 'B 2468 PQR', 'vin' => 'MH1XX4444444444U', 'model' => 'CR-V', 'color' => 'Hitam', 'year' => 2022, 'km' => 18000, 'status' => 'reserved'],
            ['plate' => 'B 1357 STU', 'vin' => 'MH1XX6666666666T', 'model' => 'Xenia', 'color' => 'Putih', 'year' => 2021, 'km' => 42000, 'status' => 'available'],
        ];

        foreach ($vehicles as $v) {
            Vehicle::firstOrCreate(
                ['license_plate' => $v['plate']],
                [
                    'vin' => $v['vin'],
                    'model_id' => $modelMap[$v['model']],
                    'color' => $v['color'],
                    'year' => $v['year'],
                    'mileage' => $v['km'],
                    'status' => $v['status'],
                    'purchase_date' => Carbon::create($v['year'] - 1, 6, 15)->toDateString(),
                    'purchase_price' => $v['model'] === 'Fortuner' || $v['model'] === 'Pajero Sport' ? 600000000 : 250000000,
                    'current_value' => 200000000,
                    'notes' => null,
                ]
            );
        }

        // ===========================================
        // 4. PELANGGAN
        // ===========================================
        $customers = [
            ['type' => 'individual', 'first' => 'Andi', 'last' => 'Pratama', 'company' => null, 'email' => 'andi.pratama@mail.com', 'phone' => '081234567801', 'license' => 'SIM-001', 'idcard' => '3171010101900001'],
            ['type' => 'individual', 'first' => 'Budi', 'last' => 'Santoso', 'company' => null, 'email' => 'budi.santoso@mail.com', 'phone' => '081234567802', 'license' => 'SIM-002', 'idcard' => '3273010101900002'],
            ['type' => 'individual', 'first' => 'Citra', 'last' => 'Dewi', 'company' => null, 'email' => 'citra.dewi@mail.com', 'phone' => '081234567803', 'license' => 'SIM-003', 'idcard' => '3374010101900003'],
            ['type' => 'individual', 'first' => 'Doni', 'last' => 'Kusuma', 'company' => null, 'email' => 'doni.kusuma@mail.com', 'phone' => '081234567804', 'license' => 'SIM-004', 'idcard' => '3173010101900004'],
            ['type' => 'corporate', 'first' => 'PT', 'last' => 'Maju Jaya', 'company' => 'PT Maju Jaya', 'email' => 'corp@majujaya.com', 'phone' => '021234567805', 'license' => 'SIM-005', 'idcard' => null],
            ['type' => 'corporate', 'first' => 'CV', 'last' => 'Karya Abadi', 'company' => 'CV Karya Abadi', 'email' => 'corp@karyaabadi.com', 'phone' => '021234567806', 'license' => 'SIM-006', 'idcard' => null],
            ['type' => 'individual', 'first' => 'Eka', 'last' => 'Wijaya', 'company' => null, 'email' => 'eka.wijaya@mail.com', 'phone' => '081234567807', 'license' => 'SIM-007', 'idcard' => '3175010101900007'],
            ['type' => 'individual', 'first' => 'Fitri', 'last' => 'Handayani', 'company' => null, 'email' => 'fitri.handayani@mail.com', 'phone' => '081234567808', 'license' => 'SIM-008', 'idcard' => '3578010101900008'],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(
                ['driver_license_number' => $c['license']],
                [
                    'customer_type' => $c['type'],
                    'first_name' => $c['first'],
                    'last_name' => $c['last'],
                    'company_name' => $c['company'],
                    'email' => $c['email'],
                    'phone' => $c['phone'],
                    'address' => 'Jl. Contoh No. 12, Kel. Contoh, Kec. Contoh',
                    'city' => 'Jakarta Selatan',
                    'province' => 'DKI Jakarta',
                    'postal_code' => '12190',
                    'country' => 'Indonesia',
                    'driver_license_expiry' => Carbon::now()->addYears(3)->toDateString(),
                    'id_card_number' => $c['idcard'],
                    'date_of_birth' => Carbon::create(1990, 1, 1)->toDateString(),
                    'is_verified' => true,
                    'notes' => null,
                ]
            );
        }

        // ===========================================
        // 5. SOPIR
        // ===========================================
        $drivers = [
            ['first' => 'Slamet', 'last' => 'Riyadi', 'license' => 'DRV-1001', 'phone' => '085711112221'],
            ['first' => 'Joko', 'last' => 'Susilo', 'license' => 'DRV-1002', 'phone' => '085711112222'],
            ['first' => 'Bambang', 'last' => 'Pamungkas', 'license' => 'DRV-1003', 'phone' => '085711112223'],
            ['first' => 'Agus', 'last' => 'Salim', 'license' => 'DRV-1004', 'phone' => '085711112224'],
        ];

        foreach ($drivers as $d) {
            Driver::firstOrCreate(
                ['license_number' => $d['license']],
                [
                    'first_name' => $d['first'],
                    'last_name' => $d['last'],
                    'license_expiry' => Carbon::now()->addYears(2)->toDateString(),
                    'phone' => $d['phone'],
                    'is_active' => true,
                    'notes' => null,
                ]
            );
        }

        // ===========================================
        // 6. LOKASI
        // ===========================================
        $locations = [
            ['name' => 'Cabang Jakarta', 'addr' => 'Jl. Sudirman No.1', 'city' => 'Jakarta', 'prov' => 'DKI Jakarta', 'phone' => '021-1234567'],
            ['name' => 'Cabang Bandung', 'addr' => 'Jl. Asia Afrika No.2', 'city' => 'Bandung', 'prov' => 'Jawa Barat', 'phone' => '022-7654321'],
        ];

        foreach ($locations as $l) {
            Location::firstOrCreate(
                ['location_name' => $l['name']],
                ['address' => $l['addr'], 'city' => $l['city'], 'province' => $l['prov'], 'contact_phone' => $l['phone'], 'is_active' => true]
            );
        }

        // ===========================================
        // 7. BENGKEL MITRA
        // ===========================================
        $workshops = [
            ['name' => 'Bengkel Auto Prima', 'addr' => 'Jl. Gatot Subroto No. 45', 'phone' => '021-8887776', 'contact' => 'Pak Heri'],
            ['name' => 'Bengkel Sentral Motor', 'addr' => 'Jl. Ahmad Yani No. 78', 'phone' => '022-7778889', 'contact' => 'Pak Rudi'],
            ['name' => 'Bengkel Anugerah Jaya', 'addr' => 'Jl. Pemuda No. 12', 'phone' => '024-5554443', 'contact' => 'Bu Sari'],
        ];

        foreach ($workshops as $w) {
            Workshop::firstOrCreate(
                ['name' => $w['name']],
                ['address' => $w['addr'], 'phone' => $w['phone'], 'contact_person' => $w['contact'], 'is_active' => true]
            );
        }

        // ===========================================
        // 8. TIPE MAINTENANCE
        // ===========================================
        $maintenanceTypes = [
            ['name' => 'Ganti Oli', 'km' => 5000, 'month' => 6, 'desc' => 'Ganti oli mesin dan filter oli'],
            ['name' => 'Tune Up', 'km' => 10000, 'month' => 12, 'desc' => 'Penyetelan mesin, busi, filter udara'],
            ['name' => 'Ganti Ban', 'km' => 40000, 'month' => 24, 'desc' => 'Penggantian ban baru'],
            ['name' => 'Servis Berkala', 'km' => 15000, 'month' => 12, 'desc' => 'Servis menyeluruh sesuai jadwal pabrik'],
            ['name' => 'Ganti Aki', 'km' => null, 'month' => 24, 'desc' => 'Penggantian aki mobil'],
        ];

        foreach ($maintenanceTypes as $mt) {
            MaintenanceType::firstOrCreate(
                ['type_name' => $mt['name']],
                ['interval_km' => $mt['km'], 'interval_months' => $mt['month'], 'description' => $mt['desc'], 'is_active' => true]
            );
        }

        // ===========================================
        // 9. PROMO
        // ===========================================
        $promos = [
            ['code' => 'WELCOME10', 'desc' => 'Diskon 10% untuk penyewa baru', 'type' => 'percentage', 'value' => 10.00, 'min_days' => 2, 'valid_from' => $now->copy()->subDays(30), 'valid_to' => $now->copy()->addDays(300), 'max_usage' => 100, 'usage' => 5],
            ['code' => 'SEWA3HARI', 'desc' => 'Diskon 100rb untuk sewa 3 hari', 'type' => 'fixed_amount', 'value' => 100000, 'min_days' => 3, 'valid_from' => $now->copy()->subDays(10), 'valid_to' => $now->copy()->addDays(90), 'max_usage' => 50, 'usage' => 12],
            ['code' => 'WEEKEND', 'desc' => 'Diskon 7% sewa weekend', 'type' => 'percentage', 'value' => 7.00, 'min_days' => 2, 'valid_from' => $now->copy()->subDays(15), 'valid_to' => $now->copy()->addDays(60), 'max_usage' => 80, 'usage' => 20],
            ['code' => 'KORPORAT', 'desc' => 'Diskon 15% untuk korporasi', 'type' => 'percentage', 'value' => 15.00, 'min_days' => 5, 'valid_from' => $now->copy()->subDays(45), 'valid_to' => $now->copy()->addDays(200), 'max_usage' => 30, 'usage' => 8],
        ];

        foreach ($promos as $p) {
            Promo::firstOrCreate(
                ['promo_code' => $p['code']],
                [
                    'description' => $p['desc'],
                    'discount_type' => $p['type'],
                    'discount_value' => $p['value'],
                    'min_rental_days' => $p['min_days'],
                    'valid_from' => $p['valid_from']->toDateString(),
                    'valid_to' => $p['valid_to']->toDateString(),
                    'max_usage' => $p['max_usage'],
                    'usage_count' => $p['usage'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info(
            'RentalErpMasterSeeder selesai: '.Brand::count().' brand, '.VehicleModel::count().' model, '
            .Vehicle::count().' vehicle, '.Customer::count().' customer, '.Driver::count().' driver, '
            .Location::count().' lokasi, '.Workshop::count().' workshop, '
            .MaintenanceType::count().' tipe maintenance, '.Promo::count().' promo.'
        );
    }
}