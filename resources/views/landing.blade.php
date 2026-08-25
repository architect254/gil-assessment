<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIL Technical Assessment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between p-6 antialiased">

<!-- Top Navigation Header -->
<header class="max-w-6xl w-full mx-auto flex items-center justify-between pb-6 border-b border-slate-800">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/30">
            GIL
        </div>
        <div>
            <h1 class="text-lg font-bold text-white leading-tight">Assessment Gateway</h1>
            <p class="text-xs text-slate-400">Laravel 13 & Filament Suite</p>
        </div>
    </div>
    <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 rounded-full px-4 py-1.5 text-xs text-slate-400">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>SQL Server & M-Pesa Connected</span>
    </div>
</header>

<!-- Main Content Grid -->
<main class="max-w-6xl w-full mx-auto my-auto py-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-3">
            Technical Assessment Applications
        </h2>
        <p class="text-slate-400 text-base">
            Select an application below to evaluate full-stack form logic, real-time validation, gate operations, and API integrations.
        </p>
    </div>

    <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">

        <!-- Task 1: ERP Invoicing App -->
        <a href="/admin" class="group relative bg-slate-900/90 hover:bg-slate-900 border border-slate-800 hover:border-indigo-500/60 rounded-2xl p-8 transition-all duration-300 transform hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-indigo-500/10 flex flex-col justify-between">
            <div>
                <!-- Badge & Icon -->
                <div class="flex items-center justify-between mb-6">
                        <span class="text-xs font-semibold uppercase tracking-wider bg-indigo-500/10 text-indigo-400 px-3 py-1 rounded-full border border-indigo-500/20">
                            Task 1
                        </span>
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>

                <h3 class="text-2xl font-bold text-white mb-2 group-hover:text-indigo-400 transition-colors">
                    Sales Invoice Entry
                </h3>
                <p class="text-slate-400 text-sm mb-6 leading-relaxed">
                    Full SAP-style ERP sales invoice entry system built with SQL Server schema integration.
                </p>

                <!-- Feature Tags -->
                <ul class="text-xs text-slate-300 space-y-2 mb-8 border-t border-slate-800/80 pt-4">
                    <li class="flex items-center space-x-2">
                        <span class="text-indigo-400">✓</span>
                        <span>Customer & Item type-ahead search</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-indigo-400">✓</span>
                        <span>Dynamic calculations & 3-decimal precision</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-indigo-400">✓</span>
                        <span>Auto-trigger approval label (> 10,000)</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-indigo-400">✓</span>
                        <span>Strict validation (>50% discount limit)</span>
                    </li>
                </ul>
            </div>

            <div class="flex items-center justify-between text-sm font-semibold text-indigo-400 group-hover:text-indigo-300 pt-4 border-t border-slate-800">
                <span>Open Invoice App</span>
                <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>

        <!-- Task 2: Gate Operations App -->
        <a href="/gate" class="group relative bg-slate-900/90 hover:bg-slate-900 border border-slate-800 hover:border-emerald-500/60 rounded-2xl p-8 transition-all duration-300 transform hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-emerald-500/10 flex flex-col justify-between">
            <div>
                <!-- Badge & Icon -->
                <div class="flex items-center justify-between mb-6">
                        <span class="text-xs font-semibold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 px-3 py-1 rounded-full border border-emerald-500/20">
                            Task 2
                        </span>
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <h3 class="text-2xl font-bold text-white mb-2 group-hover:text-emerald-400 transition-colors">
                    Vehicle Gate Operations
                </h3>
                <p class="text-slate-400 text-sm mb-6 leading-relaxed">
                    Mobile-responsive gate entry and exit portal with real-time session tracking.
                </p>

                <!-- Feature Tags -->
                <ul class="text-xs text-slate-300 space-y-2 mb-8 border-t border-slate-800/80 pt-4">
                    <li class="flex items-center space-x-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Secure login & session timestamping</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Gate-In logging (Vehicle, Driver, ID, Phone)</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Gate-Out auto-populate for active vehicles</span>
                    </li>
                    <li class="flex items-center space-x-2">
                        <span class="text-emerald-400">✓</span>
                        <span>Automatic timestamp & user context capture</span>
                    </li>
                </ul>
            </div>

            <div class="flex items-center justify-between text-sm font-semibold text-emerald-400 group-hover:text-emerald-300 pt-4 border-t border-slate-800">
                <span>Open Gate Portal</span>
                <span class="transform group-hover:translate-x-1 transition-transform">&rarr;</span>
            </div>
        </a>

    </div>

    <!-- Task 3 Integration Banner -->
    <div class="mt-8 max-w-4xl mx-auto bg-slate-900/50 border border-slate-800 rounded-xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-white">Task 3: M-Pesa C2B API Callback Handler</h4>
                <p class="text-xs text-slate-400">REST Endpoint active at <code class="text-amber-400 bg-slate-950 px-1.5 py-0.5 rounded border border-slate-800">/api/mpesa/c2b/callback</code></p>
            </div>
        </div>
        <span class="text-xs font-mono bg-slate-950 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-800 shrink-0">
                POST JSON Listener
            </span>
    </div>
</main>

<!-- Footer -->
<footer class="max-w-6xl w-full mx-auto pt-6 border-t border-slate-800 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2">
    <p>Built for GIL Technical Assessment</p>
    <p>Laravel v13.26.1 • PHP v8.4.24</p>
</footer>

</body>
</html>
