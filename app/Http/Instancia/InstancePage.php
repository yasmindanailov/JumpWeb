<?php

namespace App\Http\Instancia;

/**
 * **Una página que declara el paquete de la instancia**, ya validada ({@see InstancePages}).
 *
 * Es lo que la instancia DECIDE de su página —su dirección, su vista, qué hechos consume y cómo la trata el
 * sitemap—; lo que DICE (el título para Google y WhatsApp, la descripción) son textos de su `lang/`, en el idioma
 * de la visita.
 */
final readonly class InstancePage
{
    /**
     * @param  list<string>  $hechos  nombres de {@see PageFacts::HECHOS}
     * @param  ?string  $ocupa  la RUTA DEL PRODUCTO que ocupa, sin ruta propia (`#827`, `#832`): `home` —la portada, que
     *                          pintan `/` y sus puertas— o una de {@see InstancePages::OCUPABLES}, cuyo controlador le
     *                          cede el sitio (`cumpleanos`, T6b de `isla-y-landing-nueva.md` §4.18)
     * @param  bool  $sitemap  si entra en el sitemap (`'sitemap' => false`, T6c·4b: la hoja para imprimir de colegios no es
     *                         una página que buscar; la vista dice además `noindex`)
     * @param  list<string>  $sustituye  las RUTAS VIEJAS del producto que responden 301 a esta página (`#843`, T6f de
     *                                   `isla-y-landing-nueva.md` §4.22), de {@see InstancePages::SUSTITUIBLES}
     */
    public function __construct(
        public string $slug,
        public string $vista,
        public array $hechos,
        public string $prioridad,
        public string $frecuencia,
        public ?string $ocupa = null,
        public bool $sitemap = true,
        public array $sustituye = [],
    ) {}

    /** El nombre de su ruta. */
    public function ruta(): string
    {
        return 'instancia.'.$this->slug;
    }
}
