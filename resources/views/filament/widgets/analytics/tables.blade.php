{{--
    Tablas de un informe de «Analítica» (`specs/analitica.md` §4.5): el widget entrega el título, la nota y
    una lista de tablas con celdas YA formateadas; aquí no se calcula nada y todo va escapado (ninguna
    columna analítica pasa por `->html()`). Una tabla `wide` ocupa las dos columnas. Los importes no se parten
    (la captura móvil del 24-09 los mostraba en dos líneas): las celdas numéricas van sin salto y cada tabla
    se desplaza sola si no cabe.

    T2f: la sección nace PLEGADA —el owner vio el cuadro y pidió menos tablas: los gráficos van arriba y el
    detalle en cifras se abre cuando hace falta— y recuerda si se abrió (por widget, en el navegador). Las
    tablas siguen en el DOM plegadas: son la vista de tabla de los gráficos y lo que lleva el CSV.
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
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($tables as $table)
                @include('filament.widgets.analytics.table', ['table' => $table])
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
