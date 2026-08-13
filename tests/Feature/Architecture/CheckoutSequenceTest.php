<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Cierre de Fase 3 — **la secuencia de la compra tiene UN solo sitio, y esto lo impone**
 * (`docs/specs/checkout-orquestado.md` §4.7).
 *
 * El refactor que bajó «admitir → crear → abrir cobro» al dominio arregla el síntoma —cinco copias
 * en cuatro clases de entrega— pero no el mecanismo: nada impedía que apareciera una sexta. Peor
 * aún, tras el refactor tendría un camino MÁS cómodo que antes, porque `ModuleBoundariesTest` exime
 * la capa de entrega entera (es el *composition root*) y permite inyectar cualquier `Contracts`, y
 * `ApiBoundariesTest` no prohíbe `open()`/`reopen()`. Un `grep` en el commit del refactor no vale:
 * se cumple el día que se escribe y caduca al siguiente. Esta es su versión ejecutable.
 *
 * **La regla, dicha de una vez**: fuera de `app/Domain`, nadie abre un cobro ni crea o suelta un
 * pedido. Se pide la secuencia entera a `Booking\Contracts\ReservationCheckout` y se traduce su
 * resultado a HTTP, a Livewire o a lo que toque.
 *
 * Nótese lo que **sí** se permite y por qué: `OrderCreator` se puede seguir nombrando —el sidebar
 * refleja su `MAX_LINES_PER_CART` para la UI (`PAY-12`)—, porque nombrar una clase para leer una
 * constante no es escribir la secuencia. Lo que se prohíbe son las tres cosas que la componen.
 *
 * Escaneo por TOKENIZADOR: los docblocks de los controladores citan `PaymentInitiator` a propósito,
 * para explicar dónde vive ahora lo que ya no está ahí, y una regex los contaría como infracciones.
 */
class CheckoutSequenceTest extends TestCase
{
    /**
     * La IDA del pago no se nombra fuera del dominio. Ni la implementación ni su puerto: inyectar
     * `PaymentInitiation` en un controlador es exactamente la forma cómoda de volver a escribir la
     * secuencia a mano, y la que este test existe para cerrar.
     *
     * @var list<string>
     */
    private const FORBIDDEN_CLASSES = ['PaymentInitiator', 'PaymentInitiation'];

    /**
     * Crear un pedido y soltarlo son las otras dos mitades de la secuencia. `createPendingOrder`
     * decide si nace con retención de aforo (`AFORO-10`) y `releaseAfterFailedPaymentStart` es la
     * compensación asimétrica —soltar en el primer cobro, no tocar en el reintento—, que es una
     * regla de negocio y no una decisión de presentación.
     *
     * @var list<string>
     */
    private const FORBIDDEN_METHODS = ['createPendingOrder', 'releaseAfterFailedPaymentStart'];

    /**
     * Excepciones CON NOMBRE, al estilo de las baselines de `ModuleBoundariesTest`: explícitas,
     * justificadas y solo pueden encoger.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_EXCEPTIONS = [
        // El verificador de concurrencia (dev-only, `INVARIANTES §6`) llama a `OrderCreator` a
        // propósito y sin hold: lo que mide es la carrera de `lockSlots()` bajo `pcntl_fork` sobre
        // MySQL, y pasar por el orquestador le añadiría la admisión y la ida del pago —cambiando lo
        // que la prueba mide y metiendo el limitador de frecuencia en medio de N procesos rivales—.
        'Console/Commands/VerifyPurchaseConcurrency.php' => ['createPendingOrder'],
    ];

    /** El escaneo nunca puede pasar en vacío: un glob roto dejaría un test verde que no mira nada. */
    public function test_the_scan_actually_sees_the_delivery_layer(): void
    {
        $files = $this->deliveryFiles();

        $this->assertNotEmpty($files, 'no se ha escaneado ningún fichero fuera de app/Domain');
        $this->assertContains(
            'Http/Controllers/Api/V1/OrdersController.php',
            array_map(fn (string $f): string => $this->relative($f), $files),
            'el escaneo no ve el controlador que creaba pedidos: ¿ha cambiado de sitio?'
        );
    }

    /** La guarda. */
    public function test_nobody_outside_the_domain_writes_the_checkout_sequence(): void
    {
        $violations = [];

        foreach ($this->deliveryFiles() as $file) {
            $relative = $this->relative($file);

            foreach ($this->sequenceUsesIn($file) as $use) {
                if (in_array($use, self::ALLOWED_EXCEPTIONS[$relative] ?? [], true)) {
                    continue;
                }

                $violations[] = "  app/{$relative}  →  {$use}";
            }
        }

        $this->assertSame(
            [], $violations,
            "La secuencia de la compra se está escribiendo fuera del dominio:\n".implode("\n", $violations)."\n\n".
            'Pide la secuencia entera a `Booking\Contracts\ReservationCheckout` (`start()`/`retry()`) '.
            "y traduce su resultado. El ORDEN es la regla y vive en `CheckoutOrchestrator`:\n".
            'admitir consumiendo ficha → crear con la ventana de retención → abrir el cobro → '.
            'soltar el pedido solo si es el primer intento.'
        );
    }

    /** `ALLOWED_EXCEPTIONS` también solo encoge: la que deja de usarse hay que borrarla. */
    public function test_the_named_exceptions_are_still_in_use(): void
    {
        foreach (self::ALLOWED_EXCEPTIONS as $relative => $uses) {
            $path = app_path($relative);

            $this->assertFileExists($path, "«{$relative}» ya no existe — quita su excepción");

            foreach ($uses as $use) {
                $this->assertContains(
                    $use,
                    $this->sequenceUsesIn($path),
                    "«{$relative}» ya no usa «{$use}» — quita la excepción (solo encogen)"
                );
            }
        }
    }

    /**
     * Usos de la secuencia que hay en el fichero: clases prohibidas REFERENCIADAS (por `use` o por
     * FQCN inline) y métodos prohibidos LLAMADOS.
     *
     * @return list<string>
     */
    private function sequenceUsesIn(string $file): array
    {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));

        $found = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');

                if (! str_starts_with($name, 'App\\')) {
                    continue;
                }

                $short = mb_substr($name, mb_strrpos($name, '\\') + 1);

                if (in_array($short, self::FORBIDDEN_CLASSES, true)) {
                    $found[$short] = true;
                }

                continue;
            }

            // `->createPendingOrder(...)` / `Clase::createPendingOrder(...)`
            if ($token[0] !== T_DOUBLE_COLON && $token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;

            if (is_array($next) && $next[0] === T_STRING && in_array($next[1], self::FORBIDDEN_METHODS, true)) {
                $found[$next[1]] = true;
            }
        }

        return array_keys($found);
    }

    /** Todo `app/` MENOS el dominio: es donde la secuencia no puede reaparecer. */
    private function deliveryFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (str_starts_with($file->getPathname(), app_path('Domain'))) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /** Ruta relativa a `app/`, con `/` — la clave de las excepciones. */
    private function relative(string $file): string
    {
        return mb_substr($file, mb_strlen(app_path()) + 1);
    }
}
