<div>
    <x-breadcrumb :items="[['name' => 'Campaigns', 'url' => route('campaigns.index')], ['name' => $campaign->name]]" />

    <div class="flex items-center justify-end gap-3 mb-4 text-sm">
        @if($campaign->mailAccount?->repliesEnabled() && auth()->user()->hasPrivilege('campaigns.send'))
        <span class="text-slate-400 dark:text-slate-500" wire:loading.remove wire:target="fetchReplies">
            Replies last checked:
            {{ $campaign->mailAccount->imap_last_fetched_at?->ist()->format('d M Y, H:i') ?? 'never' }}
        </span>
        <button type="button" wire:click="fetchReplies" wire:loading.attr="disabled" wire:target="fetchReplies"
                class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-60">
            <i class="ti ti-mail-forward text-base" wire:loading.class="animate-spin ti-loader-2" wire:target="fetchReplies"></i>
            Check for replies
        </button>
        @endif
        <div class="relative" x-data="{ open: false }">
            <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium px-3 py-1.5 rounded-lg transition-colors">
                <i class="ti ti-download text-base"></i> Export
            </button>
            <div x-show="open" x-cloak x-on:click.outside="open = false"
                 class="absolute right-0 mt-1 w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg overflow-hidden z-10">
                <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Excel</p>
                <button type="button" wire:click="export('xlsx')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-spreadsheet mr-1"></i> All (current filters)
                </button>
                <button type="button" wire:click="export('xlsx', 'no')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-spreadsheet mr-1"></i> Not responded only
                </button>
                <button type="button" wire:click="export('xlsx', 'yes')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-spreadsheet mr-1"></i> Responded only
                </button>
                <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700">PDF</p>
                <button type="button" wire:click="export('pdf')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-type-pdf mr-1"></i> All (current filters)
                </button>
                <button type="button" wire:click="export('pdf', 'no')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-type-pdf mr-1"></i> Not responded only
                </button>
                <button type="button" wire:click="export('pdf', 'yes')" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i class="ti ti-file-type-pdf mr-1"></i> Responded only
                </button>
            </div>
        </div>
    </div>

    @php
        $statCardClass = fn (?string $value) => 'stat-card block text-left transition-shadow cursor-pointer'
            .($statusFilter === ($value ?? '') ? ' ring-2 ring-indigo-500' : '');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <button type="button" wire:click="$set('statusFilter', 'pending')" class="{{ $statCardClass('pending') }}">
            <div class="stat-icon bg-slate-50 dark:bg-slate-900/30 text-slate-500 dark:text-slate-400"><i class="ti ti-clock"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Waiting</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['pending'] ?? 0 }}</p>
            </div>
        </button>
        <button type="button" wire:click="$set('statusFilter', 'sent')" class="{{ $statCardClass('sent') }}">
            <div class="stat-icon bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400"><i class="ti ti-circle-check"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Sent</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['sent'] ?? 0 }}</p>
            </div>
        </button>
        <button type="button" wire:click="$set('statusFilter', 'failed')" class="{{ $statCardClass('failed') }}">
            <div class="stat-icon bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400"><i class="ti ti-alert-circle"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Failed</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $statusCounts['failed'] ?? 0 }}</p>
            </div>
        </button>
        <button type="button" wire:click="$set('respondedFilter', 'yes')"
                class="stat-card block text-left transition-shadow cursor-pointer{{ $respondedFilter === 'yes' ? ' ring-2 ring-indigo-500' : '' }}">
            <div class="stat-icon bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400"><i class="ti ti-checkbox"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Responded</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $respondedCount }}</p>
            </div>
        </button>
        <button type="button" wire:click="$set('statusFilter', '')" class="{{ $statCardClass('') }}">
            <div class="stat-icon bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400"><i class="ti ti-users"></i></div>
            <div>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ array_sum($statusCounts->all()) }}</p>
            </div>
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <input type="text" wire:model.live.debounce.500ms="search" placeholder="Search name or email…"
               class="flex-1 min-w-[180px] text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
        <select wire:model.live="statusFilter" class="text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
            <option value="">All statuses</option>
            <option value="pending">Waiting</option>
            <option value="queued">Queued</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
        </select>
        <select wire:model.live="respondedFilter" class="text-sm border border-slate-200 dark:border-slate-700 dark:bg-slate-800 rounded-lg px-3 py-2">
            <option value="">Responded — any</option>
            <option value="yes">Responded</option>
            <option value="no">Not responded</option>
        </select>
        @if($search || $statusFilter || $respondedFilter)
        <button type="button" wire:click="clearFilters" class="text-sm text-slate-500 hover:underline">Clear</button>
        @endif
    </div>

    @if($recipients->isNotEmpty() && auth()->user()->hasPrivilege('campaigns.send'))
    <div class="flex items-center gap-3 mb-3 text-sm">
        <span class="text-slate-400 dark:text-slate-500">This page ({{ $recipients->count() }}):</span>
        <button type="button" wire:click="bulkMarkResponded(true)" class="text-teal-600 dark:text-teal-400 hover:underline">Mark all responded</button>
        <button type="button" wire:click="bulkMarkResponded(false)" class="text-slate-500 dark:text-slate-400 hover:underline">Mark all not responded</button>
    </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden relative">
        <div wire:loading.class="opacity-50" wire:target="search, statusFilter, respondedFilter, sortBy, gotoPage, previousPage, nextPage" class="overflow-x-auto transition-opacity">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                    <tr>
                        @foreach(['name' => 'Recipient', 'status' => 'Status'] as $field => $label)
                        @php
                            $icon = $sort !== $field ? 'ti-selector' : ($direction === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending');
                        @endphp
                        <th class="text-left px-4 py-3">
                            <button type="button" wire:click="sortBy('{{ $field }}')"
                               class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $label }} <i class="ti {{ $icon }} text-sm"></i>
                            </button>
                        </th>
                        @endforeach
                        <th class="text-center px-4 py-3">Responded</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                @forelse($recipients as $recipient)
                <tbody x-data="{ showReplies: false }" class="divide-y divide-slate-100 dark:divide-slate-700" wire:key="recipient-{{ $recipient->id }}">
                    @php
                        $statusColor = match($recipient->status) {
                            'sent' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400',
                            'failed' => 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400',
                            'queued' => 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-400',
                            default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                        };
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                            {{ $recipient->name ?: '—' }}
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
                        <td class="px-4 py-3 text-center">
                            @if(auth()->user()->hasPrivilege('campaigns.send'))
                            <button type="button" wire:click="toggleResponded({{ $recipient->id }})"
                                    title="{{ $recipient->responded_at ? 'Responded on '.$recipient->responded_at->ist()->format('d M Y, H:i').' — click to unmark' : 'Click to mark as responded' }}"
                                    class="inline-flex items-center justify-center w-5 h-5 rounded border transition-colors {{ $recipient->responded_at ? 'bg-teal-600 border-teal-600 text-white' : 'border-slate-300 dark:border-slate-600 text-transparent hover:border-teal-500' }}">
                                <i class="ti ti-check text-sm"></i>
                            </button>
                            @elseif($recipient->responded_at)
                            <i class="ti ti-check text-teal-600 dark:text-teal-400" title="Responded on {{ $recipient->responded_at->ist()->format('d M Y, H:i') }}"></i>
                            @else
                            <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if(in_array($recipient->status, ['sent', 'failed']) && auth()->user()->hasPrivilege('campaigns.send'))
                            <div class="inline-block text-left">
                                <div class="flex items-center justify-end gap-3">
                                    @if($recipient->status === 'failed')
                                    <button type="button" wire:click="retry({{ $recipient->id }})" wire:confirm="Resend to {{ $recipient->email }}?"
                                            class="text-indigo-600 dark:text-indigo-400 hover:underline">Retry</button>
                                    <button type="button" wire:click="markSent({{ $recipient->id }})"
                                            wire:confirm="Mark {{ $recipient->email }} as sent? Use this only if it was actually sent manually from the section's own inbox."
                                            class="text-emerald-600 dark:text-emerald-400 hover:underline">Mark as sent</button>
                                    @endif
                                    @unless($recipient->responded_at)
                                    <button type="button" wire:click="toggleResend({{ $recipient->id }})" class="text-slate-500 dark:text-slate-400 hover:underline">
                                        Resend{{ $campaign->attachment_mode === 'zip_per_recipient' ? ' / fix attachment' : ' to different email' }}…
                                    </button>
                                    @endunless
                                </div>
                                @if($resendOpenId === $recipient->id)
                                <form wire:submit="resend({{ $recipient->id }})"
                                      class="mt-2 text-left bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg p-3 w-72">
                                    <label class="field-label">New email address</label>
                                    <input type="email" wire:model="resendEmail" required class="field-input text-sm">
                                    @error('resendEmail')<p class="field-err-msg">{{ $message }}</p>@enderror
                                    @if($recipient->recipient_type !== 'manual')
                                    <label class="flex items-center gap-2 mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        <input type="checkbox" wire:model="resendSaveToDirectory"
                                               class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                        Also save as the on-file email for this {{ str_replace('_', ' ', $recipient->recipient_type) }}
                                    </label>
                                    @endif
                                    @if($campaign->attachment_mode === 'zip_per_recipient' && ! empty($availableAttachments))
                                    <label class="field-label mt-3">Attachment</label>
                                    <select wire:model="resendAttachmentPath" class="field-input text-sm">
                                        <option value="">No attachment</option>
                                        @foreach($availableAttachments as $path)
                                        <option value="{{ $path }}">{{ basename($path) }}</option>
                                        @endforeach
                                    </select>
                                    @if(! $recipient->attachment_path)
                                    <p class="field-hint">This recipient went out with no attachment — pick the right file above.</p>
                                    @endif
                                    @endif
                                    <button type="submit" wire:loading.attr="disabled" wire:target="resend({{ $recipient->id }})"
                                            class="mt-3 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors disabled:opacity-60">
                                        Send
                                    </button>
                                </form>
                                @endif
                            </div>
                            @endif
                        </td>
                    </tr>
                    @if($recipient->replies_count > 0)
                    <tr x-show="showReplies" x-cloak>
                        <td colspan="4" class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50">
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
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No recipients.</td></tr>
                </tbody>
                @endforelse
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $recipients->links() }}</div>

    @if($hasInFlight)
    {{-- A send is still in flight — poll for updates instead of the old sessionStorage-diff +
         full-page setInterval(reload) hack; Livewire's own diffing morphs just the changed rows
         in place (no navigation, no lost search focus, no sidebar flash) and stops entirely
         once nothing is pending/queued anymore. --}}
    <div wire:poll.6s="$refresh"></div>
    @endif
</div>
