{{-- Pengumuman di semua dashboard: TEKS BERJALAN + KARTU.
     - Teks berjalan (satu baris, terpisah di atas) menampilkan gabungan
       judul seluruh pengumuman aktif.
     - Kartu pengumuman ditampilkan 4 kolom SEJAJAR dalam 1 baris,
       maksimal 4 kartu per halaman.
       Jika lebih dari 4, halaman kartu bisa DIGESER (panah / titik / swipe).
     - Klik kartu membuka popup detail (gambar, judul, teks & tombol tautan). --}}
@php
    // dukung pemanggilan lama (:announcement) maupun baru (:announcements)
    $items = isset($announcements) && $announcements->isNotEmpty()
        ? $announcements
        : collect([$announcement ?? null])->filter();
    $items = $items->filter(fn ($a) => $a && ($a->image_url || $a->title))->values();
@endphp

@if ($items->isNotEmpty())
    @php
        // teks berjalan = gabungan judul (atau running text khusus) semua kartu
        $marqueeText = $items
            ->map(fn ($a) => trim($a->running_text ?: $a->title))
            ->filter()
            ->implode(' &nbsp;&bull;&nbsp; ');

        // maksimal 4 kartu per halaman geser
        $pages = $items->chunk(4);
        $multipage = $pages->count() > 1;
    @endphp

    <div class="announcement-banner announcement-cards" data-cards>

        {{-- ====== TEKS BERJALAN (judul-judul semua kartu) ====== --}}
        @if ($marqueeText)
            <div class="announcement-running">
                <span class="badge-umum"><i class="bi bi-megaphone-fill"></i> PENGUMUMAN</span>
                <div class="announcement-marquee">
                    <span class="announcement-marquee-text">{{ $marqueeText }} &nbsp;&bull;&nbsp; {{ $marqueeText }}</span>
                </div>
            </div>
        @endif

        {{-- ====== KARTU PENGUMUMAN ====== --}}
        <div class="announcement-body">

            @if ($multipage)
                <button type="button" class="announcement-arrow announcement-arrow-prev"
                        aria-label="Halaman kartu sebelumnya">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="announcement-arrow announcement-arrow-next"
                        aria-label="Halaman kartu berikutnya">
                    <i class="bi bi-chevron-right"></i>
                </button>
                <span class="announcement-counter"><strong>1</strong>/{{ $pages->count() }}</span>
            @endif

            <div class="announcement-pages">
                @foreach ($pages as $page)
                    <div class="announcement-page">
                        <div class="announcement-grid">
                            @foreach ($page as $announcement)
                                <button type="button" class="announcement-card"
                                        data-bs-toggle="modal"
                                        data-bs-target="#announcementDetail{{ $announcement->id }}"
                                        title="Lihat detail: {{ $announcement->title }}">
                                    <span class="announcement-card-image">
                                        @if ($announcement->image_url)
                                            <img src="{{ $announcement->image_url }}"
                                                 alt="{{ $announcement->title }}" loading="lazy">
                                        @else
                                            <i class="bi bi-megaphone-fill"></i>
                                        @endif
                                    </span>
                                    <span class="announcement-card-body">
                                        <span class="announcement-card-title">{{ $announcement->title }}</span>
                                        <span class="announcement-card-hint"><i class="bi bi-zoom-in"></i> Lihat detail</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($multipage)
                {{-- titik indikator halaman kartu --}}
                <div class="announcement-dots announcement-dots-cards">
                    @foreach ($pages as $page)
                        <button type="button" class="announcement-dot {{ $loop->first ? 'active' : '' }}"
                                aria-label="Ke halaman kartu {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    {{-- ====== POPUP DETAIL PENGUMUMAN ====== --}}
    @foreach ($items as $announcement)
        <div class="modal fade announcement-detail-modal" id="announcementDetail{{ $announcement->id }}"
             tabindex="-1" aria-hidden="true" aria-labelledby="announcementDetailLabel{{ $announcement->id }}">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="announcementDetailLabel{{ $announcement->id }}">
                            <i class="bi bi-megaphone-fill me-2"></i>{{ $announcement->title }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @if ($announcement->image_url)
                            <img src="{{ $announcement->image_url }}" class="announcement-detail-image"
                                 alt="{{ $announcement->title }}">
                        @endif

                        @if ($announcement->running_text)
                            <p class="announcement-detail-text">{{ $announcement->running_text }}</p>
                        @endif

                        @if ($announcement->link_url)
                            <a href="{{ $announcement->link_url }}" target="_blank" rel="noopener"
                               class="btn btn-osdmrb btn-sm px-4" data-no-loader>
                                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Tautan Terkait
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @once
        @push('scripts')
        <script>
            /* ====== Halaman geser kartu pengumuman (maks 4 kartu / halaman) ====== */
            document.querySelectorAll('.announcement-banner[data-cards]').forEach(function (banner) {
                var pages  = banner.querySelectorAll('.announcement-page');
                if (! pages.length) return;

                var dots    = banner.querySelectorAll('.announcement-dot');
                var counter = banner.querySelector('.announcement-counter');
                var track   = banner.querySelector('.announcement-pages');
                var current = 0;
                var total   = pages.length;

                function goTo(index) {
                    /* modulo agar index selalu 0..total-1 (looping kiri/kanan) */
                    current = ((index % total) + total) % total;
                    track.style.transform = 'translateX(-' + (current * 100) + '%)';

                    dots.forEach(function (dot, i) {
                        dot.classList.toggle('active', i === current);
                    });

                    if (counter) {
                        counter.innerHTML = '<strong>' + (current + 1) + '</strong>/' + total;
                    }
                }

                var nextBtn = banner.querySelector('.announcement-arrow-next');
                var prevBtn = banner.querySelector('.announcement-arrow-prev');

                if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });
                if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });

                dots.forEach(function (dot, i) {
                    dot.addEventListener('click', function () { goTo(i); });
                });

                /* geser halaman kartu dengan swipe layar sentuh */
                var touchX = null;
                banner.addEventListener('touchstart', function (e) {
                    touchX = e.touches[0].clientX;
                }, { passive: true });
                banner.addEventListener('touchend', function (e) {
                    if (touchX === null) return;
                    var delta = e.changedTouches[0].clientX - touchX;
                    if (Math.abs(delta) > 50) goTo(delta < 0 ? current + 1 : current - 1);
                    touchX = null;
                }, { passive: true });
            });

            /* ====== Kecepatan teks berjalan menyesuaikan panjang judul ======
               (kecepatan tetap dalam px/detik, durasi minimal 18 detik) */
            document.querySelectorAll('.announcement-marquee .announcement-marquee-text').forEach(function (span) {
                var width = span.scrollWidth || span.offsetWidth;
                if (! width) return;

                var seconds = Math.max(18, width / 85);
                span.style.animationDuration = seconds + 's';
            });
        </script>
        @endpush
    @endonce
@endif
