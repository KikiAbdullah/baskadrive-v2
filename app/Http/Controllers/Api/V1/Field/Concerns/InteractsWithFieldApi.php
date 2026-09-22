<?php

namespace App\Http\Controllers\Api\V1\Field\Concerns;

use App\Models\ApiIdempotencyKey;
use App\Models\Rental;
use App\Models\UserLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Helper bersama untuk controller API mobile `/api/v1/field`.
 *
 * Kontrak Lampiran B (konsep BaskaDrive Operator):
 * - Envelope `{status, msg, data}` via `responseSuccess()/responseFailed()`.
 * - Idempotency `client_uuid`: duplikat → 409 ALREADY_PROCESSED.
 * - Audit `user_logs` dengan metadata mobile (source/device/app).
 * - Pemetaan exception → kode error khusus mobile (B.7).
 */
trait InteractsWithFieldApi
{
    /**
     * Eksekusi aksi tulis dengan idempotency `client_uuid`.
     *
     * Bila `client_uuid` pernah diproses (user+endpoint sama), respons hasil
     * pertama dikembalikan lagi dengan HTTP 409 + kode ALREADY_PROCESSED —
     * mobile memperlakukan ini sukses (konsep B.7).
     *
     * @param  callable(): array  $action  mengembalikan payload `data` respons
     */
    protected function idempotent(Request $request, string $endpoint, callable $action): JsonResponse
    {
        $clientUuid = (string) $request->input('client_uuid', '');

        if ($clientUuid !== '') {
            $existing = ApiIdempotencyKey::where('client_uuid', $clientUuid)
                ->where('endpoint', $endpoint)
                ->first();

            if ($existing) {
                // Duplikat = sukses secara bisnis (kontrak B.7): payload hasil
                // pertama + kode ALREADY_PROCESSED agar mobile anggap selesai.
                return response()->json(
                    array_merge(responseSuccess($existing->result ?? [], 'Sudah diproses sebelumnya.'), ['code' => 'ALREADY_PROCESSED']),
                    409,
                    ['X-Idempotent-Replayed' => '1', 'X-Error-Code' => 'ALREADY_PROCESSED'],
                );
            }
        }

        try {
            $data = $action();

            if ($clientUuid !== '') {
                ApiIdempotencyKey::create([
                    'client_uuid' => $clientUuid,
                    'user_id' => $request->user()?->id,
                    'endpoint' => $endpoint,
                    'http_status' => '200',
                    'result' => is_array($data) ? $data : null,
                    'created_at' => now(),
                ]);
            }

            return response()->json(responseSuccess($data, 'Berhasil disimpan.'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);

            throw $this->toFieldApiException($e);
        }
    }

    /** Petakan exception domain ke HTTP + kode khusus mobile (konsep B.7). */
    protected function toFieldApiException(Exception $e): FieldApiException
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'tutup buku') || str_contains($msg, 'Periode terkunci')) {
            return new FieldApiException($msg, 423, 'PERIOD_CLOSED');
        }
        if (str_contains($msg, 'melebihi sisa tagihan') || str_contains($msg, 'melebihi deposit')) {
            return new FieldApiException($msg, 422, 'VALIDATION_ERROR');
        }

        return new FieldApiException($msg, 422, 'VALIDATION_ERROR');
    }

    /** Respons sukses dengan header kode error (untuk alur non-idempotent). */
    protected function ok(array $data, string $msg = 'Berhasil disimpan.'): JsonResponse
    {
        return response()->json(responseSuccess($data, $msg));
    }

    /** Audit aksi mobile ke `user_logs` (jangan pernah gagalkan aksi utama). */
    protected function audit(Request $request, string $action, string $menu, string $message): void
    {
        try {
            UserLog::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'menu' => $menu,
                'message' => sprintf(
                    '%s [mobile] device=%s app=%s',
                    $message,
                    $request->header('X-Device-Id', '-'),
                    $request->header('X-App-Version', '-'),
                ),
            ]);
        } catch (\Throwable) {
            // Logging tidak boleh menggagalkan transaksi bisnis.
        }
    }

    /** Pastikan sewa dalam status yang diharapkan; lainnya → konflik 409. */
    protected function rentalInStatus(Rental $rental, array $statuses): Rental
    {
        if (! in_array($rental->status, $statuses, true)) {
            throw new FieldApiException(
                'Status sewa '.$rental->rental_code.' kini "'.$rental->status.'" — data perangkat mungkin kedaluwarsa. Muat ulang tugas.',
                409,
                'RENTAL_STATE_CHANGED',
            );
        }

        return $rental;
    }
}
