@php
    $routeName = request()->route() ? (request()->route()->getName() ?? '') : '';
    $is = function (string ...$prefixes) use ($routeName): bool {
        foreach ($prefixes as $p) {
            if ($routeName === $p || str_starts_with($routeName, $p . '.')) {
                return true;
            }
        }
        return false;
    };
@endphp
<!-- Main navbar -->
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0">
    <div class="container-xxl d-flex h-100">
        <ul class="menu-inner">

            <!-- ========================================
                 DASHBOARD & MONITORING
            ======================================== -->
            <li class="menu-item {{ $is('dashboard', 'siteurl') ? 'active open' : '' }}">
                <a href="{{ route('siteurl') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-home-smile-line"></i>
                    <div data-i18n="Dashboard">Dashboard</div>
                </a>
            </li>

            <!-- ========================================
                 MASTER DATA
            ======================================== -->
            @can('master_view')
            <li class="menu-item {{ $is('master') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-database-2-line"></i>
                    <div data-i18n="Master Data">Master Data</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-header mt-0">
                        <span class="menu-header-text" data-i18n="Armada">Armada</span>
                    </li>
                    <li class="menu-item {{ $is('master.brand', 'master.vehicle-model') ? 'active' : '' }}">
                        <a href="{{ route('master.brand.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-price-tag-3-line"></i>
                            <div data-i18n="Data Merek & Model">Data Merek &amp; Model</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.vehicle') ? 'active' : '' }}">
                        <a href="{{ route('master.vehicle.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-car-line"></i>
                            <div data-i18n="Data Unit Mobil">Data Unit Mobil</div>
                        </a>
                    </li>

                    <li class="menu-header">
                        <span class="menu-header-text" data-i18n="Pelanggan & SDM">Pelanggan &amp; SDM</span>
                    </li>
                    <li class="menu-item {{ $is('master.customer') ? 'active' : '' }}">
                        <a href="{{ route('master.customer.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-user-star-line"></i>
                            <div data-i18n="Data Pelanggan">Data Pelanggan</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.employee') ? 'active' : '' }}">
                        <a href="{{ route('master.employee.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-user-settings-line"></i>
                            <div data-i18n="Data Karyawan">Data Karyawan</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.driver') ? 'active' : '' }}">
                        <a href="{{ route('master.driver.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-steering-2-line"></i>
                            <div data-i18n="Data Sopir">Data Sopir</div>
                        </a>
                    </li>

                    <li class="menu-header">
                        <span class="menu-header-text" data-i18n="Operasional">Operasional</span>
                    </li>
                    <li class="menu-item {{ $is('master.location') ? 'active' : '' }}">
                        <a href="{{ route('master.location.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-map-pin-line"></i>
                            <div data-i18n="Lokasi / Cabang">Lokasi / Cabang</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.workshop') ? 'active' : '' }}">
                        <a href="{{ route('master.workshop.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-tools-line"></i>
                            <div data-i18n="Bengkel Mitra">Bengkel Mitra</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.maintenance-type') ? 'active' : '' }}">
                        <a href="{{ route('master.maintenance-type.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-calendar-check-line"></i>
                            <div data-i18n="Tipe Maintenance">Tipe Maintenance</div>
                        </a>
                    </li>

                    <li class="menu-header">
                        <span class="menu-header-text" data-i18n="Keuangan & Akuntansi">Keuangan &amp; Akuntansi</span>
                    </li>
                    <li class="menu-item {{ $is('master.promo') ? 'active' : '' }}">
                        <a href="{{ route('master.promo.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-percent-line"></i>
                            <div data-i18n="Manajemen Promo">Manajemen Promo</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('master.coa') ? 'active' : '' }}">
                        <a href="{{ route('master.coa.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-bank-line"></i>
                            <div data-i18n="Chart of Account (COA)">Chart of Account (COA)</div>
                        </a>
                    </li>
                </ul>
            </li>
            @endcan

            <!-- ========================================
                 OPERASIONAL SEWA
            ======================================== -->
            @can('rental_view')
            <li class="menu-item {{ $is('rental') ? 'active' : '' }}">
                <a href="{{ route('rental.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-handbag-line"></i>
                    <div data-i18n="Sewa">Sewa</div>
                </a>
            </li>
            @endcan

            <!-- ========================================
                 FLEET MAINTENANCE & KERUSAKAN
            ======================================== -->
            @can('fleet_view')
            <li class="menu-item {{ $is('fleet') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-settings-3-line"></i>
                    <div data-i18n="Fleet">Fleet</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ $is('fleet.maintenance') ? 'active' : '' }}">
                        <a href="{{ route('fleet.maintenance.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-tools-line"></i>
                            <div data-i18n="Jadwal Maintenance">Jadwal Maintenance</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('fleet.damage', 'fleet.insurance-claim') ? 'active' : '' }}">
                        <a href="{{ route('fleet.damage.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-error-warning-line"></i>
                            <div data-i18n="Kerusakan & Klaim">Kerusakan &amp; Klaim</div>
                        </a>
                    </li>
                </ul>
            </li>
            @endcan

            <!-- ========================================
                 KEUANGAN & PENAGIHAN
            ======================================== -->
            @can('finance_view')
            <li class="menu-item {{ $is('finance') ? 'active' : '' }}">
                <a href="{{ route('finance.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-wallet-3-line"></i>
                    <div data-i18n="Keuangan">Keuangan</div>
                </a>
            </li>
            @endcan

            <!-- ========================================
                 AKUNTANSI
            ======================================== -->
            @can('accounting_view')
            <li class="menu-item {{ $is('accounting') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-book-2-line"></i>
                    <div data-i18n="Akuntansi">Akuntansi</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ $is('accounting.journal') ? 'active' : '' }}">
                        <a href="{{ route('accounting.journal.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-book-open-line"></i>
                            <div data-i18n="Jurnal Umum">Jurnal Umum</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('accounting.ledger') ? 'active' : '' }}">
                        <a href="{{ route('accounting.ledger.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-file-chart-line"></i>
                            <div data-i18n="Buku Besar">Buku Besar</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('accounting.statement.income') ? 'active' : '' }}">
                        <a href="{{ route('accounting.statement.income') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-line-chart-line"></i>
                            <div data-i18n="Laporan Laba Rugi">Laporan Laba Rugi</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('accounting.statement.balance') ? 'active' : '' }}">
                        <a href="{{ route('accounting.statement.balance') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-scales-3-line"></i>
                            <div data-i18n="Neraca">Neraca</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('accounting.statement.cashflow') ? 'active' : '' }}">
                        <a href="{{ route('accounting.statement.cashflow') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-funds-line"></i>
                            <div data-i18n="Laporan Arus Kas">Laporan Arus Kas</div>
                        </a>
                    </li>
                </ul>
            </li>
            @endcan

            <!-- ========================================
                 LAPORAN & ANALYTICS
            ======================================== -->
            @can('report_view')
            <li class="menu-item {{ $is('report') ? 'active' : '' }}">
                <a href="{{ route('report.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-bar-chart-2-line"></i>
                    <div data-i18n="Laporan">Laporan</div>
                </a>
            </li>
            @endcan

            <!-- ========================================
                 SISTEM & ADMINISTRASI
            ======================================== -->
            @canany(['settings_view', 'logs_view'])
            <li class="menu-item {{ $is('system') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-settings-5-line"></i>
                    <div data-i18n="Sistem">Sistem</div>
                </a>
                <ul class="menu-sub">
                    @can('settings_view')
                    <li class="menu-item {{ $is('system.settings') ? 'active' : '' }}">
                        <a href="{{ route('system.settings') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-equalizer-line"></i>
                            <div data-i18n="Pengaturan Umum">Pengaturan Umum</div>
                        </a>
                    </li>
                    @endcan
                    @can('logs_view')
                    <li class="menu-item {{ $is('system.activity-log') ? 'active' : '' }}">
                        <a href="{{ route('system.activity-log') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-file-list-line"></i>
                            <div data-i18n="Log Aktivitas">Log Aktivitas</div>
                        </a>
                    </li>
                    @endcan
                </ul>
            </li>
            @endcanany

            {{-- ========================================
                 SETUP (User, Role, Permission)
            ======================================== --}}
            @canany(['permissions_view', 'roles_view', 'users_view', 'debug_view'])
                <li class="menu-item {{ $is('user-setup', 'debug') ? 'active open' : '' }}">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons ri-user-settings-line"></i>
                        <div data-i18n="Setup">Setup</div>
                    </a>
                    <ul class="menu-sub">
                        @can('permissions_view')
                            <li class="menu-item {{ $is('user-setup.permission') ? 'active' : '' }}">
                                <a href="{{ route('user-setup.permission.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-key-line"></i>
                                    <div data-i18n="Permission">Permission</div>
                                </a>
                            </li>
                        @endcan
                        @can('roles_view')
                            <li class="menu-item {{ $is('user-setup.role') ? 'active' : '' }}">
                                <a href="{{ route('user-setup.role.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-shield-user-line"></i>
                                    <div data-i18n="Role">Role</div>
                                </a>
                            </li>
                        @endcan
                        @can('users_view')
                            <li class="menu-item {{ $is('user-setup.user') ? 'active' : '' }}">
                                <a href="{{ route('user-setup.user.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-user-line"></i>
                                    <div data-i18n="User">User</div>
                                </a>
                            </li>
                        @endcan
                        @can('debug_view')
                            <li class="menu-item {{ $is('debug.log-viewer') ? 'active' : '' }}">
                                <a href="{{ route('debug.log-viewer.index') }}" class="menu-link">
                                    <i class="menu-icon tf-icons ri-error-warning-line"></i>
                                    <div data-i18n="Log Viewer">Log Viewer</div>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endcanany

        </ul>
    </div>
</aside><!-- /main navbar -->
