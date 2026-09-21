<a href="{{ route('fleet.damage.show', $item->damage_id) }}" class="action-link-icon-text btnShow">
    <i class="ri-search-line"></i>
    <span class="fw-semibold text-uppercase">SHOW</span>
</a>

@can('fleet_claim_manage')
    @if (!$item->insuranceClaim && !in_array($item->status, ['closed', 'rejected']))
        <a href="{{ route('fleet.insurance-claim.create') }}?damage_id={{ $item->damage_id }}"
            class="action-link-icon-text btnClaim">
            <i class="ri-shield-check-line"></i>
            <span class="fw-semibold text-uppercase">AJUKAN KLAIM</span>
        </a>
    @endif

    @if ($item->insuranceClaim)
        <a href="{{ route('fleet.insurance-claim.show', $item->insuranceClaim->claim_id) }}"
            class="action-link-icon-text btnClaimShow">
            <i class="ri-shield-line"></i>
            <span class="fw-semibold text-uppercase">LIHAT KLAIM</span>
        </a>
    @endif
@endcan
