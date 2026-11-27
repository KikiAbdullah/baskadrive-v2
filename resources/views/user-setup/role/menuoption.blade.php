@if ($role->name !== 'SUPERADMIN')
    <a href="{{ route('user-setup.role.edit', $role->id) }}" onclick="editRole(this, event)" class="role-edit-modal">
        Edit Role
    </a>

    @can('roles_delete')
        <form method="POST" action="{{ route('user-setup.role.destroy', $role->id) }}" class="delete" style="display: contents">
            @csrf
            @method('DELETE')
            <a href="#" class="text-danger deleteBtn">
                <i class="ri-close-circle-line"></i>
            </a>
        </form>
    @endcan
@endif
