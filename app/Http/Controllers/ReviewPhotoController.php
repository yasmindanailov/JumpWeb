<?php

namespace App\Http\Controllers;

use App\Domain\Content\Services\GoogleReviewImages;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * **Sirve una imagen de una reseña desde NUESTRO servidor** (T2·4,
 * `docs/specs/google-business-profile.md` §4.3·6; `DECISIONES #524`, `#730`).
 *
 * Es la única puerta al disco privado. Existe —en vez de dejar los ficheros bajo `public/`— por dos
 * razones que no se pueden conseguir de otra forma:
 *
 *  1. **El fichero se borra en la misma operación que su fila.** Con el servidor web sirviéndolos
 *     por detrás, esa promesa dependería de que nadie tuviera la URL guardada.
 *  2. **Son bytes de un tercero**, así que salen con su propia
 *     `Content-Security-Policy: default-src 'none'; sandbox` y con `nosniff`. Bajo `public/` las
 *     cabeceras las pone el servidor web y son las mismas para todo el sitio.
 *
 * ⚠️⚠️ **La ruta es PÚBLICA y sin firma, a propósito.** Estas imágenes se pintan en la portada, que
 * la ve cualquiera y que se cachea; una URL firmada caduca y dejaría la sección con huecos. Lo que
 * protege aquí no es un permiso: es que **el nombre tiene que ser uno de los nuestros**
 * ({@see GoogleReviewImages::isOwnName()}, 64 hexadecimales y una extensión de la lista). Con eso no
 * hay ruta que recorrer ni fichero ajeno que pedir.
 *
 * ⚠️ **`SecurityHeaders` respeta la CSP que ya venga puesta** (comprobado en su línea del `if`), así
 * que la de aquí no se la pisa la del sitio. Si algún día ese `if` desapareciera, estas imágenes
 * pasarían a correr con la CSP de la web: hay caso que lo vigila.
 */
class ReviewPhotoController extends Controller
{
    public function __invoke(string $fichero): BinaryFileResponse
    {
        abort_unless(GoogleReviewImages::isOwnName($fichero), 404);

        $disco = GoogleReviewImages::disk();

        abort_unless($disco->exists($fichero), 404);

        return response()->file($disco->path($fichero), [
            // Lo más cerrado que admite una imagen: no carga nada, no ejecuta nada, y `sandbox` le
            // quita el origen aunque el navegador acabe interpretándola como un documento.
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        // ⚠️ **`nosniff` NO se pone aquí**, y lo destapó el arnés: `SecurityHeaders` lo estampa
        // incondicionalmente en TODA respuesta, así que escribirlo otra vez era una línea que
        // ninguna mutación podía poner en rojo — es decir, código muerto disfrazado de seguridad.
        // El caso sigue aseverando la cabecera: lo que se vigila es la propiedad, venga de donde
        // venga.
        // ⚠️⚠️ **AQUÍ NO SE PONE `Cache-Control`, y es una renuncia consciente.** §4.3·6 pide una
        // caché «corta»; lo que hay es `no-store`, que es más estricto todavía —lo pone
        // `NoStoreWebResponses`, que va GLOBAL e incondicional por una decisión medida de `RGPD-04`—.
        // Escribir aquí un `max-age` sería una cabecera muerta: ese middleware corre después y la
        // pisa, comprobado.
        // ▶ El coste es real y está medido a ojo, no en el navegador: **cada visita vuelve a pedir
        // cada foto**. Hacerlas cacheables exige EXIMIR a esta ruta de un middleware que se escribió
        // incondicional a propósito, y su propia doc dice que eso «merece medirse aparte y no
        // colarse de propina» en otro trabajo. Va anotado para la T2·6, que es la que mide el
        // presupuesto de la portada (`PERF-02`).
    }
}
