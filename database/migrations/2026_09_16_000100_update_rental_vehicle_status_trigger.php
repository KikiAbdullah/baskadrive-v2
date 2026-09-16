<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit sewa §9: menutup celah trigger status kendaraan.
 * trg_rental_after_update_vehicle_status sebelumnya hanya menangani
 * ongoing & completed/cancelled. Kini ikut menangani:
 *   - reserved            -> kendaraan 'reserved'
 *   - overdue             -> kendaraan TETAP 'rented' (tidak dibebaskan)
 * Transition guard (NOT IN) mencegah penulisan ulang yang tidak perlu
 * (mis. ongoing->overdue tidak menyentuh kendaraan, completed->completed diabaikan).
 */
return new class extends Migration
{
    private const NEW_BODY = "
        BEGIN
            IF NEW.status = 'reserved' AND OLD.status <> 'reserved' THEN
                UPDATE m_vehicle SET status = 'reserved' WHERE vehicle_id = NEW.vehicle_id;
            END IF;

            IF NEW.status IN ('ongoing', 'overdue') AND OLD.status NOT IN ('ongoing', 'overdue') THEN
                UPDATE m_vehicle SET status = 'rented' WHERE vehicle_id = NEW.vehicle_id;
            END IF;

            IF NEW.status IN ('completed', 'cancelled') AND OLD.status NOT IN ('completed', 'cancelled') THEN
                UPDATE m_vehicle SET status = 'available' WHERE vehicle_id = NEW.vehicle_id;
            END IF;
        END
    ";

    private const OLD_BODY = "
        BEGIN
            IF NEW.status = 'ongoing' AND OLD.status != 'ongoing' THEN
                UPDATE m_vehicle SET status = 'rented' WHERE vehicle_id = NEW.vehicle_id;
            END IF;

            IF NEW.status IN ('completed', 'cancelled') AND OLD.status != 'completed' THEN
                UPDATE m_vehicle SET status = 'available' WHERE vehicle_id = NEW.vehicle_id;
            END IF;
        END
    ";

    public function up(): void
    {
        $this->recreate(self::NEW_BODY);
    }

    public function down(): void
    {
        $this->recreate(self::OLD_BODY);
    }

    private function recreate(string $body): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_rental_after_update_vehicle_status');
        DB::statement("
            CREATE TRIGGER trg_rental_after_update_vehicle_status
            AFTER UPDATE ON tr_rental
            FOR EACH ROW
            {$body}
        ");
    }
};
