<x-layouts.app :title="'Beranda'" :page="'home'">
    @php
        $activeTab = auth()->check() && request('tab') !== 'tenants' ? 'managed' : 'tenants';
    @endphp

    <main class="page-shell space-y-8">
        <section class="panel overflow-hidden">
            <div class="grid gap-6 bg-white px-6 py-8 sm:px-8 sm:py-10">
                <div class="space-y-3">
                    <span class="badge border-stone-300 bg-amber-200/70 text-amber-900">Sistem Antrian</span>
                    <h1 class="hero-title">Kelola dan tampilkan antrian tanpa proses yang rumit.</h1>
                </div>

                @guest
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            <i class="fa-solid fa-user-lock"></i>
                            Masuk sebagai petugas
                        </a>
                    </div>
                @endguest
            </div>
        </section>

        <section class="panel">
            <div class="tabs">
                @auth
                    <a href="{{ route('home', ['tab' => 'managed']) }}" class="tab-button {{ $activeTab === 'managed' ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building-user"></i>
                        Tenant Saya
                    </a>
                @endauth
                <a href="{{ route('home', ['tab' => 'tenants']) }}" class="tab-button {{ $activeTab === 'tenants' ? 'is-active' : '' }}">
                    <i class="fa-solid fa-building"></i>
                    Daftar Tenant
                </a>
            </div>
            <div class="panel-body">
                @if ($activeTab === 'managed')
                    <div class="mb-4 flex flex-wrap justify-end gap-3">
                        <a href="{{ route('tenants.create') }}" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Buat Tenant
                        </a>
                    </div>

                    @if (count($data['managed_tenants']) === 0)
                        <div class="empty-state">Belum ada tenant yang terhubung dengan akun Anda. Buat tenant pertama untuk mulai mengelola antrian.</div>
                    @else
                        <div class="table-wrap rounded-none border-0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <span class="inline-flex items-center gap-2">
                                                <i class="fa-solid fa-building text-stone-500"></i>
                                                <span>Tenant</span>
                                            </span>
                                        </th>
                                        <th class="w-px whitespace-nowrap">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="fa-solid fa-screwdriver-wrench text-stone-500"></i>
                                                <span>Aksi</span>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data['managed_tenants'] as $tenant)
                                        <tr>
                                            <td class="font-medium">{{ $tenant['name'] }}</td>
                                            <td class="whitespace-nowrap">
                                                <div class="table-actions">
                                                    @if ($tenant['can_manage'])
                                                        <a href="{{ route('admin.show', $tenant['id']) }}" class="btn btn-primary">
                                                            <i class="fa-solid fa-gear"></i>
                                                            Admin
                                                        </a>
                                                    @endif
                                                    @if ($tenant['can_work'])
                                                        <a href="{{ route('counter.show', $tenant['id']) }}" class="btn btn-secondary">
                                                            <i class="fa-solid fa-headset"></i>
                                                            Counter
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('tenant.display', $tenant['code']) }}" class="btn btn-secondary">
                                                        <i class="fa-solid fa-tv"></i>
                                                        Display
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if ($activeTab === 'tenants')
                    @if (count($data['tenants']) === 0)
                        <div class="empty-state">Belum ada tenant yang tersedia.</div>
                    @else
                        <div class="table-wrap rounded-none border-0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <span class="inline-flex items-center gap-2">
                                                <i class="fa-solid fa-building text-stone-500"></i>
                                                <span>Tenant</span>
                                            </span>
                                        </th>
                                        <th class="w-px whitespace-nowrap">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="fa-solid fa-screwdriver-wrench text-stone-500"></i>
                                                <span>Aksi</span>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data['tenants'] as $tenant)
                                        <tr>
                                            <td class="font-medium">{{ $tenant['name'] }}</td>
                                            <td class="whitespace-nowrap">
                                                <div class="table-actions">
                                                    <a href="{{ route('tenant.queue', $tenant['code']) }}" class="btn btn-primary">
                                                        <i class="fa-solid fa-ticket-simple"></i>
                                                        Ambil Antrian
                                                    </a>
                                                    <a href="{{ route('tenant.display', $tenant['code']) }}" class="btn btn-secondary">
                                                        <i class="fa-solid fa-display"></i>
                                                        Layar Display
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    </main>
</x-layouts.app>
