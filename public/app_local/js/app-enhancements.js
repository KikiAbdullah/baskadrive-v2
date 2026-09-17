/**
 * Peningkatan UX global BaskaDrive (audit UI/UX 4.x & keamanan 5.1):
 * 1. Spinner loading saat area .menuoption diisi via AJAX get-button-option.
 * 2. Indikator spinner pada ikon .action-link-icon-text selama request AJAX berjalan.
 * 3. Proteksi double-submit pada semua form (tombol submit langsung dinonaktifkan).
 * 4. Flatpickr global untuk semua form tanggal/waktu (.flatpickr-datetime / .flatpickr-date).
 */
(function ($) {
    'use strict';

    var rangeSelector = 'input[data-range-start][data-range-end]';

    function scan(root, selector) {
        var scope = (root && root.querySelectorAll) ? root : document;
        var elements = Array.prototype.slice.call(scope.querySelectorAll(selector));
        if (scope.matches && scope.matches(selector)) {
            elements.unshift(scope);
        }
        return elements;
    }

    function rangeEndpoint(el, attribute) {
        var selector = el.getAttribute(attribute);
        if (!selector || selector.charAt(0) !== '#') {
            return null;
        }
        var endpoint = document.getElementById(selector.slice(1));
        return endpoint && endpoint.type === 'hidden' && endpoint.form === el.form ? endpoint : null;
    }

    function parseRangeDate(value, time, seconds) {
        var pattern = time ? /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/ : /^(\d{4})-(\d{2})-(\d{2})$/;
        if (time && seconds && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:[0-5]\d$/.test(value)) {
            value = value.slice(0, 16);
        }
        var parts = pattern.exec(value);
        if (!parts) {
            return null;
        }
        var year = Number(parts[1]);
        var month = Number(parts[2]) - 1;
        var day = Number(parts[3]);
        var hour = time ? Number(parts[4]) : 0;
        var minute = time ? Number(parts[5]) : 0;
        var date = new Date(0);
        date.setFullYear(year, month, day);
        date.setHours(hour, minute, 0, 0);
        if (year < 1 || date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day || date.getHours() !== hour || date.getMinutes() !== minute) {
            return null;
        }
        return { date: date, value: value };
    }

    function formatRangeDate(date, time) {
        function pad(value) { return String(value).padStart(2, '0'); }
        return String(date.getFullYear()).padStart(4, '0') + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
            (time ? ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes()) : '');
    }

    function syncRange(el, notify, updatePicker) {
        var state = el._appFlatpickrRange;
        if (!state) {
            return false;
        }
        if (state.syncing) {
            return state.valid;
        }
        state.syncing = true;
        try {
            var start = rangeEndpoint(el, 'data-range-start');
            var end = rangeEndpoint(el, 'data-range-end');
            var parts = el.value.split(' to ');
            var first = parts.length === 2 ? parseRangeDate(parts[0], state.time) : null;
            var last = parts.length === 2 ? parseRangeDate(parts[1], state.time) : null;
            var connected = start && end && start !== end;
            var complete = !!(connected && first && last && first.date <= last.date);
            state.text = el.value;
            state.valid = !!(complete || (connected && el.value === '' && !el.required));
            el.setCustomValidity(state.valid ? '' : 'Masukkan rentang tanggal lengkap dan valid, mulai sebelum atau sama dengan akhir.');
            el.setAttribute('aria-invalid', state.valid ? 'false' : 'true');
            var startValue = complete ? first.value : '';
            var endValue = complete ? last.value : '';
            var changed = !!(start && start.value !== startValue || end && end.value !== endValue);
            if (start) {
                start.value = startValue;
            }
            if (end) {
                end.value = endValue;
            }
            if (state.picker && updatePicker !== false) {
                state.picker.setDate(complete ? [first.date, last.date] : [], false);
                el.value = state.text;
            }
            if (complete && changed && notify !== false) {
                start.dispatchEvent(new Event('change', { bubbles: true }));
                end.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return state.valid;
        } finally {
            state.syncing = false;
        }
    }

    function initRange(el) {
        if (el._appFlatpickrRange) {
            return;
        }
        var time = el.getAttribute('data-range-time') === 'true';
        var start = rangeEndpoint(el, 'data-range-start');
        var end = rangeEndpoint(el, 'data-range-end');
        var state = { time: time, picker: null, syncing: false, valid: false, text: el.value };
        el._appFlatpickrRange = state;
        [start, end].forEach(function (endpoint) {
            if (endpoint) {
                endpoint._appFlatpickrRangeEndpoint = true;
            }
        });
        if (el._flatpickr) {
            el._flatpickr.destroy();
        }
        el.type = 'text';
        if (!el.value && start && end) {
            var first = parseRangeDate(start.value, time, true);
            var last = parseRangeDate(end.value, time, true);
            if (start.value || end.value) {
                el.value = (first ? first.value : start.value) + ' to ' + (last ? last.value : end.value);
            }
        }
        state.initialText = el.value;
        state.text = el.value;
        el.addEventListener('input', function () { syncRange(el, true, el.value !== state.text); }, true);
        el.addEventListener('change', function () { syncRange(el, true, el.value !== state.text); }, true);
        el.addEventListener('blur', function (event) {
            syncRange(el, true, el.value !== state.text);
            event.stopImmediatePropagation();
        }, true);
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.keyCode === 13) {
                if (!syncRange(el)) {
                    event.preventDefault();
                    el.reportValidity();
                }
                event.stopImmediatePropagation();
            }
        }, true);
        var initialText = el.value;
        el.value = '';
        state.picker = window.flatpickr(el, {
            mode: 'range',
            locale: { rangeSeparator: ' to ' },
            dateFormat: time ? 'Y-m-d H:i' : 'Y-m-d',
            enableTime: time,
            time_24hr: true,
            allowInput: true,
            disableMobile: true,
            defaultDate: [],
            onChange: function (dates) {
                if (state.syncing) {
                    return;
                }
                el.value = dates.map(function (date) { return formatRangeDate(date, time); }).join(' to ');
                syncRange(el, true, false);
            },
            onValueUpdate: function (dates) {
                if (state.syncing) {
                    return;
                }
                el.value = dates.map(function (date) { return formatRangeDate(date, time); }).join(' to ');
                syncRange(el, true, false);
            },
            onClose: function () {
                el.value = state.text;
                syncRange(el);
            },
        });
        el.value = initialText;
        syncRange(el, false);
    }

    function initFlatpickr(root) {
        if (typeof window.flatpickr === 'undefined') {
            return;
        }
        scan(root, rangeSelector).forEach(initRange);
        scan(root, '.flatpickr-datetime').forEach(function (el) {
            if (el._flatpickr || el._appFlatpickrRange || el._appFlatpickrRangeEndpoint) {
                return;
            }
            window.flatpickr(el, {
                dateFormat: 'Y-m-d H:i',
                enableTime: true,
                time_24hr: true,
                allowInput: true,
            });
        });
        scan(root, '.flatpickr-date').forEach(function (el) {
            if (el._flatpickr || el._appFlatpickrRange || el._appFlatpickrRangeEndpoint) {
                return;
            }
            window.flatpickr(el, {
                dateFormat: 'Y-m-d',
                allowInput: true,
            });
        });
    }

    window.syncFlatpickrRange = function (el) {
        if (!el || !el.matches || !el.matches(rangeSelector)) {
            return false;
        }
        initFlatpickr(el);
        return syncRange(el);
    };
    window.initFlatpickr = initFlatpickr;
    ['mousedown', 'touchstart', 'focus'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            scan(document, rangeSelector).forEach(function (el) {
                var state = el._appFlatpickrRange;
                var picker = state && state.picker;
                if (!picker || !picker.isOpen || event.target === el ||
                    picker.calendarContainer.contains(event.target) ||
                    (event.relatedTarget && picker.calendarContainer.contains(event.relatedTarget))) {
                    return;
                }
                syncRange(el);
                picker.close();
            });
        }, true);
    });
    document.addEventListener('submit', function (event) {
        var invalid = null;
        scan(document, rangeSelector).forEach(function (el) {
            if (el.form === event.target && !el.disabled && !window.syncFlatpickrRange(el)) {
                invalid = invalid || el;
            }
        });
        if (invalid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            invalid.reportValidity();
        }
    }, true);
    document.addEventListener('reset', function (event) {
        setTimeout(function () {
            if (event.defaultPrevented) {
                return;
            }
            scan(document, rangeSelector).forEach(function (el) {
                if (el.form === event.target && el._appFlatpickrRange) {
                    el.value = el._appFlatpickrRange.initialText;
                    syncRange(el, false);
                }
            });
        }, 0);
    }, true);
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
