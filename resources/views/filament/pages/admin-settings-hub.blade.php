{{--
    «Ajustes» — la puerta única a las pantallas de puesta en marcha (#223).

    Tarjetas, no una lista: esto se BARRE con la vista para entrar una vez, no se navega a
    diario. Cada tarjeta lleva su rótulo Y una línea de qué hace, porque «Temporadas» o
    «Plantillas de franja» no se explican solos a quien entra dos veces al año.

    ⚠️ Sin utilidades de COLOR de Tailwind. Medido al compilar: las clases `*-primary-500`
    y `*-primary-600` SÍ entran en el bundle, pero sus variables NO están declaradas —el
    borde al pasar el ratón y el aro de foco de teclado habrían salido invisibles—. Es el
    mismo fallo que dejó la pantalla de puerta en blanco y negro (#217). El estilo vive en
    `resources/css/filament/admin/theme.css` y lee los tokens del panel (`--gray-*`,
    `--primary-*`), que además es lo que hace que el color de marca por instalación mande.
--}}
<x-filament-panels::page>
    <div class="jj-hub">
        @foreach ($this->visibleAreas() as $area)
            <x-filament::section :heading="$area['label']">
                <div class="jj-hub__grid">
                    @foreach ($area['items'] as $item)
                        <a href="{{ $item['url'] }}" class="jj-hub__card">
                            <x-filament::icon :icon="$item['icon']" class="jj-hub__ico" />

                            <span class="jj-hub__text">
                                <span class="jj-hub__label">{{ $item['label'] }}</span>
                                <span class="jj-hub__desc">{{ $item['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
