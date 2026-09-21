@php
    // FLE-12: closed kini status klaim yang valid (enum DB diperluas).
    $map = [
        'draft' => ['label' => 'Draft', 'class' => 'bg-label-secondary'],
        'submitted' => ['label' => 'Diajukan', 'class' => 'bg-label-info'],
        'under_review' => ['label' => 'Review', 'class' => 'bg-label-warning'],
        'approved' => ['label' => 'Disetujui', 'class' => 'bg-label-success'],
        'rejected' => ['label' => 'Ditolak', 'class' => 'bg-label-danger'],
        'paid' => ['label' => 'Dibayar', 'class' => 'bg-label-primary'],
        'closed' => ['label' => 'Tutup', 'class' => 'bg-label-dark'],
    ];
    $badge = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'bg-label-secondary'];
@endphp
<span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
