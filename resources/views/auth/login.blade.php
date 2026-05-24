<x-layouts.app :title="'Masuk'" :page="'login'">
    <main class="page-shell">
        <div class="mx-auto max-w-md">
            <section class="panel">
                <div class="panel-header">
                    <h1 class="section-title">Masuk Petugas</h1>
                </div>
                <form method="POST" action="{{ route('login.store') }}" class="panel-body space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="field-label">Email<span class="field-required">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" required autofocus>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="field-label">Kata Sandi<span class="field-required">*</span></label>
                        <div class="relative">
                            <input id="password" name="password" type="password" class="field pr-11" required>
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-500 transition hover:text-stone-800"
                                data-password-toggle="#password"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-stone-300 text-amber-600">
                        Ingat saya
                    </label>

                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        Masuk
                    </button>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>
