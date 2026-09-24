<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Analytics\CsvExport;
use App\Filament\Pages\AnalyticsPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **La descarga del CSV de «Analítica»** (`docs/specs/analitica.md` §4.5, T2d; `DECISIONES #735`).
 *
 * El permiso `reports.export` se comprueba AQUÍ, no solo en el botón: el botón lo esconde, el controlador es la
 * defensa (el molde de `DailySummaryController`). Cada descarga queda en la auditoría con el informe, el periodo
 * y el número de filas —sin PII: el fichero solo lleva agregados—, y la respuesta sale `no-store` por la ruta.
 */
class AnalyticsExportController extends Controller
{
    public function __invoke(Request $request, CsvExport $export): Response
    {
        abort_unless($request->user()?->hasPermission(AnalyticsPage::PERMISSION_EXPORT) ?? false, 403);

        $report = (string) $request->query('report', '');
        abort_unless(in_array($report, CsvExport::REPORTS, true), 404);

        $period = ReportPeriod::fromValue($request->query('period'));
        $built = $export->build($report, $period);

        AuditLogger::log('reports.exported', null, [
            'report' => $report,
            'period' => $period->value,
            'rows' => count($built['rows']),
        ]);

        return response(CsvExport::csv($built['rows']), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$built['filename'].'"',
        ]);
    }
}
