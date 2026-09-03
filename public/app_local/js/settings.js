function numberWithCommas(x) {
    var parts = x.toString().split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join(".");
}

$(document).on('select2:open', () => {
    // $(".select2-container--open").find('.select2-search__field').trigger('focus');
    document.querySelector('.select2-search__field').focus();
});

$(function () {
    $(".select").select2();

    // $('.daterange-single').daterangepicker({
    //     singleDatePicker: true,
    //     locale: {
    //         format: 'DD-MM-YYYY'
    //     }
    // });

    $("input.uang").keyup(function (event) {

        // skip for arrow keys
        if (event.which >= 37 && event.which <= 40) {
            event.preventDefault();
        }

        $(this).val(function (index, value) {
            value = value.replace(/,/g, '');
            return numberWithCommas(value);
        });
    });

    // Setting datatable defaults
    $.extend($.fn.dataTable.defaults, {
        autoWidth: false,
        dom: '<"datatable-header"fl><"datatable-scroll"t><"datatable-footer"ip>',
        language: {
            search: '<span class="me-3">Filter:</span> <div class="form-control-feedback form-control-feedback-end flex-fill">_INPUT_<div class="form-control-feedback-icon"><i class="ph-magnifying-glass opacity-50"></i></div></div>',
            searchPlaceholder: 'Type to filter...',
            lengthMenu: '<span class="me-3">Show:</span> _MENU_',
            paginate: { 'first': 'First', 'last': 'Last', 'next': document.dir == "rtl" ? '&larr;' : '&rarr;', 'previous': document.dir == "rtl" ? '&rarr;' : '&larr;' }
        }

    });
    // Datatable 'length' options
    $('.datatable-show-all').DataTable({
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
    });
    $('select[name="dtable_length"]').select2({
        minimumResultsForSearch: Infinity,
        width: 'auto'
    });

    //close alert after 5 sec
    setTimeout(function () {
        $(".alert").slideUp();
    }, 6000);
});

var swalInit = Swal.mixin({
    buttonsStyling: false,
    customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-light',
        denyButton: 'btn btn-light',
        input: 'form-control'
    }
});

function changepassword(url) {
    swalInit.fire({
        title: 'Change Password',
        html: '<div class="form-group row mb-2">' +
            '<label class="col-lg-3 col-form-label text-right d-none d-lg-block">Current</label>' +
            '<div class="col-lg-9">' +
            '<input type="password" name="current_password" class="form-control" id="current_password" placeholder="Current Password">' +
            '</div>' +
            '</div>' +
            '<div class="form-group row">' +
            '<label class="col-lg-3 col-form-label text-right d-none d-lg-block">New Password</label>' +
            '<div class="col-lg-9">' +
            '<input type="password" name="current_password" class="form-control" id="new_password" placeholder="New Password">' +
            '</div>' +
            '</div>',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonText: 'Change Password',
        showLoaderOnConfirm: true,
        preConfirm: function (name) {
            var current = document.getElementById('current_password').value;
            var newpass = document.getElementById('new_password').value;
            if (current != "" && newpass != "") {
                return $.ajax({
                    type: 'POST',
                    url: url,
                    data: { current: current, newpass: newpass },
                    dataType: "json",
                    success: function (data) {
                        return data;
                    }
                });
            } else {
                swalInit.showValidationMessage('Enter your current and new password.');
                //bug margin when showing validation message
                $(".swal2-confirm").css('margin-left', '10px');
            }
        },
        allowOutsideClick: false
    }).then(function (result) {
        if (result.value != null)
            if (result.value.status) {
                swalInit.fire({
                    title: 'Success',
                    text: result.value.msg,
                    icon: 'success',
                })
            } else {
                swalInit.fire({
                    title: 'Error',
                    text: result.value.msg,
                    icon: 'error',
                })
            }
    });
    $("#current_password").focus();
}