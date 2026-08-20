@props(['model' => 'body', 'value' => ''])

<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">

<div wire:ignore>
    <div id="{{ $model }}-quill" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">{!! $value !!}</div>
</div>
<textarea wire:model="{{ $model }}" id="{{ $model }}-hidden" class="hidden"></textarea>

<style>
    #{{ $model }}-quill .ql-toolbar { border: none; border-bottom: 1px solid rgb(226 232 240); }
    .dark #{{ $model }}-quill .ql-toolbar { border-bottom-color: rgb(51 65 85); }
    #{{ $model }}-quill .ql-container { border: none; font-size: 0.875rem; min-height: 220px; }
    .dark #{{ $model }}-quill .ql-stroke { stroke: rgb(148 163 184); }
    .dark #{{ $model }}-quill .ql-fill { fill: rgb(148 163 184); }
    .dark #{{ $model }}-quill .ql-picker-label { color: rgb(148 163 184); }
</style>

<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
    (function () {
        const quill = new Quill('#{{ $model }}-quill', { theme: 'snow' });
        const hidden = document.getElementById('{{ $model }}-hidden');
        hidden.value = quill.root.innerHTML;

        quill.on('text-change', function () {
            hidden.value = quill.root.innerHTML;
            hidden.dispatchEvent(new Event('input'));
        });

        window['{{ $model }}Quill'] = quill;

        // Server-side changes to $body (picking a saved template, saving a new one) can't
        // reach this DOM on their own — it's wire:ignore'd so Quill, not Livewire, owns it.
        // Called directly (not wrapped in a livewire:init/livewire:navigated listener): this
        // script re-runs fresh each time this block re-enters the DOM (step navigation), but
        // livewire:init fires exactly once per page load — wrapping in it here would mean this
        // listener never (re-)registers for any Quill instance created after the first.
        Livewire.on('quill-set-content', function (data) {
            if (data.model !== '{{ $model }}') return;
            quill.root.innerHTML = data.html || '';
            hidden.value = quill.root.innerHTML;
        });
    })();
</script>
