/**
 * Peningkatan UX global BaskaDrive (audit UI/UX 4.x & keamanan 5.1):
 * 1. Spinner loading saat area .menuoption diisi via AJAX get-button-option.
 * 2. Indikator spinner pada ikon .action-link-icon-text selama request AJAX berjalan.
 * 3. Proteksi double-submit pada semua form (tombol submit langsung dinonaktifkan).
 */
(function ($) {
    'use strict';

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

    $(document).ajaxStop(function () {
        $('i.app-icon-loading').each(function () {
            var $i = $(this), orig = $i.data('origClass');
            if (orig !== undefined) {
                $i.attr('class', orig);
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
