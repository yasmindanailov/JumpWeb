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
     */
    public function __construct(
        public string $slug,
        public string $vista,
        public array $hechos,
        public string $prioridad,
        public string $frecuencia,
        public ?string $ocupa = null,
    ) {}

    /** El nombre de su ruta. */
    public function ruta(): string
    {
        return 'instancia.'.$this->slug;
    }
}
