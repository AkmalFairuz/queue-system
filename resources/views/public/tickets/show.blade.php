<x-layouts.app :title="'Ambil Antrian - '.$service['name'].' - '.$tenant->name" :page="'queue-ticket'">
    <main class="page-shell space-y-6">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <a href="{{ route('home') }}" class="transition hover:text-stone-900">Beranda</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">{{ $tenant->name }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <a href="{{ route('tenant.queue', $tenant->code) }}" class="transition hover:text-stone-900">Pilih Layanan</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">{{ $service['name'] }}</span>
        </nav>

        <div id="queue-ticket-root" data-submit-url="{{ route('tenant.queue.store', $tenant->code) }}">
            <section class="panel">
                <div class="panel-header flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="section-title">{{ $service['name'] }}</h2>
                    </div>
                    <span class="badge {{ $service['is_open'] ? 'badge-serving' : 'badge-cancelled' }}">
                        {{ $service['availability_label'] }}
                    </span>
                </div>
                <div class="panel-body" data-queue-ticket-details>
                    <div class="empty-state">
                        Memuat detail jadwal layanan.
                    </div>
                </div>
            </section>
        </div>

        <script id="queue-ticket-payload" type="application/json">
            {!! json_encode(['service' => $service], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </main>
</x-layouts.app>
