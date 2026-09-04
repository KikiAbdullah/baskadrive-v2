@php
    $map = [
        'unpaid' => ['label' => 'Belum Bayar', 'class' => 'bg-label-danger'],
        'paid' => ['label' => 'Lunas', 'class' => 'bg-label-success'],
        'waived' => ['label' => 'Dibebaskan', 'class' => 'bg-label-secondary'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
