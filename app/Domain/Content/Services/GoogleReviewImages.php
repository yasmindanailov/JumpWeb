<?php

namespace App\Domain\Content\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * **Las imágenes de una reseña, traídas a casa y servidas desde casa** (T2·4,
 * `docs/specs/google-business-profile.md` §4.3·6 y §4.3·9; `DECISIONES #524`, `#730`).
 *
 * ❗❗❗ **Esto es lo que quita la dependencia del consentimiento, y por eso existe la T2 entera.**
 * Mientras la cara del autor se pida a `lh3.googleusercontent.com`, es el NAVEGADOR DEL VISITANTE
 * quien habla con Google: sin la categoría `maps` aceptada no se puede pintar (`RGPD-05`), y con
 * ella `img-src` tiene que nombrar a Google (`SEC-01`). Descargándola, la portada deja de pedirle
 * nada a nadie.
 *
 * ❗❗ **Y a cambio, aquí se ejecutan bytes de un tercero** —se guardan y se sirven—, así que el
 * descargador está endurecido en seis frentes y **cada uno tiene su caso**:
 *
 *  1. **host de lista blanca EXACTA y solo `https`** (nunca por sufijo ni por «contiene»);
 *  2. **sin redirecciones**: un 302 es la forma barata de sacar la petición de la lista blanca;
 *  3. **tope de bytes leyendo a trozos**: una respuesta sin fin llena el disco y tumba el worker;
 *  4. **el tipo lo dicen los BYTES, no la cabecera ni la extensión** — jpeg, png, webp o gif, y
 *     **SVG jamás**, que es XML con scripts dentro;
 *  5. **el nombre es el hash del contenido**, nunca nada del autor, y la extensión sale del tipo;
 *  6. **escritura atómica** y **ninguna librería de imagen las toca**: la política prohíbe
 *     manipular el contenido (*«cannot be manipulated»*), así que no se redimensionan.
 *
 * ⚠️ **Si la descarga falla, la tarjeta pinta la inicial — jamás la URL de Google** (§4.3·6). Un
 * respaldo que «al menos enseñe algo» deshace la tanda entera sin romper nada visible.
 */
final class GoogleReviewImages
{
    /** El disco privado. Se nombra explícito para que nadie lo mude a `public` sin darse cuenta. */
    public const DISK = 'google-reviews';

    /**
     * Hosts desde los que Google sirve imágenes de reseñas. **Coincidencia EXACTA.**
     *
     * ⚠️ Está repetido en {@see IncomingGoogleReview} a propósito, y no es descuido: allí se sanea
     * el dato **donde nace** (`SEC-07`) y aquí se comprueba **donde se ejecuta la petición**. Son
     * dos capas, y la de aquí es la que de verdad decide a quién se le abre un socket.
     *
     * @var list<string>
     */
    public const HOSTS = [
        'lh3.googleusercontent.com',
        'lh4.googleusercontent.com',
        'lh5.googleusercontent.com',
        'lh6.googleusercontent.com',
    ];

    /**
     * Tope de bytes. Una miniatura de Google no llega a esto ni de lejos; el número está para que
     * una respuesta que no termina **no llene el disco**, no para ajustar nada.
     */
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 8;

    private const CONNECT_TIMEOUT_SECONDS = 4;

    /** Cuánto se lee de golpe del flujo. */
    private const CHUNK_BYTES = 8192;

    /**
     * Los tipos que se aceptan, por sus **bytes mágicos**.
     *
     * ⚠️⚠️ **Es una lista BLANCA, y por eso el SVG no necesita una regla propia**: no empieza por
     * ninguna de estas firmas, así que cae solo. Una lista negra («todo menos SVG») habría que ir
     * ampliándola cada vez que alguien inventa un formato con scripts dentro.
     * ⚠️ WEBP se comprueba en DOS trozos: `RIFF`, cuatro bytes de tamaño que no importan, y `WEBP`.
     *
     * @var array<string,string> extensión => prefijo de bytes
     */
    private const MAGIC = [
        'jpg' => "\xFF\xD8\xFF",
        'png' => "\x89PNG\r\n\x1A\n",
        'gif' => 'GIF8',
    ];

    /**
     * **Trae una imagen y devuelve su ruta en nuestro disco**, o `null` si no se puede.
     *
     * `null` es una respuesta normal y no se registra como error: la reseña sale con la inicial.
     */
    public function fetch(?string $url): ?string
    {
        if ($url === null || ! self::allowed($url)) {
            return null;
        }

        // ⚠️⚠️ **Lo que se atrapa aquí es EXACTAMENTE «la red falló», y nada más** (`ConnectionException`).
        // Un `catch (Throwable)` alrededor de la petición parece más seguro y es lo contrario: se
        // traga los errores de programación **y** el aviso de «petición sin doble» de las pruebas,
        // así que una descarga que en realidad no se estaba midiendo sale igual de verde. Medido el
        // 2026-09-21: con el catch ancho, los casos de la pasada pasaban sin fingir ni una imagen.
        try {
            $respuesta = Http::withOptions(['allow_redirects' => false, 'stream' => true])
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $e) {
            // Del fallo se registra QUE pasó, nunca la URL: lleva dentro el identificador de la foto
            // del autor, y `RGPD-02` no lo quiere en un log.
            Log::info('google_business.image_failed', ['clase' => $e::class]);

            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        // El flujo sí puede romperse a media lectura —una respuesta truncada— y eso no puede tumbar
        // la pasada entera: ahí el `RuntimeException` sí se atrapa, y solo ahí.
        try {
            $bytes = $this->read($respuesta);
        } catch (RuntimeException $e) {
            Log::info('google_business.image_failed', ['clase' => $e::class]);

            return null;
        }

        if ($bytes === null) {
            return null;
        }

        $extension = self::sniff($bytes);

        if ($extension === null) {
            return null;
        }

        return $this->store($bytes, $extension);
    }

    /**
     * ¿La URL apunta a un host de la lista y por `https`?
     *
     * Se compara el host ENTERO: `lh3.googleusercontent.com.malo.net` **contiene** uno permitido y
     * `evil.lh3.googleusercontent.com` **termina** en uno permitido. Ninguno de los dos vale.
     */
    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::HOSTS, true);
    }

    /**
     * Lee el cuerpo **a trozos y con tope**, o `null` si se pasa o si la respuesta no vale.
     *
     * ⚠️⚠️ **`allow_redirects => false`**: seguir un 302 es exactamente cómo se sale de la lista
     * blanca sin que la lista blanca se entere. La comprobación del host vale para la URL que
     * escribimos nosotros, no para la que nos diga que visitemos un tercero.
     * ⚠️ **Se lee en trozos y se cuenta**: `->body()` traería el cuerpo ENTERO a memoria antes de
     * que nadie pudiera medirlo, que es justo lo que el tope tiene que impedir.
     */
    private function read(Response $response): ?string
    {
        $flujo = $response->toPsrResponse()->getBody();
        $bytes = '';

        while (! $flujo->eof()) {
            $bytes .= $flujo->read(self::CHUNK_BYTES);

            if (strlen($bytes) > self::MAX_BYTES) {
                // Se corta en cuanto se pasa, no al final: de eso va el tope.
                return null;
            }
        }

        return $bytes === '' ? null : $bytes;
    }

    /**
     * La extensión que dicen los BYTES, o `null` si no es un tipo que aceptemos.
     *
     * ⚠️ Ni la cabecera `Content-Type` ni la extensión de la URL entran en esta decisión: las dos
     * las escribe quien sirve el fichero, y aquí quien sirve el fichero es de fuera.
     */
    public static function sniff(string $bytes): ?string
    {
        foreach (self::MAGIC as $extension => $firma) {
            if (str_starts_with($bytes, $firma)) {
                return $extension;
            }
        }

        // WEBP: `RIFF` · 4 bytes de tamaño · `WEBP`.
        if (str_starts_with($bytes, 'RIFF') && strlen($bytes) >= 12 && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    /**
     * Guarda los bytes y devuelve el nombre del fichero.
     *
     * ⚠️⚠️ **El nombre es el hash del CONTENIDO** (§4.3·6). No se deriva del autor —sería su nombre
     * en una ruta pública— ni del identificador de Google, y de regalo **dos reseñas con la misma
     * foto comparten fichero** en vez de duplicarlo.
     * ⚠️ **Escritura atómica**: se escribe con un nombre temporal y se renombra. Sin eso, una
     * petición que llegue a mitad de la escritura sirve medio fichero, y el barrido de huérfanos se
     * encontraría restos que no sabe de quién son.
     * ⚠️ **Ninguna librería de imagen toca estos bytes**: la política prohíbe manipular el
     * contenido, así que no se redimensionan, no se recomprimen y no se les quitan metadatos.
     */
    private function store(string $bytes, string $extension): ?string
    {
        $nombre = hash('sha256', $bytes).'.'.$extension;
        $disco = self::disk();

        if ($disco->exists($nombre)) {
            return $nombre;
        }

        $temporal = $nombre.'.'.bin2hex(random_bytes(8)).'.parcial';

        if (! $disco->put($temporal, $bytes)) {
            return null;
        }

        if ($disco->move($temporal, $nombre)) {
            return $nombre;
        }

        // ⚠️ Si el renombrado falla, lo único que puede haber pasado sin ser un error es que otra
        // pasada dejara el MISMO fichero terminado entre medias —el nombre es el hash del contenido,
        // así que sería idéntico byte a byte—. Aun así se dice que no hay foto y la reseña sale con
        // la inicial (§4.3·6): la pasada de mañana encontrará el fichero ya puesto y la pondrá.
        // Preferir eso a volver a preguntar por el fichero deja el código sin una rama que solo se
        // ejecutaría en una carrera que el candado de la pasada ya hace casi imposible.
        $disco->delete($temporal);

        return null;
    }

    /**
     * Borra los ficheros de una reseña. Se llama **en la misma operación que su fila** (§4.3·6).
     *
     * @param  list<string|null>  $paths
     */
    public function forget(array $paths): void
    {
        $disco = self::disk();

        foreach ($paths as $ruta) {
            if (is_string($ruta) && $ruta !== '' && self::isOwnName($ruta)) {
                $disco->delete($ruta);
            }
        }
    }

    /**
     * **¿Este nombre lo escribimos nosotros?** Hash de 64 hexadecimales y una de las extensiones.
     *
     * ⚠️ Es lo que hace imposible el recorrido de directorios: no se comprueba que «no tenga
     * `..`» —esa lista es interminable— sino que la forma sea exactamente la nuestra. Lo usa el
     * borrado **y** la ruta que sirve los ficheros.
     */
    public static function isOwnName(string $name): bool
    {
        return preg_match('/^[0-9a-f]{64}\.(jpg|png|gif|webp)$/', $name) === 1;
    }

    /**
     * **Barrido de huérfanos** (§4.3·6): borra lo que ya no referencia ninguna fila.
     *
     * ⚠️⚠️ **Solo toca ficheros con más de una hora**, y esa guarda no es prudencia vaga: entre que
     * la descarga escribe el fichero y que la transacción guarda la fila pasa un instante, y un
     * barrido que corriera justo ahí borraría la foto de una reseña que se está guardando bien.
     *
     * @param  list<string>  $enUso  las rutas que las filas dicen estar usando
     * @return int cuántos ficheros se borraron
     */
    public function sweep(array $enUso): int
    {
        $disco = self::disk();
        $corte = now()->subHour()->getTimestamp();
        $vivos = array_flip($enUso);
        $borrados = 0;

        foreach ($disco->files() as $fichero) {
            if (isset($vivos[$fichero])) {
                continue;
            }

            if ($disco->lastModified($fichero) >= $corte) {
                continue;
            }

            $disco->delete($fichero);
            $borrados++;
        }

        return $borrados;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
