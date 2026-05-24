@props([
    'title' => config('app.name', 'Queue System'),
    'page' => '',
])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="reverb-key" content="{{ env('REVERB_APP_KEY', 'app-key') }}">
        <meta name="reverb-host" content="{{ env('REVERB_HOST', request()->getHost()) }}">
        <meta name="reverb-port" content="{{ (string) env('REVERB_PORT', 8080) }}">
        <meta name="reverb-scheme" content="{{ env('REVERB_SCHEME', request()->getScheme()) }}">

        <title>{{ $title }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-page="{{ $page }}" class="font-sans">
        @if ($page !== 'display')
            <nav class="fixed inset-x-0 top-0 z-40 border-b border-black/10 bg-amber-600 shadow-lg">
                <div class="mx-auto flex h-[65px] max-w-[1200px] items-center justify-between px-4 text-white sm:px-6">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.2em]">
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-white/15 text-white">
                            <i class="fa-solid fa-ticket"></i>
                        </span>
                        <span>ANTRIAN</span>
                    </a>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('home') }}" class="nav-link">
                            Beranda
                        </a>

                        @auth
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="nav-link">
                                    Keluar
                                </button>
                            </form>
                        @else
                            <a href="{{ route('register') }}" class="nav-link">
                                Daftar
                            </a>
                            <a href="{{ route('login') }}" class="nav-link">
                                Masuk
                            </a>
                        @endauth
                    </div>
                </div>
            </nav>
        @endif

        {{ $slot }}

        <div id="toast-root" class="pointer-events-none fixed right-4 top-[82px] z-50 flex w-[min(92vw,360px)] flex-col gap-3"></div>
        <div id="modal-root"></div>
    </body>
</html>
