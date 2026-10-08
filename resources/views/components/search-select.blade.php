{{--
    Dropdown SINGLE-select dengan KOTAK PENCARIAN.
    Cocok untuk daftar panjang, mis. master perguruan tinggi — pengguna
    mengetik kata kunci lalu memilih nama yang muncul
    (Catatan Masukan 30 Sept 2026 — butir 6).

    Pemakaian:
        <x-search-select name="education_1_campus"
                         :options="$campusList->mapWithKeys(fn ($c) => [$c->id => $c->name])"
                         :value="$defaults['campus']"
                         placeholder="- Pilih Kampus -"
                         search-placeholder="Ketik nama perguruan tinggi..." />
    Hasil submit: satu nilai (string) — sama dengan <select> biasa.

    Atribut tambahan (class, data-*) diteruskan ke input tersembunyi sehingga
    listener lama (mis. toggle "isi manual") tetap berjalan; perubahan pilihan
    memicu event "change" pada input tersebut.
--}}
@props([
    'name',
    'options' => [],
    'value' => '',
    'placeholder' => 'Pilih',
    'searchPlaceholder' => 'Ketik untuk mencari...',
])

@php
    $options = collect($options)->mapWithKeys(fn ($label, $optValue) => [(string) $optValue => (string) $label]);
    $value = (string) ($value ?? '');
    $selectedLabel = $options->get($value);
@endphp

<div class="ss-dropdown{{ $selectedLabel !== null && $value !== '' ? ' has-selection' : '' }}" data-placeholder="{{ $placeholder }}">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" {{ $attributes }}>
    <button type="button" class="ss-toggle form-select form-select-sm text-start d-flex align-items-center justify-content-between gap-2" aria-haspopup="true" aria-expanded="false">
        <span class="ss-toggle-label text-truncate">{{ $selectedLabel ?? $placeholder }}</span>
        <span class="ss-caret"><i class="bi bi-chevron-down"></i></span>
    </button>

    <div class="ss-panel">
        <input type="text" class="ss-search form-control form-control-sm" placeholder="{{ $searchPlaceholder }}" autocomplete="off">
        <div class="ss-options">
            @forelse ($options as $optValue => $optLabel)
                <button type="button" class="ss-option{{ $optValue === $value ? ' selected' : '' }}" data-value="{{ $optValue }}">{{ $optLabel }}</button>
            @empty
                <div class="ss-empty text-muted small px-2 py-1">Tidak ada pilihan.</div>
            @endforelse
        </div>
        <div class="ss-empty text-muted small px-2 py-1 d-none">Tidak ada yang cocok.</div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                /* ---------- Dropdown single-select dengan pencarian ---------- */
                document.querySelectorAll('.ss-dropdown').forEach(function (dd) {
                    var input = dd.querySelector('input[type="hidden"]');
                    var toggle = dd.querySelector('.ss-toggle');
                    var label = dd.querySelector('.ss-toggle-label');
                    var search = dd.querySelector('.ss-search');
                    var emptyMsg = Array.prototype.slice.call(dd.querySelectorAll('.ss-empty')).pop();

                    function refresh() {
                        var option = dd.querySelector('.ss-option.selected');
                        var value = option ? option.dataset.value : '';
                        input.value = value;
                        label.textContent = option ? option.textContent.trim() : dd.dataset.placeholder;
                        dd.classList.toggle('has-selection', !!option);
                    }

                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();

                        var isOpen = dd.classList.contains('open');

                        document.querySelectorAll('.ss-dropdown.open').forEach(function (o) {
                            o.classList.remove('open');
                            o.querySelector('.ss-toggle').setAttribute('aria-expanded', 'false');
                        });

                        dd.classList.toggle('open', !isOpen);
                        toggle.setAttribute('aria-expanded', String(!isOpen));

                        if (!isOpen && search) {
                            search.value = '';
                            filter('');
                            search.focus();
                        }
                    });

                    // filter opsi mengikuti kata kunci pencarian
                    function filter(keyword) {
                        keyword = keyword.trim().toLowerCase();
                        var visible = 0;

                        dd.querySelectorAll('.ss-option').forEach(function (opt) {
                            var match = !keyword || opt.textContent.toLowerCase().indexOf(keyword) !== -1;
                            opt.classList.toggle('d-none', !match);
                            if (match) visible++;
                        });

                        if (emptyMsg) emptyMsg.classList.toggle('d-none', visible > 0);
                    }

                    if (search) {
                        search.addEventListener('input', function () { filter(search.value); });
                        search.addEventListener('click', function (e) { e.stopPropagation(); });
                    }

                    dd.querySelectorAll('.ss-option').forEach(function (opt) {
                        opt.addEventListener('click', function (e) {
                            e.stopPropagation();

                            dd.querySelectorAll('.ss-option').forEach(function (o) { o.classList.remove('selected'); });
                            opt.classList.add('selected');
                            refresh();

                            dd.classList.remove('open');
                            toggle.setAttribute('aria-expanded', 'false');

                            // picu listener lama (mis. toggle "isi manual" kampus)
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                });

                // klik di luar dropdown = tutup panel
                document.addEventListener('click', function (e) {
                    document.querySelectorAll('.ss-dropdown.open').forEach(function (dd) {
                        if (!dd.contains(e.target)) {
                            dd.classList.remove('open');
                            dd.querySelector('.ss-toggle').setAttribute('aria-expanded', 'false');
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce
