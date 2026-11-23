@vite(['resources/css/app.css', 'resources/js/app.js'])

<main class="flex min-h-screen items-center justify-center bg-[#f1eff6] px-4 py-10 sm:px-6">
    <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-indigo-950/5 sm:p-8">
        <a href="{{ route('companies.index') }}" class="text-sm font-bold tracking-wide text-indigo-700">LeadRadar</a>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-slate-950">Sign in to site monitoring</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Manage DNS-verified websites and review their scheduled health checks.</p>

        @if(session('status'))
            <div role="status" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <ul class="grid gap-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 grid gap-4">
            @csrf
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Forgot password?</a>
                </div>
                <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Remember me
            </label>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">Sign in</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            New to LeadRadar?
            <a href="{{ route('register') }}" class="font-semibold text-indigo-700 hover:underline">Create an account</a>
        </p>
    </section>
</main>
