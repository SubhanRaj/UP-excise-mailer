<aside id="sidebar" class="sidebar-expanded bg-slate-950 flex flex-col flex-shrink-0 overflow-hidden fixed inset-y-0 left-0 z-50 -translate-x-full transition-transform duration-200 ease-in-out md:translate-x-0 md:relative">

    <div class="px-4 py-5 flex items-center gap-3 border-b border-slate-800/70 min-w-0">
        <div class="w-9 h-9 rounded-lg bg-indigo-600 flex items-center justify-center flex-shrink-0">
            <i class="ti ti-mail-forward text-white text-lg"></i>
        </div>
        <div class="sidebar-logo-text min-w-0">
            <p class="text-sm font-semibold text-white leading-tight">Excise Mailer</p>
            <p class="text-xs text-slate-500 truncate">UP Dept. of Excise</p>
        </div>
    </div>

    <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.slate.700)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-slate-700 [&::-webkit-scrollbar-thumb]:rounded-full">

        <a href="{{ route('dashboard') }}" wire:navigate data-tooltip="Dashboard"
           class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-layout-dashboard w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Campaigns"
           class="nav-link {{ request()->routeIs('campaigns.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-send w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Campaigns</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Templates"
           class="nav-link {{ request()->routeIs('templates.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-template w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Templates</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Recipients"
           class="nav-link {{ request()->routeIs('recipients.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-map-2 w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Recipients</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Recipient Lists"
           class="nav-link {{ request()->routeIs('recipient-lists.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-list-details w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Recipient Lists</span>
        </a>

        @auth
        @if(auth()->user()->isAdmin())
        <span class="nav-section-label">Manage</span>

        <a href="#" wire:navigate data-tooltip="Mail Accounts"
           class="nav-link {{ request()->routeIs('admin.mail-accounts.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-brand-gmail w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Mail Accounts</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Sections"
           class="nav-link {{ request()->routeIs('admin.sections.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-building w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Sections</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Users"
           class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-users w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Users</span>
        </a>

        <a href="#" wire:navigate data-tooltip="Designations"
           class="nav-link {{ request()->routeIs('admin.designations.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-id-badge-2 w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Designations</span>
        </a>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->hasPrivilege('activity-logs.view'))
        <a href="#" wire:navigate data-tooltip="Activity Log"
           class="nav-link {{ request()->routeIs('admin.activity.*') ? 'nav-link-active' : 'nav-link-idle' }}">
            <i class="ti ti-activity w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text">Activity Log</span>
        </a>
        @endif
        @endauth

    </nav>

    <div class="px-2 pb-2">
        <button id="sidebar-toggle" onclick="window.toggleSidebar()" data-tooltip="Expand sidebar"
            class="nav-link nav-link-idle w-full text-slate-500 hover:text-slate-300">
            <i id="sidebar-toggle-icon" class="ti ti-layout-sidebar-left-collapse w-5 text-center text-base flex-shrink-0"></i>
            <span class="sidebar-text text-xs">Collapse</span>
        </button>
    </div>

    <div class="px-3 py-4 border-t border-slate-800/70 flex items-center gap-3 min-w-0">
        @auth
        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
             data-tooltip="{{ auth()->user()->name }}">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <div class="sidebar-user-text flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-200 truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->role }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="sidebar-user-text flex-shrink-0">
            @csrf
            <button type="submit" data-tooltip="Log out" class="text-slate-600 hover:text-slate-300 transition-colors" title="Logout">
                <i class="ti ti-logout text-sm"></i>
            </button>
        </form>
        @else
        <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400 flex-shrink-0" data-tooltip="Guest">G</div>
        <div class="sidebar-user-text flex-1 min-w-0">
            <p class="text-sm font-medium text-slate-400 truncate">Guest</p>
            <p class="text-xs text-slate-600 truncate">Visitor</p>
        </div>
        <a href="{{ route('login') }}" data-tooltip="Sign in" class="sidebar-user-text flex-shrink-0 text-slate-500 hover:text-slate-200 transition-colors">
            <i class="ti ti-login-2 text-xl"></i>
        </a>
        @endauth
    </div>

</aside>
