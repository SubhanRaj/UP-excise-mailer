<div class="max-w-xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">

        <div>
            <label class="field-label">Send Via</label>
            @if(auth()->user()->isAdmin())
            <div class="flex gap-2 mt-1 mb-3">
                <button type="button" wire:click="$set('sendVia', 'system')" class="px-3 py-2 rounded-lg text-sm border {{ $sendVia === 'system' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">System (Resend)</button>
                <button type="button" wire:click="$set('sendVia', 'mail_account')" class="px-3 py-2 rounded-lg text-sm border {{ $sendVia === 'mail_account' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">A Mail Account</button>
            </div>
            @endif

            @if($sendVia === 'mail_account')
            <select wire:model="mailAccountId" class="field-input @error('mailAccountId') field-error @enderror">
                <option value="">— Select a mail account —</option>
                @foreach($mailAccounts as $account)
                <option value="{{ $account->id }}">{{ $account->gmail_address }} ({{ $account->section->name }})</option>
                @endforeach
            </select>
            @error('mailAccountId')<p class="field-err-msg">{{ $message }}</p>@enderror
            @if($mailAccounts->isEmpty())
            <p class="field-hint text-amber-600 dark:text-amber-400">
                @if(auth()->user()->isAdmin())
                    No mail account exists yet — add one under Mail Accounts.
                @else
                    No mail account is set up for you yet — ask a SuperAdmin to add one under Mail Accounts.
                @endif
            </p>
            @endif
            @endif
        </div>

        <div>
            <label class="field-label">Template</label>
            <select wire:model="templateId" class="field-input @error('templateId') field-error @enderror">
                <option value="">— Select —</option>
                @foreach($templates as $template)
                <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
            @error('templateId')<p class="field-err-msg">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="field-label">Send To</label>
            <div class="flex gap-2 mt-1 mb-3">
                <button type="button" wire:click="$set('recipientMode', 'user')" class="px-3 py-2 rounded-lg text-sm border {{ $recipientMode === 'user' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">An App User</button>
                <button type="button" wire:click="$set('recipientMode', 'manual')" class="px-3 py-2 rounded-lg text-sm border {{ $recipientMode === 'manual' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Any Email Address</button>
            </div>

            @if($recipientMode === 'user')
            <select wire:model="userId" class="field-input @error('userId') field-error @enderror">
                <option value="">— Select a user —</option>
                @foreach($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
            @error('userId')<p class="field-err-msg">{{ $message }}</p>@enderror
            @else
            <input type="email" wire:model="manualEmail" placeholder="you@example.com" class="field-input @error('manualEmail') field-error @enderror">
            @error('manualEmail')<p class="field-err-msg">{{ $message }}</p>@enderror
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <i wire:loading wire:target="send" class="ti ti-loader-2 animate-spin text-base"></i>
                <span wire:loading.remove wire:target="send">Send Test Email</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</a>
        </div>
    </div>
</div>
