@props(['value' => ''])

{{-- Quill (MIT, CDN, no build step) — matches this app's no-build-tooling convention
     (Tailwind Play CDN, self-hosted Tabler webfont). WYSIWYG only touches the visible
     rich-text area; {{ '{{variable}}' }} placeholders are typed as plain text and pass
     through untouched into the stored HTML. --}}
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">

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

<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
    (function () {
        const editorEl = document.getElementById('quill-editor');
        if (! editorEl) return;

        const quill = new Quill(editorEl, { theme: 'snow' });
        const hidden = document.getElementById('body-input');
        quill.on('text-change', function () {
            hidden.value = quill.root.innerHTML;
        });
        hidden.closest('form')?.addEventListener('submit', function () {
            hidden.value = quill.root.innerHTML;
        });
    })();
</script>
