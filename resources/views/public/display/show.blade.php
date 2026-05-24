<x-layouts.app :title="'Display - '.$tenant->name" :page="'display'">
    <main class="page-shell space-y-6">
        <div
            id="display-root"
            data-snapshot-url="{{ route('tenant.display.snapshot', $tenant->code) }}"
            data-channel="tenant.{{ $tenant->id }}.display"
            class="space-y-6"
        ></div>

        <script id="display-payload" type="application/json">
            {!! json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </main>
</x-layouts.app>
