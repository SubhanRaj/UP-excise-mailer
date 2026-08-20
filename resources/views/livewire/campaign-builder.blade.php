<div class="max-w-3xl">

    <div class="flex items-center gap-2 mb-6 text-xs font-medium text-slate-400 dark:text-slate-500">
        <span class="{{ $step >= 1 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">1. Who</span>
        <i class="ti ti-chevron-right"></i>
        <span class="{{ $step >= 2 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">2. What to Say</span>
        <i class="ti ti-chevron-right"></i>
        <span class="{{ $step >= 3 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">3. Files</span>
        <i class="ti ti-chevron-right"></i>
        <span class="{{ $step >= 4 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">4. Review &amp; Send</span>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">

        {{-- STEP 1 — name, mail account, who to send to --}}
        @if($step === 1)
        <div class="space-y-5">
            <div>
                <label class="field-label">Campaign Name</label>
                <input type="text" wire:model="name" placeholder="e.g. Diwali Circular to All Districts" class="field-input @error('name') field-error @enderror">
                @error('name')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Send From</label>
                <select wire:model="mailAccountId" class="field-input @error('mailAccountId') field-error @enderror">
                    <option value="">— Select a mail account —</option>
                    @foreach($mailAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->gmail_address }} ({{ $account->section->name }})</option>
                    @endforeach
                </select>
                @error('mailAccountId')<p class="field-err-msg">{{ $message }}</p>@enderror
                @if($mailAccounts->isEmpty())
                <p class="field-hint text-amber-600 dark:text-amber-400">No mail account is set up for you yet — ask a SuperAdmin to add one under Mail Accounts.</p>
                @endif
            </div>

            <div>
                <label class="field-label">Who Should Receive This?</label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mt-1">
                    <button type="button" wire:click="$set('scope', 'all')" class="px-3 py-2 rounded-lg text-sm border {{ $scope === 'all' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Everyone</button>
                    <button type="button" wire:click="$set('scope', 'zones')" class="px-3 py-2 rounded-lg text-sm border {{ $scope === 'zones' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Zones</button>
                    <button type="button" wire:click="$set('scope', 'divisions')" class="px-3 py-2 rounded-lg text-sm border {{ $scope === 'divisions' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Divisions</button>
                    <button type="button" wire:click="$set('scope', 'districts')" class="px-3 py-2 rounded-lg text-sm border {{ $scope === 'districts' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Districts</button>
                    <button type="button" wire:click="$set('scope', 'recipient_list')" class="px-3 py-2 rounded-lg text-sm border {{ $scope === 'recipient_list' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Imported List</button>
                </div>
                @error('scope')<p class="field-err-msg mt-2">{{ $message }}</p>@enderror
            </div>

            @if($scope === 'all')
            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
                <i class="ti ti-world flex-shrink-0"></i>
                Every district's officer, department-wide — no picking needed.
            </div>
            @elseif($scope === 'zones')
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 max-h-64 overflow-y-auto">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2 pb-2 border-b border-slate-100 dark:border-slate-700 cursor-pointer">
                    <input type="checkbox" wire:click="selectAllZones" {{ count($selectedZoneIds) === $zones->count() && $zones->count() > 0 ? 'checked' : '' }} class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    Select All
                </label>
                @foreach($zones as $zone)
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 py-1 cursor-pointer">
                    <input type="checkbox" wire:model="selectedZoneIds" value="{{ $zone->id }}" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    {{ $zone->name }} <span class="text-xs text-slate-400">({{ $zone->jc_email ?: 'no email on file' }})</span>
                </label>
                @endforeach
            </div>
            @elseif($scope === 'divisions')
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 max-h-64 overflow-y-auto">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2 pb-2 border-b border-slate-100 dark:border-slate-700 cursor-pointer">
                    <input type="checkbox" wire:click="selectAllDivisions" {{ count($selectedDivisionIds) === $divisions->count() && $divisions->count() > 0 ? 'checked' : '' }} class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    Select All
                </label>
                @foreach($divisions as $division)
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 py-1 cursor-pointer">
                    <input type="checkbox" wire:model="selectedDivisionIds" value="{{ $division->id }}" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    {{ $division->name }} <span class="text-xs text-slate-400">({{ $division->dc_email ?: 'no email on file' }})</span>
                </label>
                @endforeach
            </div>
            @elseif($scope === 'districts')
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 max-h-64 overflow-y-auto">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2 pb-2 border-b border-slate-100 dark:border-slate-700 cursor-pointer">
                    <input type="checkbox" wire:click="selectAllDistricts" {{ count($selectedDistrictIds) === $districts->count() && $districts->count() > 0 ? 'checked' : '' }} class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    Select All
                </label>
                @foreach($districts as $district)
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 py-1 cursor-pointer">
                    <input type="checkbox" wire:model="selectedDistrictIds" value="{{ $district->id }}" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600">
                    {{ $district->name }} <span class="text-xs text-slate-400">({{ $district->deo_email ?: 'no email on file' }})</span>
                </label>
                @endforeach
            </div>
            @elseif($scope === 'recipient_list')
            <div>
                <select wire:model="recipientListId" class="field-input">
                    <option value="">— Select a list —</option>
                    @foreach($recipientLists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }} ({{ $list->items_count }} people)</option>
                    @endforeach
                </select>
                @if($recipientLists->isEmpty())
                <p class="field-hint">No lists imported yet — go to Recipient Lists to import one first.</p>
                @endif
            </div>
            @endif

            <p class="text-sm text-slate-500 dark:text-slate-400">
                <i class="ti ti-users mr-1"></i>
                <strong>{{ $this->recipientCount }}</strong> {{ $this->recipientCount === 1 ? 'person' : 'people' }} will receive this — only those with an email on file are counted.
            </p>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="proceedToTemplate" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">Next</button>
                <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</a>
            </div>
        </div>
        @endif

        {{-- STEP 2 — pick or write what to say --}}
        @if($step === 2)
        <div class="space-y-5">
            <div>
                <label class="field-label">Use a Saved Template</label>
                <div class="flex items-center gap-2">
                    <select wire:model="templateId" wire:change="updatedTemplateId" class="field-input">
                        <option value="">— Write it myself —</option>
                        @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="$set('showNewTemplateModal', true)"
                        class="flex-shrink-0 whitespace-nowrap text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        + New Template
                    </button>
                </div>
            </div>

            @if(count($this->availableVariables))
            <div>
                <label class="field-label">Available Words You Can Use</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->availableVariables as $var)
                    <button type="button" wire:click="insertSubjectVariable('{{ $var }}')"
                        onclick="insertVariable('body', '{{ \App\Models\MailTemplate::variableToken($var) }}')"
                        class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 hover:text-indigo-700 dark:hover:text-indigo-400 cursor-pointer">
                        {{ ucfirst($var) }}
                    </button>
                    @endforeach
                </div>
                <p class="field-hint">Click a word to add it to the Subject — it also drops into the message wherever your cursor is. Each one is swapped for the real value when the email is sent.</p>
            </div>
            @endif

            <div>
                <label class="field-label">Subject</label>
                <input type="text" wire:model="subject" class="field-input @error('subject') field-error @enderror">
                @error('subject')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="field-label">Message</label>
                <div wire:key="body-editor-{{ $step }}">
                    @include('livewire._quill-editor', ['model' => 'body', 'value' => $body])
                </div>
                @error('body')<p class="field-err-msg">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="proceedToAttachments" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">Next</button>
                <button type="button" wire:click="$set('step', 1)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </div>

        {{-- Quick "new template" panel — a lighter-weight version of the full Templates page,
             good enough for a one-off campaign; open /templates for the full rich editor. --}}
        @if($showNewTemplateModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4" wire:click.self="$set('showNewTemplateModal', false)">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 w-full max-w-lg space-y-4">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">New Template</h3>

                <div>
                    <label class="field-label">Name</label>
                    <input type="text" wire:model="newTemplateName" class="field-input @error('newTemplateName') field-error @enderror">
                    @error('newTemplateName')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Subject</label>
                    <input type="text" wire:model="newTemplateSubject" class="field-input @error('newTemplateSubject') field-error @enderror">
                    @error('newTemplateSubject')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label">Message</label>
                    <textarea wire:model="newTemplateBody" rows="6" class="field-input @error('newTemplateBody') field-error @enderror"></textarea>
                    <p class="field-hint">Plain text or simple HTML. For a richer editor, use the full Templates page instead.</p>
                    @error('newTemplateBody')<p class="field-err-msg">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="saveNewTemplate" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">Save &amp; Use</button>
                    <button type="button" wire:click="$set('showNewTemplateModal', false)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Cancel</button>
                </div>
            </div>
        </div>
        @endif
        @endif

        {{-- STEP 3 — attachments --}}
        @if($step === 3)
        <div class="space-y-5">
            <div>
                <label class="field-label">Do You Want to Attach Any Files?</label>
                <div class="flex gap-2 mt-1">
                    <button type="button" wire:click="$set('wantsAttachment', false)" class="px-4 py-2 rounded-lg text-sm border {{ !$wantsAttachment ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">No</button>
                    <button type="button" wire:click="$set('wantsAttachment', true)" class="px-4 py-2 rounded-lg text-sm border {{ $wantsAttachment ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}">Yes</button>
                </div>
            </div>

            @if($wantsAttachment)
            <div>
                <label class="field-label">What Kind of File?</label>
                <div class="space-y-2 mt-1">
                    <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
                        <input type="radio" wire:model="attachmentMode" value="single_file" class="mt-1 text-indigo-600">
                        <span><strong>One file for everyone</strong> — the same document goes to every recipient.</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
                        <input type="radio" wire:model="attachmentMode" value="zip_per_recipient" class="mt-1 text-indigo-600">
                        <span><strong>A different file per recipient</strong> — upload a zip; we'll match each file to a recipient by name.</span>
                    </label>
                </div>
            </div>

            @if($attachmentMode === 'single_file')
            <div>
                <label class="field-label">File</label>
                <input type="file" wire:model="singleFile" class="field-input @error('singleFile') field-error @enderror">
                @error('singleFile')<p class="field-err-msg">{{ $message }}</p>@enderror
                <div wire:loading wire:target="singleFile" class="text-xs text-slate-400 mt-1">Uploading…</div>
            </div>
            @elseif($attachmentMode === 'zip_per_recipient')
            <div class="space-y-3">
                <div>
                    <label class="field-label">Zip File</label>
                    <input type="file" wire:model="zipFile" accept=".zip" class="field-input @error('zipFile') field-error @enderror">
                    @error('zipFile')<p class="field-err-msg">{{ $message }}</p>@enderror
                    <div wire:loading wire:target="zipFile,uploadZip" class="text-xs text-slate-400 mt-1">Extracting and matching…</div>
                    @if($zipFile && empty($zipExtractedFiles))
                    <button type="button" wire:click="uploadZip" class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Extract &amp; Match</button>
                    @endif
                </div>

                @if(!empty($zipExtractedFiles))
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                            <tr>
                                <th class="text-left px-3 py-2">Recipient</th>
                                <th class="text-left px-3 py-2">Matched File</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($this->candidateRecipients() as $i => $r)
                            <tr>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $r['name'] ?: $r['email'] }}</td>
                                <td class="px-3 py-2">
                                    <select wire:model="zipMatchOverride.{{ $i }}" class="field-input py-1">
                                        <option value="">— No file —</option>
                                        @foreach($zipExtractedFiles as $file)
                                        <option value="{{ $file }}">{{ $file }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endif
            @endif

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="proceedToReview" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">Next</button>
                <button type="button" wire:click="$set('step', 2)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </div>
        @endif

        {{-- STEP 4 — review & send --}}
        @if($step === 4)
        <div class="space-y-5">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">Ready to Send?</h3>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-slate-400 dark:text-slate-500">Campaign</dt><dd class="text-slate-700 dark:text-slate-200 font-medium">{{ $name }}</dd></div>
                <div><dt class="text-slate-400 dark:text-slate-500">Sending From</dt><dd class="text-slate-700 dark:text-slate-200 font-medium">{{ $mailAccounts->firstWhere('id', $mailAccountId)?->gmail_address }}</dd></div>
                <div><dt class="text-slate-400 dark:text-slate-500">Recipients</dt><dd class="text-slate-700 dark:text-slate-200 font-medium">{{ $this->recipientCount }} people</dd></div>
                <div><dt class="text-slate-400 dark:text-slate-500">Attachments</dt><dd class="text-slate-700 dark:text-slate-200 font-medium">{{ $wantsAttachment ? ($attachmentMode === 'single_file' ? 'One file for everyone' : 'Different file per recipient') : 'None' }}</dd></div>
            </dl>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold mb-1">Subject</p>
                <p class="text-sm text-slate-700 dark:text-slate-200 mb-3">{{ $subject }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wide font-semibold mb-1">Message</p>
                <div class="text-sm text-slate-700 dark:text-slate-200 prose prose-sm dark:prose-invert max-w-none">{!! $body !!}</div>
            </div>

            <div class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-3">
                <i class="ti ti-clock flex-shrink-0 mt-0.5"></i>
                <span>Emails go out gradually, a few seconds apart, so the mailbox isn't flagged for sending too fast. This can take a few minutes for a large group.</span>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="button" wire:click="confirmAndQueue" wire:loading.attr="disabled" wire:target="confirmAndQueue"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                    Confirm &amp; Send
                </button>
                <button type="button" wire:click="$set('step', 3)" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">Back</button>
            </div>
        </div>
        @endif

    </div>
</div>

<script>
    function insertVariable(model, text) {
        const quill = window[model + 'Quill'];
        if (!quill) return;
        const range = quill.getSelection(true) || { index: quill.getLength() };
        quill.insertText(range.index, text, 'user');
        quill.setSelection(range.index + text.length);
    }
</script>
