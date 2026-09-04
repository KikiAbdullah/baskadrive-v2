@php
    $map = [
        'reserved' => ['label' => 'Reservasi', 'class' => 'bg-label-warning'],
        'ongoing' => ['label' => 'Berjalan', 'class' => 'bg-label-info'],
        'completed' => ['label' => 'Selesai', 'class' => 'bg-label-success'],
        'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-label-secondary'],
        'overdue' => ['label' => 'Terlambat', 'class' => 'bg-label-danger'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>