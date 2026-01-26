<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Login - Qammaris Perfumes</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-brand-black antialiased flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md p-8">
        
        <div class="text-center mb-12">
            <h1 class="font-bold tracking-[0.3em] text-2xl uppercase mb-2">Qammaris</h1>
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Akses Admin</p>
        </div>

        <form action="{{ route('login.perform') }}" method="POST" class="space-y-6">
            @csrf

            <div class="group">
                <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-black transition-colors">Alamat Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full bg-transparent border-b border-gray-300 py-3 text-sm focus:border-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                    placeholder="admin@qammaris.com">
                @error('email')
                    <span class="text-xs text-red-500 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="group">
                <label class="block text-[10px] uppercase tracking-widest text-gray-400 mb-2 group-focus-within:text-black transition-colors">Kata Sandi</label>
                <input type="password" name="password" required
                    class="w-full bg-transparent border-b border-gray-300 py-3 text-sm focus:border-black focus:outline-none focus:ring-0 transition-colors placeholder-gray-300"
                    placeholder="Kata sandi">
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full bg-black text-white py-4 text-xs font-bold uppercase tracking-[0.2em] hover:bg-gray-800 transition-all duration-300 shadow-lg">
                    Masuk
                </button>
            </div>
            
        </form>

        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" class="text-[10px] text-gray-400 hover:text-black uppercase tracking-widest border-b border-transparent hover:border-black transition-all pb-0.5">
                Kembali ke Beranda
            </a>
        </div>

    </div>

</body>
</html>
