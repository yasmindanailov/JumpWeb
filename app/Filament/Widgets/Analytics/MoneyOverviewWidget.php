<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\MoneyMetrics;

/**
 * **El dinero, arriba** (T3a de `analitica-para-decidir.md` §4.13, `#759`; las cifras, T2a y T0b): lo que decide —los
 * ingresos netos (la principal), lo vendido, lo que queda por cobrar en el parque, lo devuelto, los pedidos y el valor
 * medio—. «Cobrado» y las gestiones posteriores, plegados ({@see MoneyMoreWidget}); los clientes que compran, en
 * «Clientes». Sin título: la cabecera de la pestaña es su pregunta.
 */
class MoneyOverviewWidget extends MetricsWidget
{
    public const KEYS = ['money.net', 'money.sold', 'money.pending_in_park', 'money.refunded', 'money.orders', 'money.avg_order'];

    public const PRINCIPAL = 'money.net';

    protected static ?int $sort = 1;

    /** Cuatro columnas: la principal ocupa dos y las otras cinco llenan dos filas. */
    protected int|array|null $columns = 4;

    protected function metrics(): array
    {
        return MoneyMetrics::for($this->window(), $this->comparison());
    }
}
