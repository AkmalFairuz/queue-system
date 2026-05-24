<x-layouts.app :title="$pageTitle.' - '.$tenant->name" :page="'admin'">
    <main class="page-shell space-y-6">
        @php
            $navSection = $navSection ?? $section;
            $breadcrumbs = $breadcrumbs ?? [
                ['label' => 'Beranda', 'href' => route('home')],
                ['label' => $tenant->name, 'href' => route('admin.show', $tenant->id)],
                ['label' => $pageTitle],
            ];
        @endphp

        <nav class="flex flex-wrap items-center gap-2 text-sm text-stone-500">
            @foreach ($breadcrumbs as $index => $breadcrumb)
                @if ($index > 0)
                    <span>/</span>
                @endif

                @if (! empty($breadcrumb['href']))
                    <a href="{{ $breadcrumb['href'] }}" class="transition hover:text-stone-900">{{ $breadcrumb['label'] }}</a>
                @else
                    <span class="font-medium text-stone-900">{{ $breadcrumb['label'] }}</span>
                @endif
            @endforeach
        </nav>

        <div class="tabs rounded-md border border-stone-200 px-2 pb-0 pt-2">
            <a href="{{ route('admin.show', $tenant->id) }}" class="tab-button {{ $navSection === 'overview' ? 'is-active' : '' }}">
                <i class="fa-solid fa-chart-column"></i>
                <span>Ringkasan</span>
            </a>
            <a href="{{ route('admin.services.page', $tenant->id) }}" class="tab-button {{ $navSection === 'services' ? 'is-active' : '' }}">
                <i class="fa-solid fa-bell-concierge"></i>
                <span>Layanan</span>
            </a>
            <a href="{{ route('admin.counters.page', $tenant->id) }}" class="tab-button {{ $navSection === 'counters' ? 'is-active' : '' }}">
                <i class="fa-solid fa-headset"></i>
                <span>Counter</span>
            </a>
            <a href="{{ route('admin.users.page', $tenant->id) }}" class="tab-button {{ $navSection === 'users' ? 'is-active' : '' }}">
                <i class="fa-solid fa-users"></i>
                <span>Akses</span>
            </a>
            <a href="{{ route('admin.settings.page', $tenant->id) }}" class="tab-button {{ $navSection === 'settings' ? 'is-active' : '' }}">
                <i class="fa-solid fa-sliders"></i>
                <span>Pengaturan</span>
            </a>
        </div>

        <div
            id="admin-root"
            data-section="{{ $section }}"
            data-snapshot-url="{{ $section === 'service-schedules' ? route('admin.service-schedules.snapshot', [$tenant->id, $initialData['service']['id']]) : route('admin.snapshot', $tenant->id) }}"
            data-services-store-url="{{ route('admin.services.store', $tenant->id) }}"
            data-service-url-base="{{ url('/tenant/'.$tenant->id.'/admin/services') }}"
            data-service-schedules-page-base="{{ url('/tenant/'.$tenant->id.'/admin/layanan') }}"
            data-schedules-store-url="{{ route('admin.schedules.store', $tenant->id) }}"
            data-schedule-url-base="{{ url('/tenant/'.$tenant->id.'/admin/schedules') }}"
            data-counters-store-url="{{ route('admin.counters.store', $tenant->id) }}"
            data-counter-url-base="{{ url('/tenant/'.$tenant->id.'/admin/counters') }}"
            data-users-store-url="{{ route('admin.users.store', $tenant->id) }}"
            data-user-url-base="{{ url('/tenant/'.$tenant->id.'/admin/users') }}"
            data-settings-url="{{ route('admin.settings.update', $tenant->id) }}"
            data-tenant-delete-url="{{ route('tenants.destroy', $tenant->id) }}"
            class="space-y-6"
        ></div>

        <script id="admin-payload" type="application/json">
            {!! json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </main>
</x-layouts.app>
