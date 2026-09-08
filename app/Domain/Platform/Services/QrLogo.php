<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Services\Qr\PngWithLogo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Throwable;

/**
 * Fase 6 · subsistema A — **quién decide qué icono va dentro del QR** (`specs/identidad-qr-puerta.md`
 * §9.7 C·3). El dibujo lo estampa {@see PngWithLogo}; esta clase
 * solo contesta a «¿qué bytes PNG?».
 *
 * ▶ **Es el MISMO hueco que el favicon** (`components/site/favicon.blade.php`, `DECISIONES #143`):
 * si la instalación dejó `public/img/client-favicon.svg`, ese; si no, el `apple-touch-icon.png` del
 * producto, **tal cual** (ya es PNG: cero rasterizado, cero dependencias). Se comprueba con el mismo
 * `@filemtime` que el Blade —una sola llamada a disco para existencia y frescura—.
 *
 * ⚠️ **Cuando la instalación SÍ declaró su icono y no se puede rasterizar, la respuesta es `null`,
 * NO el icono del producto.** Enseñar la «J» de JumpWeb dentro del carné de un cliente que entregó
 * su marca es una fuga de white-label impresa en un correo; un QR liso no lo es. La degradación
 * baja de calidad, nunca de marca.
 *
 * ▶ **El rasterizado es una CADENA que se degrada, y ninguno de los dos eslabones está en las dos
 * máquinas** (medido, 2026-08-28):
 *   1. **Imagick** — solo si `queryFormats('SVG')` lo trae. **Staging sí; en local NO**: al contenedor
 *      de Sail le falta `libmagickcore-6.q16-7-extra`, así que `extension_loaded('imagick')` da `true`
 *      y aun así no sabe leer un SVG. Preguntar por la extensión NO es preguntar por el formato.
 *   2. **`rsvg-convert`** por `proc_open` con tope de tiempo. **Local sí (2.58.0); staging NO.**
 *   3. `null` = QR liso, con un `Log::warning` **deduplicado una hora** (un correo de confirmación
 *      por cliente inundaría el log con la misma línea).
 *
 * ⚠️ **Toda salida a `null` deja aviso, y eso no era cierto hasta el `#445`**: escribir el SVG
 * temporal y lanzar el proceso fallaban **en silencio**, que son justo las dos que la presión de
 * procesos dispara — una degradación sin rastro es indistinguible de que no haya pasado nada, y en
 * la suite en paralelo llegó como un rojo que solo sabía decir «null no es string».
 *
 * ⚠️⚠️ **Nada de aquí puede lanzar.** Esta clase la llama el correo de confirmación de un pedido ya
 * cobrado: un icono mal subido no puede costar un 500 ni un correo sin enviar. Todo `Throwable` se
 * traga, se anota una vez y se sigue sin icono.
 *
 * ⚠️ Y el icono ha de ser **contornos, nunca `<text>`**: los dos rasterizadores pintan descentrado el
 * texto sin fuente incrustada (le pasa al propio favicon del producto). Misma regla que
 * `INSTALACION-CLIENTE.md` §4.
 */
class QrLogo
{
    /** Lado del PNG rasterizado. Sobra para 56 px de estampa y sirve si algún día sube la escala. */
    public const SIDE = 256;

    /** Segundos que se le dan a `rsvg-convert` antes de matarlo. Un favicon tarda ~30 ms. */
    private const RSVG_TIMEOUT = 2.0;

    /** Un favicon que pesa más que esto no es un favicon: se rechaza antes de rasterizar nada. */
    private const MAX_SVG_BYTES = 262144;

    private const CACHE_DIR = 'qr-logo';

    private const WARN_TTL = 3600;

    /**
     * Memo **de instancia**, no estático: la clase vive como `singleton` del contenedor, así que
     * dura lo que la petición y **el contenedor de cada test nace limpio**. Un memo estático habría
     * necesitado su reset en `TestCase::setUp()` (`SUITE-02`) y habría hecho depender del ORDEN de
     * los tests, que es la peor forma de fallo.
     *
     * ▶ Y no basta con `?string $memo`: `null` es una RESPUESTA legítima (no hay icono), no «aún no
     * lo he mirado». Sin la bandera, cada llamada reintentaría el rasterizado que ya falló.
     */
    private bool $resolved = false;

    private ?string $memo = null;

    /** Bytes PNG del icono de la instalación, o `null` si no hay ninguno utilizable. */
    public function png(): ?string
    {
        if ($this->resolved) {
            return $this->memo;
        }

        $this->resolved = true;

        try {
            $this->memo = $this->resolve();
        } catch (Throwable $e) {
            $this->warnOnce('resolve', 'el icono del QR no se pudo resolver: '.$e->getMessage());
            $this->memo = null;
        }

        return $this->memo;
    }

    /** Olvida el memo. Solo para tests que cambian el icono a mitad de proceso. */
    public function forget(): void
    {
        $this->resolved = false;
        $this->memo = null;
    }

    private function resolve(): ?string
    {
        $svgPath = $this->clientSvgPath();

        // `@filemtime` hace existencia y frescura en una sola llamada a disco, igual que el Blade.
        if (@filemtime($svgPath) === false) {
            return $this->productIcon();
        }

        $svg = @file_get_contents($svgPath);

        if (! is_string($svg) || $svg === '') {
            $this->warnOnce('svg-read', 'el icono de la instalación existe pero no se pudo leer: '.$svgPath);

            return null;
        }

        if (strlen($svg) > self::MAX_SVG_BYTES) {
            $this->warnOnce('svg-size', 'el icono de la instalación pesa '.strlen($svg).' B (tope '.self::MAX_SVG_BYTES.' B)');

            return null;
        }

        // ▶ La caché va por **CONTENIDO**, no por ruta ni por `mtime`: sustituir el fichero por otro
        // distinto invalida solo, y volver al anterior reaprovecha lo ya rasterizado. Sobrevive al
        // `rsync --delete` del despliegue porque `storage/` no entra en él.
        $key = self::CACHE_DIR.'/'.sha1($svg).'-'.self::SIDE.'.png';

        $cached = $this->readCache($key);

        if ($cached !== null) {
            return $cached;
        }

        $png = $this->rasterize($svg);

        // ⚠️ Aquí NO se avisa, y es deliberado: este punto no sabe POR QUÉ falló. El mensaje que
        // había afirmaba que no hay rasterizador, y con `rsvg-convert` instalado y un tope de tiempo
        // agotado eso manda al operador a instalar un paquete que ya tiene. **Una causa, una línea**:
        // avisa el eslabón que sabe cuál cedió, y son los seis.
        if ($png === null) {
            return null;
        }

        $this->writeCache($key, $png);

        return $png;
    }

    /** El suelo: el icono DEL PRODUCTO, ya en PNG. Sin él (instalación mutilada) no hay icono. */
    private function productIcon(): ?string
    {
        $bytes = @file_get_contents($this->productIconPath());

        if (! is_string($bytes) || ! str_starts_with($bytes, "\x89PNG")) {
            $this->warnOnce('product-icon', 'falta el icono del producto: '.$this->productIconPath());

            return null;
        }

        return $bytes;
    }

    private function rasterize(string $svg): ?string
    {
        if ($this->imagickReadsSvg()) {
            $png = $this->rasterizeWithImagick($svg);

            if ($png !== null) {
                return $png;
            }
        }

        $binary = $this->rsvgBinary();

        if ($binary !== null) {
            return $this->rasterizeWithRsvg($binary, $svg);
        }

        // Y aquí sí es cierto: esta máquina no tiene con qué, que es un estado legítimo del sistema.
        $this->warnOnce('raster-missing', 'no hay rasterizador de SVG en esta máquina (ni Imagick con SVG ni rsvg-convert): el QR sale sin icono');

        return null;
    }

    // ─── Los eslabones, cada uno en su método para poder doblarlos en los tests ──────────────

    /** Ruta del icono de la INSTALACIÓN (no versionada; puede no existir). */
    protected function clientSvgPath(): string
    {
        return public_path('img/client-favicon.svg');
    }

    /** Ruta del icono DEL PRODUCTO (siempre en el repo). */
    protected function productIconPath(): string
    {
        return public_path('apple-touch-icon.png');
    }

    /**
     * ⚠️ **Las DOS condiciones, y la segunda es la que sorprende**: la extensión puede estar cargada
     * y no traer el delegado de SVG (es el caso del contenedor local). Preguntarlo por adelantado
     * evita depender de una excepción para elegir camino.
     */
    protected function imagickReadsSvg(): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        try {
            return in_array('SVG', Imagick::queryFormats('SVG'), true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * `setResolution()` va **ANTES** de `readImageBlob()`: después no hace nada, porque el SVG ya se
     * ha rasterizado a la resolución por defecto (96 ppp) y lo que quedaría es un reescalado borroso.
     * 384 ppp = 4× → se rasteriza grande y se baja a 256 con Lanczos, que es donde se gana el filo.
     * El fondo transparente y `png32` conservan el alfa: el icono no trae su propio cuadrado blanco.
     */
    protected function rasterizeWithImagick(string $svg): ?string
    {
        try {
            $im = new Imagick;
            $im->setBackgroundColor(new ImagickPixel('transparent'));
            $im->setResolution(384, 384);
            $im->readImageBlob($svg);
            $im->setImageFormat('png32');
            $im->resizeImage(self::SIDE, self::SIDE, Imagick::FILTER_LANCZOS, 1);
            $blob = $im->getImageBlob();
            $im->clear();

            return str_starts_with($blob, "\x89PNG") ? $blob : null;
        } catch (Throwable $e) {
            $this->warnOnce('imagick', 'Imagick no pudo rasterizar el icono: '.$e->getMessage());

            return null;
        }
    }

    /**
     * El fichero temporal donde aterriza el SVG. Es un ESLABÓN DE ENTORNO más —como las dos rutas y
     * el binario— y por eso es sustituible: sin la costura, «el disco temporal dijo que no» sería la
     * única salida a `null` de esta clase que ningún caso puede ejercitar.
     *
     * @return string|false
     */
    protected function tempSvgPath()
    {
        return @tempnam(sys_get_temp_dir(), 'qr-logo-');
    }

    /**
     * Rutas absolutas y `is_executable()`: nada de `command -v`, que sería abrir una shell para
     * preguntar si hay que abrir un proceso.
     */
    protected function rsvgBinary(): ?string
    {
        foreach (['/usr/bin/rsvg-convert', '/usr/local/bin/rsvg-convert', '/opt/homebrew/bin/rsvg-convert'] as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * ⚠️ **El SVG va a un fichero temporal, NO por `stdin`**, y es a propósito: escribir en la tubería
     * de entrada de un proceso que aún no lee puede BLOQUEAR pasados los 64 KB del búfer, y ese
     * bloqueo ocurriría **antes** de que el tope de tiempo entrase en juego. Con el fichero, la única
     * tubería es la de salida y el tope la vigila de verdad.
     *
     * El comando va en forma de ARRAY: sin shell, así que ni la ruta ni nada se interpreta.
     */
    protected function rasterizeWithRsvg(string $binary, string $svg): ?string
    {
        $tmp = $this->tempSvgPath();

        if ($tmp === false || @file_put_contents($tmp, $svg) === false) {
            $this->warnOnce('rsvg-tmp', 'no se pudo escribir el SVG temporal en '.sys_get_temp_dir().': el QR sale sin icono');

            return null;
        }

        $pipes = [];
        $process = @proc_open(
            [$binary, '-w', (string) self::SIDE, '-h', (string) self::SIDE, '-f', 'png', $tmp],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );

        if (! is_resource($process)) {
            @unlink($tmp);
            $this->warnOnce('rsvg-spawn', 'no se pudo lanzar '.$binary.' (sin procesos o sin memoria): el QR sale sin icono');

            return null;
        }

        // Cerrar la entrada da EOF al hijo: no espera un dato que no va a llegar.
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = '';
        $deadline = microtime(true) + self::RSVG_TIMEOUT;
        $killed = false;

        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);

            if (! proc_get_status($process)['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                $killed = true;

                break;
            }

            usleep(5000);
        }

        // Lo que quedó en el búfer después de que el proceso terminase.
        $out .= (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($tmp);

        if ($killed) {
            $this->warnOnce('rsvg-timeout', 'rsvg-convert no terminó en '.self::RSVG_TIMEOUT.' s: el QR sale sin icono');

            return null;
        }

        if ($code !== 0 || ! str_starts_with($out, "\x89PNG")) {
            $this->warnOnce('rsvg-exit', 'rsvg-convert salió con código '.$code.' y '.strlen($out).' B de salida');

            return null;
        }

        return $out;
    }

    // ─── Caché de fichero y aviso deduplicado ───────────────────────────────────────────────

    private function readCache(string $key): ?string
    {
        try {
            $disk = Storage::disk('local');

            if (! $disk->exists($key)) {
                return null;
            }

            $cached = $disk->get($key);

            return (is_string($cached) && str_starts_with($cached, "\x89PNG")) ? $cached : null;
        } catch (Throwable) {
            // Un disco caprichoso cuesta una rasterización de más, no un icono de menos.
            return null;
        }
    }

    private function writeCache(string $key, string $png): void
    {
        try {
            Storage::disk('local')->put($key, $png);
        } catch (Throwable $e) {
            $this->warnOnce('cache-write', 'no se pudo guardar el icono rasterizado: '.$e->getMessage());
        }
    }

    /**
     * Un aviso por causa y hora. `Cache::add()` es atómico y devuelve `true` **solo la primera vez**:
     * eso es lo que deduplica, no un `has()` seguido de un `put()` (que dejaría pasar dos avisos
     * simultáneos). Y si la caché también está caída, se anota igualmente: perder el aviso sería
     * peor que repetirlo.
     */
    private function warnOnce(string $cause, string $message): void
    {
        try {
            if (! Cache::add('qr-logo:warn:'.$cause, true, self::WARN_TTL)) {
                return;
            }
        } catch (Throwable) {
            // sin caché no hay deduplicación, pero el aviso sale
        }

        Log::warning('[qr-logo] '.$message, ['causa' => $cause]);
    }
}
