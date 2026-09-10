@extends('layouts.app')

@section('page_title', 'Struktur Organisasi')
@section('page_subtitle', 'Bagan struktur unit kerja Kementerian Transmigrasi')

@section('content')

<!-- ================= STATISTIK UNIT ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-building"></i></div>
            <div>
                <span>Eselon I</span>
                <h2>{{ number_format($stats['es1']) }}</h2>
                <small>unit kerja</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon accent"><i class="bi bi-building-fill"></i></div>
            <div>
                <span>Eselon II</span>
                <h2>{{ number_format($stats['es2']) }}</h2>
                <small>unit kerja</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-diagram-2"></i></div>
            <div>
                <span>Eselon III</span>
                <h2>{{ number_format($stats['es3']) }}</h2>
                <small>unit kerja</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6">
        <div class="stat-card">
            <div class="stat-icon danger"><i class="bi bi-geo-alt"></i></div>
            <div>
                <span>Balai</span>
                <h2>{{ number_format($stats['balai']) }}</h2>
                <small>unit teknis</small>
            </div>
        </div>
    </div>
</div>

{{-- ================= BAGAN RESMI KEMENTERIAN (SOTK) =================
     Sumber: https://www.transmigrasi.go.id/profil/struktur-organisasi/ --}}
<div class="row g-3 mb-4">
    <div class="col-lg-12">
        <div class="table-card">
            <div class="card-header-custom flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-image me-2"></i>Bagan Resmi Kementerian (SOTK)
                    <a href="https://www.transmigrasi.go.id/profil/struktur-organisasi/" target="_blank"
                       rel="noopener" class="ms-2 small text-decoration-none" data-no-loader
                       title="Sumber: situs resmi Kementerian Transmigrasi">
                        <i class="bi bi-box-arrow-up-right"></i> transmigrasi.go.id
                    </a>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted d-none d-sm-inline">Perkecil / Perbesar:</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Zoom bagan resmi">
                        <button type="button" class="btn btn-outline-secondary" data-zoom-out="sotk"
                                title="Perkecil (minimize)"><i class="bi bi-dash-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary" style="min-width:58px" data-zoom-label="sotk">100%</button>
                        <button type="button" class="btn btn-outline-secondary" data-zoom-in="sotk"
                                title="Perbesar (maximize)"><i class="bi bi-plus-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-zoom-reset="sotk"
                                title="Kembalikan ukuran awal"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                </div>
            </div>

            <div class="org-zoom-viewport" data-zoom-viewport="sotk">
                <div class="org-zoom-stage" data-zoom-stage="sotk">
                    <img src="{{ asset('images/struktur-organisasi.png') }}"
                         alt="Struktur Organisasi Kementerian Transmigrasi"
                         class="img-fluid org-zoom-image" draggable="false">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= BAGAN ORGANISASI (TREE UNIT) ================= -->
<div class="row g-3">
    <div class="col-lg-12">
        <div class="table-card">
            <div class="card-header-custom flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Bagan Struktur Unit Kerja</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <ul class="org-legend mb-0">
                        <li class="lg-kementerian">Kementerian</li>
                        <li class="lg-es1">Eselon I</li>
                        <li class="lg-es2">Eselon II</li>
                        <li class="lg-es3">Eselon III</li>
                        <li class="lg-balai">Balai / UPT</li>
                    </ul>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Zoom bagan unit">
                        <button type="button" class="btn btn-outline-secondary" data-zoom-out="tree"
                                title="Perkecil (minimize)"><i class="bi bi-dash-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary" style="min-width:58px" data-zoom-label="tree">100%</button>
                        <button type="button" class="btn btn-outline-secondary" data-zoom-in="tree"
                                title="Perbesar (maximize)"><i class="bi bi-plus-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-zoom-reset="tree"
                                title="Kembalikan ukuran awal"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                </div>
            </div>

            <div class="org-tree-scroll org-zoom-viewport" data-zoom-viewport="tree">
                <div class="org-tree org-zoom-stage" data-zoom-stage="tree">
                    @include('modules.partials.unit-node', ['node' => ['unit' => (object) ['id' => null, 'name' => 'Kementerian Transmigrasi', 'code' => 'ROOT', 'level' => 'KEMENTERIAN'], 'children' => $tree], 'isRoot' => true])
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    /* ====== ZOOM BAGAN (minimize / maximize) ======
       Tombol + / − / reset mengubah skala stage (transform: scale)
       dalam viewport yang bisa di-scroll & di-drag. */
    (function () {
        var MIN = 0.3, MAX = 2.5, STEP = 0.15, DEFAULT = 1;

        var groups = {};

        function group(name) {
            if (!groups[name]) {
                groups[name] = {
                    viewport: document.querySelector('[data-zoom-viewport="' + name + '"]'),
                    stage: document.querySelector('[data-zoom-stage="' + name + '"]'),
                    label: document.querySelector('[data-zoom-label="' + name + '"]'),
                    scale: DEFAULT,
                };
            }
            return groups[name];
        }

        function apply(name, scale) {
            var g = group(name);
            if (!g.stage) return;

            g.scale = Math.min(MAX, Math.max(MIN, scale));
            g.stage.style.transform = 'scale(' + g.scale + ')';
            g.stage.dataset.zoom = g.scale;

            if (g.label) {
                g.label.textContent = Math.round(g.scale * 100) + '%';
            }

            // stage diperkecil tidak perlu scroll; diperbesar butuh ruang
            if (g.viewport) {
                g.viewport.classList.toggle('zoomed-in', g.scale > 1);
            }
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-zoom-in],[data-zoom-out],[data-zoom-reset]');
            if (!btn) return;

            var name = btn.dataset.zoomIn || btn.dataset.zoomOut || btn.dataset.zoomReset;
            var g = group(name);
            if (!g.stage) return;

            if (btn.dataset.zoomIn !== undefined)   apply(name, g.scale + STEP);
            if (btn.dataset.zoomOut !== undefined)  apply(name, g.scale - STEP);
            if (btn.dataset.zoomReset !== undefined) apply(name, DEFAULT);
        });

        /* Ctrl + scroll = zoom bagan di bawah kursor */
        document.addEventListener('wheel', function (e) {
            if (!e.ctrlKey) return;

            var viewport = e.target.closest('.org-zoom-viewport');
            if (!viewport) return;

            e.preventDefault();

            var name = viewport.dataset.zoomViewport;
            var g = group(name);

            apply(name, g.scale + (e.deltaY < 0 ? STEP : -STEP));
        }, { passive: false });

        /* Drag untuk menggeser bagan yang diperbesar */
        document.addEventListener('mousedown', function (e) {
            var viewport = e.target.closest('.org-zoom-viewport');
            if (!viewport || !viewport.classList.contains('zoomed-in')) return;

            var startX = e.pageX - viewport.offsetLeft, startY = e.pageY - viewport.offsetTop;
            var startScroll = { left: viewport.scrollLeft, top: viewport.scrollTop };

            function onMove(ev) {
                viewport.scrollLeft = startScroll.left - (ev.pageX - viewport.offsetLeft - startX);
                viewport.scrollTop = startScroll.top - (ev.pageY - viewport.offsetTop - startY);
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            e.preventDefault();
        });

        /* init semua grup */
        ['sotk', 'tree'].forEach(function (name) { apply(name, DEFAULT); });
    })();
</script>
@endpush

@endsection
