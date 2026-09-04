@php
    $map = [
        'pending' => ['label' => 'Pending', 'class' => 'bg-label-warning'],
        'completed' => ['label' => 'Selesai', 'class' => 'bg-label-success'],
        'failed' => ['label' => 'Gagal', 'class' => 'bg-label-danger'],
        'refunded' => ['label' => 'Refund', 'class' => 'bg-label-info'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
