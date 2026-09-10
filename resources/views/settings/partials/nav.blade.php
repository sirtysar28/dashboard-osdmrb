{{-- Navigasi antar halaman Pengaturan (hanya Administrator Utama) --}}
<ul class="nav settings-nav gap-2 mb-4 flex-nowrap overflow-auto">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.appearance*') ? 'active' : '' }}"
           href="{{ route('settings.appearance') }}">
            <i class="bi bi-palette me-1"></i> Tampilan &amp; Menu
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.announcements*') ? 'active' : '' }}"
           href="{{ route('settings.announcements') }}">
            <i class="bi bi-megaphone me-1"></i> Pengumuman
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('settings.smtp*') ? 'active' : '' }}"
           href="{{ route('settings.smtp') }}">
            <i class="bi bi-envelope-check me-1"></i> SMTP &amp; Notifikasi
        </a>
    </li>
</ul>
