@php
    $map = [
        'reported' => ['label' => 'Laporan', 'class' => 'bg-label-info'],
        'inspected' => ['label' => 'Diinspeksi', 'class' => 'bg-label-warning'],
        'approved' => ['label' => 'Disetujui', 'class' => 'bg-label-primary'],
        'in_repair' => ['label' => 'Diperbaiki', 'class' => 'bg-label-warning'],
        'repaired' => ['label' => 'Selesai', 'class' => 'bg-label-success'],
        'rejected' => ['label' => 'Ditolak', 'class' => 'bg-label-secondary'],
        'closed' => ['label' => 'Tutup', 'class' => 'bg-label-dark'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>