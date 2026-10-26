<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Attendance') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    {{-- Guarded so a missing Vite build degrades to an unstyled page instead of throwing
         ViteManifestNotFoundException (500). Fix: run `npm run build` and upload public/build. --}}
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Brand panel -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 relative overflow-hidden">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-brand-500/30 blur-3xl"></div>
            <div class="absolute -bottom-32 -right-16 w-96 h-96 rounded-full bg-brand-400/20 blur-3xl"></div>

            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 w-full">
                <a href="/" class="flex items-center gap-3 text-white">
                    <x-application-logo class="w-10 h-10 text-white" />
                    <span class="text-xl font-bold tracking-tight">{{ config('app.name', 'Attendance') }}</span>
                </a>

                <div class="max-w-md">
                    <h1 class="text-3xl xl:text-4xl font-bold text-white leading-snug">
                        Attendance made simple, accurate, and effortless.
                    </h1>
                    <p class="mt-4 text-brand-100/90 text-base xl:text-lg">
                        Track check-ins, manage time-off, and get powerful reports — all in one clean workspace built for modern teams.
                    </p>

                    <ul class="mt-8 space-y-3">
                        @foreach ([
                        'One-tap check in and check out',
                        'Real-time team attendance overview',
                        'Automated timesheets and reports',
                        ] as $item)
                        <li class="flex items-center gap-3 text-brand-50">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-white/15">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span class="text-sm xl:text-base">{{ $item }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-brand-200/70 text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Attendance') }}. All rights reserved.
                </p>
            </div>
        </div>

        <!-- Form panel -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 bg-gradient-to-b from-brand-50 via-white to-white">
            <a href="/" class="flex items-center gap-3 lg:hidden mb-8 text-brand-700">
                <x-application-logo class="w-10 h-10" />
                <span class="text-xl font-bold tracking-tight">{{ config('app.name', 'Attendance') }}</span>
            </a>

            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl shadow-brand-900/5 ring-1 ring-gray-100 px-8 py-10 sm:px-10">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>