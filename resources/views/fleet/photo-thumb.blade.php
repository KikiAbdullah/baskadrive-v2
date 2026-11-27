@php
    $photoExists = \Illuminate\Support\Facades\Storage::disk('public')->exists($photo->photo_url);
@endphp

@if ($photoExists)
    <img src="{{ asset('storage/' . $photo->photo_url) }}" class="img-fluid rounded"
        alt="{{ $photo->caption ?: 'Foto bukti' }}"
        onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('d-none');">
    <div class="d-none d-flex flex-column align-items-center justify-content-center bg-label-secondary rounded text-muted"
        style="min-height:120px">
        <i class="ri-image-line ri-24px"></i>
        <small>Bukti foto tidak dapat ditampilkan</small>
    </div>
@else
    <div class="d-flex flex-column align-items-center justify-content-center bg-label-secondary rounded text-muted"
        style="min-height:120px">
        <i class="ri-image-line ri-24px"></i>
        <small>Bukti foto belum tersedia</small>
    </div>
@endif
