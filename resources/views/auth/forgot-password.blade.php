@vite(['resources/css/app.css', 'resources/js/app.js'])

<main class="flex min-h-screen items-center justify-center bg-[#f1eff6] px-4 py-10 sm:px-6">
    <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-indigo-950/5 sm:p-8">
        <a href="{{ route('companies.index') }}" class="text-sm font-bold tracking-wide text-indigo-700">LeadRadar</a>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-slate-950">Forgot your password?</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Enter your account email and we will send you a link to choose a new password.</p>

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

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 grid gap-4">
            @csrf
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">Email reset link</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Remembered it?
            <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:underline">Back to sign in</a>
        </p>
    </section>
</main>
