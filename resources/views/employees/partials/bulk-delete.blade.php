{{--
    Tombol & skrip HAPUS MASSAL data pegawai berdasarkan ceklis baris tabel.

    KHUSUS SUPER ADMIN — pada akun lain (admin, biro_sdm, pegawai) partial
    ini tidak merender apa pun sehingga ceklis & tombol tidak pernah tampil.

    Pemakaian:
        @include('employees.partials.bulk-delete', ['type' => 'asn'])
        @include('employees.partials.bulk-delete', ['type' => 'non_asn'])
        @include('employees.partials.bulk-delete', ['type' => ''])  (direktori gabungan)

    Tabel cukup menambahkan:
        - checkbox "pilih semua"  : class="bulk-select-all" (di thead)
        - checkbox per baris      : class="bulk-check" value="{id}" (di tbody)
    Submit dikirim lewat form dinamis (POST + _method DELETE) sehingga
    tidak perlu membungkus tabel dengan <form> (menghindari form bersarang).
--}}

@php($bulkType = $type ?? '')

@if (auth()->user()->isSuperAdmin())
    <button type="button" class="btn btn-sm btn-outline-danger btn-bulk-delete" data-type="{{ $bulkType }}" disabled>
        <i class="bi bi-trash3"></i> Hapus Terpilih (<span class="bulk-count">0</span>)
    </button>

    @once
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    /* ---------- HAPUS MASSAL PEGAWAI (khusus super admin) ---------- */

                    function bulkUpdateState() {
                        var checked = document.querySelectorAll('input.bulk-check:checked');
                        var total = checked.length;

                        document.querySelectorAll('.btn-bulk-delete').forEach(function (btn) {
                            btn.querySelector('.bulk-count').textContent = total;
                            btn.disabled = total === 0;
                        });

                        // status "pilih semua" per tabel
                        document.querySelectorAll('.bulk-select-all').forEach(function (cb) {
                            var table = cb.closest('table');
                            if (!table) return;
                            var boxes = table.querySelectorAll('input.bulk-check');
                            cb.checked = boxes.length > 0
                                && table.querySelectorAll('input.bulk-check:checked').length === boxes.length;
                        });
                    }

                    document.addEventListener('change', function (e) {
                        if (e.target.classList.contains('bulk-select-all')) {
                            var table = e.target.closest('table');
                            if (table) {
                                table.querySelectorAll('input.bulk-check').forEach(function (c) {
                                    c.checked = e.target.checked;
                                });
                            }
                        }

                        if (e.target.classList.contains('bulk-check') || e.target.classList.contains('bulk-select-all')) {
                            bulkUpdateState();
                        }
                    });

                    document.addEventListener('click', function (e) {
                        var btn = e.target.closest('.btn-bulk-delete');
                        if (!btn) return;

                        var ids = Array.prototype.map.call(
                            document.querySelectorAll('input.bulk-check:checked'),
                            function (c) { return c.value; }
                        );

                        if (!ids.length) {
                            alert('Centang minimal satu data pegawai terlebih dahulu.');
                            return;
                        }

                        if (!confirm('Hapus ' + ids.length + ' data pegawai terpilih? Data yang sudah dihapus tidak dapat dikembalikan.')) {
                            return;
                        }

                        // form dinamis: POST + spoofing DELETE (tanpa membungkus tabel)
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('employees.bulk-destroy') }}';
                        form.style.display = 'none';

                        [{ name: '_token', value: '{{ csrf_token() }}' },
                         { name: '_method', value: 'DELETE' }].forEach(function (f) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = f.name;
                            input.value = f.value;
                            form.appendChild(input);
                        });

                        if (btn.dataset.type) {
                            var type = document.createElement('input');
                            type.type = 'hidden';
                            type.name = 'type';
                            type.value = btn.dataset.type;
                            form.appendChild(type);
                        }

                        ids.forEach(function (id) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'ids[]';
                            input.value = id;
                            form.appendChild(input);
                        });

                        document.body.appendChild(form);
                        form.submit();
                    });

                    bulkUpdateState();
                });
            </script>
        @endpush
    @endonce
@endif
