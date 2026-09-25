<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\TicketType;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiSurface;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — **el contrato manda** (`DECISIONES #21`, spec §2 criterio 2).
 *
 * `openapi/v1.yaml` no se genera desde el código: se escribe a mano, y estas guardas impiden que
 * el código y el documento se separen sin que nadie se entere. Reparten el trabajo con los tests
 * de cada endpoint:
 *
 *  - **allí** (vía `ApiTestCase`) se valida la RESPUESTA REAL contra el esquema, que es lo que la
 *    v1 del spec no hacía y por lo que su criterio de éxito era inauditable;
 *  - **aquí** se valida que las dos listas —rutas registradas y `paths` del documento— son la
 *    misma, y que el esquema es lo bastante ESTRICTO como para que esa validación muerda.
 *
 * Ese último punto es el que sostiene la prueba por mutación del estándar de Fase 2: renombrar un
 * campo de `UserResource` deja el test del endpoint en rojo *solo si* el esquema declara
 * `additionalProperties: false` y lista el campo en `required`. Si alguien relajara el esquema, la
 * mutación dejaría de morder y nadie lo notaría — salvo por
 * `test_response_schemas_are_strict_enough_for_a_rename_to_fail`.
 */
class ApiContractTest extends TestCase
{
    /**
     * Campos OPCIONALES a propósito, con su porqué. Exención con nombre, no laxitud: la alternativa
     * —permitir que cualquier esquema declare menos `required` que `properties`— convertiría la
     * guarda en decorativa, que es justo lo que `ModuleBoundariesTest` evita con sus baselines.
     *
     * Cada entrada debe seguir existiendo en el esquema: si el campo desaparece, el test cae y hay
     * que borrar la entrada. La lista solo encoge.
     *
     * @var array<string, list<string>>
     */
    private const OPTIONAL_BY_DESIGN = [
        // Los rótulos de la isla viajan SOLO con la isla como carcasa (`DECISIONES #682`, T3e·2): una instalación
        // con el cajón no los pinta nunca, y mandarlos siempre los pagaría cada página pública.
        'SidebarBoot' => ['isla'],
        // ⚠️⚠️ **El menú de hechos es opcional POR DEFINICIÓN** (F5 · T1, `#631`, `#639`): `/site` publica lo
        // que la instalación ha rellenado y **omite lo demás**, en vez de emitir `"tiktok": ""`. Exigir aquí
        // los campos obligaría a toda instalación a tener TikTok, WhatsApp y una imagen de `og:` — o a que el
        // producto mintiera con cadenas vacías que cada landing tendría que volver a filtrar. Lo que SÍ sigue
        // mordiendo es `additionalProperties: false` en los seis bloques, que es lo que impide que un campo
        // nuevo salga a la API sin pasar por el contrato, y `required` en la raíz: los seis bloques van
        // siempre, aunque lleguen `{}`.
        'SiteFacts.identity' => ['name', 'legal_name', 'nif', 'domain'],
        // ⚠️ `written` es la dirección ya compuesta (`#650`) y falta cuando no hay ninguna línea, igual
        // que las líneas mismas: no se puede escribir lo que no existe.
        'SiteFacts.address' => ['line1', 'line2', 'city', 'maps_url', 'maps_embed_url', 'written'],
        // ⚠️ `topics` NO entra aquí (`#676`): va siempre. Es un vocabulario del PRODUCTO, no un
        // campo que la instalación rellene, así que no puede faltar — y si faltara, una landing no
        // sabría qué asuntos existen y el `in:` del formulario le rechazaría cualquiera que inventase.
        'SiteFacts.contact' => ['email', 'phone', 'whatsapp'],
        'SiteFacts.social' => ['instagram', 'tiktok', 'feed_embed_url', 'google_place_id'],
        'SiteFacts.legal' => ['jurisdiction', 'fiscal_address'],
        'SiteFacts.seo' => ['og_image'],
        // El horario, por el mismo principio y con un motivo más fuerte: **son EXCLUYENTES**. `closes_at`
        // solo tiene sentido con el parque abierto y `opens_at` solo con el parque cerrado; exigir los dos
        // obligaría a emitir uno de los dos mintiendo. Y `opens_at` puede faltar también cerrado: es una
        // instalación sin horario configurado, y ahí lo correcto es no afirmar nada.
        'OpeningNow' => ['closes_at', 'opens_at'],
        // Las claves que la instalación no rellenó de un día especial: la nota y la etiqueta de tarifa. La
        // fecha y si cierra van SIEMPRE, que es lo que hace útil al día.
        'Schedule.special_days.items' => ['opens_at', 'closes_at', 'note', 'rate_label'],
        // El RESUMEN de las normas (`#653`) falta con la tabla vacía: no se resume lo que no existe.
        'Rules' => ['summary'],
        // ❗❗ `plain_weekdays` es opcional con un motivo que NO es «no lo rellenaron» (`#676`): su
        // ausencia significa **«no se puede saber»** —una tarifa especial activa sin días
        // declarados—, y ésa es justo la información que una lista vacía destruiría. Emitirlo
        // siempre obligaría a elegir entre mentir con `[]` o inventar la semana.
        'Prices' => ['plain_weekdays'],
        // Y de una tarifa, los días que reclama: la NORMAL no reclama ninguno —se aplica a lo que
        // sobra—, así que exigirlos obligaría a declararle una semana que no tiene.
        'Prices.rates.items' => ['weekdays'],
        // Y de una norma: el momento —el negocio puede no haberla situado— y los dos textos largos. El
        // nombre va siempre: una norma sin nombre no es una norma.
        'Rules.rules.items' => ['moment', 'description', 'reason'],
        // De una PROMOCIÓN (`#770`): el fin falta en la que no acaba (un regalo permanente) —emitirlo obligaría a
        // inventar una fecha—, y el objetivo lleva la zona O el producto según su `type`, nunca los dos.
        'Promotions.promotions.items' => ['ends_on'],
        'Promotions.promotions.items.target' => ['zone', 'product'],
        // De una OPINIÓN (`#771`): lo que la reseña puede no tener —la línea del autor, su foto, la nota, el día, el
        // enlace (solo las copiadas de Google lo llevan) y la respuesta del parque—. Las listas van siempre.
        'Reviews.reviews.items' => ['author_meta', 'avatar_url', 'rating', 'date', 'url', 'reply'],
        // De una SECCIÓN de servicios: los adornos editoriales y la foto, que una instalación puede no
        // haber escrito. ⚠️ `slug`, `title` y `products` NO entran aquí y es deliberado: el slug es el
        // ancla, una sección sin título no se publica (por eso el título va siempre en lo que viaja), y
        // `products` es la lista —vacía si es de solo-contacto— que dice si la sección vende algo. Si
        // `products` fuera opcional, «ausente» y «vacía» significarían lo mismo y la landing tendría que
        // tratar dos casos para una sola realidad.
        'Services.services.items' => ['accent_word', 'body', 'zone_label', 'image_url', 'specs'],
        // De un JUEGO (`#674`): los adornos editoriales. ⚠️ `zone` y `name` NO entran: la zona es la
        // referencia que hace resoluble al juego, y un juego sin nombre no se publica.
        'Attractions.attractions.items' => ['description', 'age', 'badge', 'image_url', 'video_url'],
        // **El BAR** (`#673`). `bar` falta entero cuando no está publicado —sin nombre no hay página—, y
        // dentro, lo que la instalación puede no haber escrito. ⚠️ `free_entry` es opcional con un motivo
        // más fuerte que «no lo rellenaron»: su ausencia **no significa `false`**. Emitirlo siempre
        // obligaría a elegir un valor por defecto, y las dos opciones mienten sobre la mitad de los bares.
        'Bar' => ['bar'],
        'Bar.bar' => ['lede', 'free_entry', 'venue'],
        // De una imagen: el `alt` —obligatorio en el panel, así que solo falta en filas metidas por SQL—
        // y las dimensiones, que faltan si el fichero no se pudo medir. La `url` va siempre: una imagen
        // sin URL no es una imagen.
        'BarImage' => ['alt', 'width', 'height'],
        'Bar.bar.venue' => ['alt', 'width', 'height', 'caption'],
        // `signed_version` existe SOLO donde hay documento publicado y versionado (hoy `condiciones` y
        // `waiver`): exigirlo obligaría a inventar una versión para la política de cookies.
        // ⚠️ Su `published_at` NO entra aquí: la columna es `NOT NULL` (medido), así que una versión
        // publicada tiene fecha por definición. Declararlo opcional «por si acaso» es una mentira que cada
        // cliente tendría que programar.
        'LegalDocumentSummary' => ['signed_version'],
        // **La FICHA de producto y de zona** (F5 · T6, `#632` P1), por el mismo principio del menú: lo que la
        // instalación no rellenó NO viaja, ni como `""`. Exigir estos campos obligaría a toda instalación a
        // escribir una descripción por zona y a subir una foto por producto para que su API validara.
        // ⚠️ La identidad de la zona —`id`, `slug`, `name`— NO entra aquí: una zona sin nombre no es una zona,
        // y el `slug` es la clave del deep-link.
        // ⚠️ `height` y `age_range` entran en `#676` y son opcionales por el mismo principio: una zona
        // sin restricción de altura no emite un bloque vacío. Dentro de `height`, las dos cifras son
        // excluyentes en la práctica —«a partir de» o «hasta»—, así que exigir las dos obligaría a
        // inventar una; `written` falta solo si no hay ninguna, y entonces el bloque tampoco está.
        'CatalogZoneDetail' => ['description', 'image_url', 'age_range', 'height', 'escort'],
        'CatalogZoneDetail.height' => ['from_cm', 'up_to_cm', 'written'],
        // ⚠️ `escort` entra en 1.26.0 (`#699`, `#761`) por el mismo principio que `height`: una zona sin regla de
        // «con un adulto» no emite un bloque vacío, y dentro las dos cifras son independientes (Kids tiene una, Jump
        // la otra); `written` falta solo si no hay ninguna, y entonces el bloque tampoco está.
        'CatalogZoneDetail.escort' => ['under_age_from_cm', 'below_cm', 'written'],
        // ⚠️ El reparto es asimétrico A PROPÓSITO y por eso las dos entradas dicen cosas distintas: la FOTO va
        // en la lista (un catálogo se recorre mirándolas) y la DESCRIPCIÓN solo en el detalle (es prosa, se lee
        // al abrir). Medido el 19-09: la descripción son ~340 bytes en las 6 que la tienen, sobre un payload de
        // 4.079 bytes con 24 productos.
        // ⚠️ La edad y la duración entran en `#676` y son opcionales porque **el producto puede no
        // declararlas**: medido, de 24 vendibles solo 2 traen edad y 12 duración. Exigirlas obligaría
        // a inventar una edad para una entrada suelta.
        // ⚠️ `cancellation` entra en 1.26.0 (`#699`): el producto puede no publicar su plazo. Dentro del bloque, en
        // cambio, las dos claves van siempre: una cifra sin su frase no se escribe sola sin el defecto de `#660`.
        'CatalogProduct' => ['image_url', 'guest_age_min', 'guest_age_max', 'duration_min', 'cancellation'],
        'CatalogProductDetail' => ['image_url', 'description', 'guest_age_min', 'guest_age_max', 'duration_min', 'cancellation'],
        // **La prueba social** (F5 · T6, `#646`). El sobre puede venir VACÍO —no hay cifra sostenible— y por
        // eso `rating` es opcional; exigirlo obligaría a toda instalación a tener reseñas para que su API
        // validara, y a inventar un `0` cuando no las tiene. ⚠️ Dentro de la cifra, en cambio, solo `url` es
        // opcional: una media sin recuento o sin fuente no se puede publicar —la atribución es obligatoria—.
        'SocialProofFacts' => ['rating'],
        'SocialProofFacts.rating' => ['url'],
        // Y el RESUMEN (`#653`) falta en un documento sin ningún párrafo, por lo mismo que en `Rules`.
        'LegalDocument' => ['signed_version', 'summary'],
        // Una sección puede traer solo titular o solo párrafo: los documentos los escribe una persona en el
        // panel, y hay secciones que son un titular con su lista debajo.
        'LegalDocument.sections.items' => ['h', 'p'],
        // De un producto con precio: la zona —un producto puede no tenerla— y la unidad de venta, que el
        // panel deja vacía cuando no aplica. El id, el nombre y los precios van siempre.
        // ❗ Y la ESCALERA de tramos (`#677`), cuya ausencia AFIRMA algo: que el precio no depende de la
        // cantidad. Emitirla siempre obligaría a una fila única que repite `prices`, y la landing tendría
        // que distinguir «escalera de una fila» de «sin escalera» para una sola realidad.
        'Prices.products.items' => ['zone', 'unit', 'tiers'],
        // El lote del libro de eventos (`#678`): de un evento, `route`, `props` y `occurred_at` son opcionales
        // porque no todo hecho tiene página, propiedades ni reloj (un `call_clicked` no lleva nada), y el
        // sobre `meta` entero porque solo viaja cuando hay algo que decir del lote. Lo que va siempre es lo
        // que hace al evento un evento: su id y su nombre.
        'EventsBatch' => ['meta'],
        'EventsBatch.events.items' => ['route', 'props', 'occurred_at'],
        'EventsBatch.meta' => ['webdriver', 'internal', 'consent'],
        // El sobre de error omite estos dos cuando están vacíos (spec §4.3): un `"fields": {}` en
        // cada 500 sería ruido que todo cliente tendría que aprender a ignorar.
        'Error.error' => ['params', 'fields'],
        // Los campos de paginación solo existen donde hay paginación. La alternativa —emitir
        // `current_page: 1, last_page: 1` en una lista que no pagina— sería fingir una paginación
        // que el endpoint ignora: `?page=2` no haría nada. Que estén o no ES la señal de si se
        // puede paginar; `total` va siempre, para que leerlo no exija saberlo.
        'ListMeta' => ['current_page', 'last_page', 'per_page'],
        // Esquema de PETICIÓN, no de respuesta: un cuerpo sí puede tener campos legítimamente
        // opcionales. `remember` por defecto es `false`, y exigirlo obligaría a todo cliente a
        // enviarlo. Lo que sigue mordiendo aquí es `additionalProperties: false`, que es lo que
        // impide colar un campo que el servidor ignoraría en silencio.
        'LoginRequest' => ['remember'],
        // Cuerpo de PETICIÓN, y aquí la opcionalidad es CONDICIONAL: `current_password` solo hace
        // falta si `email` cambia (tanda 2 · paso 7). Exigirla siempre obligaría a reconfirmar la
        // contraseña para corregir una errata en el teléfono —que no defiende nada y hace que el
        // titular acabe evitando la pantalla—, y OpenAPI 3.0 no sabe expresar «obligatorio si otro
        // campo cambia»: quien lo decide es el servidor, que la exige cuando toca.
        'ProfileUpdateRequest' => ['current_password'],
        // Cuerpo de PETICIÓN, y la opcionalidad vuelve a ser CONDICIONAL (`#349`): las condiciones
        // solo se envían si esta instalación las publica **y** este titular no tiene aceptada la
        // versión vigente; el teléfono, solo si la cuenta no lo tiene. Exigir los dos siempre
        // convertiría en 422 a quien ya aceptó y ya dio su teléfono — o sea, a casi todo el mundo.
        // OpenAPI 3.0 no sabe decir «obligatorio si el servidor dice que falta», así que lo decide el
        // servidor: `account-context.terms_pending` es la pista y el 422 por campo es la autoridad.
        'CreateOrderRequest' => ['accept_terms', 'phone'],
        // `#441` · MISMO patrón, tercera vez: cuerpo de PETICIÓN con opcionalidad CONDICIONAL. La
        // exención del menor solo se acepta **si esta instalación la gestiona dentro** (`waiver.mode
        // = interno`) **y** hay una versión publicada; en `externo`, o sin publicar, no hay nada que
        // aceptar y exigir los dos campos dejaría a esa instalación **sin poder declarar un menor**.
        // ⚠️ Van los DOS y no uno: la casilla es el acto afirmativo del art. 7.1 y el identificador
        // solo dice qué texto se sirvió — un `document_id` suelto no prueba que nadie aceptara nada.
        // Quien lo decide es el servidor: 422 sobre `accept_waiver` cuando falta, 409 si el texto se
        // republicó. Lo que sigue mordiendo aquí es `additionalProperties: false`.
        'DependentCreateRequest' => ['accept_waiver', 'waiver_document_id'],
        // Mismo caso: cuerpo de PETICIÓN. `context` tiene valor por defecto, y los dos anti-bot solo
        // los envía quien los tiene: el señuelo `website` lo rellenan los bots y `turnstile_token`
        // solo existe si la instalación configuró claves. Obligarlos convertiría en 422 a un cliente
        // correcto. Y desde Fase 6 la casilla del waiver, que es opcional por definición (desmarcada
        // por defecto): quien la marca debe decir qué texto leyó, y eso lo exige el servidor.
        // ⚠️ `marketing` estuvo aquí hasta la T8·c (`#350`) y ya no está en el esquema: el alta no lo
        // pide, lo pide el interruptor de «Mi cuenta → Privacidad» (`PUT /me/marketing`).
        'RegisterRequest' => ['context', 'website', 'turnstile_token', 'accept_waiver', 'waiver_document_id'],
        // Cuerpo de PETICIÓN del alta con Google (`specs/auth-con-google.md` §7). Las dos claves del
        // descargo son opcionales por la MISMA razón que arriba y una más: en una instalación en modo
        // externo —o sin versión publicada— **no hay texto que aceptar**, así que exigirlas convertiría
        // en 422 al único cliente correcto que puede existir allí. Quien decide cuándo son obligatorias
        // es el servidor, que las exige justo cuando `GET /legal/waiver` sirve un documento.
        // ⚠️ Lo que sigue mordiendo es `additionalProperties: false`: es lo que impide colar aquí un
        // `email` que el servidor ignoraría en silencio — y ese silencio sería la vulnerabilidad.
        'GoogleSignupRequest' => ['accept_waiver', 'waiver_document_id'],
        // `#574` · cuerpo de PETICIÓN de la INVITACIÓN DIGITAL, y aquí la opcionalidad es **el
        // diseño de la feature**, no una concesión: `[DECIDIDO owner]` D10 y §2.1 de
        // `specs/celebracion-e-invitacion.md` dicen que **el padre contesta con un nombre y un
        // gesto**, y que «el resto es opcional y dice quién lo hace si no». Exigir `companion`
        // obligaría a todo padre a declarar si se queda en el parque para poder decir «sí», y exigir
        // `guest_data` le pediría las alergias de su hijo a quien solo quiere confirmar que viene —
        // los dos se ofrecen DESPUÉS, con el recibo de dos horas (§4.5·6).
        // ⚠️ Lo que sigue mordiendo aquí es `additionalProperties: false`: es lo que impide colar un
        // campo que el servidor ignoraría en silencio, y ese silencio sí sería el agujero.
        'InvitationReplyRequest' => ['companion', 'guest_data'],
        // Cuerpo de PETICIÓN otra vez, y por el mismo motivo. Una línea de cesta sin complementos y
        // sin datos de evento es lo normal —una entrada suelta—, así que exigir los dos campos
        // convertiría en 422 la petición más frecuente de todas. `additionalProperties: false`
        // sigue impidiendo colar un campo que el servidor ignoraría en silencio.
        // Y desde Fase 6 · menores a cargo (tanda 4) los menores para los que son las entradas: la
        // línea sin menores es la normal, y exigir el campo obligaría a mandar `dependent_ids: []` en
        // cada línea de cada presupuesto. Solo `POST /orders` lo lee; los demás lo validan e ignoran.
        // Y desde la T6 del justificante (`specs/waiver-por-reserva.md` §12.2) el
        // `guardian_authorization`, por lo mismo y con un motivo propio: la línea SIN menores
        // invitados es la abrumadora mayoría —exigirlo metería `false` en cada línea de cada
        // presupuesto— y **ausente ya significa «no» en el servidor**, que es la lectura que hace
        // todo cliente que no conozca el campo. Lo que sigue mordiendo es `additionalProperties:
        // false`, que impide colar una variante mal escrita que el servidor ignoraría en silencio.
        'CartLine' => ['event_data', 'addons', 'dependent_ids', 'guardian_authorization'],
        // Y otro cuerpo de PETICIÓN: en la disponibilidad la cesta es opcional de verdad —la
        // primera compra empieza sin nada elegido— y ausente equivale a vacía. Exigirla obligaría a
        // todo cliente a mandar `items: []` para preguntar por unas horas.
        'AvailabilityTimesRequest' => ['items'],
        // Y el mismo caso otra vez, por el mismo motivo: al validar una línea candidata, la cesta
        // que se lleva es la que YA retiene cupo, y la primera línea de una compra se añade sobre
        // una cesta vacía. Exigirla obligaría a mandar `items: []` para preguntar por la primera.
        // Lo que sigue mordiendo aquí es `line`, que sí es obligatoria.
        'CartLineCheckRequest' => ['items'],
        // Cuerpo de PETICIÓN, y los cuatro son opcionales de verdad. `date`/`time` van SOLO para
        // tarificar la línea —los complementos no dependen del día reservado—, así que exigirlos
        // obligaría a haber elegido franja para poder mirar los complementos. `addons` y `choices`
        // faltan en la PRIMERA llamada a propósito: sin ellos la respuesta trae el estado inicial
        // con el elegido por defecto de cada grupo. Lo que sigue mordiendo es `quantity`, que es
        // obligatoria porque decide la cantidad de los complementos por invitado.
        'AddonSelectionRequest' => ['date', 'time', 'addons', 'choices'],
        // Cuerpo de PETICIÓN, y aquí los DOS campos son opcionales de verdad: **la ausencia de una
        // clave significa «no la toques»**, así que un cliente puede guardar las fichas sin reenviar
        // las observaciones generales, o al revés. Lo que sigue mordiendo es
        // `additionalProperties: false`, que impide colar un campo que el servidor ignoraría en silencio.
        //
        // ⚠️⚠️ **Este porqué DECÍA lo mismo y era MENTIRA hasta la T0 de
        // `specs/complementos-post-reserva.md` (`#413`, 2026-09-03)**: hablaba de «guardar a trozos»
        // mientras el controlador hacía `$validated['guests'] ?? []`, y `[]` significa VACIAR —
        // medido, un `PUT` con solo `general` borraba los nombres y las edades de los ocho niños de
        // una reserva real, con un 200 por respuesta. *Un campo opcional cuya ausencia destruye no
        // es un campo opcional.* Hoy la ausencia se decide con `array_key_exists` en
        // `AuthorizesGuestForm::submittedGuestFormArray()` y el dominio recibe `null`.
        // ▶ `addons` sigue la MISMA regla (T3 de `#413`): ausente = «no toques los extras». Y
        // `expected_version` es opcional porque el testigo es una DEFENSA que el cliente elige usar:
        // exigirlo rompería a quien guarda sin haber leído antes, y su ausencia no destruye nada —
        // solo renuncia a que el servidor le avise de que el parque movió algo entre medias.
        // ▶ Y `guest_count` entra con la MISMA regla (`#444`), donde importa el doble: un valor por
        // defecto convertiría una petición que **no habla de invitados** en un cambio de aforo y de
        // dinero que nadie pidió. Ausente = no se toca; lo decide `submittedGuestCount()` con
        // `array_key_exists`, igual que sus tres hermanas.
        // ▶ `adopt` (T4·6, `#578`) entra con la MISMA regla, y aquí la ausencia es especialmente
        // obvia: un `PUT` que solo corrige una alergia no adopta ninguna respuesta de la invitación,
        // y exigir la clave obligaría a todo cliente a mandar `[]` para decir «nada». La lista tiene
        // además una propiedad que conviene no perder de vista: va **fuera de `guests`** porque cada
        // fila es un mapa ABIERTO cuyas claves inventa cada instalación, y una marca metida dentro
        // chocaría el día que alguien llamara a una columna igual (§1.3·13, §7.2·R3).
        'GuestFormRequest' => ['guests', 'general', 'addons', 'expected_version', 'guest_count', 'adopt'],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $contract = null;

    /** @return array<string, mixed> */
    private function contract(): array
    {
        return self::$contract ??= Yaml::parseFile(
            base_path((string) config('api.openapi.directory').'/'.(string) config('api.openapi.file'))
        );
    }

    /**
     * **Los tipos de campo del EVENTO son los mismos en el dominio y en el contrato.**
     *
     * ⚠️⚠️ Nace de un defecto medido (`specs/cumple-mixto.md` §17.2·4): el dominio estrenó un cuarto
     * tipo de campo —la EDAD del invitado— y el saneo lo aceptaba en los DOS esquemas, aunque el
     * `Select` del panel solo lo ofreciera en uno. Forzado, persistía y salía por
     * `GET /catalog/products/{id}` contra este mismo `enum`, que además va con
     * `additionalProperties: false`: para un cliente estricto, una respuesta inválida.
     *
     * ▶ Esta guarda cierra el hueco por los DOS lados: añadir un tipo al dominio sin declararlo en el
     * contrato la pone roja, y declararlo en el contrato sin que el dominio lo acepte, también. Es lo
     * único que impide que la próxima vez pase igual — el defecto no fue no saberlo, fue que **nada
     * lo comprobaba**.
     */
    public function test_the_event_field_types_are_the_same_in_the_domain_and_in_the_contract(): void
    {
        $enContrato = $this->contract()['components']['schemas']['CatalogEventField']['properties']['type']['enum'] ?? null;

        $this->assertIsArray($enContrato, 'el contrato ya no cierra los tipos de `CatalogEventField` con un `enum`');

        $delDominio = TicketType::EVENT_FIELD_TYPES;
        sort($enContrato);
        sort($delDominio);

        $this->assertSame(
            $delDominio, $enContrato,
            "los tipos de campo del EVENTO no coinciden.\n".
            '  dominio  (TicketType::EVENT_FIELD_TYPES): '.implode(', ', $delDominio)."\n".
            '  contrato (CatalogEventField.type.enum):   '.implode(', ', $enContrato)."\n".
            '▶ El contrato manda: o el tipo entra en los dos, o no entra en ninguno.',
        );

        // Y el CONTROL: que la lista por invitado sea distinta, porque si algún día se fundieran las
        // dos este caso pasaría en verde sin vigilar nada.
        $this->assertNotSame(
            TicketType::EVENT_FIELD_TYPES, TicketType::GUEST_FIELD_TYPES,
            'si los dos esquemas aceptan lo mismo, esta guarda ya no distingue nada',
        );
    }

    /** El documento tiene que existir y parsear: sin esto, todo lo demás pasaría en vacío. */
    public function test_the_contract_document_parses_and_declares_paths(): void
    {
        $contract = $this->contract();

        $this->assertSame('3.0.3', $contract['openapi'] ?? null, 'Spectator valida contra OpenAPI 3.0');
        $this->assertNotEmpty($contract['paths'] ?? [], 'el documento no declara ningún path');
    }

    /** La versión vive en la URL (spec §4.1) y el documento debe decir la misma que el router. */
    public function test_the_contract_server_matches_the_route_prefix(): void
    {
        $this->assertSame(
            '/'.ApiSurface::PREFIX,
            $this->contract()['servers'][0]['url'] ?? null,
            'el `servers.url` del documento no coincide con `ApiSurface::PREFIX`'
        );
    }

    /** Ruta publicada que el documento no describe = contrato incompleto. */
    public function test_every_registered_route_is_declared_in_the_contract(): void
    {
        $missing = array_diff($this->registeredOperations(), $this->contractOperations());

        $this->assertSame(
            [], array_values($missing),
            "Rutas de la API sin describir en openapi/v1.yaml:\n  ".implode("\n  ", $missing)
        );
    }

    /**
     * Y al revés: una operación documentada sin ruta es una promesa que el cliente móvil se
     * creería. Esta dirección es la que suele quedarse sin vigilar.
     */
    public function test_every_contract_operation_is_a_registered_route(): void
    {
        $missing = array_diff($this->contractOperations(), $this->registeredOperations());

        $this->assertSame(
            [], array_values($missing),
            "Operaciones documentadas que ninguna ruta sirve:\n  ".implode("\n  ", $missing)
        );
    }

    /**
     * Los `code` son el contrato que el cliente PROGRAMA (spec §4.3). El documento y el enum tienen
     * que enumerar exactamente los mismos: uno nuevo en PHP sin documentar es un código que el
     * cliente no espera; uno documentado sin emisor es una rama muerta en el cliente.
     */
    public function test_the_error_codes_of_the_contract_match_the_enum(): void
    {
        $documented = $this->contract()['components']['schemas']['Error']['properties']['error']['properties']['code']['enum'] ?? [];
        $implemented = array_map(static fn (ApiErrorCode $case): string => $case->value, ApiErrorCode::cases());

        sort($documented);
        sort($implemented);

        $this->assertSame($implemented, $documented, 'ApiErrorCode y el enum del documento han divergido');
    }

    /**
     * La estrictez del esquema ES la prueba por mutación: sin `additionalProperties: false` un
     * campo de más pasaría inadvertido, y sin `required` completo un campo renombrado también.
     * Con las dos, renombrar `email` a `mail` en `UserResource` pone en rojo el test del endpoint.
     */
    public function test_response_schemas_are_strict_enough_for_a_rename_to_fail(): void
    {
        foreach ($this->contract()['components']['schemas'] ?? [] as $name => $schema) {
            $this->assertObjectSchemaIsStrict($name, $schema);
        }
    }

    /**
     * **Y la comprobación de arriba MIRA DENTRO DE LAS LISTAS** (2026-09-19).
     *
     * ⚠️⚠️ Hasta esta fecha no lo hacía: un esquema `type: array` no es `object`, así que salía por el
     * primer `return` y **el objeto de dentro no se miraba**. Un campo de más en cada elemento de una lista
     * pasaba el contrato entero. Se vio al añadir el menú de hechos, cuyos esquemas son listas de objetos.
     *
     * ▶ Y hace falta ESTE caso, no una mutación: quitar el descenso hace la comprobación más PERMISIVA, y
     * una comprobación más permisiva sigue pasando. Lo único que la caza es ejercerla con un esquema laxo.
     */
    public function test_the_strictness_walk_looks_inside_lists(): void
    {
        $listaLaxa = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => true,   // ← lo que no se puede colar
                'properties' => ['a' => ['type' => 'string']],
                'required' => ['a'],
            ],
        ];

        try {
            $this->assertObjectSchemaIsStrict('Prueba', $listaLaxa);
            $this->fail('la comprobación no miró dentro de la lista: un campo de más se colaría');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('Prueba.items', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function assertObjectSchemaIsStrict(string $name, array $schema): void
    {
        // Una LISTA no tiene campos, pero el objeto de dentro sí: se baja a él antes de rendirse. Ver el
        // aviso del final de este método — era el punto ciego de la guarda.
        if (($schema['type'] ?? null) === 'array' && is_array($schema['items'] ?? null)) {
            $this->assertObjectSchemaIsStrict("{$name}.items", $schema['items']);

            return;
        }

        if (($schema['type'] ?? null) !== 'object' || ! isset($schema['properties'])) {
            return;
        }

        $this->assertFalse(
            $schema['additionalProperties'] ?? null,
            "El esquema «{$name}» admite campos no declarados: un campo de más pasaría el test"
        );

        $optional = self::OPTIONAL_BY_DESIGN[$name] ?? [];

        foreach ($optional as $field) {
            $this->assertArrayHasKey(
                $field, $schema['properties'],
                "OPTIONAL_BY_DESIGN: «{$name}.{$field}» ya no existe en el esquema — quita la entrada"
            );
        }

        $this->assertSame(
            array_values(array_diff(array_keys($schema['properties']), $optional)),
            array_values($schema['required'] ?? []),
            "El esquema «{$name}» no exige todos sus campos: omitir uno no rompería el test. ".
            'Si es opcional de verdad, decláralo en OPTIONAL_BY_DESIGN con su porqué.'
        );

        foreach ($schema['properties'] as $property => $subSchema) {
            if (is_array($subSchema)) {
                $this->assertObjectSchemaIsStrict("{$name}.{$property}", $subSchema);
            }
        }

        // ⚠️⚠️ **El punto ciego que tenía esta guarda hasta el 2026-09-19**: un esquema `type: array` salía
        // por el `return` de arriba —no es `object`— y con él se iba sin mirar **el objeto de dentro**. Un
        // campo de más en cada elemento de una lista pasaba el contrato entero sin que nadie lo viera, que
        // es justo lo que este fichero existe para impedir. Se vio al añadir el menú de hechos: `Schedule` y
        // `Rules` son listas de objetos y se habrían colado sin declarar un solo `required`.
    }

    /**
     * ⚠️ **Y la reserva próxima del contexto de cuenta, por lo MISMO** (2026-08-23,
     * `specs/account-context-vue.md` §4.4).
     *
     * `AccountContext.next_reservation` es anulable y tiene que ser exactamente lo que publica
     * `/me/reservations`, así que se probó primero con `allOf: [$ref] + nullable`. **Falla igual que
     * en la zona**: medido, una reserva presente da «The data (object) must match the type: null».
     * O sea que el hallazgo de `DECISIONES #27` no era del catálogo — es del validador— y se resuelve
     * igual: objeto entero escrito, con su `nullable`, y esta guarda como precio.
     *
     * ▶ **En tiempo de EJECUCIÓN lo cubre otra cosa distinta**, y las dos hacen falta:
     * `MeAccountContextTest::test_the_next_reservation_is_byte_for_byte_what_me_reservations_publishes`
     * compara las dos RESPUESTAS reales. Aquélla caza que el código divergiera; ésta, que divergiera
     * el CONTRATO —que en este repo manda sobre el código— dejando pasar una respuesta que el otro
     * endpoint no aceptaría.
     */
    public function test_the_inlined_upcoming_reservation_says_the_same_as_the_component(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $component = $schemas['UpcomingReservation'] ?? null;

        $this->assertIsArray($component, 'falta el componente `UpcomingReservation`');

        $inline = $schemas['AccountContext']['properties']['next_reservation'] ?? null;

        $this->assertIsArray($inline, '`AccountContext` ya no declara la reserva próxima inline');
        $this->assertTrue(
            $inline['nullable'] ?? false,
            'la reserva próxima tiene que ser anulable: un cliente sin reservas es el caso normal'
        );
        $this->assertSame(
            $component['properties'],
            $inline['properties'] ?? null,
            '`AccountContext.next_reservation` ha divergido de `UpcomingReservation`: sus propiedades '.
            'ya no coinciden, así que los dos endpoints han dejado de prometer lo mismo'
        );
        $this->assertSame(
            $component['required'],
            $inline['required'] ?? null,
            '`AccountContext.next_reservation` ha divergido de `UpcomingReservation` en sus campos obligatorios'
        );
    }

    /**
     * ⚠️ **Y la invitación a extras del contexto de cuenta, por lo MISMO** (D15 de
     * `specs/complementos-post-reserva.md`).
     *
     * `AccountContext.extras_invite` es anulable y tiene exactamente la forma de un elemento de
     * `pending_forms`. Se probó primero con `allOf: [$ref] + nullable` y **falló igual que las otras
     * dos veces**: medido, un contexto sin invitación da «The data (null) must match the type:
     * object». Objeto entero escrito, con su `nullable`, y esta guarda como precio.
     */
    public function test_the_inlined_extras_invite_says_the_same_as_the_component(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $component = $schemas['PendingGuestForm'] ?? null;

        $this->assertIsArray($component, 'falta el componente `PendingGuestForm`');

        $inline = $schemas['AccountContext']['properties']['extras_invite'] ?? null;

        $this->assertIsArray($inline, '`AccountContext` ya no declara la invitación a extras inline');
        $this->assertTrue(
            $inline['nullable'] ?? false,
            'la invitación tiene que ser anulable: no tener ninguna es el caso normal'
        );
        $this->assertSame(
            array_keys($component['properties']),
            array_keys($inline['properties'] ?? []),
            '`AccountContext.extras_invite` ha divergido de `PendingGuestForm`: ya no tienen los mismos campos'
        );
        $this->assertSame(
            $component['required'],
            $inline['required'] ?? null,
            '`AccountContext.extras_invite` ha divergido de `PendingGuestForm` en sus campos obligatorios'
        );
    }

    /**
     * ⚠️ **Y la INVITACIÓN DIGITAL del post-form, por lo MISMO** (T4·6, `DECISIONES #578`).
     *
     * `GuestForm.invitation` es anulable —casi ningún producto la ofrece— y tiene exactamente la
     * forma del componente `Invitation`. Tercera vez que se paga el mismo peaje del validador, y la
     * tercera con la misma salida: objeto escrito entero, con su `nullable`, y esta guarda de precio.
     *
     * ▶ La copia inline **no lleva las descripciones** del componente: lo que tiene que coincidir es
     * la FORMA, y obligar a copiar tres párrafos de prosa a mano garantizaría que divergieran. Por eso
     * se comparan los NOMBRES de las propiedades y los `required`, no los valores enteros.
     */
    public function test_the_inlined_invitation_says_the_same_as_the_component(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $component = $schemas['Invitation'] ?? null;

        $this->assertIsArray($component, 'falta el componente `Invitation`');

        $inline = $schemas['GuestForm']['properties']['invitation'] ?? null;

        $this->assertIsArray($inline, '`GuestForm` ya no declara la invitación inline');
        $this->assertTrue(
            $inline['nullable'] ?? false,
            'la invitación tiene que ser anulable: casi ningún producto la ofrece'
        );
        $this->assertFalse(
            $inline['additionalProperties'] ?? true,
            'la copia perdió `additionalProperties: false` y ya no caza un campo colado'
        );
        $this->assertSame(
            array_keys($component['properties']),
            array_keys($inline['properties'] ?? []),
            '`GuestForm.invitation` ha divergido de `Invitation`: sus propiedades ya no coinciden, '.
            'así que el post-form y el endpoint de personalizar han dejado de prometer lo mismo'
        );
        $this->assertSame(
            $component['required'],
            $inline['required'] ?? null,
            '`GuestForm.invitation` ha divergido de `Invitation` en sus campos obligatorios'
        );
    }

    /**
     * La zona anidada en un producto está escrita INLINE y no como `$ref`, y esta guarda es el
     * precio de esa decisión.
     *
     * El motivo (Fase 3 · paso 1b): en OpenAPI 3.0 un `$ref` no admite `nullable` a su lado, y la
     * forma canónica de sortearlo —`allOf: [$ref]` con `nullable: true`— **no funciona** con el
     * validador de Spectator: se midió, y con ella una zona nula falla («The data (null) must match
     * the type: object») y una zona presente también («The data (object) must match the type:
     * null»). La única forma que valida las dos es el objeto escrito entero, con su `nullable`.
     *
     * Copiar un esquema abre la puerta a que las copias se separen, así que aquí se comprueba que
     * dicen exactamente lo mismo que el componente `CatalogZone`. Si mañana el validador soporta la
     * forma canónica, esto se borra junto con las copias.
     */
    public function test_the_inlined_zone_schemas_say_the_same_as_the_component(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $component = $schemas['CatalogZone'] ?? null;

        $this->assertIsArray($component, 'falta el componente `CatalogZone`');

        foreach (['CatalogProduct', 'CatalogProductDetail'] as $owner) {
            $inline = $schemas[$owner]['properties']['zone'] ?? null;

            $this->assertIsArray($inline, "«{$owner}» ya no declara la zona inline");
            $this->assertTrue(
                $inline['nullable'] ?? false,
                "«{$owner}.zone» tiene que ser anulable: hay productos sin zona"
            );
            $this->assertSame(
                $component['properties'],
                $inline['properties'] ?? null,
                "«{$owner}.zone» ha divergido de `CatalogZone`: sus propiedades ya no coinciden"
            );
            $this->assertSame(
                $component['required'],
                $inline['required'] ?? null,
                "«{$owner}.zone» ha divergido de `CatalogZone`: sus campos obligatorios ya no coinciden"
            );
        }

        // Y la TERCERA copia, que no está anidada bajo `zone` sino APLANADA: la ficha de
        // `GET /catalog/zones` (contrato 1.8.0). Aquí la identidad convive con `description` e
        // `image_url`, así que no se comparan los mapas enteros —serían distintos a propósito— sino
        // que se exige que los tres campos de la identidad digan LO MISMO, letra por letra, y que
        // sigan siendo los obligatorios. Sin esto, la zona anidada en un producto y la de su propia
        // lista podrían acabar describiendo el `slug` de dos maneras.
        $ficha = $schemas['CatalogZoneDetail'] ?? null;

        $this->assertIsArray($ficha, 'falta el componente `CatalogZoneDetail`');

        foreach (array_keys($component['properties']) as $campo) {
            $this->assertSame(
                $component['properties'][$campo],
                $ficha['properties'][$campo] ?? null,
                "«CatalogZoneDetail.{$campo}» ha divergido de `CatalogZone`"
            );
        }

        $this->assertSame(
            $component['required'],
            $ficha['required'] ?? null,
            '`CatalogZoneDetail` tiene que exigir exactamente la identidad de `CatalogZone`: '.
            'lo que añade la ficha es opcional por diseño'
        );
    }

    /**
     * **La foto del LOCAL dice lo mismo que una imagen de la carta** (`#673`).
     *
     * `Bar.venue` es una copia INLINE de `BarImage` con un `caption` de más, y es inline por la misma
     * razón de siempre en esta casa: en OpenAPI 3.0 no se puede componer un `$ref` con una propiedad
     * extra bajo `additionalProperties: false` sin que el validador deje de morder (`#27`, y ya van
     * cuatro). La salida acordada es copia inline **más guarda de divergencia**, que es ésta.
     *
     * Sin ella, el día que `BarImage` gane un campo —o que alguien describa `width` de otra manera— la
     * carta y la foto del local empezarían a contarse distintas, y quien pinte las dos tendría que
     * programar dos formas de lo mismo.
     */
    public function test_the_inlined_venue_photo_says_the_same_as_a_bar_image(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $imagen = $schemas['BarImage'] ?? null;
        $local = $schemas['Bar']['properties']['bar']['properties']['venue'] ?? null;

        $this->assertIsArray($imagen, 'falta el componente `BarImage`');
        $this->assertIsArray($local, '`Bar.bar.venue` ya no se declara inline');

        foreach (array_keys($imagen['properties']) as $campo) {
            $this->assertArrayHasKey(
                $campo,
                $local['properties'] ?? [],
                "«Bar.venue» perdió «{$campo}», que `BarImage` sí declara"
            );
            $this->assertSame(
                $imagen['properties'][$campo]['type'] ?? null,
                $local['properties'][$campo]['type'] ?? null,
                "«Bar.venue.{$campo}» ha divergido de `BarImage`: ya no es del mismo tipo"
            );
        }

        $this->assertSame(
            $imagen['required'],
            $local['required'] ?? null,
            '`Bar.venue` tiene que exigir lo mismo que `BarImage`: lo que añade —el pie— es opcional'
        );

        $this->assertSame(
            ['caption'],
            array_values(array_diff(array_keys($local['properties']), array_keys($imagen['properties']))),
            '`Bar.venue` solo puede añadir el pie a `BarImage`; si añade más, hay que decidir si es de las dos'
        );
    }

    /**
     * Operaciones REGISTRADAS, como `GET /me`. Se identifican por el nombre `api.v1.*` y no por el
     * path: es lo que permite añadir rutas fuera del contrato (si alguna vez hiciera falta) sin
     * que la guarda se vuelva adivinatoria.
     *
     * @return list<string>
     */
    private function registeredOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'api.v1.')) {
                continue;
            }

            $path = '/'.ltrim(mb_substr($route->uri(), mb_strlen(ApiSurface::PREFIX)), '/');

            foreach ($route->methods() as $method) {
                // HEAD lo añade Laravel solo por cada GET; OpenAPI no lo documenta aparte.
                if ($method === 'HEAD') {
                    continue;
                }

                $operations[] = strtoupper($method).' '.$path;
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return list<string> */
    private function contractOperations(): array
    {
        $operations = [];

        foreach ($this->contract()['paths'] ?? [] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = strtoupper((string) $method).' '.$path;
            }
        }

        sort($operations);

        return $operations;
    }
}
