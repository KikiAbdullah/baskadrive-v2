@extends('layouts.header')

@section('customcss')
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="d-flex flex-column justify-content-center mb-2">
            <h4 class="mb-1">{{ $title }}</h4>
            <p class="mb-6">{{ $subtitle }}</p>
        </div>

        @include('layouts.alert')

        <div class="row">
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('accounting.manual-journal.store') }}" id="journalForm">
                            @csrf
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Transaksi <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control flatpickr-date" name="transaction_date" autocomplete="off"
                                        value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">No. Referensi</label>
                                    <input type="text" class="form-control" name="reference_number"
                                        placeholder="JRN-XXXX">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Keterangan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="description" required>
                                </div>
                            </div>

                            <hr>
                            <h6>Entri Jurnal</h6>

                            <div class="table-responsive">
                                <table class="table table-sm" id="entriesTable">
                                    <thead>
                                        <tr>
                                            <th style="width:35%">Akun</th>
                                            <th>Keterangan</th>
                                            <th class="text-end" style="width:18%">Debit</th>
                                            <th class="text-end" style="width:18%">Kredit</th>
                                            <th style="width:5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="entriesBody"></tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <th colspan="2" class="text-end">Total</th>
                                            <th class="text-end" id="totalDebit">Rp 0</th>
                                            <th class="text-end" id="totalCredit">Rp 0</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                                <i class="ri-add-line"></i> Tambah Baris
                            </button>

                            <div class="alert alert-info mt-3 d-none" id="balanceInfo"></div>

                            <div class="text-end mt-3">
                                <a href="{{ route('accounting.journal.index') }}" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Jurnal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('customjs')
    <script>
        const accounts = @json($accounts->map(function($a){ return ['id'=>$a->account_id,'code'=>$a->account_code,'name'=>$a->account_name,'children'=>$a->children_count]; }));

        function accountOptions(selected) {
            let opts = '<option value="">Pilih Akun</option>';
            accounts.forEach(a => {
                const disabled = a.children > 0 ? ' disabled' : '';
                const label = a.children > 0 ? `${a.code} - ${a.name} (Induk - tidak bisa dipilih)` : `${a.code} - ${a.name}`;
                opts += `<option value="${a.id}"${disabled} ${selected == a.id ? 'selected' : ''}>${label}</option>`;
            });
            return opts;
        }

        function addRow() {
            const idx = $('#entriesBody tr').length;
            const tr = `<tr>
                <td><select class="form-select form-select-sm select2-entry" name="entries[${idx}][account_id]" required>${accountOptions()}</select></td>
                <td><input type="text" class="form-control form-control-sm" name="entries[${idx}][description]"></td>
                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm num-debit" name="entries[${idx}][debit]" value="0"></td>
                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm num-credit" name="entries[${idx}][credit]" value="0"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger btn-del-row"><i class="ri-delete-bin-line"></i></button></td>
            </tr>`;
            $('#entriesBody').append(tr);
            recalc();
        }

        function recalc() {
            let d = 0,
                c = 0;
            $('.num-debit').each(function() {
                d += parseFloat($(this).val() || 0);
            });
            $('.num-credit').each(function() {
                c += parseFloat($(this).val() || 0);
            });
            $('#totalDebit').text('Rp ' + numberFormat(d));
            $('#totalCredit').text('Rp ' + numberFormat(c));

            const info = $('#balanceInfo');
            if (Math.abs(d - c) < 0.01) {
                info.removeClass('d-none alert-danger').addClass('alert-success')
                    .text('Seimbang: Debit = Kredit = Rp ' + numberFormat(d));
            } else {
                info.removeClass('d-none alert-success').addClass('alert-danger')
                    .text('Belum seimbang. Debit Rp ' + numberFormat(d) + ' | Kredit Rp ' + numberFormat(c));
            }
        }

        function numberFormat(n) {
            // AKN-02: dua desimal agar sen ikut tampil di form jurnal manual.
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);
        }

        $(document).ready(function() {
            if (window.initFlatpickr) { window.initFlatpickr(); }
            addRow();
            addRow();

            $('#btnAddRow').on('click', addRow);

            $(document).on('input', '.num-debit, .num-credit', recalc);
            $(document).on('click', '.btn-del-row', function() {
                $(this).closest('tr').remove();
                recalc();
            });

            $('#journalForm').on('submit', function(e) {
                const d = $('.num-debit').toArray().reduce((s, el) => s + parseFloat($(el).val() || 0), 0);
                const c = $('.num-credit').toArray().reduce((s, el) => s + parseFloat($(el).val() || 0), 0);
                if (Math.abs(d - c) >= 0.01) {
                    e.preventDefault();
                    Swal.fire('Gagal', 'Total Debit harus sama dengan Total Kredit.', 'error');
                    return false;
                }
                // AKN-07: cermin validasi server — tepat satu sisi per baris.
                let rowError = '';
                $('.num-debit').each(function(i) {
                    const dv = parseFloat($(this).val() || 0);
                    const cv = parseFloat($('.num-credit').eq(i).val() || 0);
                    if (dv > 0 && cv > 0) {
                        rowError = 'Baris ' + (i + 1) + ': debit dan kredit tidak boleh terisi bersamaan.';
                        return false;
                    }
                    if (dv === 0 && cv === 0) {
                        rowError = 'Baris ' + (i + 1) + ': salah satu sisi harus bernilai lebih dari nol.';
                        return false;
                    }
                });
                if (rowError) {
                    e.preventDefault();
                    Swal.fire('Gagal', rowError, 'error');
                    return false;
                }
                const btn = $('#btnSubmit');
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...');
            });
        });
    </script>
@endsection

@section('appmodal')
    <div id="mymodal" class="modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-indigo text-white">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('notification')
    @include('layouts.notification')
@endsection