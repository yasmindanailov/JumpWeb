<?php

namespace Database\Seeders;

use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Models\Setting;
use App\Models\OpeningHour;
use App\Models\Order;
use App\Models\RateType;
use App\Models\Season;
use App\Models\SlotTemplate;
use App\Models\SpecialDate;
use App\Models\TicketType;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seed de INSTALACIÓN (semilla neutra de Fase 1, DECISIONES #12.c) — negocio FICTICIO de
 * ejemplo del mismo sector («SaltoPark», parque de trampolines): estructura y catálogo
 * completos y realistas, SIN datos identificativos de ningún cliente. Cada instalación
 * sustituye identidad/contacto/legales desde el panel ([PENDIENTE] donde aplica).
 *
 * SEPARADO de `LandingContentSeeder` a propósito: ese sigue siendo el FIXTURE de los tests
 * (catálogo demo con entradas vendibles que ejercita todos los caminos), mientras que este
 * refleja el catálogo REAL — donde las ENTRADAS hoy NO se venden online (solo se muestran;
 * la venta online es solo de CUMPLEAÑOS). Acoplar los tests a precios/sellability reales
 * (que cambian) sería frágil; por eso son seeds distintos.
 *
 * Estrategia: reutiliza toda la ESTRUCTURA ya real de `LandingContentSeeder` (zonas con sus
 * fotos #230, atracciones, FAQs, normas, páginas legales) y SUSTITUYE lo operativo: settings
 * fiscales/contacto, catálogo (entradas/packs/complementos), tarifas, horarios, temporada de
 * verano, festivos y plantillas de franja de cumpleaños.
 *
 * Idempotente. NO genera franjas materializadas: eso lo hace el comando
 * `slots:generate-rolling` (paso del runbook `docs/INSTALACION-CLIENTE.md`), reproducible y sin
 * dependencia de "hoy" dentro del seed.
 *
 * Uso en despliegue: `php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force`.
 * El usuario admin real se crea aparte (ver `docs/INSTALACION-CLIENTE.md` §5) — este seed NO crea usuarios.
 *
 * ⚠️ Marcadores [PENDIENTE]: identidad fiscal (razón social, NIF, dominio) y contacto
 * (email/teléfono/redes) son PLACEHOLDERS — cada instalación los fija desde el panel antes del
 * go-live. El teléfono alimenta el CTA «Llamar» de las entradas no vendibles.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // 0) SALVAGUARDA ANTI-PÉRDIDA DE DATOS. Este seed REEMPLAZA el catálogo: borra y recrea
        // `ticket_types`, y `order_items`/`tickets` tienen FK `cascadeOnDelete` → re-sembrar sobre una
        // BD con PEDIDOS reales borraría pedidos de clientes en cascada. Es un seed de ARRANQUE EN FRÍO:
        // si ya hay pedidos, abortamos. Post-go-live, el contenido se edita desde el panel (/admin), que
        // es data-driven; NUNCA se re-siembra producción. (En tests/staging no hay pedidos → pasa.)
        if (Order::query()->exists()) {
            throw new RuntimeException(
                'ProductionSeeder abortado: la base de datos ya tiene PEDIDOS. Este seed solo es para el '.
                'arranque en frío (reemplaza el catálogo y borraría pedidos en cascada). Edita el contenido '.
                'desde el panel /admin, no re-sembrando producción.',
            );
        }

        // 1) Estructura ya real (zonas+fotos #230, atracciones, FAQs, normas, legales) + roles/permisos.
        $this->call([
            LandingContentSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);

        // 2) Sustituir lo operativo por los datos reales del negocio.
        $this->realSettings();
        $this->realZones();
        $this->clearDemoCatalog();
        $this->realEntries();
        $this->realPacks();
        $this->realAddons();
        $this->realSchedule();
    }

    /**
     * Settings reales / placeholders fiscales. Sobrescribe los del `LandingContentSeeder`.
     */
    private function realSettings(): void
    {
        $settings = [
            // Identidad — PLACEHOLDER ficticio (cada instalación pone la suya antes del go-live).
            ['key' => 'business.name', 'value' => 'SaltoPark', 'group' => 'business'],
            ['key' => 'business.city', 'value' => 'Villaparque', 'group' => 'business'],
            ['key' => 'business.legal_name', 'value' => 'SaltoPark S.L.', 'group' => 'business'],      // [PENDIENTE: razón social real]
            ['key' => 'business.nif', 'value' => 'B-12345678', 'group' => 'business'],                   // [PENDIENTE: NIF/CIF real]
            ['key' => 'business.address', 'value' => 'Calle del Salto, 1 · 00000 Villaparque (España)', 'group' => 'business'], // [PENDIENTE: confirmar domicilio FISCAL]
            ['key' => 'business.domain', 'value' => 'saltopark.example', 'group' => 'business'],            // [PENDIENTE: dominio real]

            // Contacto público — teléfono/email PLACEHOLDER (el teléfono alimenta el CTA «Llamar»).
            ['key' => 'contact.email', 'value' => 'hola@saltopark.example', 'group' => 'contact'],          // [PENDIENTE]
            ['key' => 'contact.phone', 'value' => '+34 600 000 000', 'group' => 'contact'],              // [PENDIENTE]
            ['key' => 'contact.whatsapp', 'value' => '', 'group' => 'social'],                            // [PENDIENTE]
            ['key' => 'contact.instagram', 'value' => '', 'group' => 'social'],                           // [PENDIENTE: redes]
            ['key' => 'contact.tiktok', 'value' => '', 'group' => 'social'],                              // [PENDIENTE: redes]
            ['key' => 'social.feed_embed_url', 'value' => '', 'group' => 'social'],                       // [PENDIENTE: widget feed]

            // Ubicación FICTICIA de ejemplo + mapa vacío (la instalación pone el suyo desde el
            // panel; la landing degrada con gracia sin mapa — [PENDIENTE: ubicación y maps reales]).
            ['key' => 'address.line1', 'value' => 'Calle del Salto, 1', 'group' => 'contact'],
            ['key' => 'address.line2', 'value' => '00000 Villaparque (España)', 'group' => 'contact'],
            ['key' => 'address.maps_url', 'value' => '', 'group' => 'contact'],
            ['key' => 'address.maps_embed_url', 'value' => '', 'group' => 'contact'],

            // Registro/waiver EXTERNO (#216): vacío = modal interno. [PENDIENTE: URL del
            // sistema externo de registro/waiver, si la instalación usa uno].
            ['key' => 'registration.url', 'value' => '', 'group' => 'business'],

            // Fuero de los textos legales (token :jurisdiction de LegalIdentity, Fase 1).
            ['key' => 'legal.jurisdiction', 'value' => '[PENDIENTE: partido judicial]', 'group' => 'business'],

            // SEO genérico de ejemplo (cada instalación pule el suyo en el panel).
            ['key' => 'seo.title.es', 'value' => 'SaltoPark · Parque de saltos', 'group' => 'seo'],
            ['key' => 'seo.title.en', 'value' => 'SaltoPark · Trampoline park', 'group' => 'seo'],
            ['key' => 'seo.title.fr', 'value' => 'SaltoPark · Parc de trampolines', 'group' => 'seo'],

            // IVA general de ocio (parques recreativos) — confirmar con el gestor.
            ['key' => 'payment.tax_rate', 'value' => '21', 'group' => 'payment'],

            // Aforo de cumpleaños (datos reales): 5 fiestas por franja; sin tope total de niños
            // (solo el tope de fiestas), cada fiesta máx. 20 niños (en el propio pack `max_qty`).
            ['key' => 'packs.max_per_slot', 'value' => '5', 'group' => 'packs'],
            ['key' => 'packs.max_guests_per_slot', 'value' => '0', 'group' => 'packs'], // 0 = sin tope total de niños
            // Sin montaje/limpieza (valor tipo del sector origen: sin montaje/limpieza) → la prep no bloquea cupo.
            ['key' => 'packs.prep_blocks_cupo', 'value' => '0', 'group' => 'packs'],

            ['key' => 'display_timezone', 'value' => 'Europe/Madrid', 'group' => 'display'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }
    }

    /**
     * Ajustes de zona específicos de producción (cupo de cumpleaños por zona = 5 fiestas/franja).
     * El resto del contenido de zona (fotos, descripciones, colores) viene de `LandingContentSeeder`.
     */
    private function realZones(): void
    {
        // La zona "cumpleanos" gobierna el cupo de packs por franja (#210): 5 fiestas/franja,
        // sin tope total de niños (override per-zona; coincide con el global de arriba).
        Zone::where('slug', 'cumpleanos')->update([
            'max_per_slot' => 5,
            'max_guests_per_slot' => null, // null → usa el global (0 = sin tope de niños)
            'prep_blocks_cupo' => false,
        ]);
    }

    /**
     * Reemplaza el catálogo DEMO (entradas/packs/complementos del `LandingContentSeeder`) por el
     * real. Seguro en un seed de producción (BD sin pedidos); zonas/atracciones/legales se conservan.
     */
    private function clearDemoCatalog(): void
    {
        Attraction::query()->update(['ticket_type_id' => null]); // desvincula atracciones de pago demo
        DB::table('product_addons')->delete();
        DB::table('prices')->where('priceable_type', (new TicketType)->getMorphClass())->delete();
        TicketType::query()->delete();
    }

    /**
     * ENTRADAS reales — Jump/Kids × 1h/2h. NO se venden online por ahora (`is_sellable=false`):
     * se MUESTRAN en la landing/precios con CTA «Llamar» (la venta es en taquilla). Finde/festivo +3€.
     */
    private function realEntries(): void
    {
        $zones = Zone::pluck('id', 'slug');
        $rates = RateType::pluck('id', 'key');

        // [zona, etiqueta, condiciones i18n, pulsera] — descripciones GENÉRICAS (se pulen en copys).
        $meta = [
            'jump' => ['label' => 'Jump', 'cond' => ['es' => 'A partir de 4 años · tirolina solo +1,30 m', 'en' => 'From age 4 · zipline only +1.30 m', 'fr' => 'Dès 4 ans · tyrolienne seulement +1,30 m'], 'band' => 'Naranja'],
            'kids' => ['label' => 'Kids', 'cond' => ['es' => '1 — 12 años (menores de 3, con tutor)', 'en' => 'Ages 1–12 (under-3s with a guardian)', 'fr' => '1 à 12 ans (moins de 3 ans avec un tuteur)'], 'band' => 'Verde'],
        ];

        // duración(min) → [normal_cents, especial_cents (+300)]. Precios reales.
        $prices = [
            'jump' => [60 => [1200, 1500], 120 => [1800, 2100]],
            'kids' => [60 => [1000, 1300], 120 => [1500, 1800]],
        ];

        $durations = [
            60 => ['es' => '1 hora', 'en' => '1 hour', 'fr' => '1 heure'],
            120 => ['es' => '2 horas', 'en' => '2 hours', 'fr' => '2 heures'],
        ];

        $position = 0;
        foreach ($meta as $slug => $m) {
            foreach ($durations as $min => $dur) {
                $position++;
                $featured = $min === 120; // la de 2h es la destacada

                $entry = TicketType::updateOrCreate(
                    ['position' => $position],
                    [
                        'name' => [
                            'es' => "{$m['label']} · {$dur['es']}",
                            'en' => "{$m['label']} · {$dur['en']}",
                            'fr' => "{$m['label']} · {$dur['fr']}",
                        ],
                        'period_label' => ['es' => 'por persona', 'en' => 'per person', 'fr' => 'par personne'],
                        'features' => [
                            'es' => ["Acceso a la zona {$m['label']}", "Tiempo de salto: {$dur['es']}"],
                            'en' => ["Access to the {$m['label']} zone", "Jump time: {$dur['en']}"],
                            'fr' => ["Accès à la zone {$m['label']}", "Temps de saut : {$dur['fr']}"],
                        ],
                        'type' => TicketType::TYPE_ENTRY,
                        'zone_id' => $zones[$slug],
                        'duration_min' => $min,
                        'tax_rate' => 21,
                        'wristband_color' => $m['band'],
                        'conditions' => $m['cond'],
                        'badge' => $featured ? ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top'] : null,
                        'featured' => $featured,
                        'seats_per_unit' => 1,
                        'min_advance_value' => 1,         // 1 día de antelación (irrelevante mientras no se vendan online)
                        'min_advance_unit' => 'days',
                        // ⚠️ Hoy las entradas NO se venden online (configuración de ejemplo del sector): se muestran con CTA «Llamar».
                        'is_sellable' => false,
                        'is_active' => true,              // visibles en la landing/precios
                    ],
                );

                [$normal, $special] = $prices[$slug][$min];
                $entry->prices()->updateOrCreate(['rate_type_id' => $rates[RateType::KEY_NORMAL]], ['amount_cents' => $normal, 'currency' => 'EUR']);
                $entry->prices()->updateOrCreate(['rate_type_id' => $rates[RateType::KEY_SPECIAL]], ['amount_cents' => $special, 'currency' => 'EUR']);
            }
        }
    }

    /**
     * PACKS de cumpleaños reales — Kids/Jump × 90/120 min. SÍ se venden online con SEÑAL de 30€
     * (resto en el parque). Mín. 8 / máx. 20 niños, 3 días de antelación, sin montaje/limpieza,
     * ventana intra-día: empezar ≥1h tras abrir y ≤1h antes de cerrar. Finde/festivo +3€/niño.
     */
    private function realPacks(): void
    {
        $zoneId = Zone::where('slug', 'cumpleanos')->value('id');
        $rates = RateType::pluck('id', 'key');

        $eventFields = [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Nombre del homenajeado/a', 'en' => "Birthday child's name", 'fr' => "Nom de l'enfant fêté"]],
            ['key' => 'age', 'type' => 'number', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad que cumple', 'en' => 'Age turning', 'fr' => 'Âge fêté']],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Notas (alergias, temática…)', 'en' => 'Notes (allergies, theme…)', 'fr' => 'Notes (allergies, thème…)']],
            ['key' => 'adults_approx', 'type' => 'number', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Nº aproximado de adultos (máx. 2 en la zona)', 'en' => 'Approx. number of adults (max 2 in the area)', 'fr' => "Nombre approximatif d'adultes (max 2 dans la zone)"]],
            ['key' => 'observations', 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Observaciones', 'en' => 'Remarks', 'fr' => 'Remarques']],
        ];

        // [posición, etiqueta, duración(min), normal_cents, condiciones] — especial = normal+300.
        $packs = [
            ['position' => 9, 'label' => 'Kids', 'duration' => 90, 'normal' => 1590, 'cond' => ['es' => 'De 1 a 12 años · 90 min + comida', 'en' => 'Ages 1–12 · 90 min + meal', 'fr' => '1 à 12 ans · 90 min + repas']],
            ['position' => 10, 'label' => 'Kids', 'duration' => 120, 'normal' => 1790, 'cond' => ['es' => 'De 1 a 12 años · 120 min + comida', 'en' => 'Ages 1–12 · 120 min + meal', 'fr' => '1 à 12 ans · 120 min + repas']],
            ['position' => 11, 'label' => 'Jump', 'duration' => 90, 'normal' => 1790, 'cond' => ['es' => 'Desde 4 años · 90 min + comida', 'en' => 'From age 4 · 90 min + meal', 'fr' => 'Dès 4 ans · 90 min + repas']],
            ['position' => 12, 'label' => 'Jump', 'duration' => 120, 'normal' => 1990, 'cond' => ['es' => 'Desde 4 años · 120 min + comida', 'en' => 'From age 4 · 120 min + meal', 'fr' => 'Dès 4 ans · 120 min + repas']],
        ];

        foreach ($packs as $p) {
            $mins = $p['duration'];
            $pack = TicketType::updateOrCreate(
                ['position' => $p['position']],
                [
                    'name' => [
                        'es' => "Cumpleaños {$p['label']} · {$mins} min",
                        'en' => "{$p['label']} Birthday · {$mins} min",
                        'fr' => "Anniversaire {$p['label']} · {$mins} min",
                    ],
                    'description' => [
                        'es' => "{$mins} minutos de salto en la zona {$p['label']}, comida (Menú 1 incluido) y una mesa reservada solo para tu grupo.",
                        'en' => "{$mins} minutes of jumping in the {$p['label']} zone, a meal (Menu 1 included) and a table reserved just for your group.",
                        'fr' => "{$mins} minutes de saut dans la zone {$p['label']}, un repas (Menu 1 inclus) et une table réservée rien que pour ton groupe.",
                    ],
                    'period_label' => ['es' => 'por niño', 'en' => 'per child', 'fr' => 'par enfant'],
                    'features' => [
                        'es' => ["Acceso a la zona {$p['label']}", "{$mins} minutos", 'Comida incluida (Menú 1)', 'Mesa reservada'],
                        'en' => ["Access to the {$p['label']} zone", "{$mins} minutes", 'Meal included (Menu 1)', 'Reserved table'],
                        'fr' => ["Accès à la zone {$p['label']}", "{$mins} minutes", 'Repas inclus (Menu 1)', 'Table réservée'],
                    ],
                    'type' => TicketType::TYPE_PACK,
                    'zone_id' => $zoneId,
                    'duration_min' => $mins,
                    'prep_before_min' => 0,            // sin montaje (config de ejemplo)
                    'prep_after_min' => 0,             // sin limpieza
                    'available_after_open_min' => 60,  // empezar ≥ 1h tras abrir
                    'available_before_close_min' => 60, // y ≤ 1h antes de cerrar
                    'min_qty' => 8,                    // mín. 8 niños
                    'max_qty' => 20,                   // máx. 20 niños
                    'min_advance_value' => 3,          // 3 días de antelación
                    'min_advance_unit' => 'days',
                    'deposit_type' => TicketType::DEPOSIT_FIXED,
                    'deposit_value' => 3000,           // señal 30,00 € (resto en el parque)
                    'event_fields' => $eventFields,
                    'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
                    'tax_rate' => 21,
                    'conditions' => $p['cond'],
                    'seats_per_unit' => 1,
                    'is_sellable' => true,
                    'is_active' => true,
                ],
            );

            $pack->prices()->updateOrCreate(['rate_type_id' => $rates[RateType::KEY_NORMAL]], ['amount_cents' => $p['normal'], 'currency' => 'EUR']);
            $pack->prices()->updateOrCreate(['rate_type_id' => $rates[RateType::KEY_SPECIAL]], ['amount_cents' => $p['normal'] + 300, 'currency' => 'EUR']);
        }
    }

    /**
     * COMPLEMENTOS reales + sus enganches (pivote `product_addons` con el modelo de inclusión).
     * Menú 1 (incluido) ⊻ Menú 2 (+2€/niño) = grupo de elección «menu». Tarta +20€, 2ª tarta +15€.
     * Calcetines (entradas+packs) y Tirolina (entradas Jump) son complementos sueltos.
     */
    private function realAddons(): void
    {
        $allEntries = TicketType::ofType(TicketType::TYPE_ENTRY)->pluck('id')->all();
        $jumpZoneId = Zone::where('slug', 'jump')->value('id');
        $jumpEntries = TicketType::ofType(TicketType::TYPE_ENTRY)->where('zone_id', $jumpZoneId)->pluck('id')->all();
        $packs = TicketType::ofType(TicketType::TYPE_PACK)->pluck('id')->all();

        // Cada addon: [posición, nombre i18n, precio_cents, descripción i18n (panel admin), features
        // i18n (bullets del «Más info» que VE el cliente en la card/checkout vía LandingAddonPresenter)].
        $make = function (int $position, array $name, int $price, array $description = [], array $features = []): TicketType {
            $rates = RateType::pluck('id', 'key');
            $addon = TicketType::updateOrCreate(
                ['position' => $position],
                ['name' => $name, 'description' => $description ?: null, 'features' => $features ?: null, 'type' => TicketType::TYPE_ADDON, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true],
            );
            foreach ([RateType::KEY_NORMAL, RateType::KEY_SPECIAL] as $key) {
                $addon->prices()->updateOrCreate(['rate_type_id' => $rates[$key]], ['amount_cents' => $price, 'currency' => 'EUR']);
            }

            return $addon;
        };

        $calcetines = $make(20, ['es' => 'Calcetines antideslizantes', 'en' => 'Grip socks', 'fr' => 'Chaussettes antidérapantes'], 200, [
            'es' => 'Obligatorios para saltar. Puedes traer los tuyos o comprarlos aquí.',
            'en' => 'Required for jumping. Bring your own or buy them here.',
            'fr' => 'Obligatoires pour sauter. Apporte les tiennes ou achète-les ici.',
        ], [
            'es' => ['Obligatorios para saltar', 'Puedes traer los tuyos o comprarlos aquí'],
            'en' => ['Required for jumping', 'Bring your own or buy them here'],
            'fr' => ['Obligatoires pour sauter', 'Apporte les tiennes ou achète-les ici'],
        ]);
        $tirolina = $make(21, ['es' => 'Tirolina', 'en' => 'Zipline', 'fr' => 'Tyrolienne'], 200, [
            'es' => 'Cruza la sala volando en la tirolina, por encima de las atracciones.',
            'en' => 'Fly across the room on the zipline, above the attractions.',
            'fr' => 'Traverse la salle en tyrolienne, au-dessus des attractions.',
        ], [
            'es' => ['Cruza la sala volando, por encima de las atracciones'],
            'en' => ['Fly across the room, above the attractions'],
            'fr' => ['Traverse la salle en vol, au-dessus des attractions'],
        ]);
        $tarta = $make(22, ['es' => 'Tarta (15 porciones)', 'en' => 'Cake (15 slices)', 'fr' => 'Gâteau (15 parts)'], 2000, [
            'es' => 'Se encarga con nosotros — no se puede traer de casa.',
            'en' => 'Ordered with us — outside cakes are not allowed.',
            'fr' => "À commander chez nous — gâteaux de l'extérieur non autorisés.",
        ], [
            'es' => ['15 porciones', 'Se encarga con nosotros — no se puede traer de casa'],
            'en' => ['15 slices', 'Ordered with us — outside cakes not allowed'],
            'fr' => ['15 parts', "À commander chez nous — pas de gâteau de l'extérieur"],
        ]);
        $tarta2 = $make(23, ['es' => 'Segunda tarta', 'en' => 'Second cake', 'fr' => 'Deuxième gâteau'], 1500, [
            'es' => '¿Sois muchos? Añade otra tarta de 15 porciones.',
            'en' => 'A big group? Add another 15-slice cake.',
            'fr' => 'Vous êtes nombreux ? Ajoute un autre gâteau de 15 parts.',
        ], [
            'es' => ['Otras 15 porciones, para grupos grandes'],
            'en' => ['Another 15 slices, for big groups'],
            'fr' => ['15 parts de plus, pour les grands groupes'],
        ]);
        $menu1 = $make(24, ['es' => 'Menú 1 (incluido)', 'en' => 'Menu 1 (included)', 'fr' => 'Menu 1 (inclus)'], 0, [
            'es' => 'El menú suave: sándwiches variados. Incluido en el pack.',
            'en' => 'The mild menu: assorted sandwiches. Included in the pack.',
            'fr' => 'Le menu doux : sandwichs variés. Inclus dans le pack.',
        ], [
            'es' => ['Sándwiches variados', 'Incluido en el pack'],
            'en' => ['Assorted sandwiches', 'Included in the pack'],
            'fr' => ['Sandwichs variés', 'Inclus dans le pack'],
        ]);
        $menu2 = $make(25, ['es' => 'Menú 2', 'en' => 'Menu 2', 'fr' => 'Menu 2'], 200, [
            'es' => 'Pizza y nuggets, en vez del Menú 1.',
            'en' => 'Pizza and nuggets, instead of Menu 1.',
            'fr' => 'Pizza et nuggets, au lieu du Menu 1.',
        ], [
            'es' => ['Pizza y nuggets', 'En vez del Menú 1'],
            'en' => ['Pizza and nuggets', 'Instead of Menu 1'],
            'fr' => ['Pizza et nuggets', 'Au lieu du Menu 1'],
        ]);

        // Enganches (product_addons). config: is_included, included_quantity, is_mandatory,
        // quantity_mode (fixed|per_guest), allow_extra, choice_group.
        $link = function (TicketType $addon, array $productIds, array $config): void {
            foreach ($productIds as $productId) {
                DB::table('product_addons')->updateOrInsert(
                    ['product_id' => $productId, 'addon_id' => $addon->id],
                    array_merge([
                        'position' => $addon->position,
                        'is_included' => false,
                        'included_quantity' => 1,
                        'is_mandatory' => false,
                        'quantity_mode' => 'fixed',
                        'allow_extra' => true,
                        'choice_group' => null,
                        'requires_addon_id' => null, // explícito → un re-seed limpia dependencias viejas
                    ], $config),
                );
            }
        };

        // Calcetines: cantidad LIBRE (fixed + stepper) tanto en entradas como en packs — el cliente
        // elige cuántos pares. La norma «obligatorios» se muestra como condición; el negocio puede
        // marcarlo obligatorio o por-invitado desde el panel (Catálogo → complementos → Configurar).
        $link($calcetines, $allEntries, ['quantity_mode' => 'fixed', 'allow_extra' => true]);
        $link($calcetines, $packs, ['quantity_mode' => 'fixed', 'allow_extra' => true]);
        // Tirolina: solo entradas de la zona Jump.
        $link($tirolina, $jumpEntries, ['quantity_mode' => 'fixed', 'allow_extra' => true]);
        // Tarta y 2ª tarta: solo packs (fijas, opcionales). La 2ª tarta REQUIERE la 1ª (no se puede
        // pedir la segunda sin la primera): dependencia data-driven, autoridad en `AddonResolver`.
        $link($tarta, $packs, ['quantity_mode' => 'fixed', 'allow_extra' => false]);
        $link($tarta2, $packs, ['quantity_mode' => 'fixed', 'allow_extra' => false, 'requires_addon_id' => $tarta->id]);
        // Menú 1 ⊻ Menú 2: grupo de elección excluyente «menu», por niño. Menú 1 incluido (gratis).
        $link($menu1, $packs, ['quantity_mode' => 'per_guest', 'allow_extra' => false, 'is_included' => true, 'choice_group' => 'menu']);
        $link($menu2, $packs, ['quantity_mode' => 'per_guest', 'allow_extra' => false, 'choice_group' => 'menu']);
    }

    /**
     * Horarios reales: L–V 16:00–22:00 · finde 11:00–22:00 · verano (jul–ago) 11:00–22:00 todos los
     * días · festivos 2026 (nacionales + autonómicos + locales de EJEMPLO) abiertos 11:00–22:00
     * con tarifa especial (+3€). Plantillas de franja SOLO para cumpleaños (lo único vendible online).
     */
    private function realSchedule(): void
    {
        // weekday: 0=domingo … 6=sábado (convención del proyecto, igual que rate_types.weekdays).
        // L–V (1–5): 16–22. Finde (0,6): 11–22.
        foreach (range(0, 6) as $weekday) {
            $isWeekend = in_array($weekday, [0, 6], true);
            OpeningHour::updateOrCreate(
                ['weekday' => $weekday],
                ['open_time' => $isWeekend ? '11:00:00' : '16:00:00', 'close_time' => '22:00:00', 'is_closed' => false],
            );
        }

        // Temporada de verano: julio y agosto, 11:00–22:00 TODOS los días (override de opening_hours).
        Season::updateOrCreate(
            ['name' => 'Horario de verano'],
            ['start_date' => '2026-07-01', 'end_date' => '2026-08-31', 'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_active' => true],
        );

        // Festivos 2026 de EJEMPLO (calendario tipo de la Región de Murcia). Abiertos 11:00–22:00 con
        // tarifa especial (+3€), como un finde. El negocio puede marcar cierres concretos (p. ej.
        // 1-ene / 25-dic) desde el panel si decide no abrir esos días.
        $specialRateId = RateType::where('key', RateType::KEY_SPECIAL)->value('id');
        $holidays = [
            ['2026-01-01', 'Año Nuevo', "New Year's Day", "Jour de l'An"],
            ['2026-01-06', 'Reyes (Epifanía)', 'Epiphany', 'Épiphanie'],
            ['2026-02-03', 'Fiesta local (ejemplo)', 'Local holiday (example)', 'Fête locale (exemple)'],
            ['2026-03-19', 'San José', "Saint Joseph's Day", 'Saint-Joseph'],
            ['2026-04-02', 'Jueves Santo', 'Maundy Thursday', 'Jeudi saint'],
            ['2026-04-03', 'Viernes Santo', 'Good Friday', 'Vendredi saint'],
            ['2026-05-01', 'Fiesta del Trabajo', 'Labour Day', 'Fête du Travail'],
            ['2026-06-09', 'Día de la Región de Murcia', 'Day of the Region of Murcia', 'Jour de la Région de Murcie'],
            ['2026-08-15', 'Asunción de la Virgen', 'Assumption Day', 'Assomption'],
            ['2026-10-12', 'Fiesta Nacional de España', 'National Day of Spain', "Fête nationale de l'Espagne"],
            ['2026-12-03', 'Fiesta local (ejemplo)', 'Local holiday (example)', 'Fête locale (exemple)'],
            ['2026-12-07', 'Día de la Constitución (traslado)', 'Constitution Day (in lieu)', 'Jour de la Constitution (report)'],
            ['2026-12-08', 'Inmaculada Concepción', 'Immaculate Conception', 'Immaculée Conception'],
            ['2026-12-25', 'Navidad', 'Christmas Day', 'Noël'],
        ];
        foreach ($holidays as [$date, $es, $en, $fr]) {
            SpecialDate::updateOrCreate(['date' => $date], [
                'is_closed' => false,
                'open_time' => '11:00:00',
                'close_time' => '22:00:00',
                'rate_type_id' => $specialRateId,
                'note' => ['es' => $es, 'en' => $en, 'fr' => $fr],
            ]);
        }

        // Producción solo vende CUMPLEAÑOS online (las entradas no se venden) → reseteamos TODAS las
        // plantillas de franja demo (jump/kids/cumpleaños) y sembramos SOLO las de cumpleaños.
        SlotTemplate::query()->delete();

        // Plantillas de franja de la zona de cumpleaños. El aforo va por CUPO (packs.max_per_slot),
        // por eso capacity/online son nominales. Inicios cada hora 11:00–21:00; el SlotOffer +
        // opening_hours + ventana intra-día filtran las válidas por día.
        $cumpleZoneId = Zone::where('slug', 'cumpleanos')->value('id');
        if ($cumpleZoneId !== null) {
            foreach (range(0, 6) as $weekday) {
                foreach (range(11, 21) as $hour) {
                    SlotTemplate::create([
                        'zone_id' => $cumpleZoneId,
                        'weekday' => $weekday,
                        'start_time' => sprintf('%02d:00:00', $hour),
                        'duration_min' => 60,
                        'capacity' => 200,
                        'online_capacity' => 200,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
