<x-layouts.app :title="'Tiket Antrian - '.$tenant->name" :page="'queue-ticket-result'">
    <main class="page-shell space-y-6">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <a href="{{ route('home') }}" class="transition hover:text-stone-900">Beranda</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">{{ $tenant->name }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <a href="{{ route('tenant.queue', $tenant->code) }}" class="transition hover:text-stone-900">Ambil Antrian</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">Tiket</span>
        </nav>

        <div
            id="queue-ticket-result-root"
            data-snapshot-url="{{ route('tenant.display.snapshot', $tenant->code) }}"
            data-channel="tenant.{{ $tenant->id }}.display"
            data-service-id="{{ $ticket['service_id'] }}"
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]"
        >
            <section class="panel">
                <div class="panel-header">
                    <h2 class="section-title">Tiket Antrian Anda</h2>
                </div>
                <div class="panel-body">
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-5 py-6 text-center">
                        <p class="text-sm font-medium text-stone-600">{{ $tenant->name }}</p>
                        <p class="mt-2 text-5xl font-semibold tracking-tight text-stone-900">{{ $ticket['queue_number'] }}</p>
                        <div class="mt-4 space-y-1 text-sm text-stone-700">
                            <p>{{ $ticket['service_name'] }}</p>
                            <p>{{ $ticket['service_date_label'] }}</p>
                            <p>{{ $ticket['schedule_label'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body" data-last-called-ticket>
                    <div class="empty-state">Memuat panggilan terakhir.</div>
                </div>
            </section>
        </div>

        <script id="queue-ticket-result-payload" type="application/json">
            {!! json_encode(['service' => $initialDisplayService], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </main>
</x-layouts.app>
