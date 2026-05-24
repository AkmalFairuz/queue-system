<x-layouts.app :title="'Counter - '.$tenant->name" :page="'counter'">
    <main class="page-shell space-y-6">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <a href="{{ route('home') }}" class="transition hover:text-stone-900">Beranda</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="transition hover:text-stone-900">{{ $tenant->name }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">Counter</span>
        </nav>

        <div
            id="counter-root"
            data-snapshot-url="{{ route('counter.snapshot', $tenant->id) }}"
            data-channel="tenant.{{ $tenant->id }}.display"
            data-context-url="{{ route('counter.context', $tenant->id) }}"
            data-call-next-url="{{ route('counter.call-next', $tenant->id) }}"
            data-recall-url="{{ route('counter.recall', $tenant->id) }}"
            data-start-serving-url="{{ route('counter.start-serving', $tenant->id) }}"
            data-complete-url="{{ route('counter.complete', $tenant->id) }}"
            data-skip-url="{{ route('counter.skip', $tenant->id) }}"
            data-cancel-url="{{ route('counter.cancel', $tenant->id) }}"
            class="space-y-6"
        ></div>

        <script id="counter-payload" type="application/json">
            {!! json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </main>
</x-layouts.app>
