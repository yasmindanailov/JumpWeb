<?php

use App\Models\Page;
use App\Support\LegalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * #220 — Reparación idempotente de Privacidad / Condiciones / Aviso legal en BD ya existentes.
 *
 * El seeder ya emite el contenido nuevo (`LegalContent`) en instalaciones limpias; pero las BD de
 * dev/prod tienen estas 3 páginas con el contenido `[PENDIENTE]` antiguo. Esta migración las
 * refresca SOLO si siguen con ese contenido por defecto, detectado por un marcador ÚNICO del texto
 * VIEJO (ausente en el nuevo — el nuevo usa tokens fiscales `:legal_*` y tiene otros `[PENDIENTE]`
 * de negocio). Si la clienta ya editó la página, el marcador habrá desaparecido y esto es no-op.
 * Patrón [[feedback_legacy_data_repair]]: conservador, idempotente, no-op en BD limpia.
 */
return new class extends Migration
{
    /** slug => marcador presente SOLO en el contenido sembrado por defecto (viejo). */
    private const OLD_MARKERS = [
        'privacidad' => 'PENDIENTE: razón social',
        'condiciones' => 'política de cancelación y reembolso',
        'aviso-legal' => 'PENDIENTE: razón social',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $content = LegalContent::pages();

        foreach (self::OLD_MARKERS as $slug => $marker) {
            $page = Page::query()->where('slug', $slug)->first();
            if ($page === null) {
                continue;
            }

            $serialized = json_encode($page->body, JSON_UNESCAPED_UNICODE) ?: '';
            if (! str_contains($serialized, $marker)) {
                continue; // ya tiene el contenido nuevo o lo editó la clienta → no tocar.
            }

            $page->title = $content[$slug]['title'];
            $page->body = $content[$slug]['body'];
            $page->save();
        }
    }

    public function down(): void
    {
        // No-op: no se restaura el contenido `[PENDIENTE]` antiguo (sería un retroceso).
    }
};
