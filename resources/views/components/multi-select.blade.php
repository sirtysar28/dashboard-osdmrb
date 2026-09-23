{{--
    Dropdown multi-select dengan CHECKLIST.
    Panel terbuka saat diklik (tinggi mengikuti jumlah pilihan, maksimal scroll)
    dan opsi dapat diceklist lebih dari satu sekaligus.

    Pemakaian:
        <x-multi-select name="status" :options="['pppk' => 'Semua PPPK', 1 => 'ASN']"
                        :selected="$filters['status'] ?? []" placeholder="Semua Status" />
    Hasil submit: status[] (array) — kompatibel dengan query whereIn di controller.
--}}
@props([
    'name',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Semua',
])

@php
    $options = collect($options)->mapWithKeys(fn ($label, $value) => [(string) $value => (string) $label]);
    $selected = collect($selected)->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (string) $v)->values();
@endphp

<div class="ms-dropdown{{ $selected->isNotEmpty() ? ' has-selection' : '' }}" data-placeholder="{{ $placeholder }}">
    <button type="button" class="ms-toggle form-select form-select-sm text-start d-flex align-items-center justify-content-between gap-2" aria-haspopup="true" aria-expanded="false">
        <span class="ms-toggle-label text-truncate">{{ $placeholder }}</span>
        <span class="ms-caret"><i class="bi bi-chevron-down"></i></span>
    </button>

    <div class="ms-panel">
        <div class="ms-actions">
            <button type="button" class="ms-action-btn ms-check-all">Pilih semua</button>
            <span class="text-muted">|</span>
            <button type="button" class="ms-action-btn ms-clear-all">Hapus</button>
        </div>
        <div class="ms-options">
            @forelse ($options as $value => $label)
                <label class="ms-option">
                    <input type="checkbox" class="form-check-input m-0" name="{{ $name }}[]" value="{{ $value }}"
                           {{ $selected->contains($value) ? 'checked' : '' }}>
                    <span class="ms-option-label">{{ $label }}</span>
                </label>
            @empty
                <div class="ms-empty text-muted small px-2 py-1">Tidak ada pilihan.</div>
            @endforelse
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                /* ---------- Dropdown multi-select checklist (filter) ---------- */
                function updateToggle(dd) {
                    var checked = Array.prototype.slice.call(dd.querySelectorAll('.ms-option input:checked'));
                    var label = dd.querySelector('.ms-toggle-label');
                    var placeholder = dd.dataset.placeholder || 'Semua';

                    dd.classList.toggle('has-selection', checked.length > 0);

                    if (!checked.length) {
                        label.textContent = placeholder;
                        return;
                    }

                    // tampilkan label pilihan; bila banyak -> "3 dipilih: A, B, ..."
                    var names = checked.map(function (c) {
                        return c.closest('.ms-option').querySelector('.ms-option-label').textContent.trim();
                    });

                    label.textContent = checked.length <= 2
                        ? names.join(', ')
                        : checked.length + ' dipilih: ' + names.slice(0, 2).join(', ') + ', ...';
                }

                document.querySelectorAll('.ms-dropdown').forEach(function (dd) {
                    updateToggle(dd);

                    dd.querySelector('.ms-toggle').addEventListener('click', function (e) {
                        e.stopPropagation();

                        var isOpen = dd.classList.contains('open');

                        // tutup dropdown lain agar tidak menumpuk
                        document.querySelectorAll('.ms-dropdown.open').forEach(function (o) {
                            o.classList.remove('open');
                            o.querySelector('.ms-toggle').setAttribute('aria-expanded', 'false');
                        });

                        dd.classList.toggle('open', !isOpen);
                        dd.querySelector('.ms-toggle').setAttribute('aria-expanded', String(!isOpen));
                    });

                    dd.addEventListener('click', function (e) {
                        e.stopPropagation();

                        if (e.target.closest('.ms-check-all')) {
                            dd.querySelectorAll('.ms-option input').forEach(function (c) { c.checked = true; });
                            updateToggle(dd);
                            return;
                        }

                        if (e.target.closest('.ms-clear-all')) {
                            dd.querySelectorAll('.ms-option input').forEach(function (c) { c.checked = false; });
                            updateToggle(dd);
                            return;
                        }

                        // klik opsi (label/checkbox) -> update label toggle
                        if (e.target.closest('.ms-option')) {
                            updateToggle(dd);
                        }
                    });
                });

                // klik di luar dropdown = tutup panel
                document.addEventListener('click', function (e) {
                    document.querySelectorAll('.ms-dropdown.open').forEach(function (dd) {
                        if (!dd.contains(e.target)) {
                            dd.classList.remove('open');
                            dd.querySelector('.ms-toggle').setAttribute('aria-expanded', 'false');
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce
