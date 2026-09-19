<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Platform\Services\PublicFacts;
use App\Domain\Platform\Services\VenueAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **`GET /api/v1/site` — la IDENTIDAD y el CONTACTO de la instalación, como hechos** (F5 · T1,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `[DECIDIDO owner]` `#631` y `#639`).
 *
 * Es el primer plato del menú de hechos, y el que hace falta para que una landing que NO es del producto
 * pueda pintar su pie, su página de contacto y sus datos legales sin teclearlos. Hasta F5 esos datos solo
 * existían dentro de un `View::composer('*')` del producto: la landing de una instancia no tenía forma de
 * leerlos, y la alternativa era copiarlos a mano y que envejecieran (el defecto que `#488` cazó en las dudas).
 *
 * ⚠️⚠️ **Lo que no está, no viaja.** Una instalación sin TikTok no publica `"tiktok": ""`: publica un objeto
 * sin esa clave. Es el «todo es opcional» de `#631` llevado al JSON — quien pinta la landing pregunta si la
 * clave existe, en vez de escribir la misma condición de cadena vacía en cinco sitios.
 *
 * ⚠️ **Y por eso cada bloque se emite con `(object)`**: un `array` de PHP vacío sale en JSON como `[]`, no
 * como `{}`. Con `seo` sin rellenar, el contrato decía «objeto» y la respuesta traía una lista. Es la misma
 * trampa que la T1 de F4 pagó en `SidebarBoot`, y el arreglo es el mismo: el tipo no puede depender de si
 * la instalación rellenó el campo.
 *
 * ⚠️⚠️ **Las claves se leen por `PublicFacts`, NO por `Setting::value()`**, y la lista blanca de abajo es el
 * contrato: `settings` mezcla `redsys_secret_key` con `contact.email` (spec §1.3). `PublicFactsBoundaryTest`
 * vigila que ningún recurso público se salte ese camino.
 *
 * ▶ **Las redes se quedan en el PANEL** (`#639`): el owner eligió que cambiar un Instagram sea editar un
 * campo y no desplegar el repo de la instancia. Por eso viajan aquí y no en el paquete del cliente.
 */
class SiteFactsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * **La lista blanca de este recurso.** Solo estas claves, y ninguna es un secreto.
     *
     * ⚠️ `contact.whatsapp` y `social.feed_embed_url` no tienen fila hoy en ninguna instalación de las dos
     * que existen: entran porque el producto ya las lee en su propia landing, y sin fila simplemente no
     * viajan. Declarar una clave no obliga a nadie a rellenarla.
     *
     * @var list<string>
     */
    public const AJUSTES = [
        'business.name',
        'business.legal_name',
        'business.nif',
        'business.city',
        'business.domain',
        'business.address',
        'address.line1',
        'address.line2',
        'address.maps_url',
        'address.maps_embed_url',
        'contact.email',
        'contact.phone',
        'contact.whatsapp',
        'contact.instagram',
        'contact.tiktok',
        'social.feed_embed_url',
        'social.google_place_id',
        'legal.jurisdiction',
        'seo.og_image',
    ];

    public function __construct()
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        $hechos = PublicFacts::allowing(self::AJUSTES);

        return [
            'identity' => (object) $hechos->compact([
                'name' => 'business.name',
                'legal_name' => 'business.legal_name',
                'nif' => 'business.nif',
                'domain' => 'business.domain',
            ]),
            // ⚠️⚠️ **SON DOS DIRECCIONES DISTINTAS, y confundirlas manda a la gente a otro sitio.**
            // `address.*` es DONDE ESTÁ EL PARQUE —lo que va en el pie, en el mapa y en el correo del
            // resguardo— y `business.address` es el **domicilio FISCAL**, que el panel rotula «puede diferir
            // de la del parque» y que en la instalación de hoy, medido, es **otro municipio**. La primera
            // versión de este recurso lo sirvió como `address.postal`: una landing que pintara su bloque de
            // contacto habría mandado a sus clientas a la gestoría. El fiscal viaja en `legal`, que es donde
            // se usa (aviso legal y facturación), y con su nombre.
            'address' => (object) ($hechos->compact([
                'line1' => 'address.line1',
                'line2' => 'address.line2',
                'city' => 'business.city',
                'maps_url' => 'address.maps_url',
                'maps_embed_url' => 'address.maps_embed_url',
            ]) + $this->direccionEscrita($hechos)),
            'contact' => (object) $hechos->compact([
                'email' => 'contact.email',
                'phone' => 'contact.phone',
                'whatsapp' => 'contact.whatsapp',
            ]),
            'social' => (object) $hechos->compact([
                'instagram' => 'contact.instagram',
                'tiktok' => 'contact.tiktok',
                'feed_embed_url' => 'social.feed_embed_url',
                'google_place_id' => 'social.google_place_id',
            ]),
            'legal' => (object) $hechos->compact([
                'jurisdiction' => 'legal.jurisdiction',
                'fiscal_address' => 'business.address',
            ]),
            'seo' => (object) $hechos->compact([
                'og_image' => 'seo.og_image',
            ]),
        ];
    }

    /**
     * **La dirección YA ESCRITA**, además de sus dos líneas (`#650`).
     *
     * ❗❗ **Publicar solo `line1` y `line2` obligaba a cada instancia a re-derivar la coma**, y la
     * primera que la olvidara publicaría «Ctra. de Prueba, 1 30000 Ciudad» —que se lee como si el
     * código postal fuera parte del portal— sin que nada fallara. Medido el 19-09: esa regla ya
     * estaba escrita dos veces dentro del producto, y ésta habría sido la tercera copia por cliente.
     *
     * ⚠️ Las dos líneas SIGUEN viajando: quien quiera maquetarlas en dos renglones puede, y quitarlas
     * habría sido romper el contrato para arreglar otra cosa.
     *
     * @return array<string, string>
     */
    private function direccionEscrita(PublicFacts $hechos): array
    {
        $escrita = VenueAddress::written($hechos->get('address.line1'), $hechos->get('address.line2'));

        // Lo que la instalación no rellenó no viaja: sin ninguna línea no hay dirección que escribir.
        return $escrita === null ? [] : ['written' => $escrita];
    }
}
