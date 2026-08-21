<x-layout title="Recipients" page-title="Recipients" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipients']]" />

    <div class="flex items-center justify-between mb-4 border-b border-slate-200 dark:border-slate-700">
        <div class="flex items-center gap-1">
            @foreach(['zones' => 'Zones', 'divisions' => 'Divisions', 'districts' => 'Districts'] as $key => $label)
            <a href="{{ route('recipients.index', ['tab' => $key]) }}" wire:navigate
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $tab === $key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
        @if(auth()->user()->hasPrivilege('recipients.manage'))
        <a href="{{ route('recipients.import') }}" wire:navigate
           class="mb-2 inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
            <i class="ti ti-file-spreadsheet text-base"></i>
            Import Officer Directory (.xlsx)
        </a>
        @endif
    </div>

    @php
        $baseQuery = fn (array $overrides = []) => request()->fullUrlWithQuery($overrides);
        $sortLink = function (string $field, string $label) use ($baseQuery, $sort, $direction) {
            $nextDirection = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
            $icon = $sort !== $field ? 'ti-selector' : ($direction === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending');

            return '<a href="'.$baseQuery(['sort' => $field, 'direction' => $nextDirection]).'" class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">'
                .$label.' <i class="ti '.$icon.' text-sm"></i></a>';
        };
    @endphp

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if($sort !== 'name')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        @if($direction !== 'asc')<input type="hidden" name="direction" value="{{ $direction }}">@endif
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or officer…"
               x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
               class="flex-1 min-w-[180px] text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
        <noscript><button type="submit" class="text-sm font-medium px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">Search</button></noscript>
        @if(request('q'))
        <a href="{{ route('recipients.index', ['tab' => $tab]) }}" class="text-sm text-slate-500 hover:underline">Clear</a>
        @endif
    </form>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            @if($tab === 'zones')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">{!! $sortLink('name', 'Zone') !!}</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('officer', 'JEC Name') !!}</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('email', 'JEC Email') !!}</th>
                        <th class="text-left px-4 py-3">JEC CUG</th>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))<th class="text-right px-4 py-3">Actions</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($zones as $zone)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $zone->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 {{ $zone->jc_name ? '' : 'italic text-slate-400 dark:text-slate-500' }}">{{ $zone->officerDisplayName() }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $zone->jc_email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $zone->jc_cug ?? '—' }}</td>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('recipients.zones.edit', $zone) }}" wire:navigate class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                <i class="ti ti-pencil text-base"></i>
                            </a>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">{{ request('q') ? 'No zones match this search.' : 'No zones seeded.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @elseif($tab === 'divisions')
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">{!! $sortLink('name', 'Division') !!}</th>
                        <th class="text-left px-4 py-3">Zone</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('officer', 'DEC Name') !!}</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('email', 'DEC Email') !!}</th>
                        <th class="text-left px-4 py-3">DEC CUG</th>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))<th class="text-right px-4 py-3">Actions</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($divisions as $division)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $division->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $division->zone?->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 {{ $division->dc_name ? '' : 'italic text-slate-400 dark:text-slate-500' }}">{{ $division->officerDisplayName() }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $division->dc_email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $division->dc_cug ?? '—' }}</td>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('recipients.divisions.edit', $division) }}" wire:navigate class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                <i class="ti ti-pencil text-base"></i>
                            </a>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">{{ request('q') ? 'No divisions match this search.' : 'No divisions seeded.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-4 py-3">{!! $sortLink('name', 'District') !!}</th>
                        <th class="text-left px-4 py-3">Division</th>
                        <th class="text-left px-4 py-3">Zone</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('officer', 'DEO Name') !!}</th>
                        <th class="text-left px-4 py-3">{!! $sortLink('email', 'DEO Email') !!}</th>
                        <th class="text-left px-4 py-3">DEO CUG</th>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))<th class="text-right px-4 py-3">Actions</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($districts as $district)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $district->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $district->division?->name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $district->division?->zone?->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300 {{ $district->deo_name ? '' : 'italic text-slate-400 dark:text-slate-500' }}">{{ $district->officerDisplayName() }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                            @if(!$district->deo_email)
                            <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">Missing</span>
                            @else
                            {{ $district->deo_email }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $district->deo_cug ?? '—' }}</td>
                        @if(auth()->user()->hasPrivilege('recipients.manage'))
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('recipients.districts.edit', $district) }}" wire:navigate class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors" title="Edit">
                                <i class="ti ti-pencil text-base"></i>
                            </a>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">{{ request('q') ? 'No districts match this search.' : 'No districts seeded.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div>
    </div>
</x-layout>
