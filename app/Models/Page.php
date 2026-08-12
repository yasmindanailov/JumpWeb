<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasTranslations;

    /**
     * Páginas legales cuyo contenido ya es DEFINITIVO (redactado de forma profesional y completa) →
     * no muestran el aviso «Texto provisional pendiente de revisión legal» (`site.legal_draft_notice`).
     * Las que siguen en borrador (p. ej. `waiver`, gestionado por el sistema externo de la clienta)
     * sí lo muestran. Se amplía a medida que se finaliza cada página (#219 cookies; legales #220).
     *
     * @var list<string>
     */
    public const REVIEWED_LEGAL_SLUGS = ['cookies', 'privacidad', 'condiciones', 'aviso-legal'];

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
