<x-layouts.app :title="'Buat Tenant'" :page="'tenant-create'">
    <main class="page-shell">
        <div class="mx-auto max-w-3xl">
            <section class="panel">
                <div class="panel-header">
                    <h1 class="section-title">Buat Tenant Baru</h1>
                </div>
                <form method="POST" action="{{ route('tenants.store') }}" class="panel-body space-y-5">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="name" class="field-label">Nama Tenant<span class="field-required">*</span></label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" class="field" required autofocus>
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="code" class="field-label">Kode Tenant<span class="field-required">*</span></label>
                            <input id="code" name="code" type="text" value="{{ old('code') }}" class="field" required>
                            <p class="field-help">Dipakai di URL publik. Gunakan huruf kecil, angka, atau tanda hubung.</p>
                            @error('code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-5 md:grid-cols-[220px,1fr]">
                        <div>
                            <label for="tts_language" class="field-label">Bahasa TTS<span class="field-required">*</span></label>
                            <select id="tts_language" name="tts_language" class="field" required>
                                <option value="id-ID" @selected(old('tts_language', 'id-ID') === 'id-ID')>Indonesia (id-ID)</option>
                                <option value="en-US" @selected(old('tts_language') === 'en-US')>English (en-US)</option>
                            </select>
                            @error('tts_language')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tts_template" class="field-label">Template TTS<span class="field-required">*</span></label>
                            <textarea id="tts_template" name="tts_template" class="field min-h-32" required>{{ old('tts_template', 'Nomor antrian {queue}, silakan menuju {counter}') }}</textarea>
                            <p class="field-help">Gunakan <code>{queue}</code> untuk nomor antrian dan <code>{counter}</code> untuk nama counter.</p>
                            @error('tts_template')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3">
                        <a href="{{ route('home') }}" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-building-circle-check"></i>
                            Buat Tenant
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>
