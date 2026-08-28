<div class="space-y-3">
    <div>
        <x-filament::input.wrapper>
            <input
                type="search"
                placeholder="Search registration no. or driver…"
                class="fi-input"
                wire:model.live.debounce.400ms="search"
            />
        </x-filament::input.wrapper>
    </div>

    <div>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="active">
                <option value="">All</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div class="space-y-3">
        @forelse ($records as $record)
            <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/30">
                <header class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ $record->vehicle?->number ?? '—' }}</h3>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $record->driver?->name ?? '—' }}</p>
                        </div>
                    </div>
                    <x-filament::badge class="shrink-0" :color="$record->active ? 'success' : 'gray'">
                        {{ $record->active ? 'Active' : 'Inactive' }}
                    </x-filament::badge>
                </header>

                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Vehicle Description</dt>
                        <dd class="text-right text-gray-950 dark:text-white">{{ $record->vehicle?->description ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Driver Phone</dt>
                        <dd class="text-right text-gray-950 dark:text-white">{{ $record->driver?->phone ?? '—' }}</dd>
                    </div>
                </dl>

                <footer class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                    <x-filament::button
                        tag="a"
                        :href="route('filament.gate.resources.vehicle-drivers.edit', ['record' => $record])"
                        color="gray"
                    >
                        Edit
                    </x-filament::button>
                </footer>
            </article>
        @empty
            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No records found.</p>
        @endforelse
    </div>

    <div x-data x-intersect="$wire.loadMore()"></div>

    @if ($records->hasMorePages())
        <div class="flex justify-center py-2 text-sm text-gray-400">
            <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-900 dark:border-t-white"></div>
        </div>
    @else
        <p class="py-2 text-center text-xs text-gray-400 dark:text-gray-500">You've reached the end.</p>
    @endif
</div>
