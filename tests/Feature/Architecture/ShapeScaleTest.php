<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA FORMA TAMBIÉN ES TEMA: la escala de canto, la ley del motivo y el anillo de foco**
 * (`docs/specs/tema-por-instalacion.md`, tanda 2a).
 *
 * La tanda 1 hizo que el COLOR siguiera al cliente. Esta hace lo mismo con la FORMA, y el
 * problema no era el mismo: en color había 866 usos que ya leían por token y bastaba
 * re-escoparlos; en forma había **95 literales de `border-radius`** y **23 reglas de foco con
 * cinco tratamientos distintos y CERO tokens**. O sea que un cliente podía cambiar toda su
 * marca y sus cantos —y el anillo con el que su visitante navega con teclado— seguían siendo
 * los del primero. No fallaba nada, no avisaba nadie.
 *
 * ⚠️ **Lo que este fichero vigila no es «que haya tokens», es que las EXCEPCIONES no crezcan.**
 * Un canto en literal no rompe nada hoy: rompe el día que una instalación redefine su escala y
 * ese canto se queda quieto mientras los otros 149 se mueven. Por eso las tres listas de abajo
 * **solo pueden encoger**, igual que `ALLOWED_SELECTORS` en `RawColourIsNotATokenTest`.
 *
 * ⚠️⚠️ **Y una trampa que costó una pasada**: un literal de `border-radius` NO es
 * automáticamente deuda. Al medir los 56 que quedaban aparecieron **dos leyes distintas
 * mezcladas**, y meterlas en el mismo saco habría roto una de ellas:
 *
 *   · **canto de contenedor** — el borde de una tarjeta, un control, un badge. Pertenece a la
 *     escala y tiene que salir de un token.
 *   · **motivo cuadrado** — la familia `.jj-block` y sus primos, donde el radio es la FORMA de
 *     un cuadradito decorativo a cada tamaño. Sigue una ley de facto **medida en el propio
 *     producto: radio ≈ lado / 4** (mediana exacta 4,00 sobre 16 declaraciones). Forzarlos a un
 *     escalón fijo convertiría una familia proporcional en cinco cuadrados mal redondeados.
 *
 * La tercera familia —dibujo— ni siquiera es un canto: son las cuatro esquinas del marcador QR,
 * un subrayado tipográfico en `em` y una barra de 2 px de alto.
 */
class ShapeScaleTest extends TestCase
{
    private const SHEETS = 'public/css/*.css';

    /** La escala de canto, CERRADA. Añadir un escalón es una decisión de producto, no un arreglo. */
    private const RADIUS_SCALE = [
        '--r-xs' => 5,
        '--r-sm' => 8,
        '--r-md' => 10,
        '--r-btn' => 14,
        '--r' => 16,
        '--r-lg' => 28,
        '--r-pill' => 999,
    ];

    /**
     * **Familia MOTIVO CUADRADO** — `selector => [lado en px, radio en px]`.
     *
     * Su radio no sale de la escala: sale de la ley `lado / 4`. La lista solo encoge; para que
     * entre uno nuevo hay que poder justificar que es un motivo y no un contenedor.
     */
    private const SQUARE_MOTIF = [
        // ⚠️ `.jj-block--xs/--sm/--md` vivían aquí y SE RETIRARON el 2026-08-31: el motivo «foam»
        // es del cliente ANTIGUO —sus iniciales dan nombre a la clase— y el owner lo sacó de la
        // landing. Queda solo el uso del cajón, que es otra tanda.
        '.bk-context .jj-block' => [11, 3.0],
        '.offw-burst .spark' => [9, 2.0],
        // ⚠️ `.svc-marquee__item::after` vivía aquí y SE RETIRÓ el 2026-08-31 con la marquesina:
        // era el motivo «foam» del cliente antiguo **copiado como geometría**, no con la clase
        // `.jj-block`, y por eso sobrevivió al barrido que retiró aquélla. Lo cazó este mismo
        // caso, que es para lo que está. *Un motivo copiado a mano no aparece buscando su nombre.*
        '.svc-ed2__kicker::before' => [12, 3.0],
        // ⚠️ `.hero__stat .sep` vivía aquí y SE RETIRÓ el 2026-08-27 (tanda 2b, paso 1): la familia
        // `.hero__stat` entera estaba MUERTA —cero usos en `resources/`— y se fue con otras cinco.
        // Lo cazó esta misma guarda, que es para lo que está: una excepción sin sujeto tapa el
        // siguiente caso que se llame igual.
        '.bd-proc__cube' => [44, 11.0],
        '.catalog-acc__icon' => [30, 9.0],
        '.pwd-input__toggle' => [32, 6.0],
    ];

    /**
     * **Familia DIBUJO y RESET** — el radio no es el canto de un contenedor.
     *
     * Cada entrada dice POR QUÉ, porque una lista de excepciones sin motivo es una lista que
     * crece. La lista solo encoge.
     */
    private const DRAWING = [
        '.qr-corner--tl' => 'una de las cuatro esquinas del marcador QR: es el dibujo',
        '.qr-corner--tr' => 'ídem',
        '.qr-corner--bl' => 'ídem',
        '.qr-corner--br' => 'ídem',
        '.qr-slot' => 'el hueco del QR dentro del marcador, al 100% de su caja',
        '.nav__period-block' => 'el punto de la marca, en `em`: escala con la tipografía, no con la caja',
        '.hero__title .blink' => 'subrayado tipográfico con padding en `em`',
        '.reserve h2 .fill' => 'ídem',
        '.offw-gift .cft' => 'lazo del regalo del widget de ofertas: dibujo',
        '.offw-burst .spark.star' => 'punta de estrella: `0` es la forma, no un reset',
        '.slider-progress' => 'barra de 2 px de alto — el radio la hace una píldora aplastada',
        '.cal__dot' => 'muestra de color de la leyenda del calendario: su radio es la forma del swatch, '.
            'no el canto de un contenedor. Con lado 12 y radio 4 su ratio es 3,00 y no cumple la ley '.
            'del motivo; tokenizarlo a `--r-xs` lo movería +1px, así que se deja y se anota',
        // ⚠️ `.invite-card__deco` vivía aquí y SE RETIRÓ (T9): el bloque `.invite-*` era el editor
        // de invitación ANTERIOR a `bd-editor` y llevaba muerto desde aquel rediseño — 0
        // consumidores, medido. La excepción se va con su sujeto, que es para lo que está la lista.
        '.bd-card__conf' => 'confeti del diseñador de invitaciones: dibujo',
        '.hero__chip:focus-visible' => 'anillo de foco sobre el vídeo del hero (ver el bloque de foco)',
        '.hero--full .hero__stage' => 'RESET: anula un radio heredado',
        // ⚠️ `.plan-select__panel a` vivía aquí y SE RETIRÓ el 2026-08-27 (armazón, tanda 2c·1):
        // los dos desplegables de la barra desaparecieron con ella y la familia entera se quedó
        // sin consumidor. Lo cazó esta misma guarda, que es para lo que está: una excepción sin
        // sujeto tapa al siguiente que se llame igual.
        '.bd-proc__cube@media' => 'el cubo del proceso a otro tamaño dentro de un @media: sigue la ley',
    ];

    /**
     * **Las sombras que NO salen de un rol, y por qué.**
     *
     * El producto tenía **53 sombras con 42 formas distintas**. Ahora hay TRES tokens por
     * función —`lift`, `float`, `modal`— y lo que no entra en ninguna **no lleva sombra**.
     * Estas seis se quedan con forma propia, cada una por un motivo que no es «no me dio
     * tiempo». La lista **solo encoge**.
     */
    private const SHADOW_EXCEPTIONS = [
        '.sidecart__panel' => 'DIRECCIONAL (`-20px 0 …`): el panel entra desde el lado, y un token vertical lo rompe',
        '.mob-menu__panel' => 'DIRECCIONAL: ídem, el menú lateral de móvil',
        // ⚠️ `.lang-dd--up .lang-dd__panel` vivía aquí —sombra DIRECCIONAL hacia arriba— y SE
        // RETIRÓ el 2026-08-27 (armazón · tanda 2c·4): el selector de idioma salió del pie y con
        // él su variante. **De seis excepciones de elevación quedan cinco**, y la lista encogió
        // sola, que es exactamente lo que `#196` prometió que pasaría.
        // ⚠️ `.invite-card` vivía aquí (sombra propia del «artefacto imprimible») y SE RETIRÓ (T9)
        // con el bloque `.invite-*` entero, muerto desde el rediseño del editor. **De cinco
        // excepciones de elevación quedan cuatro** — la lista encogió sola, otra vez.
        '.ck-tgl::after' => 'el PULGAR de un interruptor: 1 px de sombra lo hace parecer una pieza física, no elevación',
        '.offw-badge' => 'lee `--offw-accent`, color de marca del widget, no una sombra de elevación',
    ];

    /** Las tres reglas de foco que NO pueden usar `--focus-color`, y por qué. */
    private const FOCUS_EXCEPTIONS = [
        '.skip-link:focus-visible' => 'pinta sobre su propio fondo oscuro, que aún no declara superficie',
        '.hero__chip:focus-visible' => 'pinta sobre el vídeo del hero, que aún no declara superficie',
        '.gf-fiche__head:focus-visible' => 'anillo INTERIOR (offset −2px) en el acento de zona, no en la tinta',
    ];

    /** @var ?list<string> */
    private ?array $sheets = null;

    /** @var ?list<array{selector: string, value: string, media: bool}> */
    private ?array $radii = null;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guardas de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El escaneo ve de verdad el corpus, y ve DENTRO de los `@media`.**
     *
     * La primera versión del instrumento que midió esta tanda no descendía en las at-rules y daba
     * 3 reglas de `.nav` donde hay 45. Un contador global no lo habría cazado: hay que aseverar
     * que se ven declaraciones que SOLO existen dentro de un `@media`.
     */
    public function test_the_scan_sees_the_corpus_including_inside_at_rules(): void
    {
        $radii = $this->radii();

        $this->assertGreaterThan(
            200, count($radii),
            'el escaneo encuentra menos de 200 `border-radius` y hay ~219: el parser se ha quedado '.
            'ciego a parte del corpus y todo lo de abajo estaría verde sin mirar nada.',
        );

        $this->assertNotEmpty(
            array_filter($radii, fn (array $r) => $r['media']),
            'el escaneo no ve ni un `border-radius` dentro de un `@media`, y los hay: el parser no '.
            'desciende en las at-rules. Es el defecto exacto que la medida de esta tanda cazó.',
        );

        // Por NOMBRE, no por umbral: un contador no distingue «leo poco» de «leo otra cosa».
        foreach (['.foot__strip', '.bd-card', '.offw-card'] as $needle) {
            $this->assertNotEmpty(
                array_filter($radii, fn (array $r) => str_contains($r['selector'], $needle))
                    ?: array_filter($this->sheetContents(), fn (string $c) => str_contains($c, $needle)),
                "el escaneo no ve `{$needle}` en ninguna hoja",
            );
        }
    }

    /**
     * **El clasificador caza sus propios ejemplos.**
     *
     * Sin esto, una expresión regular que dejara de reconocer `var()` mandaría todos los cantos a
     * la lista de literales —o al revés, todos a la de tokens— y el fichero seguiría verde.
     */
    public function test_the_classifier_catches_its_own_examples(): void
    {
        $cases = [
            'var(--r-md)' => 'token',
            'var(--r-pill)' => 'token',
            '50%' => 'circle',
            '10px' => 'literal',
            '0' => 'literal',
            '0.18em' => 'literal',
            '7px 0 0 0' => 'literal',
            '3.5px' => 'literal',
        ];

        foreach ($cases as $value => $expected) {
            $this->assertSame(
                $expected, $this->classify($value),
                "el clasificador ha dejado de entender «{$value}»",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La escala de canto está declarada entera y es estrictamente creciente.**
     *
     * Lo segundo no es cosmética: dos escalones que se crucen —o que valgan lo mismo— hacen que
     * elegir entre ellos deje de significar nada, y el siguiente que necesite un canto meterá un
     * literal porque «ninguno encaja».
     */
    public function test_the_radius_scale_is_declared_whole_and_strictly_increasing(): void
    {
        $root = $this->rootTokens();
        $previous = null;

        foreach (self::RADIUS_SCALE as $token => $expected) {
            $this->assertArrayHasKey(
                $token, $root,
                "`{$token}` no está declarado en ningún `:root`: la escala de canto tiene un hueco ".
                'y las reglas que lo usaran caerían al valor inicial, en silencio.',
            );

            $this->assertSame(
                "{$expected}px", $root[$token],
                "`{$token}` vale «{$root[$token]}» y esta guarda espera «{$expected}px». Si el cambio ".
                'es intencionado, se actualiza AQUÍ y en la spec — no se relaja la aserción: estos '.
                'valores salieron de minimizar el movimiento sobre los 23 literales que había.',
            );

            if ($previous !== null) {
                $this->assertGreaterThan(
                    $previous, $expected,
                    "la escala de canto no es creciente en `{$token}`",
                );
            }

            $previous = $expected;
        }
    }

    /**
     * **Un paquete de instalación puede COLAPSAR escalones, pero no INVENTAR valores.**
     *
     * Salió al adoptar el sistema del 2.º cliente (`#470`): su `client.css` declaraba
     * `--r-xs: 6px` y `--r-lg: 24px`, y **ni el 6 ni el 24 existen en la escala del producto**
     * (`5·8·10·14·16·28·999`). Nadie lo veía, porque un canto inventado **no rompe nada**: solo
     * hace que esa instalación deje de cumplir el sistema que dice cumplir — y el siguiente que
     * mire ese paquete aprende de él un valor que no existe.
     *
     * ▶ **Lo que SÍ puede hacer un paquete es colapsar**: mandar dos roles del producto al mismo
     * escalón porque su sistema no los distingue. Eso no es una violación, es la mitad del
     * mecanismo white-label — el producto tiene SIETE roles de canto y un cliente puede tener
     * cuatro. Lo que no puede es traerse un octavo valor.
     *
     * ⚠️⚠️ **Es CONDICIONAL a propósito, y NO es un agujero** — la lección de `#468`, que quemó con
     * una guarda que leía este mismo fichero: el sujeto es `public/css/client.css`, **gitignorado**
     * porque es de un cliente y no puede viajar en el producto (`DECISIONES #1`). Sin paquete no
     * hay nada que validar. Y saltarla no la deja muerta, que es el riesgo que `CacheTaggingContractTest`
     * documenta: en este proyecto **el CI es el gate local** (`CLAUDE.md`), así que la máquina que
     * empuja tiene su paquete puesto y aquí siempre se ejecuta.
     */
    public function test_an_installation_package_only_collapses_steps_it_never_invents_values(): void
    {
        $paquete = base_path('public/css/client.css');

        if (! is_file($paquete)) {
            $this->markTestSkipped(
                'sin paquete de instalación: en un clon limpio no existe `public/css/client.css` '.
                'y no hay nada que validar (`#468`).',
            );
        }

        /* La escala del producto, escrita como se escribe en CSS. Sale de la constante y no de una
           lista propia: dos listas del mismo hecho divergen. */
        $escala = array_map(
            static fn (int $px): string => $px.'px',
            array_values(self::RADIUS_SCALE),
        );

        preg_match_all(
            '/(--r(?:-[a-z]+)?)\s*:\s*([^;}]+)/',
            file_get_contents($paquete) ?: '',
            $m,
            PREG_SET_ORDER,
        );

        $inventados = [];

        foreach ($m as [, $token, $valor]) {
            $valor = trim($valor);

            if (! array_key_exists($token, self::RADIUS_SCALE)) {
                continue;   // no es un escalón de la escala: no es asunto de esta guarda
            }

            if (! in_array($valor, $escala, true)) {
                $inventados[] = "{$token}: {$valor}";
            }
        }

        $this->assertSame(
            [],
            $inventados,
            'el paquete de instalación declara cantos que NO están en la escala del producto ('.
            implode(' · ', $escala).'): '.implode(', ', $inventados).'. Colapsar escalones está '.
            'permitido; inventar un valor nuevo no, porque entonces esa instalación deja de '.
            'cumplir el sistema sin que nada falle.',
        );
    }

    /**
     * **Todo `border-radius` en literal pertenece a una de las tres familias declaradas.**
     *
     * Ésta es la aserción con más valor del fichero, y la que muerde al escribir un canto nuevo a
     * mano. Un cuarto caso no existe: o es escala, o es círculo, o es motivo, o es dibujo.
     */
    public function test_every_remaining_literal_belongs_to_a_declared_family(): void
    {
        $allowed = array_merge(array_keys(self::SQUARE_MOTIF), array_keys(self::DRAWING));
        $orphans = [];

        foreach ($this->radii() as $rule) {
            if ($this->classify($rule['value']) !== 'literal') {
                continue;
            }

            foreach ($allowed as $known) {
                $bare = str_replace('@media', '', $known);

                if ($rule['selector'] === $bare || in_array($bare, $this->splitSelectors($rule['selector']), true)) {
                    continue 2;
                }
            }

            $orphans[] = "{$rule['selector']}  →  {$rule['value']}";
        }

        $this->assertSame(
            [], $orphans,
            "hay `border-radius` en literal que no son ni escala ni excepción declarada:\n  ".
            implode("\n  ", $orphans)."\n\n".
            "Antes de añadirlo a una lista, decide QUÉ es:\n".
            "  · canto de un contenedor  → usa un token de la escala (--r-xs … --r-lg)\n".
            "  · círculo                 → `50%`\n".
            "  · motivo cuadrado         → SQUARE_MOTIF, y tiene que cumplir la ley lado/4\n".
            "  · dibujo o reset          → DRAWING, con el porqué escrito\n".
            'Las dos listas SOLO ENCOGEN: si estás ampliándolas, casi seguro es un canto.',
        );
    }

    /**
     * **Las dos listas de excepción no tienen entradas muertas.**
     *
     * Una excepción cuyo selector ya no existe es una excepción que tapa el siguiente caso con el
     * mismo nombre. Es el control que `RawColourIsNotATokenTest` aprendió a llevar.
     */
    public function test_the_exception_lists_have_no_dead_entries(): void
    {
        $css = implode("\n", $this->sheetContents());
        $dead = [];

        foreach (array_merge(array_keys(self::SQUARE_MOTIF), array_keys(self::DRAWING), array_keys(self::FOCUS_EXCEPTIONS)) as $selector) {
            $needle = str_replace(['@media', ':focus-visible'], '', $selector);

            if (! str_contains($css, $needle)) {
                $dead[] = $selector;
            }
        }

        $this->assertSame(
            [], $dead,
            'estas excepciones ya no tienen sujeto en el CSS y hay que RETIRARLAS: '.implode(', ', $dead),
        );
    }

    /**
     * **La familia del motivo cuadrado cumple su ley: radio ≈ lado / 4.**
     *
     * Es lo que impide que la lista de excepciones se convierta en un cajón: para entrar hay que
     * cumplir la ley, y si un miembro deja de cumplirla es que era un canto disfrazado.
     *
     * La tolerancia (±20 %) no es generosidad: es la dispersión REAL medida en el producto
     * —ratios de 3,00 a 5,33 con mediana exacta 4,00—, y estrecharla haría caer a miembros que
     * llevan ahí desde antes de que la ley se descubriera.
     */
    public function test_the_square_motif_family_obeys_its_law(): void
    {
        $offenders = [];

        foreach (self::SQUARE_MOTIF as $selector => [$side, $radius]) {
            $ratio = $side / $radius;

            if ($ratio < 3.2 || $ratio > 5.4) {
                $offenders[] = sprintf('%s: lado %dpx / radio %spx = %.2f', $selector, $side, $radius, $ratio);
            }
        }

        $this->assertSame(
            [], $offenders,
            "estos miembros del motivo cuadrado no cumplen `lado / 4`:\n  ".implode("\n  ", $offenders)."\n".
            'Si el valor es correcto, no es un motivo: es un canto, y va a la escala.',
        );

        // Y la ley, medida sobre la familia entera, tiene que seguir dando ~4.
        $ratios = array_map(fn (array $m) => $m[0] / $m[1], array_values(self::SQUARE_MOTIF));
        sort($ratios);
        $median = $ratios[intdiv(count($ratios), 2)];

        $this->assertEqualsWithDelta(
            4.0, $median, 0.35,
            'la mediana de `lado / radio` de la familia se ha ido de 4: la ley que justifica esta '.
            'lista de excepciones ha dejado de ser cierta, y con ella la excepción.',
        );
    }

    /**
     * **Toda sombra sale de un ROL, o es una excepción declarada.**
     *
     * ⚠️⚠️ El producto tenía **53 sombras de elevación con 42 formas distintas** — casi cada una
     * única. Y la mejor escala de cinco escalones movía **47 de 53**: no era una escala con
     * ruido, es que **no había ninguna**. El sistema del cliente al que esta capa sirve declara
     * **DOS** formas. Con 42 de un lado y 2 del otro, la pregunta no era de cuántos escalones
     * sino **para qué sirve cada sombra**.
     *
     * Salen tres roles —`lift` (se despega al pasar el ratón), `float` (flota sobre el contenido)
     * y `modal` (tapa la página)— y lo que no entra en ninguno **no lleva sombra**: una tarjeta
     * quieta no está elevada, está apoyada.
     *
     * ⚠️ **CUARTO ROL desde `#217`: el MOBILIARIO FLOTANTE.** El armazón no encaja en ninguno de
     * los tres —no está apoyado, no se despega al pasar el ratón, no tapa la página—: **flota
     * permanentemente sobre un contenido que se mueve por debajo**. Son cinco valores porque el
     * mockup del 2.º cliente declara tres pesos (el relleno de tinta proyecta más) y dos alturas.
     * ▶ **Esta guarda funcionó exactamente como debía**: la tanda añadió cinco sombras nuevas y
     * salió en rojo obligando a decidir qué eran. La respuesta fue «un rol», no «una excepción» —
     * y la diferencia importa, porque **la lista de excepciones solo encoge y la de roles no**.
     *
     * ▶ Si esto se relaja, un cliente que redefina su tema deja de mover las sombras que se le
     * escapen — y no falla nada, simplemente se queda con las del primero.
     */
    public function test_every_shadow_comes_from_a_role_or_is_a_declared_exception(): void
    {
        $roles = [
            'var(--shadow-lift)', 'var(--shadow-float)', 'var(--shadow-modal)',
            // El mobiliario flotante (`#217`): tres pesos y dos alturas, todos del mismo rol.
            'var(--shadow-nav)', 'var(--shadow-nav-ghost)', 'var(--shadow-nav-ghost-lift)',
            'var(--shadow-nav-fill)', 'var(--shadow-nav-fill-lift)',
        ];
        $offenders = [];
        $seen = 0;

        foreach ($this->rules() as $rule) {
            if (! preg_match('/(?<![-\w])box-shadow\s*:\s*([^;}]+)/', $rule['body'], $m)) {
                continue;
            }

            $seen++;
            $value = trim($m[1]);
            $selector = $rule['selector'];

            if ($value === 'none' || str_contains($value, 'inset')) {
                continue;                      // reset, o anillo interior: no es elevación
            }

            // Un anillo (`0 0 0 Npx`) tampoco lo es: es foco o selección.
            if (preg_match('/^0\s+0\s+0\s/', $value)) {
                continue;
            }

            foreach ($roles as $role) {
                if (str_contains($value, $role)) {
                    continue 2;
                }
            }

            foreach (array_keys(self::SHADOW_EXCEPTIONS) as $known) {
                if (in_array($known, $this->splitSelectors($selector), true)) {
                    continue 2;
                }
            }

            $offenders[] = "{$selector}  →  ".substr($value, 0, 70);
        }

        // Guarda de la guarda, POR NOMBRE: si el escaneo dejara de ver sombras, esto pasaría
        // sin comprobar nada. Se exige ver los tres roles EN USO, no un recuento.
        foreach ($roles as $role) {
            $this->assertNotEmpty(
                array_filter($this->rules(), fn (array $r) => str_contains($r['body'], $role)),
                "el escaneo no encuentra ni un uso de `{$role}`: o el rol ha dejado de usarse, o el ".
                'localizador se ha roto y esta guarda estaría verde sin mirar nada.',
            );
        }
        $this->assertGreaterThan(30, $seen, 'el escaneo ve menos de 30 `box-shadow` y hay ~68');

        $this->assertSame(
            [], $offenders,
            "estas sombras no salen de un rol ni son excepción declarada:\n  ".implode("\n  ", $offenders)."\n\n".
            "Decide QUÉ es antes de añadirla a la lista:\n".
            "  · se despega al pasar el ratón     → `var(--shadow-lift)`\n".
            "  · flota sobre el contenido         → `var(--shadow-float)`\n".
            "  · tapa la página, con velo detrás  → `var(--shadow-modal)`\n".
            "  · es MOBILIARIO que flota siempre  → `var(--shadow-nav*)` (`#217`)\n".
            "  · está quieta y apoyada            → `none`. Una tarjeta en reposo no está elevada.\n".
            'La lista de excepciones SOLO ENCOGE: si estás ampliándola, casi seguro es un rol.',
        );
    }

    /**
     * **Las sombras leen `--paper-fg`, no `--fg`.**
     *
     * Dentro de `[data-surface="ink"]` el token `--fg` vale CLARO, así que una sombra escrita con
     * él **se vuelve clara dentro del hero**. Una sombra es ausencia de luz: es oscura en las dos
     * superficies. `--paper-fg` es el alias que no se mueve al entrar en tinta.
     */
    public function test_the_shadow_roles_use_the_stable_ink_alias(): void
    {
        $root = $this->rootTokens();

        foreach ([
            '--shadow-lift', '--shadow-float', '--shadow-modal',
            '--shadow-nav', '--shadow-nav-ghost', '--shadow-nav-ghost-lift',
            '--shadow-nav-fill', '--shadow-nav-fill-lift',
        ] as $token) {
            $this->assertArrayHasKey($token, $root, "falta el rol de sombra `{$token}`");

            $this->assertStringContainsString(
                'var(--paper-fg)', $root[$token],
                "`{$token}` no lee `--paper-fg`. Con `--fg` la sombra se vuelve CLARA dentro del ".
                'hero, que declara superficie de tinta — y una sombra clara no es una sombra.',
            );
        }
    }

    /**
     * **El anillo de foco sale de tokens, y sus excepciones son exactamente tres.**
     *
     * Un cliente tiene que poder cambiar el color con el que se ve su foco de teclado. Antes de
     * esta tanda no podía: eran 23 reglas con literales y cinco tratamientos distintos.
     */
    public function test_the_focus_ring_comes_from_tokens(): void
    {
        $root = $this->rootTokens();

        foreach (['--focus-w', '--focus-color', '--focus-outline'] as $token) {
            $this->assertArrayHasKey($token, $root, "falta el token de foco `{$token}`");
        }

        $this->assertStringContainsString(
            'var(--focus-w)', $root['--focus-outline'],
            '`--focus-outline` no se compone del grosor tokenizado: cambiar `--focus-w` dejaría de '.
            'tener efecto y el token sería decorativo.',
        );
        $this->assertStringContainsString(
            'var(--focus-color)', $root['--focus-outline'],
            '`--focus-outline` no se compone del color tokenizado: un cliente no podría cambiarlo.',
        );

        // Ninguna regla de foco puede volver a escribir el anillo a mano, salvo las tres declaradas.
        $offenders = [];

        foreach ($this->focusOutlineRules() as $rule) {
            if (str_contains($rule['value'], 'var(--focus-outline)')) {
                continue;
            }

            if ($rule['value'] === 'none' || $rule['value'] === '0') {
                continue; // se sustituye por `box-shadow` o por `border-color`; lo vigila el test de abajo
            }

            foreach (array_keys(self::FOCUS_EXCEPTIONS) as $known) {
                if (in_array($known, $this->splitSelectors($rule['selector']), true)) {
                    continue 2;
                }
            }

            $offenders[] = "{$rule['selector']}  →  outline: {$rule['value']}";
        }

        $this->assertSame(
            [], $offenders,
            "estas reglas escriben el anillo de foco a mano en vez de usar `var(--focus-outline)`:\n  ".
            implode("\n  ", $offenders)."\n".
            'Si de verdad necesita otro color, va a FOCUS_EXCEPTIONS con su porqué — y esa lista '.
            'solo encoge: las tres que hay desaparecen cuando el hero declare superficie.',
        );
    }

    /**
     * **Ningún `outline: none` se queda sin sustituto visible.**
     *
     * Es el hallazgo `M-01` de la auditoría del propio cliente, severidad Alta, traído a nuestro
     * código: matar el contorno sin poner nada en su sitio deja a quien navega con teclado sin
     * saber dónde está, y no lo caza ningún test de los que había.
     */
    public function test_no_focus_rule_kills_the_outline_without_a_replacement(): void
    {
        $offenders = [];

        foreach ($this->focusRules() as $rule) {
            if (! preg_match('/(?<![-\w])outline\s*:\s*(none|0)\b/', $rule['body'])) {
                continue;
            }

            // El sustituto vale si es un anillo (`box-shadow`) o un cambio de borde bien visible.
            $hasRing = (bool) preg_match('/(?<![-\w])box-shadow\s*:/', $rule['body']);
            $hasBorder = (bool) preg_match('/(?<![-\w])border(-color)?\s*:/', $rule['body']);

            // `*:focus { outline: none }` es el reset global que da paso a `:focus-visible`: correcto.
            $isGlobalReset = str_starts_with($rule['selector'], '*:focus') && ! str_contains($rule['selector'], 'visible');

            if (! $hasRing && ! $hasBorder && ! $isGlobalReset) {
                $offenders[] = $rule['selector'];
            }
        }

        $this->assertSame(
            [], $offenders,
            "estas reglas apagan el contorno de foco y no ponen nada en su sitio:\n  ".
            implode("\n  ", $offenders)."\n".
            'Pon un `box-shadow` de anillo o un `border-color` que se distinga, o quita el '.
            '`outline: none`.',
        );
    }

    /**
     * **La tira de marca DERIVA y no lleva ni un color en crudo.**
     *
     * Es la misma regla que la paleta de tinta de `[data-surface]`: si un escalón se teclea, el
     * cliente cambia su marca y esa franja se queda con la del primero, sin fallar y sin avisar.
     */
    public function test_the_brand_strip_derives_from_the_brand_tokens(): void
    {
        $root = $this->rootTokens();

        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertArrayHasKey($token, $root, "falta `{$token}`: la tira del pie tiene un hueco");

            $this->assertMatchesRegularExpression(
                '/var\(\s*--(zone|brand)-[12]\s*\)/', $root[$token],
                "`{$token}` vale «{$root[$token]}» y no lee ningún token de marca: es un color en ".
                'crudo, y el pie del cliente se quedaría con la franja del primero.',
            );

            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,8}\b/', $root[$token],
                "`{$token}` trae un hex literal. La tira DERIVA; no se teclea.",
            );
        }

        // Y no puede pintarse con un color semántico: dejaría de significar lo que significa
        // (hallazgo `C-04` de la auditoría del cliente), y `--attn` vale lo mismo que `--zone-2`.
        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertDoesNotMatchRegularExpression(
                '/var\(\s*--(ok|err|warn|attn|refund)\b/', $root[$token],
                "`{$token}` usa un token SEMÁNTICO como decoración: `--ok` significa «reserva ".
                'confirmada» y `--err` «algo ha fallado». Si decoran, dejan de informar.',
            );
        }

        // ⚠️ Y ninguna franja puede ser una MEZCLA de los dos colores de marca. Se escribió así
        // primero y la medida lo tumbó: con dos colores casi complementarios —los del segundo
        // cliente— la franja del medio salía barro (`#868D7D`, croma 0,025), y en `oklab` era
        // exactamente igual de malo (0,024). El producto no elige la marca de su cliente, así
        // que no puede apostar a que sus dos colores interpolen bien. Ciclan.
        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertStringNotContainsString(
                'color-mix', $root[$token],
                "`{$token}` mezcla dos colores de marca. Con un par casi complementario la mezcla ".
                'sale gris sucio, y no lo arregla cambiar de espacio de color: medido, `oklab` da '.
                'croma 0,024 y `srgb` 0,025. Las franjas CICLAN sobre los colores que haya.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function sheetContents(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $out = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
            // ⚠️ **La hoja de una INSTALACIÓN queda fuera, y no es un descuido.** `client.css`
            // no es del producto: existe precisamente para que un cliente declare sus valores
            // —literales incluidos, que es de lo que está hecho un paquete de tema— y juzgarla
            // con las reglas del producto sería prohibirle hacer aquello para lo que existe.
            // ▶ Y además la hacía MENTIR al gate: una guarda que asevera por hoja cambiaba el
            // recuento de aserciones según si la máquina tenía o no un paquete instalado, así
            // que el `pre-push` bloqueaba en una máquina o en la otra. Medido el 2026-08-28 al
            // montar el paquete del segundo cliente. Mismo criterio que `SidebarStyleWiringTest`,
            // que enumera las hojas del producto en vez de barrer la carpeta.
            if (basename($path) === 'client.css') {
                continue;
            }

            // ⚠️ Los comentarios se BLANQUEAN conservando la longitud, no se borran: al borrarlos,
            // un `/* … */` pegado al selector de la línea de arriba hacía que el localizador diera
            // CERO reglas donde había una. Lo pagó el script que hizo las conversiones.
            $out[] = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                (string) file_get_contents($path),
            );
        }

        return $this->sheets = $out;
    }

    /**
     * Todas las declaraciones de `border-radius`, descendiendo en `@media` y compañía.
     *
     * @return list<array{selector: string, value: string, media: bool}>
     */
    private function radii(): array
    {
        if ($this->radii !== null) {
            return $this->radii;
        }

        $out = [];

        foreach ($this->rules() as $rule) {
            if (preg_match('/(?<![-\w])border(-[a-z]+)*-radius\s*:\s*([^;}]+)/', $rule['body'], $m)) {
                $out[] = [
                    'selector' => $rule['selector'],
                    'value' => trim($m[2]),
                    'media' => $rule['media'],
                ];
            }
        }

        return $this->radii = $out;
    }

    /** @return list<array{selector: string, body: string, media: bool}> */
    private function focusRules(): array
    {
        return array_values(array_filter(
            $this->rules(),
            fn (array $r) => str_contains($r['selector'], ':focus'),
        ));
    }

    /** @return list<array{selector: string, value: string}> */
    private function focusOutlineRules(): array
    {
        $out = [];

        foreach ($this->focusRules() as $rule) {
            if (preg_match('/(?<![-\w])outline\s*:\s*([^;}]+)/', $rule['body'], $m)) {
                $out[] = ['selector' => $rule['selector'], 'value' => trim($m[1])];
            }
        }

        return $out;
    }

    /**
     * Reglas de las hojas, DESCENDIENDO en las at-rules de bloque.
     *
     * @return list<array{selector: string, body: string, media: bool}>
     */
    private function rules(): array
    {
        $out = [];

        foreach ($this->sheetContents() as $css) {
            $this->walk($css, false, $out);
        }

        return $out;
    }

    /** @param list<array{selector: string, body: string, media: bool}> $out */
    private function walk(string $css, bool $inMedia, array &$out): void
    {
        $length = strlen($css);
        $depth = 0;
        $selectorStart = 0;
        $bodyStart = 0;
        $selector = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '{') {
                if ($depth === 0) {
                    $selector = trim((string) preg_replace('/\s+/', ' ', substr($css, $selectorStart, $i - $selectorStart)));
                    $bodyStart = $i + 1;
                }
                $depth++;

                continue;
            }

            if ($char !== '}') {
                continue;
            }

            $depth--;

            if ($depth !== 0) {
                continue;
            }

            $body = substr($css, $bodyStart, $i - $bodyStart);

            if (preg_match('/^@(media|supports|layer|container|scope)\b/', $selector)) {
                $this->walk($body, true, $out);
            } elseif (! str_starts_with($selector, '@')) {
                $out[] = ['selector' => $selector, 'body' => $body, 'media' => $inMedia];
            }

            $selectorStart = $i + 1;
        }
    }

    /**
     * Tokens declarados en cualquier `:root` de las hojas.
     *
     * @return array<string, string>
     */
    private function rootTokens(): array
    {
        $out = [];

        foreach ($this->rules() as $rule) {
            if ($rule['selector'] !== ':root') {
                continue;
            }

            foreach ($this->declarations($rule['body']) as $declaration) {
                if (! str_contains($declaration, ':')) {
                    continue;
                }

                [$property, $value] = explode(':', $declaration, 2);
                $property = trim($property);

                if (str_starts_with($property, '--')) {
                    $out[$property] = trim($value);
                }
            }
        }

        return $out;
    }

    /**
     * Parte un cuerpo en declaraciones respetando los paréntesis anidados.
     *
     * ⚠️ Un `explode(';')` a secas parte por dentro de `color-mix(in srgb, var(--fg) 10%, …)` en
     * cuanto lleve un `;` — y ese es el defecto que hizo que el primer inventario de colores de
     * este repo diera 76 donde había 234 (`DECISIONES #143` §8).
     *
     * @return list<string>
     */
    private function declarations(string $body): array
    {
        $out = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                if (trim($buffer) !== '') {
                    $out[] = trim($buffer);
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $out[] = trim($buffer);
        }

        return $out;
    }

    /** @return list<string> */
    private function splitSelectors(string $selector): array
    {
        return array_map('trim', explode(',', $selector));
    }

    /** `token` · `circle` · `literal` */
    private function classify(string $value): string
    {
        if (str_contains($value, 'var(')) {
            return 'token';
        }

        return trim($value) === '50%' ? 'circle' : 'literal';
    }
}
