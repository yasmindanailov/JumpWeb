<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Setting;
use App\Models\Attraction;
use App\Models\Faq;
use App\Models\LandingService;
use App\Models\Page;
use App\Models\ParkRule;
use App\Models\RateType;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\CookiePolicyContent;
use App\Support\LegalContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contenido de ejemplo de la landing (ES/EN/FR), tomado de design_mockup.
 * Son PLACEHOLDERS editables desde el panel (Fase 7). Datos del negocio reales
 * por confirmar (ver docs/02-CUESTIONARIO-DESCUBRIMIENTO.md).
 */
class LandingContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedZonesAndAttractions();
        $this->seedRateTypes();
        $this->seedTicketTypes();
        $this->seedPacks();
        $this->seedAddons();
        $this->seedFaqs();
        $this->seedRules();
        $this->seedLandingServices();
        $this->seedPages();
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'business.name', 'value' => 'SaltoPark', 'group' => 'business'],
            ['key' => 'business.city', 'value' => 'Villaparque', 'group' => 'business'],
            // Datos fiscales (Fase 7.0, decisión #118/#120). [PENDIENTE] por la instalación;
            // los rellena desde el panel cuando los aporte. No bloquean Fase 7 pero sí Fase 9.
            ['key' => 'business.legal_name', 'value' => '[PENDIENTE]', 'group' => 'business'],
            ['key' => 'business.nif', 'value' => '[PENDIENTE]', 'group' => 'business'],
            ['key' => 'business.address', 'value' => '[PENDIENTE]', 'group' => 'business'],
            // Dominio para los textos legales (token `:site_domain`, #220). Vacío → host de la petición.
            ['key' => 'business.domain', 'value' => '', 'group' => 'business'],
            ['key' => 'contact.email', 'value' => 'hola@saltopark.example', 'group' => 'contact'],
            ['key' => 'contact.phone', 'value' => '968 22 22 22', 'group' => 'contact'],
            ['key' => 'contact.instagram', 'value' => 'https://instagram.com/saltopark', 'group' => 'social'],
            ['key' => 'contact.tiktok', 'value' => 'https://tiktok.com/@saltopark', 'group' => 'social'],
            ['key' => 'address.line1', 'value' => 'Avenida de los Saltos, 22', 'group' => 'contact'],
            ['key' => 'address.line2', 'value' => '30009 Murcia', 'group' => 'contact'],
            ['key' => 'address.maps_url', 'value' => '#', 'group' => 'contact'],
            ['key' => 'seo.og_image', 'value' => '', 'group' => 'seo'], // [PENDIENTE] imagen para compartir en redes
            ['key' => 'payment.tax_rate', 'value' => '21', 'group' => 'payment'], // IVA % por defecto [PENDIENTE: confirmar con el gestor]
            ['key' => 'sales.hold_minutes', 'value' => '15', 'group' => 'payment'], // retención de plaza durante el pago
            // Retención de Order pendiente cuando el panel crea un pedido manual con
            // método "enlace email" (decisión #120). 24h por defecto: cliente que paga
            // esa noche desde casa. DISTINTO de sales.hold_minutes (15 min, pago síncrono).
            ['key' => 'sales.manual_hold_minutes', 'value' => '1440', 'group' => 'payment'],
            // 7.2e.2bis6 (#160) — Horizonte máximo de compra/edición en meses.
            // Restricción operativa del negocio: ninguna entrada o reserva se
            // agenda más allá de today+N meses (default 6). Aplicado en
            // `availableDatesForItem` (modal Gestionar) + `validateNewSlot`
            // (defense in depth backend).
            ['key' => 'sales.purchase_horizon_months', 'value' => '6', 'group' => 'payment'],
            // Zona horaria de presentación al cliente (#111, 2026-05-28). La BD guarda en UTC
            // (Laravel estándar, white-label friendly); aquí va la TZ en que se MUESTRAN las fechas.
            ['key' => 'display_timezone', 'value' => 'Europe/Madrid', 'group' => 'display'],
            // Color de marca GLOBAL (white-label, Fase 7.10 iter.2). Lo usan los elementos
            // genéricos de la web (`--zone-1`), el color primario del panel y el acento de los
            // emails. Los colores POR ZONA viven en `zones.color` (#210) para sus contextos
            // específicos. Default = el acento histórico (naranja Jump) → sin cambio inicial.
            ['key' => 'theme.brand', 'value' => '#FF5B22', 'group' => 'theme'],
            // Redsys — sandbox público (#104). Credenciales reales = Fase 9 (van a `.env` o
            // se sobrescriben desde el panel; nunca al repo). Ver `docs/PLAN-REDSYS.md` §11.
            ['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment'],          // test | live
            ['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment'],  // FUC sandbox
            ['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment'],
            ['key' => 'redsys_secret_key', 'value' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', 'group' => 'payment'], // clave sandbox pública
            ['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment'],             // ISO-4217 EUR
            ['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment'],
            // NOTA: `redsys_next_gateway_order` NO va en este array — es estado
            // OPERATIVO (contador vivo), no config. Se siembra abajo con
            // `firstOrCreate` para NO resetearlo en cada `db:seed` (#169).
            // Puerta — operativa de validación de registro (Fase 7.1a, decisión #126).
            // Rate limit alto por defecto (100/min); el throughput de la puerta en pico
            // del parque puede ser intenso. Configurable desde panel.
            ['key' => 'puerta.validate_rate_limit_per_minute', 'value' => '100', 'group' => 'puerta'],
            // Aforo de packs (#82): cupo por franja, pool propio (no toca las plazas de jump/kids).
            ['key' => 'packs.max_per_slot', 'value' => '5', 'group' => 'packs'],        // nº de cumpleaños por franja (0 = sin tope)
            ['key' => 'packs.max_guests_per_slot', 'value' => '60', 'group' => 'packs'], // nº de niños totales por franja (0 = sin tope)
            // ¿La preparación (montaje/limpieza) cuenta para el cupo, bloqueando franjas vecinas?
            // [DECIDIDO 2026-05-25] activable/desactivable; por defecto sí. Editable en panel (Fase 7).
            ['key' => 'packs.prep_blocks_cupo', 'value' => '1', 'group' => 'packs'],    // 1 = sí, 0 = solo la franja de inicio
            // Mantenimiento — subsistema de disponibilidad (#218, fuera de roadmap). OFF por defecto:
            // el mantenimiento exige opt-in EXPLÍCITO desde el panel (Configuración → Mantenimiento)
            // y es fail-safe (un valor corrupto NO cierra la web; ver App\Domain\Platform\Services\MaintenanceSettings).
            // El mensaje por idioma es un override OPCIONAL; vacío → texto i18n por defecto.
            ['key' => 'maintenance.site', 'value' => '0', 'group' => 'maintenance'],    // 1 = toda la web en mantenimiento
            ['key' => 'maintenance.message.es', 'value' => '', 'group' => 'maintenance'],
            ['key' => 'maintenance.message.en', 'value' => '', 'group' => 'maintenance'],
            ['key' => 'maintenance.message.fr', 'value' => '', 'group' => 'maintenance'],
            // Reservas (#218, item 3): '0' = abiertas (default). '1' = en pausa → los CTAs de
            // «Reservar» caen al teléfono y los guards de servidor bloquean la reserva online; el
            // pedido manual del panel y las callbacks de Redsys siguen funcionando. Misma polaridad
            // que `maintenance.site` (default '0', estado de mantenimiento = '1').
            ['key' => 'reservations.paused', 'value' => '0', 'group' => 'maintenance'],
            // Mantenimiento POR PÁGINA (#218, item 1): '0' = disponible (default). '1' = esa página
            // muestra «sección no disponible» (503) con el nav/pie para navegar a otras. Lista fija
            // = App\Domain\Platform\Services\MaintenanceSettings::PAGE_KEYS (home/precios/cumpleanos/servicios/normas/contacto).
            ['key' => 'maintenance.page.home', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.page.precios', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.page.cumpleanos', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.page.servicios', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.page.normas', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance.page.contacto', 'value' => '0', 'group' => 'maintenance'],
            // Cookies (#219): mostrar el banner de consentimiento. Default ON ('1'). Apagarlo solo
            // oculta el banner; el bloqueo previo de los iframes de tercero sigue activo (gateado por
            // la cookie de consentimiento). Ver App\Support\CookieConsent y docs/PLAN-COOKIES.md.
            ['key' => 'cookies.banner_enabled', 'value' => '1', 'group' => 'cookies'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }

        // Contador de `gateway_order`: estado OPERATIVO (no config). Sembrar SOLO si falta
        // (`firstOrCreate`) — nunca resetear el valor vivo en un re-seed, porque dejaría el
        // contador por debajo de `gateway_order` ya emitidos → colisión en bucle al iniciar el
        // pago (#169). El suelo anti-colisión de `nextGatewayOrder` lo recupera igualmente, pero
        // no resetear es la primera línea de defensa.
        //
        // VALOR INICIAL = timestamp Unix (#169 seguimiento 2026-06-09, verificado contra la doc de
        // Redsys): el `Ds_Merchant_Order` debe ser ÚNICO DE POR VIDA por comercio+terminal y Redsys
        // RECUERDA cada número ya procesado (incluido el sandbox). Un arranque FIJO (100000)
        // colisiona tras un `migrate:fresh`/clon nuevo —el sandbox ya vio 100000…— → SIS0051 /
        // `Ds_Response 0913` ("pedido repetido"). Arrancar desde el timestamp (≈10 dígitos
        // numéricos, primeras 4 numéricas, ≤12 chars) hace que cada base nueva empiece POR ENCIMA
        // de cualquier sesión previa → resistente a resets, en línea con la recomendación de Redsys
        // de un nº de pedido derivado de fecha/hora (su formato sugerido es `yyyyMMdd0000`). NO
        // toca el algoritmo de generación (contador monotónico + suelo): solo el punto de partida.
        Setting::firstOrCreate(
            ['key' => 'redsys_next_gateway_order'],
            ['value' => (string) now()->timestamp, 'group' => 'payment'],
        );
    }

    private function seedZonesAndAttractions(): void
    {
        $jump = Zone::updateOrCreate(['slug' => 'jump'], [
            'name' => ['es' => 'JUMP', 'en' => 'JUMP', 'fr' => 'JUMP'],
            'subtitle' => [
                'es' => 'Para los que ya saltan.',
                'en' => 'For those who already jump.',
                'fr' => 'Pour ceux qui sautent déjà.',
            ],
            'description' => [
                'es' => 'Trampolines de pared a pared, foam pit, tirolina y free run. Si buscas adrenalina, esta es tu zona. Desde 6 años: si el niño mide menos de 1,30 m, entra con un adulto.',
                'en' => 'Wall-to-wall trampolines, foam pit, zipline and free run. Pure adrenaline. From age 6: if the child is under 1.30 m, they enter with an adult.',
                'fr' => 'Trampolines mur à mur, foam pit, tyrolienne et free run. Adrénaline pure. Dès 6 ans : si l\'enfant mesure moins de 1,30 m, il entre avec un adulte.',
            ],
            'age_label' => ['es' => 'Edad', 'en' => 'Age', 'fr' => 'Âge'],
            'age_range' => ['es' => '+6 años · 1,30 m', 'en' => '+6 yrs · 1.30 m', 'fr' => '+6 ans · 1,30 m'],
            'area_sqm' => 5000,
            'rides_count' => 15,
            // Foto de zona (feature 2026-06-11): la landing pinta la card con la foto integrada
            // (patrón A del mockup); editable en el panel. Vacío → card sin foto (fallback).
            'image' => 'images/attractions/park_jump.webp',
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
        ]);

        $kids = Zone::updateOrCreate(['slug' => 'kids'], [
            'name' => ['es' => 'KIDS', 'en' => 'KIDS', 'fr' => 'KIDS'],
            'subtitle' => [
                'es' => 'Para los más peques.',
                'en' => 'For the little ones.',
                'fr' => 'Pour les tout-petits.',
            ],
            'description' => [
                'es' => 'Piscina de bolas, mini trampolines, toboganes y un circuito blando pensado para los que están aprendiendo. De 1 a 12 años; los menores de 3 años acceden acompañados de un adulto.',
                'en' => 'Ball pool, mini trampolines, slides and a soft motor circuit designed for first jumps. Ages 1 to 12; under-3s must be with an adult.',
                'fr' => 'Piscine à balles, mini-trampolines, toboggans et un parcours moteur tout doux pour les premiers sauts. De 1 à 12 ans ; les moins de 3 ans accompagnés d\'un adulte.',
            ],
            'age_label' => ['es' => 'Edad', 'en' => 'Age', 'fr' => 'Âge'],
            'age_range' => ['es' => '1 — 12 años', 'en' => '1 — 12 yrs', 'fr' => '1 — 12 ans'],
            'area_sqm' => 2000,
            'rides_count' => 8,
            'image' => 'images/attractions/kids_zone.webp',
            'accent' => 'kids',
            'color' => '#C6FF3A',
            'position' => 2,
        ]);

        // Atracciones REALES (fotos reales del sector origen, 2026-06-11). Una card por foto, deduplicando
        // ángulos repetidos (los sobrantes van a la galería). Nombre/edad/descripción i18n son
        // PLACEHOLDER editables desde el panel (CMS #211). Formato: [name[3], age[3], desc[3],
        // archivo de imagen (en images/attractions/), badge opcional [3]].
        $jumpRides = [
            [['Saltos libres', 'Free jump', 'Saut libre'], ['Todos los públicos', 'All ages', 'Tous publics'], ['Trampolines de pared a pared para saltar sin parar de un lado a otro.', 'Wall-to-wall trampolines to jump non-stop from side to side.', 'Trampolines mur à mur pour sauter sans arrêt d\'un côté à l\'autre.'], 'jump_saltos_libres.webp'],
            [['Salto a la nube', 'Cloud jump', 'Saut dans le nuage'], ['+8 años', '+8 yrs', '+8 ans'], ['Lánzate desde el trampolín a un colchón de aire gigante. Aterrizaje blando.', 'Launch off the trampoline onto a giant airbag. Soft landing.', 'Lance-toi du trampoline sur un coussin d\'air géant. Atterrissage tout doux.'], 'jump_saltos_de_nube.webp', ['XL', 'XL', 'XL']],
            [['Tirolina', 'Zipline', 'Tyrolienne'], ['+10 años', '+10 yrs', '+10 ans'], ['Cruza la sala colgado de la tirolina, por encima de las atracciones.', 'Cross the room hanging from the zipline, above the attractions.', 'Traverse la salle suspendu à la tyrolienne, au-dessus des attractions.'], 'jump_tirolina_jump.webp'],
            [['Basket Jump', 'Basket Jump', 'Basket Jump'], ['+8 años', '+8 yrs', '+8 ans'], ['Salta del trampolín y machaca el aro como un profesional.', 'Jump off the trampoline and dunk the hoop like a pro.', 'Saute du trampoline et dunk comme un pro.'], 'jump_basket_jump.webp'],
            [['Boxing Jump', 'Boxing Jump', 'Boxing Jump'], ['+10 años', '+10 yrs', '+10 ans'], ['Duelo sobre la viga: derriba a tu rival al foam y aguanta en pie.', 'Beam duel: knock your rival into the foam and stay standing.', 'Duel sur la poutre : fais tomber ton rival dans la mousse et reste debout.'], 'jump_boxing_jump.webp'],
            [['Barredora', 'Sweeper', 'Balayeuse'], ['+8 años', '+8 yrs', '+8 ans'], ['Salta el brazo giratorio que barre la pista o caerás al suelo.', 'Jump the spinning arm sweeping the floor or you will fall.', 'Saute le bras tournant qui balaie la piste ou tu tomberas.'], 'jump_barredora1.webp'],
            [['Barredora circular', 'Spinning sweeper', 'Balayeuse circulaire'], ['+8 años', '+8 yrs', '+8 ans'], ['La barredora, ahora en círculo: salta sin parar para no caer.', 'The sweeper, now in a circle: keep jumping so you do not fall.', 'La balayeuse, version circulaire : saute sans arrêt pour ne pas tomber.'], 'jump_barredora_circular.webp'],
            [['Parkour & Free Run', 'Parkour & Free Run', 'Parkour & Free Run'], ['+12 años', '+12 yrs', '+12 ans'], ['Circuito de obstáculos blandos para entrenar tus saltos de parkour.', 'Soft obstacle circuit to train your parkour moves.', 'Parcours d\'obstacles en mousse pour entraîner tes mouvements de parkour.'], 'jump_parkour.webp'],
            [['Escalada vertical', 'Vertical climbing', 'Escalade verticale'], ['+8 años', '+8 yrs', '+8 ans'], ['Pared de escalada con colchoneta de seguridad para llegar a lo más alto.', 'Climbing wall with a safety mat to reach the very top.', 'Mur d\'escalade avec tapis de sécurité pour atteindre le sommet.'], 'jump_escalada_vertical.webp'],
            [['Equilibrio', 'Balance beam', 'Équilibre'], ['+6 años', '+6 yrs', '+6 ans'], ['Cruza la viga de equilibrio sin perder pie.', 'Cross the balance beam without losing your footing.', 'Traverse la poutre d\'équilibre sans perdre pied.'], 'jump_equilibrio_.webp'],
            [['Circuito de postes', 'Pole circuit', 'Parcours de poteaux'], ['+6 años', '+6 yrs', '+6 ans'], ['Esquiva los postes a toda velocidad de un extremo a otro.', 'Dodge the poles at full speed from end to end.', 'Esquive les poteaux à toute vitesse d\'un bout à l\'autre.'], 'jump_circuito_de_postes.webp'],
            [['Circuito High', 'High circuit', 'Parcours en hauteur'], ['+12 años', '+12 yrs', '+12 ans'], ['Circuito en altura para los más atrevidos.', 'An elevated circuit for the boldest.', 'Un parcours en hauteur pour les plus audacieux.'], 'jump_circuito_high.webp', ['PRO', 'PRO', 'PRO']],
            [['Tobogán colchoneta', 'Mat slide', 'Toboggan à tapis'], ['Todos los públicos', 'All ages', 'Tous publics'], ['Deslízate por el gran tobogán sobre la colchoneta.', 'Slide down the big slide on a mat.', 'Glisse sur le grand toboggan avec un tapis.'], 'jump_tobogan_colchone.webp'],
            [['Atina la bola', 'Hit the target', 'Vise la cible'], ['Todos los públicos', 'All ages', 'Tous publics'], ['Pon a prueba tu puntería lanzando a las dianas.', 'Test your aim by throwing at the targets.', 'Teste ta précision en visant les cibles.'], 'jump_atina_bal.webp'],
            [['Bee Jump', 'Bee Jump', 'Bee Jump'], ['Todos los públicos', 'All ages', 'Tous publics'], ['Salta y rebota sin parar en el trampolín de la abeja.', 'Bounce non-stop on the bee trampoline.', 'Rebondis sans arrêt sur le trampoline de l\'abeille.'], 'jump_bee.webp'],
        ];

        $kidsRides = [
            [['Piscina de bolas', 'Ball pool', 'Piscine à balles'], ['1 — 7 años', '1 — 7 yrs', '1 — 7 ans'], ['Más de 10.000 bolas blanditas para tirarse, esconderse y nadar.', 'More than 10,000 soft balls to dive, hide and swim in.', 'Plus de 10 000 balles toutes douces pour plonger, se cacher et nager.'], 'kids_piscina_de_bolas.webp'],
            [['Toboganes', 'Slides', 'Toboggans'], ['2 — 7 años', '2 — 7 yrs', '2 — 7 ans'], ['Toboganes de colores que terminan en un cojín gigante.', 'Colourful slides that end in a giant cushion.', 'Toboggans colorés qui finissent sur un coussin géant.'], 'kids_tobganes.webp', ['Grande', 'Big', 'Grand']],
            [['Tobogán de bolas', 'Ball slide', 'Toboggan à balles'], ['2 — 7 años', '2 — 7 yrs', '2 — 7 ans'], ['Un tobogán que acaba directo en la piscina de bolas.', 'A slide that ends straight in the ball pool.', 'Un toboggan qui finit droit dans la piscine à balles.'], 'kids_tobogan_bolas.webp'],
            [['Castillo de bloques', 'Block castle', 'Château de blocs'], ['1 — 6 años', '1 — 6 yrs', '1 — 6 ans'], ['Construye y trepa por el castillo de bloques gigantes.', 'Build and climb the giant block castle.', 'Construis et grimpe le château de blocs géants.'], 'kids_castillo_lego.webp'],
            [['Circuito de obstáculos', 'Obstacle circuit', 'Parcours d\'obstacles'], ['1 — 4 años', '1 — 4 yrs', '1 — 4 ans'], ['Espumas blandas para gatear, trepar y dar los primeros saltos.', 'Soft foam shapes to crawl, climb and take first jumps.', 'Mousses douces pour ramper, grimper et faire les premiers sauts.'], 'kids_circuito_obstaculos.webp', ['Baby', 'Baby', 'Bébé']],
            [['Cohete 360', 'Rocket 360', 'Fusée 360'], ['3 — 7 años', '3 — 7 yrs', '3 — 7 ans'], ['Gira y gira en el cohete 360 sin parar de reír.', 'Spin and spin on the rocket 360, laughing all the way.', 'Tourne et tourne sur la fusée 360 en riant.'], 'kids_cohete360.webp'],
            [['Túnel loco', 'Crazy tunnel', 'Tunnel fou'], ['2 — 6 años', '2 — 6 yrs', '2 — 6 ans'], ['Un túnel mullido que cruza la zona Kids de extremo a extremo.', 'A padded tunnel that crosses the Kids zone end to end.', 'Un tunnel rembourré qui traverse la zone Kids d\'un bout à l\'autre.'], 'kids_crazy_tunel.webp'],
            [['Campo de fútbol', 'Football pitch', 'Terrain de foot'], ['3 — 12 años', '3 — 12 yrs', '3 — 12 ans'], ['Marca goles en el mini campo de fútbol acolchado.', 'Score goals on the padded mini football pitch.', 'Marque des buts sur le mini terrain de foot rembourré.'], 'kids_campo_futrbol.webp'],
        ];

        $this->seedRides($jump, $jumpRides);
        $this->seedRides($kids, $kidsRides);
    }

    private function seedRides(Zone $zone, array $rides): void
    {
        foreach ($rides as $i => $ride) {
            [$name, $age, $desc, $imageFile] = $ride;
            $badge = $ride[4] ?? null; // 5º elemento opcional: [es, en, fr] del sub-badge (#4)
            $image = 'images/attractions/'.$imageFile;

            Attraction::updateOrCreate(
                ['zone_id' => $zone->id, 'position' => $i + 1],
                [
                    'name' => ['es' => $name[0], 'en' => $name[1], 'fr' => $name[2]],
                    'age' => ['es' => $age[0], 'en' => $age[1], 'fr' => $age[2]],
                    'description' => ['es' => $desc[0], 'en' => $desc[1], 'fr' => $desc[2]],
                    'badge' => $badge ? ['es' => $badge[0], 'en' => $badge[1], 'fr' => $badge[2]] : null,
                    'image' => file_exists(public_path($image)) ? $image : null,
                ]
            );
        }

        // Poda idempotente: el catálogo real tiene MÁS atracciones que el placeholder anterior
        // (8 jump / 6 kids). En un re-seed sobre una BD vieja quedan filas con position > las
        // nuevas → se borran (ninguna FK apunta a `attractions`; el complemento vinculado #228
        // está en la propia fila). No-op en BD limpia.
        Attraction::where('zone_id', $zone->id)->where('position', '>', count($rides))->delete();
    }

    /** Tarifas por tipo de día (#59). v1: normal y especial (festivo/finde/víspera). */
    private function seedRateTypes(): void
    {
        RateType::updateOrCreate(['key' => RateType::KEY_NORMAL], [
            'label' => ['es' => 'Día normal', 'en' => 'Regular day', 'fr' => 'Jour normal'],
            'is_special' => false,
            'weekdays' => null,
            'priority' => 0,
            'is_active' => true,
        ]);

        RateType::updateOrCreate(['key' => RateType::KEY_SPECIAL], [
            'label' => ['es' => 'Findes y festivos', 'en' => 'Weekends & holidays', 'fr' => 'Week-ends et fériés'],
            'is_special' => true,
            'weekdays' => [0, 6], // domingo y sábado; festivos/vísperas se marcan en special_dates
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    /**
     * Entradas vendibles: 2 zonas (jump/kids) × 4 duraciones (1H/2H/3H/ilimitada).
     * Precios por tarifa en `prices` (única fuente, #61). Valores PLACEHOLDER, editables
     * desde el panel (Fase 7); datos reales [PENDIENTE].
     */
    private function seedTicketTypes(): void
    {
        $zones = Zone::pluck('id', 'slug');       // ['jump' => id, 'kids' => id]
        $rates = RateType::pluck('id', 'key');    // ['normal' => id, 'special' => id]

        $durations = [
            ['min' => 60,   'es' => '1 hora',    'en' => '1 hour',     'fr' => '1 heure',  'time_es' => '1 hora',      'time_en' => '1 hour',  'time_fr' => '1 heure'],
            ['min' => 120,  'es' => '2 horas',   'en' => '2 hours',    'fr' => '2 heures', 'time_es' => '2 horas',     'time_en' => '2 hours', 'time_fr' => '2 heures'],
            ['min' => 180,  'es' => '3 horas',   'en' => '3 hours',    'fr' => '3 heures', 'time_es' => '3 horas',     'time_en' => '3 hours', 'time_fr' => '3 heures'],
            ['min' => null, 'es' => 'Ilimitada', 'en' => 'Unlimited',  'fr' => 'Illimité', 'time_es' => 'todo el día', 'time_en' => 'all day', 'time_fr' => 'toute la journée'],
        ];

        // Precios placeholder por zona y duración (céntimos): [normal, especial].
        $priceTable = [
            'jump' => [60 => [990, 1190], 120 => [1390, 1590], 180 => [1690, 1890], 0 => [1990, 2290]],
            'kids' => [60 => [790, 990],  120 => [1090, 1290], 180 => [1390, 1590], 0 => [1690, 1890]],
        ];

        $zoneMeta = [
            'jump' => ['label' => 'Jump', 'cond' => ['es' => '+6 años · 1,30 m', 'en' => '+6 yrs · 1.30 m', 'fr' => '+6 ans · 1,30 m'],   'band' => 'Naranja'],
            'kids' => ['label' => 'Kids', 'cond' => ['es' => '1 — 12 años', 'en' => '1 — 12 yrs', 'fr' => '1 — 12 ans'], 'band' => 'Verde'],
        ];

        $position = 0;

        foreach ($zoneMeta as $slug => $zone) {
            foreach ($durations as $d) {
                $position++;
                // Entrada "Top" (destacada) de cada zona: la de 2 horas (configurable en el panel, Fase 7).
                $featured = $d['min'] === 120;

                $ticket = TicketType::updateOrCreate(
                    ['position' => $position],
                    [
                        'name' => [
                            'es' => "{$zone['label']} · {$d['es']}",
                            'en' => "{$zone['label']} · {$d['en']}",
                            'fr' => "{$zone['label']} · {$d['fr']}",
                        ],
                        'period_label' => ['es' => 'por persona', 'en' => 'per person', 'fr' => 'par personne'],
                        'features' => [
                            'es' => ["Acceso a la zona {$zone['label']}", "Tiempo de salto: {$d['time_es']}", 'Reserva online recomendada'],
                            'en' => ["Access to the {$zone['label']} zone", "Jump time: {$d['time_en']}", 'Online booking recommended'],
                            'fr' => ["Accès à la zone {$zone['label']}", "Temps de saut : {$d['time_fr']}", 'Réservation en ligne recommandée'],
                        ],
                        'zone_id' => $zones[$slug],
                        'duration_min' => $d['min'],
                        'tax_rate' => 21,
                        'wristband_color' => $zone['band'],
                        'conditions' => $zone['cond'],
                        'badge' => $featured ? ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top'] : null,
                        'featured' => $featured,
                        'is_sellable' => true,
                        'seats_per_unit' => 1,
                        'is_active' => true,
                    ],
                );

                [$normalCents, $specialCents] = $priceTable[$slug][$d['min'] ?? 0];

                $ticket->prices()->updateOrCreate(
                    ['rate_type_id' => $rates[RateType::KEY_NORMAL]],
                    ['amount_cents' => $normalCents, 'currency' => 'EUR'],
                );
                $ticket->prices()->updateOrCreate(
                    ['rate_type_id' => $rates[RateType::KEY_SPECIAL]],
                    ['amount_cents' => $specialCents, 'currency' => 'EUR'],
                );
            }
        }
    }

    /**
     * Packs (cumpleaños) como producto del catálogo unificado (type=pack, #82/#83). Viven en una
     * zona "Cumpleaños" propia que **opera** (is_active=true → vende packs y aparece en el flujo de
     * compra) pero **no se muestra en la landing/precios** (show_in_landing=false; #210 desacopló
     * ambos conceptos), con sus franjas (SalesSeeder) para elegir fecha/hora. El aforo NO va por
     * plazas sino por CUPO (PackAvailability, Capa 2b). Precio POR NIÑO (× invitados) en la matriz
     * de tarifas. Valores PLACEHOLDER editables en el panel (Fase 7); reales [PENDIENTE].
     */
    private function seedPacks(): void
    {
        $zone = Zone::updateOrCreate(['slug' => 'cumpleanos'], [
            'name' => ['es' => 'Cumpleaños', 'en' => 'Birthdays', 'fr' => 'Anniversaires'],
            'subtitle' => [
                'es' => 'Su día, su mesa, su monitor.',
                'en' => 'Their day, their table, their host.',
                'fr' => 'Leur jour, leur table, leur animateur.',
            ],
            // Zona operativa de packs (vende cumpleaños) pero NO se muestra como tarjeta de zona
            // en la landing: is_active = opera; show_in_landing = aparece en la landing (#82).
            'is_active' => true,
            'show_in_landing' => false,
            // accent propio (no jump/kids) → el subtítulo de zona en el sidebar de compra usa el
            // color de la zona (`--zone-color`), no el tono afinado de jump. color = fucsia.
            'accent' => 'cumpleanos',
            'color' => '#C026D3',
            // Foto de la zona cumpleaños (#231): la usa la sección de cumpleaños de la landing
            // (polaroid de la banda). Editable en el panel; vacío → polaroid sin foto (fallback).
            'image' => 'images/attractions/cumplea_1.webp',
            'position' => 3,
        ]);

        $rates = RateType::pluck('id', 'key'); // ['normal' => id, 'special' => id]

        // Campos del evento, compartidos por ambos packs (#86): editables en el panel (Fase 7).
        // `stage` (#217): los `booking` se piden al RESERVAR; los `postform`, en el formulario
        // POSTERIOR a la reserva (junto a los datos por niño), para no saturar la compra.
        $eventFields = [
            ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Nombre del homenajeado/a', 'en' => "Birthday child's name", 'fr' => "Nom de l'enfant fêté"]],
            ['key' => 'age', 'type' => 'number', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad que cumple', 'en' => 'Age turning', 'fr' => 'Âge fêté']],
            ['key' => 'notes', 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Notas (alergias, temática…)', 'en' => 'Notes (allergies, theme…)', 'fr' => 'Notes (allergies, thème…)']],
            ['key' => 'adults_approx', 'type' => 'number', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Nº aproximado de adultos', 'en' => 'Approx. number of adults', 'fr' => "Nombre approximatif d'adultes"]],
            ['key' => 'observations', 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Observaciones', 'en' => 'Remarks', 'fr' => 'Remarques']],
        ];

        // DOS packs de cumpleaños: JUMP (exclusivo zona Jump) y KIDS (exclusivo zona Kids).
        // Ambos: mín. 8 invitados + señal de 30€ para reservar. Comparten la zona operativa
        // "cumpleanos" (cupo/franjas, SalesSeeder); el color de marca para la landing se deriva
        // en el blade por el slug/orden (no hay columna `accent` en TicketType).
        $packs = [
            [
                'position' => 9,
                'name' => ['es' => 'Cumpleaños Jump', 'en' => 'Jump Birthday', 'fr' => 'Anniversaire Jump'],
                'description' => [
                    'es' => 'El cumple exclusivo de la zona Jump: trampolines, foam pit y adrenalina. Mesa reservada, monitor para el grupo y salto sin parar.',
                    'en' => 'The birthday exclusive to the Jump zone: trampolines, foam pit and adrenaline. Reserved table, a host for the group and non-stop jumping.',
                    'fr' => "L'anniversaire exclusif de la zone Jump : trampolines, foam pit et adrénaline. Table réservée, un animateur et du saut sans arrêt.",
                ],
                'features' => [
                    'es' => ['Acceso exclusivo a la zona Jump', 'Mesa reservada para el grupo', 'Monitor para la sesión', '120 minutos de saltos'],
                    'en' => ['Exclusive access to the Jump zone', 'Reserved table for the group', 'Host for the session', '120 minutes of jumping'],
                    'fr' => ['Accès exclusif à la zone Jump', 'Table réservée pour le groupe', 'Animateur pour la séance', '120 minutes de saut'],
                ],
                'conditions' => ['es' => 'Desde 6 años · 1,30 m', 'en' => 'From age 6 · 1.30 m', 'fr' => 'Dès 6 ans · 1,30 m'],
            ],
            [
                'position' => 10,
                'name' => ['es' => 'Cumpleaños Kids', 'en' => 'Kids Birthday', 'fr' => 'Anniversaire Kids'],
                'description' => [
                    'es' => 'El cumple exclusivo de la zona Kids: piscina de bolas, toboganes y circuito blando. Mesa reservada y monitor para los más peques.',
                    'en' => 'The birthday exclusive to the Kids zone: ball pool, slides and a soft circuit. Reserved table and a host for the little ones.',
                    'fr' => "L'anniversaire exclusif de la zone Kids : piscine à balles, toboggans et parcours doux. Table réservée et un animateur pour les petits.",
                ],
                'features' => [
                    'es' => ['Acceso exclusivo a la zona Kids', 'Mesa reservada para el grupo', 'Monitor para la sesión', '120 minutos de juego'],
                    'en' => ['Exclusive access to the Kids zone', 'Reserved table for the group', 'Host for the session', '120 minutes of play'],
                    'fr' => ['Accès exclusif à la zone Kids', 'Table réservée pour le groupe', 'Animateur pour la séance', '120 minutes de jeu'],
                ],
                'conditions' => ['es' => 'De 1 a 12 años', 'en' => 'Ages 1–12', 'fr' => '1 à 12 ans'],
            ],
        ];

        foreach ($packs as $p) {
            $pack = TicketType::updateOrCreate(
                ['position' => $p['position']],
                [
                    'name' => $p['name'],
                    'description' => $p['description'],
                    'period_label' => ['es' => 'por niño', 'en' => 'per child', 'fr' => 'par enfant'],
                    'features' => $p['features'],
                    'zone_id' => $zone->id,
                    'type' => TicketType::TYPE_PACK,
                    'duration_min' => 120,         // duración de la fiesta (la franja-rejilla sigue siendo de 60')
                    'prep_before_min' => 60,       // montaje (bloquea el cupo antes)
                    'prep_after_min' => 30,        // limpieza (bloquea el cupo después)
                    'min_qty' => 8,                // mín. 8 invitados para reservar
                    'max_qty' => 20,               // máx. invitados
                    // Señal de 30€ para reservar (#7). [PENDIENTE/DEUDA 2026-06-01]: la landing
                    // ya anuncia la señal y `TicketType::depositCents()` la calcula, pero el
                    // checkout (OrderCreator→Redsys) aún cobra el total; conectar el cobro parcial
                    // de la señal es trabajo de una fase posterior (decidido: dejar como deuda).
                    'deposit_type' => TicketType::DEPOSIT_FIXED,
                    'deposit_value' => 3000,       // 30,00 € (céntimos)
                    'event_fields' => $eventFields,
                    // Esquema por-niño del post-form (#217): 4 columnas por defecto
                    // {nombre, alergia, observaciones, menú especial}, editables en el panel.
                    'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
                    'tax_rate' => 21,
                    'conditions' => $p['conditions'],
                    'seats_per_unit' => 1,         // 1 niño = 1 invitado (cuenta para el tope de niños)
                    'is_sellable' => true,
                    'is_active' => true,
                ],
            );

            // Precio POR NIÑO por tipo de día (céntimos): [normal, especial].
            $pack->prices()->updateOrCreate(
                ['rate_type_id' => $rates[RateType::KEY_NORMAL]],
                ['amount_cents' => 1500, 'currency' => 'EUR'],
            );
            $pack->prices()->updateOrCreate(
                ['rate_type_id' => $rates[RateType::KEY_SPECIAL]],
                ['amount_cents' => 1800, 'currency' => 'EUR'],
            );
        }
    }

    /**
     * Complementos (type=addon, #87): productos sin zona/aforo, con precio plano (igual en
     * normal y especial para que sean vendibles cualquier día). Se asocian POR PRODUCTO vía el
     * pivote `product_addons` (calcetines/taquilla → todas; tarta/monitor → solo cumpleaños).
     * PLACEHOLDERS editables en el panel (Fase 7); productos y aplicabilidad reales [PENDIENTE].
     */
    private function seedAddons(): void
    {
        $rates = RateType::pluck('id', 'key');
        $entries = TicketType::ofType(TicketType::TYPE_ENTRY)->pluck('id')->all();
        $packs = TicketType::ofType(TicketType::TYPE_PACK)->pluck('id')->all();

        $addons = [
            ['position' => 20, 'applies' => 'all', 'price' => 200,
                'name' => ['es' => 'Calcetines antideslizantes', 'en' => 'Grip socks', 'fr' => 'Chaussettes antidérapantes']],
            ['position' => 21, 'applies' => 'all', 'price' => 200,
                'name' => ['es' => 'Taquilla', 'en' => 'Locker', 'fr' => 'Casier']],
            ['position' => 22, 'applies' => 'pack', 'price' => 2500,
                'name' => ['es' => 'Tarta', 'en' => 'Cake', 'fr' => 'Gâteau']],
            ['position' => 23, 'applies' => 'pack', 'price' => 4000,
                'name' => ['es' => 'Monitor extra', 'en' => 'Extra host', 'fr' => 'Animateur supplémentaire']],
        ];

        foreach ($addons as $a) {
            $addon = TicketType::updateOrCreate(
                ['position' => $a['position']],
                [
                    'name' => $a['name'],
                    'type' => TicketType::TYPE_ADDON,
                    'tax_rate' => 21,
                    'is_sellable' => true,
                    'is_active' => true,
                ],
            );
            // Precio plano: mismo importe en cualquier tarifa (los complementos no varían por día).
            foreach ([RateType::KEY_NORMAL, RateType::KEY_SPECIAL] as $key) {
                $addon->prices()->updateOrCreate(
                    ['rate_type_id' => $rates[$key]],
                    ['amount_cents' => $a['price'], 'currency' => 'EUR'],
                );
            }

            // Productos a los que aplica (pivote, idempotente).
            $targets = match ($a['applies']) {
                'pack' => $packs,
                'entry' => $entries,
                default => array_merge($entries, $packs),
            };
            foreach ($targets as $productId) {
                DB::table('product_addons')->updateOrInsert(
                    ['product_id' => $productId, 'addon_id' => $addon->id],
                    ['position' => $a['position']],
                );
            }
        }
    }

    private function seedFaqs(): void
    {
        $faqs = [
            [['¿Es necesario reservar?', 'Do I need to book?', 'Faut-il réserver ?'], ['Para saltar, ven directo — no hace falta reservar. Los findes nos llenamos rápido; si quieres asegurar tu sitio, llámanos. Los cumpleaños sí se reservan online.', 'To jump, just come in — no booking needed. Weekends fill up fast, so call us if you want to make sure of your spot. Birthdays do book online.', 'Pour sauter, viens directement — pas besoin de réserver. Le week-end on se remplit vite ; appelle-nous pour assurer ta place. Les anniversaires, eux, se réservent en ligne.']],
            [['¿Desde qué edad pueden saltar?', 'Minimum age?', 'À partir de quel âge ?'], ['Zona Kids: de 1 a 12 años (los menores de 3, con un adulto). Zona Jump: desde 6 años; si el niño mide menos de 1,30 m, entra con un adulto.', 'Kids zone: ages 1–12 (under-3s with an adult). Jump zone: from age 6; if the child is under 1.30 m, they enter with an adult.', 'Zone Kids : de 1 à 12 ans (les moins de 3 ans avec un adulte). Zone Jump : dès 6 ans ; si l\'enfant mesure moins de 1,30 m, il entre avec un adulte.']],
            [['¿Hay parking?', 'Is there parking?', 'Y a-t-il un parking ?'], ['Sí, parking gratuito durante 2 horas en el mismo recinto. Después, 1€/h.', 'Yes, free for 2 hours on site. €1/h after that.', 'Oui, gratuit pendant 2 heures sur place. 1€/h après.']],
            [['¿Puedo cancelar una reserva?', 'Can I cancel my booking?', 'Puis-je annuler ma réservation ?'], ['Sí. Llámanos o escríbenos y lo gestionamos. Si reduces algo ya pagado, te devolvemos la diferencia automáticamente. Los plazos están en nuestras Condiciones.', 'Yes. Call or write to us and we\'ll handle it. If you reduce something already paid, we refund the difference automatically. The deadlines are in our Terms.', 'Oui. Appelle-nous ou écris-nous et on s\'en occupe. Si tu réduis quelque chose déjà payé, on te rembourse la différence automatiquement. Les délais sont dans nos Conditions.']],
            [['¿Tenéis tarifa de grupos?', 'Group rates?', 'Tarif groupe ?'], ['Sí. Preparamos excursiones de colegio, jornadas de empresa y salidas de grupos de adultos a medida, también fuera del horario. Escríbenos desde Contacto o mira la página de Servicios.', 'Yes. We arrange school trips, company days and adult group outings tailored to you, also outside opening hours. Write to us from Contact or see our Services page.', "Oui. On organise des sorties scolaires, des journées d'entreprise et des sorties de groupes d'adultes sur mesure, aussi hors horaires. Écris-nous depuis Contact ou vois la page Services."]],
            [['¿Y si llueve?', 'And if it rains?', 'Et s\'il pleut ?'], ['Mejor — el parque es interior, climatizado y a 22ºC todo el año.', 'Better — it\'s all indoor, climate-controlled at 22ºC year round.', 'Encore mieux — c\'est en intérieur, climatisé à 22ºC toute l\'année.']],
        ];

        foreach ($faqs as $i => [$q, $a]) {
            Faq::updateOrCreate(['position' => $i + 1], [
                'question' => ['es' => $q[0], 'en' => $q[1], 'fr' => $q[2]],
                'answer' => ['es' => $a[0], 'en' => $a[1], 'fr' => $a[2]],
            ]);
        }
    }

    private function seedRules(): void
    {
        $rules = [
            [['Registro', 'Registration', 'Inscription'], ['Si es tu primera visita, antes de hacer cola para entrar debes registrarte en nuestra web para aprobar las normas del parque y el contenido legal. En el caso de menores de 16 años, el registro lo realiza el/la tutor/a legal.', "If it's your first visit, before queueing to enter you must register on our website to approve the park rules and legal terms. For under-16s, the legal guardian completes the registration.", "Lors de ta première visite, avant de faire la queue, tu dois t'inscrire sur notre site pour approuver le règlement du parc et les mentions légales. Pour les moins de 16 ans, l'inscription est faite par le tuteur légal."]],
            [['Zona Kids', 'Kids zone', 'Zone Kids'], ['Consulta las condiciones del centro.', 'Check the conditions of the centre.', 'Consulte les conditions du centre.']],
            [['Zona Jump', 'Jump zone', 'Zone Jump'], ['Entrada desde los 6 años y 1,30 m de estatura. Si se supera la edad mínima y la estatura está comprendida entre 1 m y 1,30 m, la entrada deberá ser con el/la tutor/a.', 'Entry from age 6 and 1.30 m tall. If the minimum age is met and height is between 1 m and 1.30 m, entry must be with a guardian.', "Entrée dès 6 ans et 1,30 m. Si l'âge minimum est atteint et la taille est entre 1 m et 1,30 m, l'entrée doit se faire avec un tuteur."]],
            [['Conducta', 'Conduct', 'Conduite'], ['Un uso inadecuado de las instalaciones, hacer caso omiso a las indicaciones del Staff o generar altercados con otros usuarios puede ser motivo de expulsión.', 'Improper use of the facilities, ignoring staff instructions or causing altercations with other users may result in expulsion.', "Un usage inapproprié des installations, le non-respect des consignes du Staff ou des altercations avec d'autres usagers peuvent entraîner l'expulsion."]],
            [['Información', 'Information', 'Information'], ['No dudes en consultar con nuestro Staff cualquier término o condición sobre tarifas, promociones, cumpleaños o de otra índole.', 'Feel free to ask our staff about any term or condition regarding rates, promotions, birthdays or anything else.', "N'hésite pas à demander à notre Staff toute information sur les tarifs, promotions, anniversaires ou autre."]],
        ];

        foreach ($rules as $i => [$name, $desc]) {
            ParkRule::updateOrCreate(['position' => $i + 1], [
                'name' => ['es' => $name[0], 'en' => $name[1], 'fr' => $name[2]],
                'description' => ['es' => $desc[0], 'en' => $desc[1], 'fr' => $desc[2]],
            ]);
        }

        // Limpia normas antiguas sobrantes (antes había 6; ahora 5). Idempotente.
        ParkRule::where('position', '>', count($rules))->delete();
    }

    /**
     * Secciones editoriales de /servicios (#256, modelo A). Migradas desde `lang/services.php`.
     * Hoy son SOLO-CONTACTO (`ticket_type_id` null): líneas comerciales que no se venden online
     * (colegios, empresas, sesión de adultos). El negocio puede vincularles un pack desde el panel
     * para volverlas comprables. Los `slug` son los ANCHORS estables (el nav enlaza /servicios#slug;
     * los tests los verifican). `updateOrCreate` por slug = idempotente y NO pisa los servicios que
     * el negocio añada aparte (a diferencia de FAQs/normas, que sí podan).
     *
     * PÚBLICO: lo reutiliza el seeder dedicado `LandingServicesSeeder` (fuente única, sin duplicar
     * datos) para sembrar SOLO los servicios en una instalación ya en marcha (p. ej. producción tras
     * el deploy, que migra pero no siembra) sin tocar el resto del contenido:
     *   php artisan db:seed --class='Database\Seeders\LandingServicesSeeder' --force
     */
    public function seedLandingServices(): void
    {
        $services = [
            [
                'slug' => 'excursionescolegio',
                'image' => 'images/attractions/kids_zone.webp',
                'accent_word' => ['es' => 'Colegio', 'en' => 'School', 'fr' => 'École'],
                'title' => ['es' => 'Excursiones de colegio', 'en' => 'School trips', 'fr' => 'Sorties scolaires'],
                'zone_label' => ['es' => 'Kids + Jump', 'en' => 'Kids + Jump', 'fr' => 'Kids + Jump'],
                'nav_subtitle' => ['es' => 'Sesiones de 2 o 3 h', 'en' => '2- or 3-hour sessions', 'fr' => 'Séances de 2 ou 3 h'],
                'body' => [
                    'es' => 'Organizamos visitas para centros educativos con monitores y todo el material, en sesiones de 2 o 3 horas fuera de nuestro horario de apertura al público. Precios especiales por grupo; nos adaptamos al número de alumnos y a vuestro calendario.',
                    'en' => 'We host school visits with monitors and all the gear, in 2- or 3-hour sessions outside our public opening hours. Group pricing, and we adapt to your class size and calendar.',
                    'fr' => "On accueille les sorties scolaires avec moniteurs et tout le matériel, lors de séances de 2 ou 3 heures en dehors de nos horaires d'ouverture au public. Tarifs de groupe et on s'adapte au nombre d'élèves et à votre calendrier.",
                ],
                'specs' => [
                    'es' => [['label' => 'Duración', 'value' => '2 o 3 horas'], ['label' => 'Horario', 'value' => 'Fuera de apertura']],
                    'en' => [['label' => 'Duration', 'value' => '2 or 3 hours'], ['label' => 'Hours', 'value' => 'Outside opening']],
                    'fr' => [['label' => 'Durée', 'value' => '2 ou 3 heures'], ['label' => 'Horaires', 'value' => 'Hors ouverture']],
                ],
                // Tarifas de grupo INFORMATIVAS (precio por niño, en CÉNTIMOS): por zona → duración →
                // tramo de cantidad, con precio L–V (`weekday`) y finde/festivo (`weekend`). Solo se
                // MUESTRAN (#256); la reserva es por teléfono. Datos FICTICIOS de ejemplo del sector.
                // `unit` = clave i18n de la fila/nota (`kids` → «X niños»; `people` → «X personas»).
                'price_table' => [
                    'unit' => 'kids',
                    'zones' => [
                        [
                            'label' => 'Kids',
                            'accent' => 'kids',  // → tokens --kids-1/--on-kids (data-driven, zones.color)
                            'durations' => [
                                ['minutes' => 120, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1200, 'weekend' => 1400],
                                    ['size' => 75, 'weekday' => 1100, 'weekend' => 1300],
                                    ['size' => 100, 'weekday' => 1000, 'weekend' => 1200],
                                ]],
                                ['minutes' => 180, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1500, 'weekend' => 1700],
                                    ['size' => 75, 'weekday' => 1400, 'weekend' => 1600],
                                    ['size' => 100, 'weekday' => 1200, 'weekend' => 1400],
                                ]],
                            ],
                        ],
                        [
                            'label' => 'Jump',
                            'accent' => 'jump',  // → tokens --jump-1/--on-jump (data-driven, zones.color)
                            'durations' => [
                                ['minutes' => 120, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1500, 'weekend' => 1700],
                                    ['size' => 75, 'weekday' => 1400, 'weekend' => 1600],
                                    ['size' => 100, 'weekday' => 1200, 'weekend' => 1400],
                                ]],
                                ['minutes' => 180, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1800, 'weekend' => 2000],
                                    ['size' => 75, 'weekday' => 1600, 'weekend' => 1800],
                                    ['size' => 100, 'weekday' => 1500, 'weekend' => 1700],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'teambuilding',
                'image' => 'images/attractions/park_jump.webp',
                'accent_word' => ['es' => 'Equipo', 'en' => 'Team', 'fr' => 'Équipe'],
                'title' => ['es' => 'Empresas', 'en' => 'Companies', 'fr' => 'Entreprises'],
                'zone_label' => ['es' => 'Parque completo', 'en' => 'Whole park', 'fr' => 'Parc entier'],
                'nav_subtitle' => ['es' => 'Desde 30 personas', 'en' => 'From 30 people', 'fr' => 'À partir de 30 personnes'],
                'body' => [
                    'es' => 'Jornadas para equipos: cohesión, juegos cooperativos y mucha actividad física, con el parque para vosotros fuera del horario habitual. Reservas a partir de 30 personas; adaptamos la duración y las dinámicas a tu empresa.',
                    'en' => 'Team days: cohesion, cooperative games and plenty of action, with the park to yourselves outside regular hours. Bookings from 30 people; we tailor the duration and activities to your company.',
                    'fr' => "Journées pour les équipes : cohésion, jeux coopératifs et beaucoup d'action, avec le parc rien que pour vous en dehors des horaires habituels. Réservations à partir de 30 personnes ; on adapte la durée et les activités à ton entreprise.",
                ],
                'specs' => [
                    'es' => [['label' => 'Grupo', 'value' => 'Mínimo 30 personas'], ['label' => 'Horario', 'value' => 'Fuera de apertura']],
                    'en' => [['label' => 'Group', 'value' => 'Minimum 30 people'], ['label' => 'Hours', 'value' => 'Outside opening']],
                    'fr' => [['label' => 'Groupe', 'value' => 'Minimum 30 personnes'], ['label' => 'Horaires', 'value' => 'Hors ouverture']],
                ],
                // Tarifas de grupo INFORMATIVAS (céntimos) — SOLO zona Jump (2h/3h), MISMOS precios que el
                // colegio (config del sector origen): una sola zona → la card se pinta SIN pestañas. `unit=people`
                // → filas/nota en «personas» (no «niños»): es un servicio de empresas/adultos.
                'price_table' => [
                    'unit' => 'people',
                    'zones' => [
                        [
                            'label' => 'Jump',
                            'accent' => 'jump',
                            'durations' => [
                                ['minutes' => 120, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1500, 'weekend' => 1700],
                                    ['size' => 75, 'weekday' => 1400, 'weekend' => 1600],
                                    ['size' => 100, 'weekday' => 1200, 'weekend' => 1400],
                                ]],
                                ['minutes' => 180, 'tiers' => [
                                    ['size' => 30, 'weekday' => 1800, 'weekend' => 2000],
                                    ['size' => 75, 'weekday' => 1600, 'weekend' => 1800],
                                    ['size' => 100, 'weekday' => 1500, 'weekend' => 1700],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'sesionadultos',
                'image' => 'images/attractions/jump_saltos_libres.webp',
                'accent_word' => ['es' => 'Noche', 'en' => 'Night', 'fr' => 'Nuit'],
                'title' => ['es' => 'Excursión para mayores', 'en' => 'Adults outing', 'fr' => 'Sortie adultes'],
                'zone_label' => ['es' => 'Parque completo', 'en' => 'Whole park', 'fr' => 'Parc entier'],
                'nav_subtitle' => ['es' => '22:00–01:00 · mín. 30', 'en' => '22:00–01:00 · min. 30', 'fr' => '22h00–01h00 · min. 30'],
                'body' => [
                    'es' => 'Abrimos el parque solo para grupos de adultos, de 22:00 a 01:00: saltos sin niños, música y ambiente relajado. Reservas a partir de 30 personas.',
                    'en' => 'We open the park for adult groups only, from 22:00 to 01:00: jumping without kids, music and a relaxed vibe. Bookings from 30 people.',
                    'fr' => "On ouvre le parc uniquement pour les groupes d'adultes, de 22h00 à 01h00 : sauts sans enfants, musique et ambiance détendue. Réservations à partir de 30 personnes.",
                ],
                'specs' => [
                    'es' => [['label' => 'Horario', 'value' => '22:00 – 01:00'], ['label' => 'Grupo', 'value' => 'Mínimo 30 personas']],
                    'en' => [['label' => 'Hours', 'value' => '22:00 – 01:00'], ['label' => 'Group', 'value' => 'Minimum 30 people']],
                    'fr' => [['label' => 'Horaires', 'value' => '22h00 – 01h00'], ['label' => 'Groupe', 'value' => 'Minimum 30 personnes']],
                ],
            ],
        ];

        foreach ($services as $i => $data) {
            LandingService::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'position' => $i + 1,
                    'ticket_type_id' => null,
                    'is_active' => true,
                    'show_in_nav' => true,
                ]),
            );
        }
    }

    /**
     * Páginas de texto legal. BORRADOR propio [PENDIENTE de revisión legal]: redacción
     * genérica estándar (RGPD/LSSI) adaptada al negocio, con los datos del titular
     * marcados [PENDIENTE]. NO se copian textos de terceros (validez legal + copyright).
     * Editable desde el panel (Fase 7). Cuerpo = lista de secciones {h, p} por idioma.
     */
    private function seedPages(): void
    {
        foreach ($this->legalDocs() as $slug => $doc) {
            Page::updateOrCreate(['slug' => $slug], [
                'title' => $doc['title'],
                'body' => $doc['body'],
            ]);
        }
    }

    private function legalDocs(): array
    {
        // Las 3 legales (privacidad/condiciones/aviso-legal) vienen redactadas y ancladas al código
        // desde la fuente única `LegalContent` (#220); cookies desde `CookiePolicyContent` (#219).
        // Ambas se comparten con sus migraciones de reparación. El waiver sigue en borrador (su
        // gestión es el sistema externo del negocio, #216).
        $legal = LegalContent::pages();

        return [
            'privacidad' => $legal['privacidad'],
            'cookies' => [
                'title' => CookiePolicyContent::title(),
                'body' => CookiePolicyContent::body(),
            ],
            'condiciones' => $legal['condiciones'],
            'aviso-legal' => $legal['aviso-legal'],
            'waiver' => [
                'title' => ['es' => 'Exención de responsabilidad', 'en' => 'Liability waiver', 'fr' => 'Décharge de responsabilité'],
                'body' => [
                    'es' => [
                        ['h' => 'Conocimiento del riesgo', 'p' => 'Saltar en trampolines y usar las atracciones conlleva riesgos. Al acceder al parque declaras conocerlos y aceptarlos.'],
                        ['h' => 'Normas de seguridad', 'p' => 'Te comprometes a seguir las normas del parque y las indicaciones del personal en todo momento.'],
                        ['h' => 'Menores a tu cargo', 'p' => 'El adulto que acepta es responsable de los menores que le acompañan y acepta esta exención en su nombre.'],
                        ['h' => 'Aptitud física', 'p' => 'Declaras encontrarte en condiciones físicas adecuadas para la actividad y no tener contraindicaciones médicas.'],
                        ['h' => 'Aceptación', 'p' => 'Este texto es un borrador y será revisado por un asesor legal antes de su publicación. [PENDIENTE: redacción definitiva].'],
                    ],
                    'en' => [
                        ['h' => 'Awareness of risk', 'p' => 'Trampoline jumping and using the attractions involve risks. By entering the park you declare that you understand and accept them.'],
                        ['h' => 'Safety rules', 'p' => 'You agree to follow the park rules and staff instructions at all times.'],
                        ['h' => 'Minors in your care', 'p' => 'The accepting adult is responsible for accompanying minors and accepts this waiver on their behalf.'],
                        ['h' => 'Physical fitness', 'p' => 'You declare that you are in suitable physical condition for the activity and have no medical contraindications.'],
                        ['h' => 'Acceptance', 'p' => 'This text is a draft and will be reviewed by a legal advisor before publication. [PENDING: final wording].'],
                    ],
                    'fr' => [
                        ['h' => 'Conscience du risque', 'p' => 'Le saut sur trampoline et l\'usage des attractions comportent des risques. En entrant dans le parc, tu déclares les connaître et les accepter.'],
                        ['h' => 'Règles de sécurité', 'p' => 'Tu t\'engages à respecter les règles du parc et les consignes du personnel à tout moment.'],
                        ['h' => 'Mineurs à ta charge', 'p' => 'L\'adulte qui accepte est responsable des mineurs qui l\'accompagnent et accepte cette décharge en leur nom.'],
                        ['h' => 'Aptitude physique', 'p' => 'Tu déclares être en condition physique adaptée à l\'activité et sans contre-indication médicale.'],
                        ['h' => 'Acceptation', 'p' => 'Ce texte est un brouillon et sera revu par un conseiller juridique avant publication. [À COMPLÉTER : rédaction définitive].'],
                    ],
                ],
            ],
        ];
    }
}
