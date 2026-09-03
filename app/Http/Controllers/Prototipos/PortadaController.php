<?php

namespace App\Http\Controllers\Prototipos;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Las tres formas de la portada de la fase 2c (`docs/specs/guion-de-la-portada.md` §6.5), sobre los
 * DATOS REALES de la instalación. Solo existe en local (`routes/prototipos.php`).
 *
 * ⚠️ **No duplica las consultas de la portada**: pide a `HomeController` su vista y se queda con sus
 * datos (`getData()`), así que zonas, entradas, packs, normas y FAQ son exactamente los que pinta la
 * portada real. Lo que añade es lo que el guion necesita y la portada no calcula: las tarifas por día
 * y los hechos de cada zona (edad y altura) sacados del texto `age_range`.
 *
 * ⚠️ **La edad y la altura se PARSEAN de un texto libre** (`zones.age_range`: «+8 años · +1,30 m»).
 * Es la decisión D-G6 del guion: hoy no hay dato estructurado. Aquí se lee lo que hay para poder
 * renderizar; cuando existan los campos, esto desaparece.
 */
class PortadaController extends Controller
{
    private const ZONA = ['altura', 'tarjetas', 'pestanas'];

    private const PRECIO = ['semana', 'dos', 'desde'];

    public function portada(Request $request, string $forma): View
    {
        $datos = $this->datos($request);

        $zona = in_array($request->query('zona'), self::ZONA, true) ? $request->query('zona') : 'altura';
        $precio = in_array($request->query('precio'), self::PRECIO, true) ? $request->query('precio') : 'semana';

        return view('prototipos.portada-'.$forma, $datos + [
            'forma' => $forma,
            'varZona' => $zona,
            'varPrecio' => $precio,
            // `?orden=cumple` adelanta el cumpleaños a antes de los juegos (D-G3, para medirlo).
            'cumpleAntes' => $request->query('orden') === 'cumple',
        ]);
    }

    public function piezas(Request $request): View
    {
        return view('prototipos.piezas', $this->datos($request));
    }

    /** @return array<string, mixed> */
    private function datos(Request $request): array
    {
        $home = app(HomeController::class)($request);
        $datos = $home instanceof View ? $home->getData() : [];

        $rateTypes = RateType::where('is_active', true)->orderBy('priority')->get();
        $normal = $rateTypes->first(fn (RateType $r) => $r->key === RateType::KEY_NORMAL);
        $especiales = $rateTypes->filter(fn (RateType $r) => $r->is_special)->values();

        /** @var Collection<int, Zone> $zones */
        $zones = $datos['zones'];

        $hechos = $zones->mapWithKeys(fn (Zone $z) => [$z->slug => $this->hechos($z)]);

        return $datos + [
            'rateTypes' => $rateTypes,
            'rateNormal' => $normal,
            'rateEspeciales' => $especiales,
            'hechos' => $hechos,
            // Los días de la semana (0 = domingo) que caen en alguna tarifa especial. Lo que no cae
            // ahí es tarifa normal: es la misma lectura que hace `RateResolver`, sin las fechas.
            'diasEspeciales' => $especiales->flatMap(fn (RateType $r) => $r->weekdays ?? [])->unique()->values()->all(),
            // Precio por entrada y tarifa, ya resuelto (céntimos), para pintar sin lógica en la vista.
            'precioPor' => fn (TicketType $t, ?RateType $r) => $r ? $t->priceCentsForRate($r) : null,
        ];
    }

    /**
     * Edad mínima/máxima y altura mínima de una zona, leídas del texto `age_range`.
     *
     * @return array{min_age: ?int, max_age: ?int, min_height_cm: ?int, texto: string}
     */
    private function hechos(Zone $z): array
    {
        $texto = (string) $z->tr('age_range');
        $alturaCm = null;
        if (preg_match('/(\d)[,.](\d{2})\s*m/u', $texto, $m)) {
            $alturaCm = (int) $m[1] * 100 + (int) $m[2];
        }
        $sinAltura = preg_replace('/\d[,.]\d{2}\s*m/u', '', $texto) ?? $texto;
        preg_match_all('/\d+/', $sinAltura, $edades);
        $edades = array_map('intval', $edades[0]);
        $abierto = str_contains($texto, '+') && count($edades) === 1;

        return [
            'min_age' => $edades[0] ?? null,
            'max_age' => (! $abierto && count($edades) > 1) ? $edades[1] : null,
            'min_height_cm' => $alturaCm,
            'texto' => $texto,
        ];
    }
}
