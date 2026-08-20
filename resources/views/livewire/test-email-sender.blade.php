<div class="max-w-xl">
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 space-y-5">

        <div class="flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
            <i class="ti ti-flask flex-shrink-0 mt-0.5"></i>
            <span>Sends through the system's Resend account (same as sign-in emails), not a section's Gmail — use this to check the mailer works without needing a Gmail app password set up yet.</span>
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
                class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                Send Test Email
            </button>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</a>
        </div>
    </div>
</div>
