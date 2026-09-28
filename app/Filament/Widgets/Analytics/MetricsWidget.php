<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * **Un grupo de tarjetas del cuadro** (T3a de `specs/analitica-para-decidir.md` §4.13, `#759`): ELIGE claves del catálogo
 * (`Filament\Analytics\Metrics\*`) y no compone ninguna cifra. Declara qué pinta (`KEYS`, en su orden de lectura), cuál va a
 * doble ancho (`PRINCIPAL`, §4.11) y si nace plegado (`FOLDED`: lo que no decide queda a un clic, §4.1.bis). Arriba de
 * cada pestaña, como mucho seis (`MAX_TOP`; «Resumen», ocho).
 */
abstract class MetricsWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    /** Tarjetas arriba de una pestaña, como mucho (§2, criterio 1). */
    public const MAX_TOP = 6;

    /** @var list<string> las claves que pinta, en su orden de lectura */
    public const KEYS = [];

    /** La cifra principal de la pestaña, a doble ancho desde tableta. */
    public const PRINCIPAL = null;

    /** ¿Nace plegado? Plegado, recuerda en el navegador si se abrió (como las tablas, T2f). */
    public const FOLDED = false;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    /**
     * Las cifras de las que elige: el catálogo de su informe (o de varios, en «Resumen»).
     *
     * @return array<string, Metric>
     */
    abstract protected function metrics(): array;

    /**
     * Tras guardar los objetivos del mes (T3c·2) la tarjeta se vuelve a pintar y los lee de nuevo: el guardado olvidó su
     * caché. No hace nada más: escuchar el evento ya es pedir otra vuelta.
     */
    #[On(AnalyticsPage::GOALS_SAVED_EVENT)]
    public function goalsSaved(): void {}

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $metrics = $this->metrics();

        return array_map(fn (string $key): Stat => $this->tile($metrics[$key]), static::KEYS);
    }

    /** Una tarjeta; la principal ocupa dos columnas desde tableta y una en el móvil. */
    protected function tile(Metric $metric): Stat
    {
        $stat = $this->metric($metric);

        return $metric->key === static::PRINCIPAL ? $stat->columnSpan(2) : $stat;
    }

    /**
     * Lo que va bajo las tarjetas, dentro del mismo grupo (una tabla en «Calidad del dato»).
     *
     * @return list<Component>
     */
    protected function afterTiles(): array
    {
        return [];
    }

    /**
     * La sección de Filament; la de un grupo plegado lleva borde, como las tablas plegadas, y su estado se recuerda por
     * widget.
     */
    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->schema([...$this->getCachedStats(), ...$this->afterTiles()])
            ->columns($this->getColumns())
            ->gridContainer()
            ->contained(static::FOLDED)
            ->collapsible(static::FOLDED)
            ->collapsed(static::FOLDED)
            ->persistCollapsed(static::FOLDED)
            ->id('analitica-'.Str::kebab(class_basename($this)))
            ->extraAttributes(['data-analytics-group' => static::FOLDED ? 'folded' : 'top']);
    }
}
