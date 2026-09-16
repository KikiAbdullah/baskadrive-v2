/**
 * Peningkatan UX global BaskaDrive (audit UI/UX 4.x & keamanan 5.1):
 * 1. Spinner loading saat area .menuoption diisi via AJAX get-button-option.
 * 2. Indikator spinner pada ikon .action-link-icon-text selama request AJAX berjalan.
 * 3. Proteksi double-submit pada semua form (tombol submit langsung dinonaktifkan).
 * 4. Flatpickr global untuk semua form tanggal/waktu (.flatpickr-datetime / .flatpickr-date).
 */
(function ($) {
    'use strict';

    // 4. Initor flatpickr global — idempoten, ikut memindai ulang konten hasil muat AJAX (wizard/modal).
    function initFlatpickr(root) {
        if (typeof window.flatpickr === 'undefined') {
            return;
        }
        var scope = (root && root.querySelectorAll) ? root : document;
        scope.querySelectorAll('.flatpickr-datetime').forEach(function (el) {
            if (el._flatpickr) {
                return;
            }
            window.flatpickr(el, {
                dateFormat: 'Y-m-d H:i',
                enableTime: true,
                time_24hr: true,
                allowInput: true,
            });
        });
        scope.querySelectorAll('.flatpickr-date').forEach(function (el) {
            if (el._flatpickr) {
                return;
            }
            window.flatpickr(el, {
                dateFormat: 'Y-m-d',
                allowInput: true,
            });
        });
    }
    window.initFlatpickr = initFlatpickr;
    $(function () { initFlatpickr(); });
    $(document).ajaxComplete(function () { initFlatpickr(); });

    // 1. Spinner pada container menuoption saat memuat tombol aksi baris tabel
    $.ajaxPrefilter(function (options) {
        if (options.url && options.url.indexOf('get-button-option') !== -1) {
            var container = $('.menuoption');
            if (container.length) {
                container.html(
                    '<span class="d-inline-flex align-items-center text-muted small">' +
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
                    'Memuat pilihan aksi...</span>'
                );
            }
        }
    });

    // 2. Ganti ikon action-link/tombol AJAX menjadi loader selama request aktif
    $(document).on('click', '.action-link-icon-text, .btn-confirm, .btn-cancel, .btn-waive, .btn-pay', function () {
        var icon = $(this).find('i').first();
        if (icon.length && !icon.data('origClass')) {
            icon.data('origClass', icon.attr('class') || '');
            icon.addClass('app-icon-loading');
        }
    });

    $(document).ajaxStart(function () {
        $('i.app-icon-loading').each(function () {
            var $i = $(this);
            if ($i.data('origClass')) {
                $i.attr('class', 'ri ri-loader-4-line app-icon-loading app-spin');
            }
        });
    });

    // 4. Proteksi double-submit tombol AJAX action-link/JS (FASE 3 audit sewa):
    //    cegah double-click pada tombol yang memicu request AJAX (confirm/cancel/payment)
    $(document).on('click', '.btn-confirm, .btn-cancel, .btn-waive, .btn-pay, .btnReturn, .btnInvoice', function (e) {
        var btn = $(this);
        if (btn.data('ajax-busy')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }
        btn.data('ajax-busy', true).addClass('disabled').attr('aria-disabled', 'true');
        // release saat request selesai (ajaxStop) atau 8 detik sebagai pengaman
        var release = function () { btn.data('ajax-busy', false).removeClass('disabled').removeAttr('aria-disabled'); };
        $(document).one('ajaxStop', release);
        setTimeout(function () {
            btn.off('ajaxStop', release);
            release();
        }, 8000);
    });

    $(document).ajaxStop(function () {
        $('i.app-icon-loading').each(function () {
            var $i = $(this);
            if ($i.data('origClass')) {
                $i.attr('class', $i.data('origClass'));
                $i.removeData('origClass');
            }
        });
    });

    // 3. Proteksi double-submit: kunci tombol submit segera setelah form dikirim
    $(document).on('submit', 'form', function () {
        var form = $(this);
        setTimeout(function () {
            form.find('button[type="submit"], input[type="submit"]').each(function () {
                var btn = $(this);
                if (!btn.prop('disabled')) {
                    btn.data('was-enabled', true).prop('disabled', true);
                    btn.find('i').first().addClass('app-spin');
                }
            });
        }, 0);
    });

    // Aktifkan kembali tombol saat halaman muncul dari cache back/forward
    window.addEventListener('pageshow', function () {
        $('form button[type="submit"], form input[type="submit"]').each(function () {
            var btn = $(this);
            if (btn.data('was-enabled')) {
                btn.prop('disabled', false).removeData('was-enabled');
                btn.find('i').first().removeClass('app-spin');
            }
        });
    });
})(jQuery);
