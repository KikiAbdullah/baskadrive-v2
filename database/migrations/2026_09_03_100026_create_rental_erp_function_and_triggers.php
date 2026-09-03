<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        // 7.1 Fungsi kalkulasi total sewa
        DB::statement("DROP FUNCTION IF EXISTS fn_calculate_rental_total");

        DB::statement("
            CREATE FUNCTION fn_calculate_rental_total(
                p_base_rate_per_day DECIMAL(10,2),
                p_rental_days INT,
                p_insurance_rate DECIMAL(5,2),
                p_driver_fee DECIMAL(10,2),
                p_is_with_driver BOOLEAN,
                p_discount_amount DECIMAL(10,2),
                p_tax_rate DECIMAL(5,2)
            )
            RETURNS DECIMAL(12,2)
            DETERMINISTIC
            BEGIN
                DECLARE v_base_total DECIMAL(12,2);
                DECLARE v_insurance DECIMAL(10,2);
                DECLARE v_driver_total DECIMAL(10,2);
                DECLARE v_tax DECIMAL(10,2);
                DECLARE v_total DECIMAL(12,2);

                SET v_base_total = p_base_rate_per_day * p_rental_days;
                SET v_insurance = v_base_total * (p_insurance_rate / 100);
                SET v_driver_total = IF(p_is_with_driver, p_driver_fee * p_rental_days, 0);
                SET v_tax = (v_base_total + v_insurance + v_driver_total - p_discount_amount) * (p_tax_rate / 100);

                SET v_total = v_base_total + v_insurance + v_driver_total - p_discount_amount + v_tax;

                RETURN v_total;
            END
        ");

        // 7.2 Trigger: update status mobil saat transaksi berubah
        DB::statement("DROP TRIGGER IF EXISTS trg_rental_after_update_vehicle_status");

        DB::statement("
            CREATE TRIGGER trg_rental_after_update_vehicle_status
            AFTER UPDATE ON tr_rental
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'ongoing' AND OLD.status != 'ongoing' THEN
                    UPDATE m_vehicle SET status = 'rented' WHERE vehicle_id = NEW.vehicle_id;
                END IF;

                IF NEW.status IN ('completed', 'cancelled') AND OLD.status != 'completed' THEN
                    UPDATE m_vehicle SET status = 'available' WHERE vehicle_id = NEW.vehicle_id;
                END IF;
            END
        ");

        // 7.3 Trigger: hitung rental_days & total_amount sebelum insert
        DB::statement("DROP TRIGGER IF EXISTS trg_rental_before_insert");

        DB::statement("
            CREATE TRIGGER trg_rental_before_insert
            BEFORE INSERT ON tr_rental
            FOR EACH ROW
            BEGIN
                DECLARE v_days INT;
                SET v_days = DATEDIFF(NEW.rental_end_date, NEW.rental_start_date);
                SET NEW.rental_days = v_days;

                IF NEW.total_amount IS NULL THEN
                    SET NEW.total_amount = fn_calculate_rental_total(
                        NEW.base_rate_per_day,
                        v_days,
                        COALESCE(NEW.insurance_fee, 0),
                        COALESCE(NEW.driver_fee, 0),
                        NEW.is_with_driver,
                        COALESCE(NEW.discount_amount, 0),
                        11.00
                    );
                END IF;
            END
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("DROP TRIGGER IF EXISTS trg_rental_before_insert");
        DB::statement("DROP TRIGGER IF EXISTS trg_rental_after_update_vehicle_status");
        DB::statement("DROP FUNCTION IF EXISTS fn_calculate_rental_total");
    }
};