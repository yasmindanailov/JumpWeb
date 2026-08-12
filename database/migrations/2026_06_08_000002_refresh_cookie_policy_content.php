<?php

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\CookiePolicyContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * #219 — Reparación idempotente del contenido de la política de cookies en BD ya existentes.
 *
 * El seeder ya emite el contenido nuevo (fuente única `CookiePolicyContent`) en instalaciones
 * limpias; pero las BD de dev/prod tienen la fila `cookies` sembrada con el listado `[PENDIENTE]`.
 * Esta migración la refresca SOLO si el marcador sigue presente → no pisa ediciones de la clienta
 * (si ya editó la página, el marcador habrá desaparecido y esto es no-op). Patrón de reparación de
 * datos legacy ([[feedback_legacy_data_repair]]): conservador, idempotente, no-op en BD limpia.
 */
return new class extends Migration
{
    /** Señal del contenido sembrado por defecto (el listado sin redactar). Si está, se reemplaza. */
    private const MARKERS = [
        '[PENDIENTE: listado detallado',
        '[PENDING: detailed list',
        '[À COMPLÉTER : liste détaillée',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $page = Page::query()->where('slug', 'cookies')->first();
        if ($page === null) {
            return;
        }

        $serialized = json_encode($page->body, JSON_UNESCAPED_UNICODE) ?: '';
        $stillPlaceholder = false;
        foreach (self::MARKERS as $marker) {
            if (str_contains($serialized, $marker)) {
                $stillPlaceholder = true;
                break;
            }
        }

        if (! $stillPlaceholder) {
            return; // ya tiene contenido real o lo editó la clienta → no tocar.
        }

        $page->title = CookiePolicyContent::title();
        $page->body = CookiePolicyContent::body();
        $page->save();
    }

    public function down(): void
    {
        // No-op: no se restaura el listado `[PENDIENTE]` (sería un retroceso). El contenido real
        // queda; si hace falta, se re-siembra o se edita desde el panel.
    }
};
