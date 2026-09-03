<?php

/**
 * Vuelca a JSON los DATOS REALES que pinta la portada — instrumento de la fase 2 del diseño
 * (`docs/specs/guion-de-la-portada.md` §6.8, `DECISIONES #433`). Reutiliza `HomeController` igual
 * que `Prototipos\PortadaController` para que zonas, entradas, packs, normas y FAQ sean exactamente
 * los de la portada; añade tarifas por día y hechos de zona. La salida (gitignorada) es la fuente
 * de los datos escritos a mano en `index.src.html`.
 *
 * Uso: docker compose exec -T -u sail laravel.test php artisan tinker \
 *        --execute "require '/var/www/html/scripts/prototipo-b/dump-portada.php';"
 */

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Str;

$home = app(HomeController::class)(request());
$d = $home->getData();

$rateTypes = RateType::where('is_active', true)->orderBy('priority')->get();
$normal = $rateTypes->first(fn (RateType $r) => $r->key === RateType::KEY_NORMAL);
$especiales = $rateTypes->filter(fn (RateType $r) => $r->is_special)->values();

$hechos = function (Zone $z): array {
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
};

$precios = function (TicketType $t) use ($rateTypes): array {
    $out = [];
    foreach ($rateTypes as $r) {
        $out[$r->key] = $t->priceCentsForRate($r);
    }

    return $out;
};

$producto = fn (TicketType $t) => [
    'id' => $t->id,
    'name' => $t->tr('name'),
    'description' => $t->tr('description'),
    'zone_id' => $t->zone_id,
    'type' => $t->type,
    'duration_min' => $t->duration_min,
    'period_label' => $t->tr('period_label'),
    'badge' => $t->tr('badge'),
    'features' => $t->features,
    'icon' => $t->icon,
    'featured' => (bool) $t->featured,
    'min_qty' => $t->min_qty,
    'max_qty' => $t->max_qty,
    'deposit_type' => $t->deposit_type,
    'deposit_value' => $t->deposit_value,
    'guest_age_min' => $t->guest_age_min,
    'guest_age_max' => $t->guest_age_max,
    'display_price_cents' => $t->displayPriceCents(),
    'price_varies' => $t->priceVaries(),
    'prices' => $precios($t),
    'addons' => $t->addons->map(fn ($a) => ['name' => $a->tr('name'), 'price_cents' => $a->displayPriceCents(), 'type' => $a->type])->values()->all(),
];

$complements = $d['complements'];

$out = [
    'generated_at' => now()->toDateTimeString(),
    'rate_types' => $rateTypes->map(fn (RateType $r) => [
        'key' => $r->key, 'label' => $r->tr('label'), 'is_special' => (bool) $r->is_special,
        'weekdays' => $r->weekdays, 'priority' => $r->priority,
    ])->values()->all(),
    'zones' => $d['zones']->map(fn (Zone $z) => [
        'id' => $z->id, 'slug' => $z->slug, 'name' => $z->tr('name'), 'subtitle' => $z->tr('subtitle'),
        'description' => $z->tr('description'), 'age_label' => $z->tr('age_label'), 'age_range' => $z->tr('age_range'),
        'area_sqm' => $z->area_sqm, 'rides_count' => $z->rides_count, 'image' => $z->image,
        'accent' => $z->accent, 'color' => $z->color, 'color_secondary' => $z->color_secondary,
        'hechos' => $hechos($z),
        'attractions' => $z->attractions->map(fn ($a) => [
            'name' => $a->tr('name'), 'badge' => $a->tr('badge'), 'image' => $a->image,
            'is_special' => (bool) $a->is_special, 'age' => $a->age ?? null, 'position' => $a->position,
            'purchasable' => $complements->isPurchasable($a),
            'ticket_type' => $a->ticketType?->tr('name'),
        ])->values()->all(),
    ])->values()->all(),
    'tickets' => $d['tickets']->map($producto)->values()->all(),
    'packages' => $d['packages']->map($producto)->values()->all(),
    'socks' => TicketType::with('prices.rateType')->where('is_active', true)->get()
        ->first(fn ($t) => str_contains(Str::lower($t->tr('name')), 'calcet'))?->displayPriceCents(),
    'faqs' => $d['faqs']->map(fn ($f) => ['q' => $f->tr('question'), 'a' => $f->tr('answer')])->values()->all(),
    'rules' => $d['rules']->map(fn ($r) => ['name' => $r->tr('name'), 'description' => $r->tr('description')])->values()->all(),
];

file_put_contents('/var/www/html/storage/app/portada-datos.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo '→ storage/app/portada-datos.json ('.count($out['zones']).' zonas · '.count($out['tickets']).' entradas · '.count($out['packages']).' packs · '.count($out['faqs']).' faq · '.count($out['rules'])." normas)\n";
