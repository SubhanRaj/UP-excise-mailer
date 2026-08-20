@props([
    'title'        => 'Dashboard',
    'pageTitle'    => 'Dashboard',
    'pageSubtitle' => 'UP Department of Excise — Mailer',
])

<!DOCTYPE html>
<html lang="en" class="h-full{{ request()->cookie('color_scheme') === 'dark' ? ' dark' : '' }}">

<x-head :title="$title" :description="$pageSubtitle" />

<body class="bg-slate-100 dark:bg-slate-950 h-full transition-colors duration-200">
<div class="flex h-screen overflow-hidden">

    <x-sidebar />

    <div id="sidebar-backdrop" onclick="window.toggleMobileSidebar()"
         class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">

        <x-header :page-title="$pageTitle" :page-subtitle="$pageSubtitle" />

        <main class="flex-1 p-3 sm:p-6">
            {{ $slot }}
        </main>

        <x-footer />

    </div>
</div>

<div id="nav-tooltip-bubble"
     style="display:none;position:fixed;z-index:9999;pointer-events:none;transform:translateY(-50%)"
     class="px-2.5 py-1.5 text-xs font-medium text-slate-100 bg-slate-800 rounded-md shadow-lg whitespace-nowrap">
</div>

@flasher_render

@stack('scripts')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
if (! window.__layoutScriptsInitialized) {
window.__layoutScriptsInitialized = true;

// Delegated once for the whole app — any form with data-confirm="..." gets a SweetAlert2
// confirmation instead of the browser's plain confirm() popup before it actually submits.
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-confirm')) return;
    e.preventDefault();
    Swal.fire({
        title: form.dataset.confirm,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, continue',
    }).then(function (result) {
        if (result.isConfirmed) form.submit();
    });
});

// A Livewire action that doesn't redirect anywhere (e.g. Send Test Email) has no page load
// for php-flasher's server-rendered toast to appear on — components dispatch a "toast"
// browser event instead, shown here as a SweetAlert2 toast.
document.addEventListener('livewire:init', function () {
    Livewire.on('toast', function (data) {
        Swal.fire({
            toast: true, position: 'top-end', timer: 4000, timerProgressBar: true,
            showConfirmButton: false, icon: data.type, title: data.message,
        });
    });
});

window.toggleDarkMode = function () {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('color_scheme', isDark ? 'dark' : 'light');
    document.cookie = 'color_scheme=' + (isDark ? 'dark' : 'light') + ';path=/;max-age=31536000;SameSite=Lax';
    updateDarkIcon();
};

function updateDarkIcon() {
    const icon = document.getElementById('dark-mode-icon');
    if (!icon) return;
    icon.className = document.documentElement.classList.contains('dark') ? 'ti ti-sun text-base' : 'ti ti-moon text-base';
}

window.toggleMobileSidebar = function () {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    sidebar.classList.toggle('-translate-x-full');
    sidebar.classList.toggle('translate-x-0');
    backdrop.classList.toggle('hidden');
};

window.toggleSidebar = function () {
    const sidebar   = document.getElementById('sidebar');
    const collapsed = sidebar.classList.contains('sidebar-collapsed');
    sidebar.classList.toggle('sidebar-collapsed', !collapsed);
    sidebar.classList.toggle('sidebar-expanded',   collapsed);
    localStorage.setItem('sidebar_collapsed', collapsed ? '0' : '1');
    updateSidebarIcon();
    hideTooltip();
};

function updateSidebarIcon() {
    const icon    = document.getElementById('sidebar-toggle-icon');
    const sidebar = document.getElementById('sidebar');
    if (!icon) return;
    const collapsed = sidebar.classList.contains('sidebar-collapsed');
    icon.className  = collapsed
        ? 'ti ti-layout-sidebar-left-expand w-5 text-center text-base flex-shrink-0'
        : 'ti ti-layout-sidebar-left-collapse w-5 text-center text-base flex-shrink-0';
}

const tooltipEl = document.getElementById('nav-tooltip-bubble');

function showTooltip(el) {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('sidebar-collapsed')) return;
    const label = el.dataset.tooltip;
    if (!label) return;
    const rect = el.getBoundingClientRect();
    tooltipEl.textContent = label;
    tooltipEl.style.left  = (rect.right + 10) + 'px';
    tooltipEl.style.top   = (rect.top + rect.height / 2) + 'px';
    tooltipEl.style.display = 'block';
}

function hideTooltip() { if (tooltipEl) tooltipEl.style.display = 'none'; }

function initTooltips() {
    document.querySelectorAll('#sidebar [data-tooltip]').forEach(function (el) {
        el.addEventListener('mouseenter', function () { showTooltip(el); });
        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('click',      hideTooltip);
    });
}

function updateClock() {
    const timeEl = document.getElementById('live-clock-time');
    const dateEl = document.getElementById('live-clock-date');
    if (!timeEl || !dateEl) return;
    const now = new Date();
    timeEl.textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
    dateEl.textContent = now.toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
}
setInterval(updateClock, 1000);

document.addEventListener('livewire:navigated', function () {
    const storedScheme = localStorage.getItem('color_scheme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', storedScheme === 'dark' || (!storedScheme && prefersDark));

    if (localStorage.getItem('sidebar_collapsed') === '1') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('sidebar-expanded');
            sidebar.classList.add('sidebar-collapsed');
        }
    }
    updateSidebarIcon();
    updateDarkIcon();
    updateClock();
    initTooltips();

    document.querySelectorAll('#sidebar a, #sidebar button[type="submit"]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (window.innerWidth < 768) window.toggleMobileSidebar();
        });
    });
});

}
</script>

@livewireScripts

</body>
</html>
