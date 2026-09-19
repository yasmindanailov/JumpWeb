<?php

namespace App\Http\Instancia;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;

/**
 * **Las vistas de la INSTANCIA** — la vía B de `docs/specs/paquete-de-instancia.md` §4.1.
 *
 * La landing de una instalación no vive en este repo. El producto presta el motor (las rutas, los
 * componentes, el cajón, el panel) y la instancia pone la página: un directorio `web/` dentro de su
 * paquete, registrado aquí como el namespace `instancia`.
 *
 * ❗❗❗ **Es un NAMESPACE y no un `prependLocation()`, y la diferencia es de seguridad, no de gusto.**
 * Con `prependLocation` la instancia sobrescribiría vistas del producto **por su nombre**: un fichero
 * llamado `mail/verify.blade.php` en el paquete de un cliente secuestraría el correo de verificación
 * del producto, y una página de error se podría sustituir por otra — sin que nada avisara y con el
 * fallo apareciendo en la instalación del cliente, no aquí. Con namespace, lo de la instancia **se
 * llama distinto** (`instancia::contacto`) y no puede colisionar con nada del producto.
 *
 * ⚠️⚠️ **`SEC-12`: esto es una ruta desde la que el servidor EJECUTA CÓDIGO.** Blade compila a PHP.
 * Por eso {@see rutaDelPaquete} solo mira la configuración y las tres comprobaciones de abajo son
 * invariante y no validación cosmética.
 *
 * ⚠️ **Sin paquete no pasa nada malo**: el namespace no se registra, {@see pick} devuelve el respaldo
 * del producto y la instalación sirve su anfitrión mínimo. Una instalación recién montada está así, y
 * un 500 ahí sería un producto que no arranca hasta que alguien le dé una landing.
 */
class InstanceViews
{
    /** El namespace con el que se nombran las vistas de la instancia: `instancia::contacto`. */
    public const NAMESPACE = 'instancia';

    /** La subcarpeta del paquete donde viven las vistas. El resto del paquete no es código de vistas. */
    public const SUBCARPETA = 'web';

    /** El fichero donde el paquete declara con qué producto está hecho para funcionar. */
    public const MANIFIESTO = 'instancia.json';

    /**
     * **La versión del CONTRATO DE INSTANCIA que sirve este producto.**
     *
     * Sube cuando un paquete existente deja de valer tal cual: cambia el nombre de una carpeta, una vista
     * pasa a recibir otras variables, se retira un componente que las landings usaban. Es el MAYOR de
     * `producto-e-instancias.md` §4.1, y por eso NO sube al añadir cosas: añadir no rompe a nadie.
     */
    public const CONTRATO = 1;

    public function __construct(private readonly ViewFactory $vistas) {}

    /**
     * Registra el namespace si hay un paquete VÁLIDO. Se llama una vez, al arrancar.
     *
     * Devuelve la ruta registrada, o `null` si no se registró nada — que es el caso normal en
     * desarrollo y en una instalación sin landing propia todavía.
     */
    public function registrar(): ?string
    {
        $raiz = self::rutaDelPaquete();

        if ($raiz === null) {
            return null;
        }

        $web = $raiz.DIRECTORY_SEPARATOR.self::SUBCARPETA;

        if (! is_dir($web)) {
            // ⚠️ Se avisa pero NO se revienta: un paquete a medio desplegar deja la web con el
            // anfitrión mínimo, que es feo pero está en pie. Reventar aquí tumbaría también
            // `/admin` y `/api/v1`, que no tienen nada que ver con la landing.
            Log::warning('instancia: el paquete no tiene carpeta de vistas', ['web' => $web]);

            return null;
        }

        $this->avisarSiElContratoNoCuadra($raiz);

        $this->vistas->addNamespace(self::NAMESPACE, $web);

        return $web;
    }

    /**
     * **El contrato del paquete contra el del producto: avisa, NO tumba** (spec §4.3).
     *
     * ⚠️⚠️ Y esto es deliberado, no una tibieza: negarse a servir una landing porque su manifiesto dice `1`
     * y el producto va por `2` deja la web del cliente EN BLANCO por un número, justo después de un
     * despliegue y sin que el visitante tenga la culpa de nada. El aviso va al log, donde lo ve quien puede
     * arreglarlo; la web sigue en pie mientras tanto.
     *
     * ⚠️ Un manifiesto ausente o ilegible tampoco tumba nada: hay paquetes hechos a mano y eso no es un
     * error, es una instalación que todavía no lo declara.
     */
    private function avisarSiElContratoNoCuadra(string $raiz): void
    {
        $fichero = $raiz.DIRECTORY_SEPARATOR.self::MANIFIESTO;

        if (! is_file($fichero)) {
            return;
        }

        $manifiesto = json_decode((string) file_get_contents($fichero), true);

        if (! is_array($manifiesto) || ! isset($manifiesto['contrato'])) {
            return;
        }

        if ((int) $manifiesto['contrato'] !== self::CONTRATO) {
            Log::warning('instancia: el paquete está hecho para otra versión del producto', [
                'paquete' => (int) $manifiesto['contrato'],
                'producto' => self::CONTRATO,
            ]);
        }
    }

    /**
     * El nombre de vista a renderizar: la de la instancia si existe, y si no el respaldo del producto.
     *
     * Se usa así, y la decisión de qué respaldo toca es del llamante porque solo él sabe qué página
     * es equivalente a la suya:
     *
     *     return view($this->vistas->pick('contacto', 'anfitrion.pagina'), [...]);   (ejemplo)
     */
    public function pick(string $deLaInstancia, string $respaldo): string
    {
        $nombre = self::NAMESPACE.'::'.$deLaInstancia;

        return $this->vistas->exists($nombre) ? $nombre : $respaldo;
    }

    /**
     * **La ruta del paquete, leída SOLO de configuración** (`SEC-12`).
     *
     * ⚠️⚠️ Estática y sin parámetros **a propósito**: así no hay forma de pasarle nada. Una firma que
     * aceptara una ruta invitaría a que algún día alguien le pasara `$request->input(...)`, y eso
     * sería ejecución remota de código con un `?paquete=`. Lo que no se puede pasar no se puede
     * colar.
     *
     * Las tres comprobaciones son el invariante, no cortesía:
     *  1. **absoluta** — una relativa se resolvería contra el cwd del proceso, distinto en `artisan`,
     *     en php-fpm y en la cola: tres landings según quién renderice;
     *  2. **existe y es directorio** — si no, no hay nada que registrar;
     *  3. **FUERA DEL ÁRBOL DEL PRODUCTO** (`base_path()`), que es la que de verdad muerde y cubre dos
     *     peligros de golpe:
     *     · bajo `public/` el servidor web entregaría el `.blade.php` **en crudo**, con lo que lleve
     *       dentro — y `public/` está dentro del árbol, así que esta comprobación ya lo prohíbe;
     *     · en cualquier otro sitio del árbol, el `rsync --delete` del despliegue **se lo lleva**: la
     *       instalación se quedaría sin landing en el siguiente despliegue y nadie sabría por qué.
     *     ▶ Y es lo coherente con el diseño: el paquete es OTRO repo (`instancia-<slug>`), así que no
     *     tiene nada que hacer dentro de éste.
     */
    public static function rutaDelPaquete(): ?string
    {
        $bruta = config('instancia.ruta');

        if (! is_string($bruta) || trim($bruta) === '') {
            return null;
        }

        $ruta = rtrim(trim($bruta), DIRECTORY_SEPARATOR);

        if (! str_starts_with($ruta, DIRECTORY_SEPARATOR)) {
            Log::error('instancia: la ruta del paquete no es absoluta y se ignora', ['ruta' => $ruta]);

            return null;
        }

        $real = realpath($ruta);

        if ($real === false || ! is_dir($real)) {
            return null;
        }

        if (self::dentroDelProducto($real)) {
            Log::error(
                'instancia: la ruta del paquete está DENTRO del árbol del producto y se ignora (SEC-12)',
                ['ruta' => $real],
            );

            return null;
        }

        return $real;
    }

    /**
     * ¿Cuelga esta ruta del árbol del producto?
     *
     * ⚠️ Se compara con el separador pegado (`/var/www/html/`) y no con el prefijo a secas: sin él,
     * `/var/www/html-instancia` daría positivo por empezar igual, y una carpeta legítima quedaría
     * descartada. Es el mismo cuidado que pide cualquier comparación de prefijos de ruta.
     */
    private static function dentroDelProducto(string $real): bool
    {
        $arbol = realpath(base_path());

        if ($arbol === false) {
            return false;
        }

        return $real === $arbol
            || str_starts_with($real.DIRECTORY_SEPARATOR, $arbol.DIRECTORY_SEPARATOR);
    }
}
