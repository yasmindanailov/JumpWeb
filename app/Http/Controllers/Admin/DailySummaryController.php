<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Services\DailyReservationsSummary;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resumen imprimible (PDF A4 horizontal) para la operativa física — el listado de
 * las reservas/entradas de un DÍA o, desde la L5 de `#876`, de su SEMANA (de lunes
 * a domingo) o de su MES, por días, con filtro de tipo. Generado desde el botón
 * "Imprimir resumen" de la página de Calendario y del Escritorio.
 *
 * GET /admin/calendario/resumen-dia?date=YYYY-MM-DD&period=day|week|month&type=all|pack|trip|entry
 *
 * Defensa: middleware `web+auth+panel_role` + permiso **`calendar.view`** (el
 * mismo que ve el calendario y los widgets de reservas). Parámetros validados de
 * forma no destructiva (fecha inválida → HOY; periodo inválido → el día; tipo
 * inválido → todas). Se fuerza español (documento del personal). Del dinero, solo
 * lo que la hoja de cada reserva ya dice: el saldo del libro, por cobrar o pagado;
 * nada de la pasarela.
 */
class DailySummaryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('calendar.view'), 403);

        // Documento operativo → SIEMPRE en español, sea cual sea el locale del panel.
        App::setLocale('es');

        $date = $this->resolveDate($request->query('date'));
        $type = (string) $request->query('type', DailyReservationsSummary::TYPE_ALL);
        $period = (string) $request->query('period', DailyReservationsSummary::PERIOD_DAY);

        $summary = DailyReservationsSummary::for($date, $type, $period);

        AuditLogger::log('calendar.day_summary_printed', null, [
            'date' => $date,
            'period' => $summary->period,
            'type' => $summary->type,
            'count' => $summary->count(),
        ]);

        $pdf = Pdf::loadView('pdf.daily-summary', ['summary' => $summary])
            ->setPaper('a4', 'landscape');

        return $pdf->stream($summary->period === DailyReservationsSummary::PERIOD_DAY
            ? "resumen-{$date}.pdf"
            : 'resumen-'.($summary->period === DailyReservationsSummary::PERIOD_MONTH ? 'mes' : 'semana').'-'.$summary->from->toDateString().'.pdf');
    }

    /**
     * Fecha YYYY-MM-DD **real** (no solo con formato válido); fallback no
     * destructivo a HOY (huso del parque). `checkdate` rechaza fechas imposibles
     * (mes 13, 30-feb…): sin esto `Carbon::parse` lanzaría (mes 13 → 500) o haría
     * un rollover silencioso al día equivocado (30-feb → 2-mar) e imprimiríamos
     * la hoja de OTRO día.
     */
    private function resolveDate(mixed $raw): string
    {
        if (is_string($raw)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $raw;
        }

        return now(DisplayTime::timezone())->toDateString();
    }
}
