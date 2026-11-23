@vite(['resources/css/app.css', 'resources/js/app.js'])

<main class="flex min-h-screen items-center justify-center bg-[#f1eff6] px-4 py-10 sm:px-6">
    <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-indigo-950/5 sm:p-8">
        <a href="{{ route('companies.index') }}" class="text-sm font-bold tracking-wide text-indigo-700">LeadRadar</a>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight text-slate-950">Choose a new password</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Use at least 12 characters with letters and numbers.</p>

        @if($errors->any())
            <div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <ul class="grid gap-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 grid gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required autofocus class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200">Reset password</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:underline">Back to sign in</a>
        </p>
    </section>
</main>
