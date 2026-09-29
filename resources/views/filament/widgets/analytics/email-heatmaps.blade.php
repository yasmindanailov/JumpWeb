{{--
    CUÁNDO ABREN Y PULSAN (`specs/correos-salientes.md` §4.14, `#796`, la C4): los dos mapas día × hora de los correos que al
    cliente le LLEGAN —los clics, el dato fiable, y las aperturas, aproximadas—, con la misma rejilla que la ocupación
    (`heatmap-grid`). Nace PLEGADO, como las tablas del cuadro, y recuerda si se abrió. Una celda de 1 a 4 va con «—» y su
    tooltip (`RGPD-07`); sin bastantes datos, el mapa lo dice en vez de pintarse. Nada se calcula aquí.

    @var list<array{key: string, heading: string, note: string, hours: list<string>, rows: list<array<string, mixed>>, legend: list<array{mix: int, label: string}>}> $maps
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="$heading"
        :description="$description"
        collapsible
        collapsed
        persist-collapsed
        :collapse-id="'analitica-' . \Illuminate\Support\Str::kebab(class_basename($this))"
    >
        <div class="grid gap-6">
            @foreach ($maps as $map)
                {{-- `min-w-0`: sin él, el hijo de la rejilla se ensancha al ancho de la tabla y en móvil la sección recorta su
                     nota (medido a 390 el 29-09); con él, la tabla se desplaza en su caja y el texto se parte. --}}
                <div class="min-w-0" data-email-heatmap="{{ $map['key'] }}">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $map['heading'] }}</p>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ $map['note'] }}</p>
                    @if ($map['rows'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400" data-email-heatmap-empty>{{ __('admin.analytics.emails.when.empty', ['min' => \App\Filament\Analytics\EmailsReport::MIN_CELL]) }}</p>
                    @else
                        <div class="overflow-x-auto">
                            @include('filament.widgets.analytics.heatmap-grid', ['hours' => $map['hours'], 'rows' => $map['rows'], 'hue' => $hue])
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400" aria-hidden="true">
                            @foreach ($map['legend'] as $step)
                                @if ($step['label'] !== '' && $loop->first)
                                    <span class="me-1">{{ $step['label'] }}</span>
                                @endif
                                <span class="inline-block h-3 w-5 rounded" style="background-color: color-mix(in srgb, {{ $hue }} {{ $step['mix'] }}%, transparent)"></span>
                                @if ($step['label'] !== '' && $loop->last)
                                    <span class="ms-1">{{ $step['label'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
