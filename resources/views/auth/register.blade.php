<x-layouts.app :title="'Daftar'" :page="'register'">
    <main class="page-shell">
        <div class="mx-auto max-w-lg">
            <section class="panel">
                <div class="panel-header">
                    <h1 class="section-title">Buat Akun</h1>
                </div>
                <form method="POST" action="{{ route('register.store') }}" class="panel-body space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="field-label">Nama<span class="field-required">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" class="field" required autofocus>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="field-label">Email<span class="field-required">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" required>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="register-password" class="field-label">Kata Sandi<span class="field-required">*</span></label>
                        <div class="relative">
                            <input id="register-password" name="password" type="password" class="field pr-11" required>
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-500 transition hover:text-stone-800"
                                data-password-toggle="#register-password"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="register-password-confirmation" class="field-label">Konfirmasi Kata Sandi<span class="field-required">*</span></label>
                        <div class="relative">
                            <input id="register-password-confirmation" name="password_confirmation" type="password" class="field pr-11" required>
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-500 transition hover:text-stone-800"
                                data-password-toggle="#register-password-confirmation"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fa-solid fa-user-plus"></i>
                        Daftar
                    </button>

                    <p class="text-center text-sm text-stone-600">
                        Sudah punya akun?
                        <a href="{{ route('login') }}" class="font-medium text-amber-700 hover:text-amber-800">Masuk di sini</a>
                    </p>
                </form>
            </section>
        </div>
    </main>
</x-layouts.app>
