<x-layout title="Import Recipient List" page-title="Import Recipient List" page-subtitle="UP Department of Excise — Mailer">
    <x-breadcrumb :items="[['name' => 'Recipient Lists', 'url' => route('recipient-lists.index')], ['name' => 'Import']]" />

    @livewire('recipient-list-import-wizard', ['mode' => request('mode', 'file')])
</x-layout>
