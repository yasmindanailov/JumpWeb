<?php

namespace App\Domain\Content\Services;

/**
 * **Los ASUNTOS por los que se puede escribir al negocio** (`#676`).
 *
 * Un vocabulario CERRADO y pequeño que vive en código, exactamente como `VenueRule::MOMENTS`
 * (`#533`): *«la lista vive donde se lee»*. El producto es su dueño —valida el POST del formulario
 * contra ella y mañana podrá segmentar por ella— y **quien pinta la landing es dueño de las
 * palabras**: `/site` publica las CLAVES, no los rótulos.
 *
 * ▶ **Por qué claves y no rótulos.** Los dos lectores son distintos: el selector lo lee el VISITANTE
 * en su idioma y el correo lo lee el NEGOCIO en el suyo (`config('app.locale')`). Un rótulo único
 * mentiría a uno de los dos. El producto conserva los suyos en sus ficheros de idioma, para el correo.
 *
 * ⚠️ **Y la ruta de esos ficheros NO se escribe aquí con comodín**: dentro de un docblock, la barra
 * tras el asterisco CIERRA el comentario y el fichero deja de compilar. Es la trampa de `#503`, y
 * cayó otra vez al escribir esta clase — el aviso estaba en el docblock que se acababa de leer.
 *
 * ▶ **Por qué sale del controlador.** Estaba en `ContactController::TOPICS`, y un recurso de la API
 * que lo leyera de allí ataría la API pública a un controlador de la web. El vocabulario es del
 * dominio; el controlador solo lo usa.
 *
 * ❗❗ **DEUDA DECLARADA, no resuelta**: `birthday` y `groups` son vocabulario del SECTOR de saltos
 * quemado en el producto, contra el principio «nada de negocio quemado en código». Publicar el
 * catálogo arregla que una landing de fuera no pueda saber qué asuntos existen; **no** arregla que
 * una bolera reciba «cumpleaños» y «grupos». Eso muerde el día del segundo cliente de otro sector,
 * y su salida es que la lista pase a ser DATO — el día que se haga, esta clase cambia de fuente y
 * ni el contrato ni quien pinta se enteran.
 */
final class ContactTopics
{
    /**
     * ⚠️ **El ORDEN es el del selector**, y `other` va al final a propósito: es el cajón de sastre.
     *
     * @var list<string>
     */
    public const ALL = ['birthday', 'groups', 'booking', 'other'];
}
