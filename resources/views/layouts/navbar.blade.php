<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="container-xxl">
        <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-6">
            <a href="{{ route('siteurl') }}" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                    {{-- Logo brand = app_local/img/logo.png (fallback logo unggahan perusahaan) --}}
                    <img src="{{ \App\Support\BrandAsset::logoUrl() }}" alt="{{ config('app.name') }}"
                        style="height: 34px; width: auto; max-width: 168px; object-fit: contain;" />
                </span>
                <span class="app-brand-text demo menu-text fw-semibold">{{ config('app.name') }}</span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                <i class="ri-close-fill align-middle"></i>
            </a>
        </div>

        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="ri-menu-fill ri-22px"></i>
            </a>
        </div>

        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <ul class="navbar-nav flex-row align-items-center ms-auto">

                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                    <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                        <div class="avatar avatar-online">
                            <img src="{{ asset('assets') }}/img/avatars/1.png" alt class="rounded-circle" />
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="pages-account-settings-account.html">
                                <div class="d-flex">
                                    <div class="flex-shrink-0 me-2">
                                        <div class="avatar avatar-online">
                                            <img src="{{ asset('assets') }}/img/avatars/1.png" alt
                                                class="rounded-circle" />
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="fw-medium d-block small">{{ auth()->user()->name ?? '' }}</span>
                                        <small
                                            class="text-muted">{{ auth()->user()->roles->first()->name ?? '' }}</small>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <div class="dropdown-divider"></div>
                        </li>
                        <li>
                            <div class="d-grid px-4 pt-2 pb-1">
                                <a class="btn btn-sm btn-danger d-flex" href="{{ route('logout') }}"
                                    onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
                                    <small class="align-middle">Logout</small>
                                    <i class="ri-logout-box-r-line ms-2 ri-16px"></i>
                                </a>
                                <form id="frm-logout" action="{{ route('logout') }}" method="POST"
                                    style="display: none;">
                                    {{ csrf_field() }}
                                </form>
                            </div>
                        </li>
                    </ul>
                </li>
                <!--/ User -->
            </ul>
        </div>
    </div>
</nav>
