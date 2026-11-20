<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Fake payment provider (development only)</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4 font-sans">
    <main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
        <p class="rounded-lg bg-red-600 px-3 py-2 text-center text-xs font-bold uppercase tracking-wider text-white">Development only — no real money</p>
        <h1 class="mt-6 text-2xl font-bold text-slate-900">Fake payment provider</h1>
        <p class="mt-2 text-slate-600">Stands in for the real payment page until a provider is chosen.</p>
        <dl class="mt-6 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-semibold">{{ $payment->amount_cents->format() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Method</dt><dd>{{ $payment->method->label() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Reference</dt><dd class="font-mono text-xs">{{ $payment->payer_reference }}</dd></div>
        </dl>
        <form method="POST" action="{{ $action }}" class="mt-6 grid gap-3">
            @csrf
            <button name="outcome" value="approve" class="rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white hover:bg-emerald-700">Approve payment</button>
            <button name="outcome" value="decline" class="rounded-lg bg-red-600 px-4 py-3 font-semibold text-white hover:bg-red-700">Decline payment</button>
            <button name="outcome" value="cancel" class="rounded-lg border border-slate-300 px-4 py-3 font-semibold text-slate-700 hover:bg-slate-50">Cancel and go back</button>
        </form>
    </main>
</body>
</html>
