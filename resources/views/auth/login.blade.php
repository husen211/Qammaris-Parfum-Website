<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Login - Qammaris Perfumes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-navigation-shell="auth" class="bg-white text-brand-black antialiased flex items-center justify-center min-h-screen">

    <div data-navigation-content class="w-full max-w-md p-8">
        
        <div class="text-center mb-12">
            <h1 class="font-bold tracking-[0.3em] text-2xl uppercase mb-2">Qammaris</h1>
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Akses Admin</p>
        </div>

        <form action="{{ route('login.perform') }}" method="POST" class="space-y-6">
            @csrf

            @if (session('error'))
                <p role="alert" class="border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</p>
            @endif

            <div class="group">
                <label for="login" class="block text-[10px] uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-black transition-colors">Email atau username</label>
                <input id="login" type="text" name="login" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                    class="w-full bg-transparent border-b border-gray-300 py-3 text-base focus:border-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                    placeholder="admin@qammaris.com atau username">
                @error('login')
                    <span class="text-xs text-red-500 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="group">
                <label for="password" class="block text-[10px] uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-black transition-colors">Kata Sandi</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                    class="w-full bg-transparent border-b border-gray-300 py-3 text-sm focus:border-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                    placeholder="Kata sandi">
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full bg-black text-white py-4 text-xs font-bold uppercase tracking-[0.2em] hover:bg-gray-800 transition-colors duration-300 shadow-lg">
                    Masuk
                </button>
            </div>
            
        </form>

        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" class="text-[10px] text-gray-400 hover:text-black uppercase tracking-widest border-b border-transparent hover:border-black transition-colors pb-0.5">
                Kembali ke Beranda
            </a>
        </div>

    </div>

    @include('components.navigation-skeleton')
</body>
</html>
