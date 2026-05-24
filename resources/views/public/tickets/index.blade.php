<x-layouts.app :title="'Ambil Antrian - '.$tenant->name" :page="'queue-ticket'">
    <main class="page-shell space-y-6">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            <a href="{{ route('home') }}" class="transition hover:text-stone-900">Beranda</a>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">{{ $tenant->name }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
            <span class="font-medium text-stone-900">Ambil Antrian</span>
        </nav>

        <section class="panel">
            <div class="panel-header">
                <h2 class="section-title">Pilih Layanan</h2>
            </div>
            <div class="panel-body">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($services as $service)
                        <article class="rounded-md border border-stone-200 bg-white">
                            <div class="space-y-4 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-stone-900">{{ $service['name'] }}</h3>
                                    </div>
                                    <span class="badge {{ $service['is_open'] ? 'badge-serving' : 'badge-cancelled' }}">
                                        {{ $service['availability_label'] }}
                                    </span>
                                </div>
                                <p class="text-sm text-stone-600">
                                    {{ $service['is_open'] ? 'Lanjutkan untuk memilih tanggal dan jadwal layanan.' : 'Belum ada tanggal atau jadwal yang bisa dipilih saat ini.' }}
                                </p>
                                @if ($service['is_open'])
                                    <a href="{{ route('tenant.queue.service', [$tenant->code, $service['id']]) }}" class="btn btn-primary w-full">
                                        <i class="fa-solid fa-arrow-right"></i>
                                        Pilih Layanan
                                    </a>
                                @else
                                    <button type="button" class="btn btn-muted w-full" disabled>
                                        <i class="fa-solid fa-ban"></i>
                                        Tidak Tersedia
                                    </button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</x-layouts.app>
