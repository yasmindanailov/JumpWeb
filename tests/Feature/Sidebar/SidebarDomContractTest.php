<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.2 — **el contrato visual es el ÁRBOL, y aquí se compara**
 * (`docs/specs/sidebar-spa.md` §4.2 y §6, criterio CE-2).
 *
 * La v1 del spec daba por hecho que el contrato eran los nombres de clase y que bastaría con
 * extraerlos del código. Se midió y es **falso**: de los 292 selectores que estilan el cajón, **90
 * no se satisfacen emitiendo la clase correcta** —89 son estructurales (descendencia, `+`,
 * `:last-of-type`) y 37 dependen del TIPO DE ELEMENTO sin clase en el nodo, como `.catalog__go svg`
 * o `.purchase__total strong`—. Un `<div>` donde había un `<button>` pierde el estilo con el
 * contrato de clases cumplido al 100%.
 *
 * Por eso esto compara **árboles renderizados**, no código fuente: Livewire por el lado de PHP y Vue
 * con `@vue/server-renderer` por el lado de Node. Sin navegador headless — Vue ya trae su
 * renderizador de servidor, y una dependencia de navegador en el gate es lenta y tiene su propio
 * modo de fallo.
 *
 * ⚠️ **Lo que se normaliza y lo que NO**, porque ahí está el valor del test:
 *  - **fuera** los atributos de cada motor (`wire:*`, `x-*`, `@*`, `:*`, `data-v-*`, `id` de
 *    Livewire): son andamiaje, no contrato, y compararlos haría el test imposible de pasar;
 *  - **dentro** la etiqueta, las clases, el anidamiento y los atributos de accesibilidad
 *    (`role`, `aria-*`, `disabled`, `type`) — que es exactamente lo que el CSS y el lector de
 *    pantalla miran;
 *  - **no se desciende dentro de un `<svg>`**: su interior es geometría, no estructura estilable, y
 *    exigir que dos motores emitan los mismos `<path>` convertiría un contrato visual en una copia
 *    literal de los iconos. Que HAYA un `<svg>` donde toca sí se comprueba, porque de eso sí
 *    dependen selectores como `.catalog__go svg`.
 */
class SidebarDomContractTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $rateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump', 'color' => '#FF5B22',
            'position' => 1, 'is_active' => true,
        ]);
    }

    private function product(string $name, string $type, ?int $priceCents = 990): TicketType
    {
        $product = TicketType::create([
            'name' => ['es' => $name], 'type' => $type, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'min_qty' => $type === TicketType::TYPE_PACK ? 6 : 1,
            'max_qty' => $type === TicketType::TYPE_PACK ? 20 : null,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        if ($priceCents !== null) {
            $product->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);
        }

        return $product;
    }

    // ── El paso 1: catálogo ───────────────────────────────────────────────────────────────────

    public function test_the_catalog_step_emits_the_same_tree_in_both_engines(): void
    {
        $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->product('Cumpleaños', TicketType::TYPE_PACK, 5000);

        $livewire = $this->livewireTreeForStep(1);
        $vue = $this->vueTree(1, $this->catalogProps());

        $this->assertSame(
            $livewire, $vue,
            "El árbol del catálogo DIFIERE entre los dos motores.\n".
            'El contrato visual es el árbol (§4.2): 90 de 292 selectores del cajón son estructurales '.
            'o dependen del tipo de elemento, así que esta diferencia es estilo perdido aunque las '.
            "clases coincidan.\n\n".
            $this->firstDivergence($livewire, $vue)
        );
    }

    // ── El paso 2: calendario ─────────────────────────────────────────────────────────────────

    /**
     * ⚠️ El contenido del paso 2 son CUATRO nodos hermanos —título, calendario, leyenda y el aviso de
     * «sin fechas»—, no un contenedor. Se comparan todos: dejar fuera los hermanos es como se cuela
     * una leyenda que no se pinta o un título con otra etiqueta.
     */
    public function test_the_date_step_emits_the_same_tree_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $livewire = $this->livewireTree($component, 'wiz__title', withSiblings: true);
        $vue = $this->vueTree(2, $this->dateProps($component), 'wiz__title', withSiblings: true);

        $this->assertSame(
            $livewire, $vue,
            "El árbol del calendario DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * La BANDA de progreso tiene su propio caso porque **no es del paso 2**: la comparten los pasos 2
     * y 3, vive fuera del bloque de cada uno en el Blade y trae el «volver» del flujo. Un diff que
     * empezara en el título del paso no la vería, y un motor que no la emitiera dejaría al cliente
     * sin salida y sin contador de fases.
     */
    public function test_the_booking_progress_band_emits_the_same_tree_in_both_engines(): void
    {
        $product = $this->product('Entrada 1h', TicketType::TYPE_ENTRY, 990);
        $this->slotsForNextDays($product, 5);

        $component = Livewire::test(Purchase::class)->call('selectType', $product->id);

        $livewire = $this->livewireTree($component, 'bk-progress');
        $vue = $this->vueTree(2, $this->dateProps($component), 'bk-progress');

        $this->assertSame(
            $livewire, $vue,
            "La banda de progreso DIFIERE entre los dos motores.\n\n".$this->firstDivergence($livewire, $vue)
        );
    }

    /**
     * **La guarda de la guarda.** Un diff de árboles que normaliza de más acaba comparando dos
     * cadenas vacías y pasando siempre. Aquí se comprueba que el normalizador CONSERVA lo que el
     * contrato necesita —las clases y el tipo de elemento— y que por tanto sabe distinguir.
     */
    public function test_the_normaliser_still_tells_two_different_trees_apart(): void
    {
        $button = $this->normalise('<div class="catalog"><button type="button" class="catalog__item"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></button></div>');
        $div = $this->normalise('<div class="catalog"><div class="catalog__item"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></div></div>');
        $noClass = $this->normalise('<div class="catalog"><button type="button"><span class="catalog__go"><svg viewBox="0 0 1 1"></svg></span></button></div>');

        $this->assertNotSame($button, $div, 'un `div` donde había un `button` TIENE que verse');
        $this->assertNotSame($button, $noClass, 'una clase que falta TIENE que verse');
        $this->assertNotEmpty($button, 'el normalizador no puede dejar el árbol vacío');
    }

    /** Y que el andamiaje de cada motor SÍ se ignora, o el test sería imposible de pasar. */
    public function test_the_normaliser_ignores_each_engines_scaffolding(): void
    {
        $withLivewire = $this->normalise('<button type="button" class="catalog__item" wire:click="selectType(1)" x-show="hit" data-search="x">a</button>');
        $withVue = $this->normalise('<button type="button" class="catalog__item" data-v-7ba5bd90 data-search="x">a</button>');

        $this->assertSame($withLivewire, $withVue);
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function catalogProps(): array
    {
        // El mismo view-model que el componente Livewire compone para su vista. Se toma de ÉL y no
        // se escribe a mano: si se escribiera, el test compararía el árbol de Vue contra una idea
        // del catálogo, no contra el catálogo.
        $component = Livewire::test(Purchase::class);

        return [
            'sections' => $component->viewData('catalog'),
            'searchEnabled' => $component->viewData('catalogSearchEnabled'),
            'messages' => __('tickets'),
        ];
    }

    /**
     * Franjas para los próximos N días, para que el calendario tenga algo que ofrecer.
     *
     * Sin ellas el paso 2 se pinta vacío y el diff compararía dos calendarios sin días
     * seleccionables — es decir, pasaría sin mirar lo que de verdad importa: la celda con precio,
     * la seleccionada y la deshabilitada.
     */
    private function slotsForNextDays(TicketType $product, int $days): void
    {
        for ($i = 1; $i <= $days; $i++) {
            Slot::create([
                'zone_id' => $product->zone_id,
                'date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '11:00:00',
                'capacity' => 20, 'online_capacity' => 20,
            ]);
        }
    }

    /**
     * El view-model del calendario, tomado del propio componente Livewire.
     *
     * @return array<string, mixed>
     */
    private function dateProps(Testable $component): array
    {
        return [
            'progress' => $component->viewData('bookingProgress'),
            'weeks' => $component->viewData('weeks'),
            'weekdayHeaders' => $component->viewData('weekdayHeaders'),
            'monthLabel' => $component->viewData('monthLabel'),
            'canPrev' => $component->viewData('canPrev'),
            'canNext' => $component->viewData('canNext'),
            'selectedDate' => $component->get('date'),
            'messages' => __('tickets'),
        ];
    }

    /** El árbol del componente Livewire en un paso, ya normalizado. */
    private function livewireTreeForStep(int $step): string
    {
        $html = Livewire::test(Purchase::class)->set('step', $step)->html();

        return $this->treeOf($html, 'catalog-acc');
    }

    /** Igual, pero para un componente ya colocado por el test en el paso que quiere comparar. */
    private function livewireTree(Testable $component, string $anchor, bool $withSiblings = false): string
    {
        return $this->treeOf($component->html(), $anchor, $withSiblings);
    }

    /**
     * El árbol que emite Vue, renderizado en Node.
     *
     * @param  array<string, mixed>  $props
     */
    private function vueTree(int $step, array $props, string $anchor = 'catalog-acc', bool $withSiblings = false): string
    {
        $bundle = base_path('storage/ssr/render-sidebar.js');

        $this->assertFileExists(
            $bundle,
            'Falta el bundle SSR del cajón: corre `npm run build:ssr`. Node no puede cargar un `.vue` '.
            'sin compilar, así que el renderizador se construye con Vite. El `pre-push` ya lo hace.'
        );

        $process = new Process(['node', $bundle], base_path());
        $process->setInput(json_encode(['step' => $step, 'props' => $props], JSON_THROW_ON_ERROR));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "El renderizador de Vue falló:\n".$process->getErrorOutput()
        );

        return $this->treeOf($process->getOutput(), $anchor, $withSiblings);
    }

    /**
     * El árbol normalizado del nodo con esa clase, dentro del HTML dado.
     *
     * ⚠️ **Se localiza con el DOM y no con una expresión regular**, y la primera versión lo hacía
     * mal: el `x-on` de Alpine contiene una flecha `=>`, así que un `[^>]*` para saltar los
     * atributos corta a media etiqueta y el recorte empezaba en el nodo equivocado. Los dos motores
     * envuelven el paso en algo propio —Livewire su `wire:id`, Vue su raíz—, así que comparar desde
     * fuera mediría el andamiaje.
     */
    private function treeOf(string $html, string $class, bool $withSiblings = false): string
    {
        $dom = $this->parse($html);
        $xpath = new DOMXPath($dom);
        $node = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]")?->item(0);

        if (! $node instanceof DOMElement) {
            $this->fail("no se ha encontrado el nodo «{$class}» en el HTML renderizado");
        }

        $lines = [];
        $this->describe($node, 0, $lines);

        // Hay pasos cuyo contenido son varios nodos HERMANOS —el paso 2 son título, calendario,
        // leyenda y un aviso— y no un solo contenedor. Comparar solo el primero dejaría fuera todo
        // lo demás, que es justo donde es fácil equivocarse.
        if ($withSiblings) {
            for ($sibling = $node->nextSibling; $sibling !== null; $sibling = $sibling->nextSibling) {
                $this->describe($sibling, 0, $lines);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Árbol normalizado, una línea por nodo con su profundidad.
     *
     * El formato es legible a propósito: cuando el test falla, lo primero que hace falta es ver EN
     * QUÉ nodo divergen, y un volcado de HTML minificado no lo enseña.
     */
    private function normalise(string $html): string
    {
        $root = $this->parse('<div id="__root">'.$html.'</div>')->getElementById('__root');

        $lines = [];
        foreach ($root?->childNodes ?? [] as $child) {
            $this->describe($child, 0, $lines);
        }

        return implode("\n", $lines);
    }

    private function parse(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return $dom;
    }

    /**
     * Atributos que SÍ son contrato. Todo lo demás —el andamiaje de cada motor y los `id`
     * generados— se ignora.
     *
     * @var list<string>
     */
    private const CONTRACT_ATTRIBUTES = ['role', 'type', 'disabled', 'aria-hidden', 'aria-label', 'aria-labelledby', 'aria-live'];

    /** @param list<string> $lines */
    private function describe(DOMNode $node, int $depth, array &$lines): void
    {
        if (! $node instanceof DOMElement) {
            return;     // texto y comentarios: el contrato es la estructura, no la copia
        }

        $parts = [str_repeat('  ', $depth).'<'.$node->tagName];

        $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
        $classes = array_values(array_filter($classes));
        sort($classes);     // el ORDEN de las clases no lo mira ningún selector

        if ($classes !== []) {
            $parts[] = 'class='.implode('.', $classes);
        }

        foreach (self::CONTRACT_ATTRIBUTES as $attribute) {
            if ($node->hasAttribute($attribute)) {
                // Los `aria-labelledby`/`id` apuntan a identificadores que cada motor puede generar
                // distintos; lo que importa es que el atributo ESTÉ, no su valor exacto.
                $value = in_array($attribute, ['aria-labelledby'], true) ? '…' : $node->getAttribute($attribute);
                $parts[] = $attribute.'='.$value;
            }
        }

        $lines[] = implode(' ', $parts).'>';

        // Dentro de un `<svg>` no se desciende: su interior es geometría, no estructura estilable.
        if (strtolower($node->tagName) === 'svg') {
            return;
        }

        foreach ($node->childNodes as $child) {
            $this->describe($child, $depth + 1, $lines);
        }
    }

    /** La primera línea en la que los dos árboles se separan, con su contexto. */
    private function firstDivergence(string $a, string $b): string
    {
        $left = explode("\n", $a);
        $right = explode("\n", $b);

        foreach ($left as $i => $line) {
            if (($right[$i] ?? null) === $line) {
                continue;
            }

            return "Primera diferencia (línea {$i}):\n".
                '  Livewire: '.$line."\n".
                '  Vue     : '.($right[$i] ?? '(no hay más nodos)')."\n";
        }

        return count($right) > count($left)
            ? 'Vue emite '.(count($right) - count($left))." nodos de MÁS.\n"
            : "Los árboles coinciden hasta donde llega el más corto.\n";
    }
}
