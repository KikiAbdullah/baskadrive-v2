@php
    // FLE-02: satu peta status kanonik bersama App\Models\DamageReport::STATUSES.
    $map = [
        'reported' => ['label' => 'Laporan', 'class' => 'bg-label-info'],
        'inspected' => ['label' => 'Diinspeksi', 'class' => 'bg-label-warning'],
        'assessment' => ['label' => 'Asesmen', 'class' => 'bg-label-info'],
        'approved' => ['label' => 'Disetujui', 'class' => 'bg-label-primary'],
        'in_repair' => ['label' => 'Dalam Perbaikan', 'class' => 'bg-label-warning'],
        'repair_in_progress' => ['label' => 'Dalam Perbaikan', 'class' => 'bg-label-warning'],
        'repaired' => ['label' => 'Selesai Diperbaiki', 'class' => 'bg-label-success'],
        'rejected' => ['label' => 'Ditolak', 'class' => 'bg-label-secondary'],
        'claimed_insurance' => ['label' => 'Klaim Asuransi', 'class' => 'bg-label-primary'],
        'written_off' => ['label' => 'Hilang (Total Loss)', 'class' => 'bg-label-danger'],
        'closed' => ['label' => 'Tutup', 'class' => 'bg-label-dark'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
