<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 12 SQL views untuk seluruh menu list.
     */
    private array $views = [

        // 6.1 Daftar Armada
        'v_vehicle_list' => "
            CREATE VIEW v_vehicle_list AS
            SELECT
                v.vehicle_id,
                v.license_plate,
                CONCAT(b.brand_name, ' ', vm.model_name) AS model_full,
                v.color,
                v.year,
                v.mileage,
                v.status,
                v.photo_url,
                l.location_name AS current_location,
                DATE_FORMAT(v.created_at, '%d/%m/%Y') AS created_date
            FROM m_vehicle v
            LEFT JOIN m_vehicle_model vm ON v.model_id = vm.model_id
            LEFT JOIN m_brand b ON vm.brand_id = b.brand_id
            LEFT JOIN m_location l ON 1 = 1
        ",

        // 6.2 Daftar Pelanggan
        'v_customer_list' => "
            CREATE VIEW v_customer_list AS
            SELECT
                c.customer_id,
                CONCAT(c.first_name, ' ', c.last_name) AS full_name,
                c.customer_type,
                c.email,
                c.phone,
                c.driver_license_number,
                c.is_verified,
                (SELECT COUNT(*) FROM tr_rental WHERE customer_id = c.customer_id) AS total_transactions,
                DATE_FORMAT(c.created_at, '%d/%m/%Y') AS registered_date
            FROM m_customer c
        ",

        // 6.3 Sewa Aktif / Ongoing
        'v_rental_active_list' => "
            CREATE VIEW v_rental_active_list AS
            SELECT
                r.rental_id,
                r.rental_code,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.phone AS customer_phone,
                v.license_plate AS vehicle_plate,
                CONCAT(b.brand_name, ' ', vm.model_name) AS vehicle_model,
                r.rental_start_date,
                r.rental_end_date,
                DATEDIFF(NOW(), r.rental_end_date) AS days_overdue,
                r.total_amount,
                r.payment_status,
                r.status,
                CASE
                    WHEN r.status = 'overdue' THEN 'OVERDUE'
                    WHEN r.status = 'ongoing' THEN 'ONGOING'
                    ELSE r.status
                END AS display_status
            FROM tr_rental r
            JOIN m_customer c ON r.customer_id = c.customer_id
            JOIN m_vehicle v ON r.vehicle_id = v.vehicle_id
            LEFT JOIN m_vehicle_model vm ON v.model_id = vm.model_id
            LEFT JOIN m_brand b ON vm.brand_id = b.brand_id
            WHERE r.status IN ('ongoing', 'overdue')
            ORDER BY r.rental_end_date ASC
        ",

        // 6.4 Reservasi
        'v_rental_reserved_list' => "
            CREATE VIEW v_rental_reserved_list AS
            SELECT
                r.rental_id,
                r.rental_code,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                v.license_plate AS vehicle_plate,
                r.rental_start_date,
                r.rental_end_date,
                DATEDIFF(r.rental_end_date, r.rental_start_date) AS duration_days,
                r.deposit_amount,
                r.notes
            FROM tr_rental r
            JOIN m_customer c ON r.customer_id = c.customer_id
            JOIN m_vehicle v ON r.vehicle_id = v.vehicle_id
            WHERE r.status = 'reserved'
            ORDER BY r.rental_start_date ASC
        ",

        // 6.5 Arsip Transaksi
        'v_rental_archive_list' => "
            CREATE VIEW v_rental_archive_list AS
            SELECT
                r.rental_id,
                r.rental_code,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                v.license_plate AS vehicle_plate,
                r.rental_start_date,
                r.rental_end_date,
                r.actual_return_date,
                r.total_amount,
                r.status,
                CASE
                    WHEN r.status = 'completed' THEN 'Selesai'
                    WHEN r.status = 'cancelled' THEN 'Dibatalkan'
                    ELSE r.status
                END AS display_status
            FROM tr_rental r
            JOIN m_customer c ON r.customer_id = c.customer_id
            JOIN m_vehicle v ON r.vehicle_id = v.vehicle_id
            WHERE r.status IN ('completed', 'cancelled')
            ORDER BY r.actual_return_date DESC
        ",

        // 6.6 Jadwal Maintenance
        'v_maintenance_list' => "
            CREATE VIEW v_maintenance_list AS
            SELECT
                m.maintenance_id,
                v.license_plate,
                CONCAT(b.brand_name, ' ', vm.model_name) AS vehicle_model,
                mt.type_name AS maintenance_type,
                m.scheduled_date,
                m.current_mileage,
                mt.interval_km AS target_km,
                m.status,
                m.cost AS estimated_cost,
                w.name AS workshop_name
            FROM tr_maintenance m
            JOIN m_vehicle v ON m.vehicle_id = v.vehicle_id
            JOIN m_vehicle_model vm ON v.model_id = vm.model_id
            JOIN m_brand b ON vm.brand_id = b.brand_id
            JOIN m_maintenance_type mt ON m.maintenance_type_id = mt.type_id
            LEFT JOIN m_workshop w ON m.workshop_id = w.workshop_id
            ORDER BY m.scheduled_date ASC
        ",

        // 6.7 Laporan Kerusakan
        'v_damage_list' => "
            CREATE VIEW v_damage_list AS
            SELECT
                d.damage_id,
                r.rental_code,
                v.license_plate,
                d.severity,
                d.damage_type,
                d.description,
                d.repair_cost_estimate,
                d.status,
                CONCAT(e.first_name, ' ', e.last_name) AS inspector_name,
                DATE_FORMAT(d.reported_date, '%d/%m/%Y') AS reported_date
            FROM tr_damage_report d
            JOIN tr_rental r ON d.rental_id = r.rental_id
            JOIN m_vehicle v ON d.vehicle_id = v.vehicle_id
            LEFT JOIN m_employee e ON d.inspected_by = e.employee_id
            ORDER BY d.reported_date DESC
        ",

        // 6.8 Daftar Invoice
        'v_invoice_list' => "
            CREATE VIEW v_invoice_list AS
            SELECT
                i.invoice_id,
                i.invoice_number,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                i.issue_date,
                i.due_date,
                i.total_amount,
                i.paid_amount,
                (i.total_amount - i.paid_amount) AS remaining_amount,
                i.status,
                r.rental_code
            FROM tr_invoice i
            JOIN tr_rental r ON i.rental_id = r.rental_id
            JOIN m_customer c ON r.customer_id = c.customer_id
            ORDER BY i.due_date ASC
        ",

        // 6.9 Denda Belum Lunas
        'v_fine_list' => "
            CREATE VIEW v_fine_list AS
            SELECT
                f.fine_id,
                r.rental_code,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                f.fine_type,
                f.description,
                f.amount,
                f.status,
                DATE_FORMAT(f.issued_date, '%d/%m/%Y') AS issued_date,
                CONCAT(e.first_name, ' ', e.last_name) AS issued_by_name
            FROM tr_fine f
            JOIN tr_rental r ON f.rental_id = r.rental_id
            JOIN m_customer c ON r.customer_id = c.customer_id
            LEFT JOIN m_employee e ON f.issued_by = e.employee_id
            WHERE f.status = 'unpaid'
            ORDER BY f.issued_date DESC
        ",

        // 6.10 Manajemen Promo
        'v_promo_list' => "
            CREATE VIEW v_promo_list AS
            SELECT
                promo_id,
                promo_code,
                description,
                discount_type,
                discount_value,
                CONCAT(DATE_FORMAT(valid_from, '%d/%m/%Y'), ' - ', DATE_FORMAT(valid_to, '%d/%m/%Y')) AS valid_period,
                CONCAT(usage_count, '/', max_usage) AS `usage`,
                is_active
            FROM m_promo
            ORDER BY valid_from DESC
        ",

        // 6.11 Jurnal Umum
        'v_journal_list' => "
            CREATE VIEW v_journal_list AS
            SELECT
                j.journal_id,
                j.reference_number,
                j.transaction_date,
                j.description,
                j.journal_type,
                CONCAT(e.first_name, ' ', e.last_name) AS created_by_name,
                (SELECT SUM(debit) FROM tr_journal_detail WHERE journal_id = j.journal_id) AS total_debit,
                (SELECT SUM(credit) FROM tr_journal_detail WHERE journal_id = j.journal_id) AS total_credit
            FROM tr_journal j
            LEFT JOIN m_employee e ON j.created_by = e.employee_id
            ORDER BY j.transaction_date DESC
        ",

        // 6.12 Daftar Sopir
        'v_driver_list' => "
            CREATE VIEW v_driver_list AS
            SELECT
                d.driver_id,
                CONCAT(d.first_name, ' ', d.last_name) AS full_name,
                d.license_number,
                d.license_expiry,
                CASE
                    WHEN d.license_expiry < CURDATE() THEN 'EXPIRED'
                    WHEN d.license_expiry < DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'EXPIRING_SOON'
                    ELSE 'VALID'
                END AS license_status,
                d.phone,
                d.is_active,
                (SELECT COUNT(*) FROM tr_rental WHERE driver_id = d.driver_id) AS total_tasks
            FROM m_driver d
        ",
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->views as $name => $sql) {
            DB::statement("DROP VIEW IF EXISTS `{$name}`");
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys($this->views) as $name) {
            DB::statement("DROP VIEW IF EXISTS `{$name}`");
        }
    }
};