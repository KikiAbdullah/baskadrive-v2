<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RentalErpMasterSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 5.1 Merek
        DB::table('m_brand')->insertOrIgnore([
            ['brand_name' => 'Toyota', 'created_at' => $now],
            ['brand_name' => 'Honda', 'created_at' => $now],
            ['brand_name' => 'Suzuki', 'created_at' => $now],
            ['brand_name' => 'Mitsubishi', 'created_at' => $now],
            ['brand_name' => 'Daihatsu', 'created_at' => $now],
            ['brand_name' => 'Nissan', 'created_at' => $now],
            ['brand_name' => 'Hyundai', 'created_at' => $now],
            ['brand_name' => 'Kia', 'created_at' => $now],
        ]);

        // 5.2 Model kendaraan
        DB::table('m_vehicle_model')->insertOrIgnore([
            ['brand_id' => 1, 'model_name' => 'Avanza', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 7, 'base_price_per_day' => 350000, 'insurance_rate' => 5.00, 'deposit_amount' => 500000, 'created_at' => $now],
            ['brand_id' => 1, 'model_name' => 'Innova', 'category' => 'MPV', 'fuel_type' => 'Diesel', 'transmission' => 'Automatic', 'seat_capacity' => 8, 'base_price_per_day' => 550000, 'insurance_rate' => 5.00, 'deposit_amount' => 750000, 'created_at' => $now],
            ['brand_id' => 2, 'model_name' => 'Brio', 'category' => 'Hatchback', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 5, 'base_price_per_day' => 250000, 'insurance_rate' => 4.00, 'deposit_amount' => 350000, 'created_at' => $now],
            ['brand_id' => 2, 'model_name' => 'CR-V', 'category' => 'SUV', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'seat_capacity' => 5, 'base_price_per_day' => 650000, 'insurance_rate' => 6.00, 'deposit_amount' => 1000000, 'created_at' => $now],
            ['brand_id' => 3, 'model_name' => 'Ertiga', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'seat_capacity' => 7, 'base_price_per_day' => 380000, 'insurance_rate' => 5.00, 'deposit_amount' => 500000, 'created_at' => $now],
            ['brand_id' => 5, 'model_name' => 'Xenia', 'category' => 'MPV', 'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'seat_capacity' => 7, 'base_price_per_day' => 330000, 'insurance_rate' => 5.00, 'deposit_amount' => 450000, 'created_at' => $now],
        ]);

        // 5.3 Unit mobil
        DB::table('m_vehicle')->insertOrIgnore([
            ['license_plate' => 'B 1234 ABC', 'vin' => 'MH1XX1234567890X', 'model_id' => 1, 'color' => 'Putih', 'year' => 2022, 'mileage' => 25000, 'status' => 'available', 'purchase_date' => '2022-01-15', 'purchase_price' => 250000000, 'current_value' => 210000000, 'created_at' => $now, 'updated_at' => $now],
            ['license_plate' => 'B 5678 DEF', 'vin' => 'MH1XX9876543210Y', 'model_id' => 2, 'color' => 'Hitam', 'year' => 2023, 'mileage' => 12000, 'status' => 'available', 'purchase_date' => '2023-03-10', 'purchase_price' => 380000000, 'current_value' => 350000000, 'created_at' => $now, 'updated_at' => $now],
            ['license_plate' => 'B 9012 GHI', 'vin' => 'MH1XX5555555555Z', 'model_id' => 3, 'color' => 'Merah', 'year' => 2021, 'mileage' => 35000, 'status' => 'rented', 'purchase_date' => '2021-07-20', 'purchase_price' => 180000000, 'current_value' => 150000000, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 5.4 Lokasi cabang
        DB::table('m_location')->insertOrIgnore([
            ['location_name' => 'Cabang Jakarta', 'address' => 'Jl. Sudirman No.1', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'contact_phone' => '021-1234567', 'created_at' => $now],
            ['location_name' => 'Cabang Bandung', 'address' => 'Jl. Asia Afrika No.2', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'contact_phone' => '022-7654321', 'created_at' => $now],
        ]);

        // 5.5 Tipe maintenance
        DB::table('m_maintenance_type')->insertOrIgnore([
            ['type_name' => 'Ganti Oli', 'interval_km' => 5000, 'interval_months' => 6, 'description' => 'Ganti oli mesin dan filter oli', 'created_at' => $now],
            ['type_name' => 'Tune Up', 'interval_km' => 10000, 'interval_months' => 12, 'description' => 'Penyetelan mesin, busi, filter udara', 'created_at' => $now],
            ['type_name' => 'Ganti Ban', 'interval_km' => 40000, 'interval_months' => 24, 'description' => 'Penggantian ban baru', 'created_at' => $now],
            ['type_name' => 'Servis Berkala', 'interval_km' => 15000, 'interval_months' => 12, 'description' => 'Servis menyeluruh sesuai jadwal pabrik', 'created_at' => $now],
        ]);

        $this->command->info('RentalErpMasterSeeder selesai: brand, model, vehicle, location, maintenance type.');
    }
}