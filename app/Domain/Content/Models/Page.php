<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasTranslations;

    /*
     * ⚠️ Aquí vivía `REVIEWED_LEGAL_SLUGS`, la lista de legales «ya definitivas» que apagaba el aviso de
     * borrador de la vista. Desde el lanzamiento del 2026-09-01 estaban las CINCO —y las rutas solo
     * sirven esas cinco—, así que el aviso no se pintaba nunca: regla muerta dentro de una vista que se
     * va a la instancia (F5 · T2b, `#655`). Se retiró con su vista, su clave de idioma y sus tres tests
     * que afirmaban su ausencia en vacío.
     */

    /**
     * Slugs legales que NO pueden quedar inactivos desde el panel. TODOS están enlazados de forma
     * OBLIGATORIA desde el pie (`footer`), el `sitemap.xml`, el banner de consentimiento (#219) y/o el
     * consentimiento del registro; desactivar cualquiera dejaría esos enlaces en un 404 indexable y
     * rompería el pie de TODAS las páginas (auditoría Fase 1 · Sistema 6 · W5). Fuente única consumida
     * por el form (`PageForm` deshabilita el toggle) y el guardado (`InteractsWithPageForm` fuerza
     * `is_active=true`). Coherente con que las páginas legales son EDIT-ONLY de slug inmutable (#212).
     *
     * @var list<string>
     */
    public const PROTECTED_ACTIVE_SLUGS = ['privacidad', 'condiciones', 'waiver', 'cookies', 'aviso-legal'];

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'body' => 'array',
        'is_active' => 'boolean',
    ];
}
