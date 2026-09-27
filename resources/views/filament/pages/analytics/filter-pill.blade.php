{{--
    La píldora del filtro en el móvil (T3a de `specs/analitica-para-decidir.md` §4.11, `#759`): el periodo y la comparación en
    una línea, y al tocarla se abren los dos (medido el 28-09: a 390 px el filtro con sus fechas escritas empujaba la primera
    cifra a 732 px). Desde tableta no se ve y el filtro va siempre abierto. El estado `filtersOpen` es del `x-data` del grupo
    que la envuelve (`AnalyticsPage::content()`).

    @var string $label
--}}
<div class="md:hidden" data-analytics-filter-pill>
    <x-filament::button
        color="gray"
        outlined
        icon="heroicon-m-adjustments-horizontal"
        class="w-full"
        x-on:click="filtersOpen = ! filtersOpen"
        x-bind:aria-expanded="filtersOpen ? 'true' : 'false'"
        aria-controls="analitica-filtros"
    >
        {{ $label }}
    </x-filament::button>
</div>
