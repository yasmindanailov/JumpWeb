@php($model = $model ?? ['groups' => [], 'singles' => [], 'total' => 0])
@php($euros = fn (int $c) => \App\Support\Money::amount($c))

{{-- Complementos del alta manual: MISMA lógica y condiciones que la web (incluido/obligatorio/
     por-invitado/grupo de elección) vía el view-model compartido `AddonResolver::viewModel`.
     Interactividad con `$wire.call` (la lección #162: dentro de un componente View de Filament los
     `wire:click` directos pueden no propagar; `$wire` siempre apunta a la página Livewire). --}}
<div class="space-y-3">
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.orders.create_manual.addons') }}</p>

    {{-- Grupos de elección EXCLUYENTE (Menú 1 ⊻ Menú 2) --}}
    @foreach ($model['groups'] as $group)
        <fieldset class="rounded-lg border border-gray-200 p-3 dark:border-white/10" wire:key="m-addon-group-{{ $group['key'] }}">
            <legend class="px-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $group['label'] }}</legend>
            <div class="space-y-1.5">
                @foreach ($group['options'] as $opt)
                    <div x-data="{ info: false }" wire:key="m-addon-opt-{{ $opt['id'] }}"
                         @class([
                            'rounded-md p-2 ring-1 transition',
                            'bg-primary-50 ring-primary-500 dark:bg-primary-400/10' => $opt['selected'],
                            'ring-gray-200 dark:ring-white/10' => ! $opt['selected'],
                            'opacity-60' => ! $opt['available'],
                         ])>
                        <div class="flex items-center gap-2">
                            <button type="button" class="flex flex-1 items-center gap-2 text-left disabled:cursor-not-allowed"
                                    @disabled(! $opt['available'])
                                    x-on:click="$wire.call('selectManualAddonOption', @js($group['key']), {{ $opt['id'] }})">
                                <span @class([
                                    'flex h-4 w-4 flex-none items-center justify-center rounded-full border-2',
                                    'border-primary-600' => $opt['selected'],
                                    'border-gray-300 dark:border-gray-600' => ! $opt['selected'],
                                ])>
                                    @if ($opt['selected'])<span class="h-2 w-2 rounded-full bg-primary-600"></span>@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $opt['name'] }}</span>
                                    @if ($opt['badge'])
                                        <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">{{ __('tickets.addon_badge_'.$opt['badge']) }}</span>
                                    @endif
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $opt['note'] }}@if ($opt['selected'] && $opt['charged'] > 0) · <span class="font-semibold text-gray-700 dark:text-gray-300">+{{ $euros($opt['charged']) }} €</span>@endif</span>
                                    @if (! $opt['available'] && ! empty($opt['requires_name']))<span class="block text-xs font-medium text-amber-700 dark:text-amber-400">{{ __('tickets.addon_requires', ['name' => $opt['requires_name']]) }}</span>@endif
                                </span>
                            </button>
                            @if (count($opt['features']))
                                <button type="button" class="flex-none text-xs font-medium text-primary-600 underline underline-offset-2 hover:text-primary-500" x-on:click="info = !info">{{ __('tickets.addon_more_info') }}</button>
                            @endif
                        </div>
                        @if (count($opt['features']))
                            <ul x-show="info" x-cloak class="mt-2 space-y-1 border-t border-gray-200 pt-2 dark:border-white/10">
                                @foreach ($opt['features'] as $f)
                                    <li class="flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="text-emerald-600">✓</span>{{ $f }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    {{-- Complementos sueltos: incluidos (tarta), obligatorios u opcionales --}}
    @foreach ($model['singles'] as $opt)
        <div x-data="{ info: false }" wire:key="m-addon-{{ $opt['id'] }}" @class(['flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10', 'opacity-60' => ! $opt['available']])>
            <div class="min-w-0">
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $opt['name'] }}</span>
                @if ($opt['badge'])
                    <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">{{ __('tickets.addon_badge_'.$opt['badge']) }}</span>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $opt['note'] }}</p>
                @if (! $opt['available'] && ! empty($opt['requires_name']))<p class="text-xs font-medium text-amber-700 dark:text-amber-400">{{ __('tickets.addon_requires', ['name' => $opt['requires_name']]) }}</p>@endif
                @if (count($opt['features']))
                    <button type="button" class="mt-0.5 text-xs font-medium text-primary-600 underline underline-offset-2 hover:text-primary-500" x-on:click="info = !info">{{ __('tickets.addon_more_info') }}</button>
                @endif
            </div>

            @if (! $opt['available'])
                {{-- Dependiente «requiere»: bloqueado hasta elegir el requisito. Control inerte. --}}
                <div class="flex flex-none items-center gap-2 opacity-50" aria-hidden="true">
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-400 dark:border-gray-600" disabled>&minus;</button>
                    <span class="w-6 text-center text-sm font-semibold tabular-nums text-gray-400">0</span>
                    <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-400 dark:border-gray-600" disabled>+</button>
                </div>
            @elseif ($opt['can_toggle'])
                {{-- Per-invitado opcional: checkbox (la cantidad la fija el aforo = invitados). --}}
                <label class="flex flex-none cursor-pointer items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5"
                           @checked($opt['selected'])
                           x-on:click="$wire.call('toggleManualAddon', {{ $opt['id'] }})">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('tickets.addon_per_guest_add') }}@if ($opt['selected'] && $opt['charged'] > 0) <span class="font-semibold text-gray-900 dark:text-white">+{{ number_format($opt['charged'] / 100, 2, ',', '.') }} €</span>@endif</span>
                </label>
            @elseif ($opt['per_guest'])
                <span class="flex-none text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('tickets.addon_per_guest_qty', ['count' => $opt['qty']]) }}</span>
            @else
                <div class="flex flex-none items-center gap-2">
                    <button type="button" @class(['flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300', 'opacity-40' => ! $opt['can_dec']])
                            @disabled(! $opt['can_dec'])
                            x-on:click="$wire.call('decManualAddon', {{ $opt['id'] }})">&minus;</button>
                    <span class="w-6 text-center text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $opt['qty'] }}</span>
                    <button type="button" @class(['flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300', 'opacity-40' => ! $opt['can_inc']])
                            @disabled(! $opt['can_inc'])
                            x-on:click="$wire.call('incManualAddon', {{ $opt['id'] }})">+</button>
                </div>
            @endif

            @if (count($opt['features']))
                <ul x-show="info" x-cloak class="basis-full space-y-1 border-t border-gray-200 pt-2 dark:border-white/10">
                    @foreach ($opt['features'] as $f)
                        <li class="flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="text-emerald-600">✓</span>{{ $f }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
