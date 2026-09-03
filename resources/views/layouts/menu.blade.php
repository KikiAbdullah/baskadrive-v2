<!-- Main navbar -->
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0">
    <div class="container-xxl d-flex h-100">
        <ul class="menu-inner">
            <!-- Dashboards -->
            <li class="menu-item {{ $title == 'Dashboard' ? 'active' : '' }}">
                <a href="{{ route('siteurl') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-home-smile-line"></i>
                    <div data-i18n="Dashboards">Dashboards</div>
                </a>
            </li>

            {{-- ========================================
                 SETUP
            ======================================== --}}
            @canany(['permissions_view', 'roles_view', 'users_view', 'debug_view'])
                <li class="menu-header mt-5">
                    <span class="menu-header-text" data-i18n="Setup">Setup</span>
                </li>
                <li class="menu-item {{ in_array($title, ['Permission', 'Role', 'User']) ? 'active open' : '' }}">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons ri-settings-3-line"></i>
                        <div data-i18n="Setup">Setup</div>
                    </a>
                    <ul class="menu-sub">
                        @can('permissions_view')
                            <li class="menu-item {{ $title == 'Permission' ? 'active' : '' }}">
                                <a href="{{ route('user-setup.permission.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-key-line"></i>
                                    <div data-i18n="Permission">Permission</div>
                                </a>
                            </li>
                        @endcan
                        @can('roles_view')
                            <li class="menu-item {{ $title == 'Role' ? 'active' : '' }}">
                                <a href="{{ route('user-setup.role.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-user-settings-line"></i>
                                    <div data-i18n="Role">Role</div>
                                </a>
                            </li>
                        @endcan
                        @can('users_view')
                            <li class="menu-item {{ $title == 'User' ? 'active' : '' }}">
                                <a href="{{ route('user-setup.user.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-user-line"></i>
                                    <div data-i18n="User">User</div>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endcanany

            {{-- ========================================
                 DEBUG / LOG VIEWER
            ======================================== --}}
            @can('debug_view')
                <li class="menu-item {{ $title == 'Log Viewer' ? 'active' : '' }}">
                    <a href="{{ route('debug.log-viewer.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons ri-error-warning-line text-danger"></i>
                        <div data-i18n="Log Viewer">Log Viewer</div>
                    </a>
                </li>
            @endcan

        </ul>
    </div>
</aside>
<!-- /main navbar -->
