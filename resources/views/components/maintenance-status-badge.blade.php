@php
    $map = [
        'scheduled' => ['label' => 'Terjadwal', 'class' => 'bg-label-info'],
        'overdue' => ['label' => 'Terlambat', 'class' => 'bg-label-danger'],
        'in_progress' => ['label' => 'Proses', 'class' => 'bg-label-warning'],
        'completed' => ['label' => 'Selesai', 'class' => 'bg-label-success'],
        'cancelled' => ['label' => 'Batal', 'class' => 'bg-label-secondary'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>