<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    protected $table = 'user_logs';

    protected $fillable = [
        'user_id',
        'action',
        'menu',
        'message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Kamus aksi kanonis (lowercase Inggris). Semua penulisan dinormalisasi
     * di sini sehingga filter exact-match selalu cocok apa pun casing/label
     * lama pengirimnya (LogHelper: 'Penambahan', seeder: 'Login', dsb.).
     */
    public static function normalizeAction(mixed $value): string
    {
        $v = strtolower(trim((string) $value));

        return match ($v) {
            'add', 'tambah', 'penambahan', 'create' => 'create',
            'edit', 'ubah', 'perubahan', 'update' => 'update',
            'hapus', 'penghapusan', 'delete' => 'delete',
            'login', 'logout', 'export',
            'approve', 'approve1', 'approve2', 'approve3',
            'reject', 'submit', 'verifikasi', 'verify' => $v === 'verifikasi' ? 'verify' : $v,
            default => $v,
        };
    }

    public function setActionAttribute(mixed $value): void
    {
        $this->attributes['action'] = $value === null ? null : self::normalizeAction($value);
    }
}
