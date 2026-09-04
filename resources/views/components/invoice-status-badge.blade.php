@php
    $map = [
        'draft' => ['label' => 'Draft', 'class' => 'bg-label-secondary'],
        'sent' => ['label' => 'Terkirim', 'class' => 'bg-label-info'],
        'partially_paid' => ['label' => 'Sebagian', 'class' => 'bg-label-warning'],
        'paid' => ['label' => 'Lunas', 'class' => 'bg-label-success'],
        'cancelled' => ['label' => 'Batal', 'class' => 'bg-label-dark'],
        'overdue' => ['label' => 'Jatuh Tempo', 'class' => 'bg-label-danger'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
