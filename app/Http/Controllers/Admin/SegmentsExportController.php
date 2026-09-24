<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Pages\AnalyticsPage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * **La exportación de un segmento** (`specs/analitica.md` §4.6, T4b): un CSV con las personas de un segmento que
 * dieron el opt-in de comunicaciones. A diferencia del CSV del cuadro (`AnalyticsExportController`, solo
 * agregados), esto SÍ es una lista de personas: por eso tiene permiso PROPIO (`analytics.export`, fuera del
 * staff por defecto), solo lleva a quien consintió y cada descarga deja rastro con el segmento y el recuento
 * —sin PII—. `no-store` por la ruta. Las celdas pasan por `CsvExport::cell()`: un nombre que empiece por `=`
 * no es una fórmula.
 */
class SegmentsExportController extends Controller
{
    public function __invoke(Request $request, SegmentsReport $segments): Response
    {
        abort_unless($request->user()?->hasPermission(AnalyticsPage::PERMISSION_SEGMENTS_EXPORT) ?? false, 403);

        $segment = (string) $request->query('segment', '');
        abort_unless(in_array($segment, SegmentsReport::SEGMENTS, true), 404);

        $members = $segments->members($segment);

        AuditLogger::log('segments.exported', null, [
            'segment' => $segment,
            'rows' => count($members),
        ]);

        $rows = [[
            __('admin.analytics.segments.csv.name'),
            __('admin.analytics.segments.csv.email'),
            __('admin.analytics.segments.csv.phone'),
            __('admin.analytics.segments.csv.last_purchase'),
        ]];
        foreach ($members as $member) {
            $rows[] = [$member['name'], $member['email'], $member['phone'], $member['last_purchase']];
        }

        return response(CsvExport::csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="segmento-'.$segment.'-'.now()->format('Ymd').'.csv"',
        ]);
    }
}
