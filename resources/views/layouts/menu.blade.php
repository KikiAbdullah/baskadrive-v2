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

            <!-- ========================================
                 OPERASIONAL SEWA
            ======================================== -->
            <li class="menu-item {{ $is('rental') ? 'active' : '' }}">
                <a href="{{ route('rental.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ri-handbag-line"></i>
                    <div data-i18n="Sewa">Sewa</div>
                </a>
            </li>

            <!-- ========================================
                 FLEET MAINTENANCE & KERUSAKAN
            ======================================== -->
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
                    <li class="menu-item {{ $is('fleet.damage') ? 'active' : '' }}">
                        <a href="{{ route('fleet.damage.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-error-warning-line"></i>
                            <div data-i18n="Daftar Kerusakan">Daftar Kerusakan</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('fleet.insurance-claim') ? 'active' : '' }}">
                        <a href="{{ route('fleet.insurance-claim.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-shield-check-line"></i>
                            <div data-i18n="Klaim Asuransi">Klaim Asuransi</div>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ========================================
                 KEUANGAN & PENAGIHAN
            ======================================== -->
            <li class="menu-item {{ $is('finance') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-wallet-3-line"></i>
                    <div data-i18n="Keuangan">Keuangan</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ $is('finance.invoice') ? 'active' : '' }}">
                        <a href="{{ route('finance.invoice.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-file-list-3-line"></i>
                            <div data-i18n="Daftar Invoice">Daftar Invoice</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('finance.fine') ? 'active' : '' }}">
                        <a href="{{ route('finance.fine.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-alarm-warning-line"></i>
                            <div data-i18n="Daftar Denda">Daftar Denda</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('finance.payment') ? 'active' : '' }}">
                        <a href="{{ route('finance.payment') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-bank-card-line"></i>
                            <div data-i18n="Riwayat Pembayaran">Riwayat Pembayaran</div>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ========================================
                 AKUNTANSI
            ======================================== -->
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
                    <li class="menu-item {{ $is('accounting.manual-journal') ? 'active' : '' }}">
                        <a href="{{ route('accounting.manual-journal.create') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-edit-box-line"></i>
                            <div data-i18n="Posting Jurnal Manual">Posting Jurnal Manual</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('accounting.ledger') ? 'active' : '' }}">
                        <a href="{{ route('accounting.ledger.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-file-chart-line"></i>
                            <div data-i18n="Buku Besar">Buku Besar</div>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ========================================
                 LAPORAN
            ======================================== -->
            <li class="menu-item {{ $is('report') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-bar-chart-2-line"></i>
                    <div data-i18n="Laporan">Laporan</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ $is('report.revenue') ? 'active' : '' }}">
                        <a href="{{ route('report.revenue') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-line-chart-line"></i>
                            <div data-i18n="Pendapatan & Profit">Pendapatan &amp; Profit</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('report.fleet-utilization') ? 'active' : '' }}">
                        <a href="{{ route('report.fleet-utilization') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-pie-chart-2-line"></i>
                            <div data-i18n="Utilisasi Armada">Utilisasi Armada</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('report.top-customers') ? 'active' : '' }}">
                        <a href="{{ route('report.top-customers') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-award-line"></i>
                            <div data-i18n="Top Pelanggan">Top Pelanggan</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('report.claims') ? 'active' : '' }}">
                        <a href="{{ route('report.claims') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-alert-line"></i>
                            <div data-i18n="Klaim & Denda">Klaim &amp; Denda</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('report.financial') ? 'active' : '' }}">
                        <a href="{{ route('report.financial') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-money-dollar-circle-line"></i>
                            <div data-i18n="Laporan Keuangan">Laporan Keuangan</div>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ========================================
                 SISTEM & ADMINISTRASI
            ======================================== -->
            <li class="menu-item {{ $is('system') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri-settings-5-line"></i>
                    <div data-i18n="Sistem">Sistem</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item {{ $is('system.settings') ? 'active' : '' }}">
                        <a href="{{ route('system.settings') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-equalizer-line"></i>
                            <div data-i18n="Pengaturan Umum">Pengaturan Umum</div>
                        </a>
                    </li>
                    <li class="menu-item {{ $is('system.activity-log') ? 'active' : '' }}">
                        <a href="{{ route('system.activity-log') }}" class="menu-link">
                            <i class="menu-icon tf-icons ri-file-list-line"></i>
                            <div data-i18n="Log Aktivitas">Log Aktivitas</div>
                        </a>
                    </li>
                </ul>
            </li>

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
</aside>
<!-- /main navbar -->
