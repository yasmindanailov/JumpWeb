<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\LegalIdentity;
use App\Domain\Identity\Services\LegalDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * **`GET /api/v1/legal/documents[/{clave}]` — LOS TEXTOS LEGALES** (F5 · T4 del menú,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Los cinco documentos que una landing tiene que poder publicar: privacidad, cookies, condiciones, aviso
 * legal y el justificante. `Pages` se queda en el panel cuando la landing se va (`producto-e-instancias.md`
 * §4.3) precisamente porque es esto: un texto que el negocio mantiene y la web consume.
 *
 * ⚠️⚠️ **EL CUERPO SE SIRVE YA INTERPOLADO, y esto no es un adorno.** El texto guardado lleva marcadores
 * —`:legal_name`, `:legal_nif`, `:legal_address`, `:legal_email`— que el producto sustituye AL RENDERIZAR
 * ({@see LegalIdentity}). Publicarlos en crudo dejaría a una landing escribiendo «El responsable del
 * tratamiento de tus datos es :legal_name» en su política de privacidad, que es el sitio donde peor queda.
 *
 * ⚠️⚠️ **Y viaja LA PÁGINA, no el snapshot firmado.** Medido: para `condiciones` y `waiver` existe además un
 * documento VERSIONADO ({@see LegalDocuments}) que es la página interpolada y CONGELADA al publicarla, y que
 * es lo que la gente firma. Son dos cosas distintas y sirven para dos cosas distintas:
 *   · la PÁGINA explica y se lee —en esta instalación, el `waiver` tiene **once** secciones—;
 *   · la VERSIÓN es el texto legal al que alguien se obligó —la misma, **una** sección—.
 * Servir la versión como si fuera la página quitaría diez secciones de explicación; servir la página como si
 * fuera lo firmado publicaría texto que nadie ha firmado. Así que viaja la página, y la versión viaja al lado
 * como DATO (`signed_version`), para que una landing pueda escribir «Condiciones v1, de tal fecha» sin
 * inventárselo. El texto firmable, para quien lo necesite, sigue en `/legal/waiver`.
 */
class LegalDocumentsResource extends JsonResource
{
    public static $wrap = null;

    /** @param Collection<int, Page> $paginas */
    public function __construct(
        private readonly Collection $paginas,
        private readonly bool $conCuerpo,
    ) {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $documentos = $this->paginas
            ->map(fn (Page $pagina): array => $this->documento($pagina))
            ->values()
            ->all();

        return $this->conCuerpo
            ? $documentos[0]
            : ['lang' => app()->getLocale(), 'documents' => $documentos];
    }

    /** @return array<string, mixed> */
    private function documento(Page $pagina): array
    {
        $version = in_array($pagina->slug, LegalDocuments::PUBLISHABLE, true)
            ? LegalDocuments::current($pagina->slug, app()->getLocale())
            : null;

        return array_filter([
            'key' => $pagina->slug,
            'title' => $pagina->tr('title'),
            'updated_at' => $pagina->updated_at?->toIso8601String(),
            // El dato de la versión firmada, cuando la hay. No sustituye al texto: lo acompaña.
            // ⚠️ `published_at` NO es anulable en la tabla (medido, `NOT NULL`): una versión publicada
            // tiene fecha por definición, y el contrato la exige. El primer borrador de este recurso la
            // trataba como opcional «por si acaso» — un «por si acaso» contra el esquema es una mentira
            // que el cliente tiene que programar.
            'signed_version' => $version === null ? null : [
                'version' => (int) $version->version,
                'published_at' => $version->published_at->toIso8601String(),
            ],
            'sections' => $this->conCuerpo ? $this->secciones($pagina) : null,
        ], fn ($valor): bool => $valor !== null);
    }

    /**
     * Las secciones `{h, p}` del documento, con los marcadores ya resueltos.
     *
     * ⚠️ El cuerpo es un mapa por idioma, así que `tr()` elige uno con la cadena de respaldo del dominio y
     * el cliente recibe texto, no tres idiomas que tendría que saber recorrer.
     *
     * @return list<array{h?: string, p?: string}>
     */
    private function secciones(Page $pagina): array
    {
        $cuerpo = $pagina->tr('body');

        if (! is_array($cuerpo)) {
            return [];
        }

        return array_values(array_map(fn (array $seccion): array => array_filter([
            'h' => LegalIdentity::interpolate($seccion['h'] ?? null) ?: null,
            'p' => LegalIdentity::interpolate($seccion['p'] ?? null) ?: null,
        ], fn ($valor): bool => $valor !== null), array_filter($cuerpo, 'is_array')));
    }
}
