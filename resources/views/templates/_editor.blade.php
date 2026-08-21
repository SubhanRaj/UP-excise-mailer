@props(['value' => ''])

{{-- Quill (MIT, CDN, no build step) — matches this app's no-build-tooling convention
     (Tailwind Play CDN, self-hosted Tabler webfont). WYSIWYG only touches the visible
     rich-text area; {{ '{{variable}}' }} placeholders are typed as plain text and pass
     through untouched into the stored HTML. --}}
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">

<div class="flex items-center justify-between gap-2 mb-2">
    <select id="insert-variable" class="field-input !w-auto text-sm">
        <option value="">Insert a variable…</option>
        @foreach(['district', 'division', 'zone', 'officer', 'cug', 'name', 'email'] as $var)
        <option value="{{ \App\Models\MailTemplate::variableToken($var) }}">{{ ucfirst($var) }}</option>
        @endforeach
    </select>
    <button type="button" id="preview-template" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
        <i class="ti ti-eye text-base"></i> Preview with sample data
    </button>
</div>

<div id="quill-editor" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">{!! $value !!}</div>
<textarea name="body" id="body-input" class="hidden">{{ $value }}</textarea>

<style>
    #quill-editor .ql-toolbar { border: none; border-bottom: 1px solid rgb(226 232 240); }
    .dark #quill-editor .ql-toolbar { border-bottom-color: rgb(51 65 85); }
    #quill-editor .ql-container { border: none; font-size: 0.875rem; min-height: 260px; }
    .dark #quill-editor .ql-stroke { stroke: rgb(148 163 184); }
    .dark #quill-editor .ql-fill { fill: rgb(148 163 184); }
    .dark #quill-editor .ql-picker-label { color: rgb(148 163 184); }
</style>

<script>
    (function () {
        const editorEl = document.getElementById('quill-editor');
        if (! editorEl) return;

        // wire:navigate clones/re-runs this <script> tag on every SPA visit, but a dynamically
        // inserted <script src> (unlike one parsed from raw HTML on a hard reload) loads
        // asynchronously — so `Quill` can still be undefined here on a fresh navigation even
        // though this same code works after a hard reload. Load it ourselves and wait.
        if (typeof Quill === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js';
            script.onload = initEditor;
            document.head.appendChild(script);
            return;
        }

        initEditor();

        function initEditor() {

        const quill = new Quill(editorEl, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [false, 2, 3] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link', 'blockquote'],
                    ['clean'],
                ],
            },
        });

        const hidden = document.getElementById('body-input');
        quill.on('text-change', function () {
            hidden.value = quill.root.innerHTML;
        });
        hidden.closest('form')?.addEventListener('submit', function () {
            hidden.value = quill.root.innerHTML;
        });

        document.getElementById('insert-variable')?.addEventListener('change', function () {
            if (! this.value) return;
            const range = quill.getSelection(true) || { index: quill.getLength() };
            quill.insertText(range.index, this.value, 'user');
            quill.setSelection(range.index + this.value.length);
            this.value = '';
        });

        // Deliberately obvious placeholders, not the logged-in admin's own name — a real send
        // never uses that (officer always comes from the actual recipient's district/division/
        // zone record), and using it here could look like that's how a real send behaves.
        const sampleVars = {
            district: 'Sample District', division: 'Sample Division', zone: 'Sample Zone',
            officer: 'Sample Officer Name', cug: '9876543210',
            name: 'Sample Recipient Name', email: 'you@example.com',
        };

        function renderSample(text) {
            return text.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, key) {
                return Object.prototype.hasOwnProperty.call(sampleVars, key) ? sampleVars[key] : match;
            });
        }

        document.getElementById('preview-template')?.addEventListener('click', function () {
            const subjectEl = document.querySelector('[name="subject"]');
            const subject = renderSample(subjectEl ? subjectEl.value : '');
            const body = renderSample(quill.root.innerHTML);

            Swal.fire({
                title: 'Preview',
                width: 700,
                html: '<div style="text-align:left"><p style="font-size:0.8rem;color:#94a3b8;margin-bottom:0.25rem">Subject</p>'
                    + '<p style="font-weight:600;margin-bottom:1rem">' + (subject || '<em>(empty)</em>') + '</p>'
                    + '<p style="font-size:0.8rem;color:#94a3b8;margin-bottom:0.25rem">Body</p>'
                    + '<div style="border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem">' + body + '</div></div>',
                confirmButtonText: 'Close',
            });
        });
        }
    })();
</script>
