<x-layout title="{{ $campaign->name }}" page-title="{{ $campaign->name }}" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Campaigns', 'url' => route('campaigns.index')], ['name' => $campaign->name]]" />

    <div class="flex items-center justify-end gap-3 mb-4 text-sm">
        @if($campaign->mailAccount?->repliesEnabled() && auth()->user()->hasPrivilege('campaigns.send'))
        <span class="text-slate-400 dark:text-slate-500">
            Replies last checked:
            {{ $campaign->mailAccount->imap_last_fetched_at?->ist()->format('d M Y, H:i') ?? 'never' }}
        </span>
        <form method="POST" action="{{ route('campaigns.fetch-replies', $campaign) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium px-3 py-1.5 rounded-lg transition-colors">
                <i class="ti ti-mail-forward text-base"></i>
                Check for replies
            </button>
        </form>
        @endif
        <div class="relative" x-data="{ open: false }">
            <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium px-3 py-1.5 rounded-lg transition-colors">
                <i class="ti ti-download text-base"></i> Export
            </button>
            <div x-show="open" x-cloak x-on:click.outside="open = false"
                 class="absolute right-0 mt-1 w-40 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg overflow-hidden z-10">
                @php $exportUrl = fn (string $format) => route('campaigns.export', [$campaign, $format]).'?'.http_build_query(request()->except('page')); @endphp
                <a href="{{ $exportUrl('xlsx') }}" class="block px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-spreadsheet mr-1"></i> Excel (.xlsx)
                </a>
                <a href="{{ $exportUrl('pdf') }}" class="block px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-type-pdf mr-1"></i> PDF
                </a>
            </div>
        </div>
    </div>

    @php
        $statusFilter = request('status');
        $baseQuery = fn (array $overrides = []) => request()->fullUrlWithQuery(array_merge(['page' => null], $overrides));
        $statCardClass = fn (?string $value) => 'stat-card block text-left transition-shadow'
            .($statusFilter === $value ? ' ring-2 ring-indigo-500' : '');
    @endphp

    @php $respondedFilter = request('responded'); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <a href="{{ $baseQuery(['status' => 'pending']) }}" class="{{ $statCardClass('pending') }}">
            <div class="stat-icon bg-slate-50 dark:bg-slate-900/30 text-slate-500 dark:text-slate-400"><i class="ti ti-clock"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Waiting</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['pending'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => 'sent']) }}" class="{{ $statCardClass('sent') }}">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400"><i class="ti ti-circle-check"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Sent</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['sent'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => 'failed']) }}" class="{{ $statCardClass('failed') }}">
            <div class="stat-icon bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400"><i class="ti ti-alert-circle"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Failed</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['failed'] ?? 0 }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['responded' => 'yes']) }}" class="stat-card block text-left transition-shadow{{ $respondedFilter === 'yes' ? ' ring-2 ring-indigo-500' : '' }}">
            <div class="stat-icon bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400"><i class="ti ti-checkbox"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Responded</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $respondedCount }}</p>
            </div>
        </a>
        <a href="{{ $baseQuery(['status' => null]) }}" class="{{ $statCardClass(null) }}">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400"><i class="ti ti-users"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ array_sum($statusCounts->all()) }}</p>
            </div>
        </a>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
        @if($sort !== 'id')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        @if($direction !== 'desc')<input type="hidden" name="direction" value="{{ $direction }}">@endif
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email…"
               x-data x-on:input.debounce.500ms="$el.form.requestSubmit()"
               class="flex-1 min-w-[180px] text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
        <select name="status" x-data x-on:change="$el.form.requestSubmit()"
                class="text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
            <option value="">All statuses</option>
            <option value="pending" @selected($statusFilter === 'pending')>Waiting</option>
            <option value="queued" @selected($statusFilter === 'queued')>Queued</option>
            <option value="sent" @selected($statusFilter === 'sent')>Sent</option>
            <option value="failed" @selected($statusFilter === 'failed')>Failed</option>
        </select>
        <select name="responded" x-data x-on:change="$el.form.requestSubmit()"
                class="text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
            <option value="">Responded — any</option>
            <option value="yes" @selected($respondedFilter === 'yes')>Responded</option>
            <option value="no" @selected($respondedFilter === 'no')>Not responded</option>
        </select>
        <noscript><button type="submit" class="text-sm font-medium px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">Filter</button></noscript>
        @if(request('q') || $statusFilter || $respondedFilter)
        <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm text-slate-500 hover:underline">Clear</a>
        @endif
    </form>

    @if($recipients->isNotEmpty() && auth()->user()->hasPrivilege('campaigns.send'))
    <form method="POST" action="{{ route('campaigns.mark-responded', $campaign) }}" class="flex items-center gap-3 mb-3 text-sm">
        @csrf
        @foreach($recipients as $recipient)<input type="hidden" name="ids[]" value="{{ $recipient->id }}">@endforeach
        <span class="text-slate-400 dark:text-slate-500">This page ({{ $recipients->count() }}):</span>
        <button type="submit" name="responded" value="1" class="text-teal-600 dark:text-teal-400 hover:underline">Mark all responded</button>
        <button type="submit" name="responded" value="0" class="text-slate-500 dark:text-slate-400 hover:underline">Mark all not responded</button>
    </form>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        @foreach(['name' => 'Recipient', 'email' => 'Email', 'status' => 'Status', 'sent_at' => 'Sent At'] as $field => $label)
                        @php
                            $nextDirection = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
                            $icon = $sort !== $field ? 'ti-selector' : ($direction === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending');
                        @endphp
                        <th class="text-left px-4 py-3">
                            <a href="{{ $baseQuery(['sort' => $field, 'direction' => $nextDirection]) }}"
                               class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $label }} <i class="ti {{ $icon }} text-sm"></i>
                            </a>
                        </th>
                        @endforeach
                        <th class="text-center px-4 py-3">Responded</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                @forelse($recipients as $recipient)
                <tbody x-data="{ showReplies: false }" class="divide-y divide-slate-100 dark:divide-slate-700">
                    @php
                        $statusColor = match($recipient->status) {
                            'sent' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400',
                            'failed' => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                            'queued' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-400',
                            default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $recipient->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ $recipient->email }}
                            @if($recipient->replies_count > 0)
                            <button type="button" x-on:click="showReplies = ! showReplies"
                                    class="ml-1 badge bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-400">
                                <i class="ti ti-message-reply text-sm"></i> {{ $recipient->replies_count }}
                            </button>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusColor }} capitalize">{{ $recipient->status === 'pending' ? 'Waiting' : $recipient->status }}</span>
                            @if($campaign->attachment_mode === 'zip_per_recipient' && ! $recipient->attachment_path)
                            <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400">No attachment</span>
                            @endif
                            @if($recipient->status === 'failed' && $recipient->error_message)
                            <p class="text-xs text-red-500 dark:text-red-400 mt-1">{{ $recipient->error_message }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-400 dark:text-slate-500">
                            @if($recipient->sent_at)
                                {{ $recipient->sent_at->ist()->format('d M Y, H:i') }}
                            @elseif($recipient->failed_at)
                                <span class="text-red-400 dark:text-red-500">Failed {{ $recipient->failed_at->ist()->format('d M Y, H:i') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if(auth()->user()->hasPrivilege('campaigns.send'))
                            <form method="POST" action="{{ route('campaigns.mark-responded', $campaign) }}" class="inline">
                                @csrf
                                <input type="hidden" name="ids[]" value="{{ $recipient->id }}">
                                <input type="hidden" name="responded" value="{{ $recipient->responded_at ? 0 : 1 }}">
                                <button type="submit" title="{{ $recipient->responded_at ? 'Responded on '.$recipient->responded_at->ist()->format('d M Y, H:i').' — click to unmark' : 'Click to mark as responded' }}"
                                        class="inline-flex items-center justify-center w-5 h-5 rounded border transition-colors {{ $recipient->responded_at ? 'bg-teal-600 border-teal-600 text-white' : 'border-slate-300 dark:border-slate-600 text-transparent hover:border-teal-500' }}">
                                    <i class="ti ti-check text-sm"></i>
                                </button>
                            </form>
                            @elseif($recipient->responded_at)
                            <i class="ti ti-check text-teal-600 dark:text-teal-400" title="Responded on {{ $recipient->responded_at->ist()->format('d M Y, H:i') }}"></i>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if(in_array($recipient->status, ['sent', 'failed']) && auth()->user()->hasPrivilege('campaigns.send'))
                            <div x-data="{ open: false }" class="inline-block text-left">
                                <div class="flex items-center justify-end gap-3">
                                    @if($recipient->status === 'failed')
                                    <form method="POST" action="{{ route('campaigns.retry-recipient', [$campaign, $recipient]) }}"
                                          data-confirm="Resend to {{ $recipient->email }}?">
                                        @csrf
                                        <button type="submit" class="text-indigo-600 dark:text-indigo-400 hover:underline">Retry</button>
                                    </form>
                                    <form method="POST" action="{{ route('campaigns.mark-sent-recipient', [$campaign, $recipient]) }}"
                                          data-confirm="Mark {{ $recipient->email }} as sent? Use this only if it was actually sent manually from the section's own inbox.">
                                        @csrf
                                        <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:underline">Mark as sent</button>
                                    </form>
                                    @endif
                                    <button type="button" x-on:click="open = ! open" class="text-slate-500 dark:text-slate-400 hover:underline">
                                        Resend{{ $campaign->attachment_mode === 'zip_per_recipient' ? ' / fix attachment' : ' to different email' }}…
                                    </button>
                                </div>
                                <form x-show="open" x-cloak method="POST"
                                      action="{{ route('campaigns.resend-recipient', [$campaign, $recipient]) }}"
                                      data-confirm="Resend this recipient?"
                                      class="mt-2 text-left bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg p-3 w-72">
                                    @csrf
                                    <label class="field-label">New email address</label>
                                    <input type="email" name="email" value="{{ $recipient->email }}" required
                                           class="field-input text-sm">
                                    @if($recipient->recipient_type !== 'manual')
                                    <label class="flex items-center gap-2 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        <input type="checkbox" name="save_to_directory" value="1" checked
                                               class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                        Also save as the on-file email for this {{ str_replace('_', ' ', $recipient->recipient_type) }}
                                    </label>
                                    @endif
                                    @if($campaign->attachment_mode === 'zip_per_recipient' && ! empty($availableAttachments))
                                    <label class="field-label mt-3">Attachment</label>
                                    <select name="attachment_path" class="field-input text-sm">
                                        <option value="" @selected(! $recipient->attachment_path)>No attachment</option>
                                        @foreach($availableAttachments as $path)
                                        <option value="{{ $path }}" @selected($recipient->attachment_path === $path)>{{ basename($path) }}</option>
                                        @endforeach
                                    </select>
                                    @if(! $recipient->attachment_path)
                                    <p class="field-hint">This recipient went out with no attachment — pick the right file above.</p>
                                    @endif
                                    @endif
                                    <button type="submit" class="mt-3 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors">
                                        Send
                                    </button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @if($recipient->replies_count > 0)
                    <tr x-show="showReplies" x-cloak>
                        <td colspan="6" class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50">
                            <div class="space-y-3">
                                @foreach($recipient->replies as $reply)
                                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-3">
                                    <div class="flex items-center justify-between text-xs text-slate-400 dark:text-slate-500 mb-1">
                                        <span class="font-medium text-slate-600 dark:text-slate-300">{{ $reply->from_name ?: $reply->from_address }}</span>
                                        <span>{{ $reply->received_at->ist()->format('d M Y, H:i') }}</span>
                                    </div>
                                    @if($reply->subject)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ $reply->subject }}</p>
                                    @endif
                                    <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($reply->body_text, 2000) }}</p>
                                </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
                @empty
                <tbody>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No recipients.</td></tr>
                </tbody>
                @endforelse
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $recipients->links() }}</div>

    <script>
        // Compares this load's per-recipient status against the last one seen for this
        // campaign (sessionStorage) and names exactly who changed — the only way to know a
        // send actually finished when nothing else on this static page pushes a notification.
        document.addEventListener('DOMContentLoaded', function () {
            const key = 'campaignRecipientStatus_{{ $campaign->slug }}';
            const current = @json($recipients->getCollection()->mapWithKeys(fn ($r) => [$r->id => ['email' => $r->email, 'status' => $r->status]]));
            const previous = JSON.parse(sessionStorage.getItem(key) || 'null');
            sessionStorage.setItem(key, JSON.stringify(current));

            if (previous) {
                const sent = [], failed = [];
                for (const [id, row] of Object.entries(current)) {
                    const before = previous[id];
                    if (! before || before.status === row.status) continue;
                    if (row.status === 'sent') sent.push(row.email);
                    if (row.status === 'failed') failed.push(row.email);
                }
                if (sent.length || failed.length) {
                    const describe = (list, label) => list.length
                        ? label + ': ' + list.slice(0, 3).join(', ') + (list.length > 3 ? ` (+${list.length - 3} more)` : '')
                        : '';
                    const message = [describe(sent, 'Sent'), describe(failed, 'Failed')].filter(Boolean).join(' — ');
                    flashToast(failed.length && ! sent.length ? 'error' : 'success', message);
                }
            }
        });

        // php-flasher's own <script class="flasher-js"> block (layout.blade.php's
        // @flasher_render) loads window.flasher asynchronously — same wire:navigate/CDN-load
        // race as the Quill editor and dashboard Chart.js fixes elsewhere in this app. It's
        // guaranteed to load eventually (@flasher_render runs on every page), so a short poll
        // is enough; no need to inject our own duplicate <script> tag.
        function flashToast(type, message, attempt = 0) {
            if (window.flasher) { window.flasher[type](message); return; }
            if (attempt > 40) return; // ~4s — something's actually wrong, give up silently
            setTimeout(() => flashToast(type, message, attempt + 1), 100);
        }

        @if($recipients->contains(fn ($r) => in_array($r->status, ['pending', 'queued'], true)))
        // Auto-refresh while a send is still in flight — the page has no realtime layer, so
        // without this a resend/retry sits showing "Waiting" until someone manually reloads,
        // even after the job has actually finished. Skips a tick if the user is mid-typing
        // (search box) or has a resend form's email field open, so it can't yank focus/wipe
        // an in-progress edit out from under them.
        setInterval(() => {
            const active = document.activeElement?.tagName;
            if (active === 'INPUT' || active === 'SELECT' || active === 'TEXTAREA') return;
            window.location.reload();
        }, 6000);
        @endif
    </script>
</x-layout>
