# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#707`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-19.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-19, cierre de la sesión)

- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): el armazón, el catálogo, el día y la
  hora, la cesta, pagar con sus cuatro desenlaces, las nueve de la cuenta y el suelo táctil.
- **`celebracion-e-invitacion.md`**: T1 (`#570`) y T2 (`#571`) desplegadas el 16-09 (octavo despliegue).
  **T3 (`#572`, la piel del justificante) y T4·1–T4·4 (`#573`→`#576`) ESTÁN EN PRODUCCIÓN** desde el
  18-09 a las 07:23 (v1.1.0 = `3547de9f`, noveno despliegue, parque cerrado; lo desplegó plataforma).
  La migración `create_party_invitations` quedó aplicada (118 ms) y **los dos interruptores, apagados**.
  Con ello **el defecto de la barra de firmar de 401 px ya no está vivo**. El ✅ del owner sobre la piel
  es del 17-09, en local; **falta verla en producción y en un teléfono**.
- **T4 · la invitación digital, CERRADA en sus seis unidades** (`#573`→`#578`). ⚠️ **En producción solo
  van T4·1–T4·4** (v1.1.0, interruptores apagados): **`#577` (RGPD) y `#578` (la API) están en el árbol
  SIN DESPLEGAR**, como toda la T5. El detalle vive en la spec §10.4; lo que hay que recordar al
  tocarla: el **lock de UNA fila** de `PartyInvitations`, verificado sobre InnoDB (16 padres → entra 1, y
  sin el lock entran 16) · el guard de `TicketType::saving()` y `show_in_invitation` por las CUATRO
  puertas del pivote, con `OrderCreator` intacto porque **es una oferta y no un permiso** · el cuarto
  sumando del suelo de `#444` y la **excepción del firmador**, sin la cual el padre que dijo «sí» no
  podría firmar · y el RGPD, donde el justificante **se conserva** y solo pierde el puntero.
  Arneses 9/9 · 13/13 · 11/11 · 12/12 · 5/5+1 declarado · 14/14.
  ⚠️ El botón de anular el enlace lo pinta la T6 (§4.8).
- **T5 · la página pública, LAS CINCO UNIDADES en el árbol** (`#701`→`#706`), **sin desplegar**; las
  tres primeras vistas en vivo por el owner el 18-09:
  - **T5·1→T5·3 (`#701`→`#703`)**: la ruta `/invitacion/{token}` con los tres temas —con ella
    `Invitation.url` se rellenó sola—, contestar con su aviso de privacidad, y el **recibo de 2 horas**
    con G2/G3 y el salto al justificante **atado a la respuesta**. Detalle en la spec §10.5.
    ⚠️ Lo que enseñaron: el owner cazó «Dónde» y el menú en TEXTO PLANO (hoy `.gf-extras__list`), y
    pegar parámetros a una URL ya firmada **la invalida** — viajan DENTRO.
  - **T5·4 · compartir y calendario (`#705`)**: nace **`Platform\Services\CalendarFile`** (el `.ics` como
    mecanismo genérico), su ruta con el mismo portero y 404 que la página, el bloque «Añadir al
    calendario» **sin una línea de JS** y la vista previa `og:*` con **solo** nombre, edad, día, hora y
    negocio; `focused-layout` gana un hueco de cabecera **vacío por defecto**. Arnés **8/8**.
    ❗ **El defecto lo encontró el fichero SERVIDO, no el test**: el `VTIMEZONE` declaraba observancias
    que entraban en el desfase del que venían y el unitario miraba el `TO`. Hay guarda del PAR.
  - **T5·5 · el flujo firmar ↔ invitación (`#704`)**: **C** (el recibo ya no ofrece firmar a quien
    firmó — contrato nuevo `SignedInvitationReplies`, que implementa `GuardianPlaces` y responde **por
    la ATADURA de `#576`, no por el nombre**), **D** (tras firmar se vuelve a SU recibo) y **E, un
    CUARTO defecto que no estaba en la spec**: la vuelta de un formulario RECHAZADO iba sin los extras
    firmados, así que el segundo intento **cobraba plaza** y con la lista llena acababa en «no quedan
    plazas». Lo destapó **caminar la pantalla**. Arnés **7/7**.
- ✅ **§10.6·A, CERRADA por el owner el 18-09 (`#706`)**: **dos campos** (`#236` en pie, sin migración) y
  **el prerrelleno del menor se retira** — la hoja enseña lo que escribió y lo reparte él. Las dos
  alternativas que propuso se midieron y se cayeron: partir el nombre recorta «María del Carmen» a
  «María» en un documento firmado, y pedir solo el nombre colapsa **dos «Martina» de una clase en una**
  (el anfitrión ve «vienen 1»). ▶ La prueba la dio él: firmó y quedó «Hugo Ruiz Pla» + «DANAILOV».
  ⚠️ La medición entera está en spec §10.6·A por si se reabre (unificar no habría reescrito ninguna
  firma ni cambiado ninguna clave, y `#236` no era el precedente que la spec decía).
- **REVISIÓN ADVERSARIAL de la T4 (`#579`)**, con permiso del owner (31 agentes): dos defectos reales
  arreglados —la adopción marcaba con la clave del PADRE, y un «sí» levantaba el tope N veces— más dos
  guardas frágiles. Arnés **16/16**; los diez puntos sin tocar, en spec §10.4.7·B.
- **`#700` · la lista completa deja de rechazar**: era un **oráculo de pertenencia**, y `REASON_FULL` está
  fuera del dominio y del contrato. ⚠️ El verificador fuerza **16 «sí» del MISMO niño** y exige una plaza;
  visto fallar sin el lock.
- ✅ **Cerrado**: la barra de firmar de 401 px (rota en producción desde el 16-09) la arregló la T3 y
  **salió en v1.1.0**. Queda mirarla allí, que es otra cosa que darla por buena.
- ✅ **`#707` (19-09) · el defecto VIVO de `DependentsZone.vue`**, que levantó plataforma: `addBtn` se
  usaba sin declarar, así que plegar el alta lanzaba `addBtn is not defined` y **el foco caía al
  `<body>`** —reproducido en navegador antes de tocarlo, y la promesa de `#217` llevaba rota desde que se
  escribió—. Declararlo pasó el componente de 40 a 41 líneas y el gate `CE-6` paró el commit: **no se
  subió el techo**, bajó `signDependent()` al módulo plano, donde estrena tres casos de `node --test`.
  `FROZEN_JS_ERRORS` **12 → 10** (congelaba dos errores) · sonda nueva `scripts/sonda-foco-cuenta.mjs`.
- La capa de agente: el plugin `jumpweb-agente` se instaló aquí el 17-09 y **se actualizó a `07076ac` el
  19-09**, con los arreglos del mapa de frases. Larastan entró con `composer install` (faltaba tras `#625`).
- Pendiente del ojo del owner, de antes: «Guardar» en el secundario (`#539`) y no en tinta.

## Por dónde retomar, en orden

1. ❗ **LA TAREA: la T6, «el aterrizaje», empezando por la T6·1** (spec **§10.7**, que ya trae el reparto
   en seis unidades; el owner lo acordó el 19-09 y **decidió hacer la T6 ANTES que la T7**, contra el
   orden de la spec, porque **es la que ENCIENDE la feature**: hasta que el anfitrión pueda ver y adoptar
   lo que contestan los padres, la invitación no puede encenderse en producción).
   ▶ **T6·1 = el bloque de la invitación en el post-form** (§4.7): compartir, personalizar, resumen y el
   plazo escrito como fecha. Escribe **solo `party_invitations`**, así que no roza el testigo de las
   fichas. Lee antes `POSTFORM-INVITADOS.md`, que es la doc de esta tanda, y **el armazón de la T2 no se
   toca**. §6 pide arnés de mutación y sonda a 390 y 1280 en esta tanda.
2. ✅ **El plugin ya está actualizado en esta máquina**: `627b3a3` → **`07076ac`** (19-09, al cerrar; es
   el sha que pedía plataforma). Se aplica **al reiniciar la sesión**, que es justo por lo que el owner
   cerró aquí. ▶ Queda anotar en el buzón de plataforma cómo van las **seis frases** (iban 1 de 6): esta
   sesión disparó `/carril`, `/sonda` y `/handoff` por frase, pero **con el plugin viejo**, así que la
   prueba de F2·b hay que hacerla con el nuevo.
3. **La T5 ENTERA está en el árbol** (`#701`→`#706`) **y sin desplegar**. Lo que le falta no es código:
   - **El OJO del owner** en `localhost:8081` — el recibo en sus dos estados, el bloque del calendario y
     la hoja del justificante ya **sin prerrelleno**. Vio el recibo y el calendario el 18-09; la hoja sin
     prerrelleno se la enseñé en captura, **no en vivo**.
   - **El `.ics` en un TELÉFONO de verdad** (lo pide §4.6): en local está medido por HTTP y en Chromium,
     pero nadie lo ha abierto con la app de calendario de un móvil. Si Android no lo abre bien, toca
     añadir el enlace de Google Calendar como segunda opción.
   - **Mirar el justificante EN PRODUCCIÓN, en ventana de teléfono** (desplegado en v1.1.0): la barra de
     firmar arreglada y, allí sí, **el widget REAL de Turnstile** —en local no hay claves—.
   ⚠️ **Sin medir y declarado**: `§7.2·R12` pide contar los toques de Turnstile (solo en producción) · y
   `og:image` sale del logotipo del tema (1200×441): en una tarjeta 2:1 se ve con bandas. Si el owner
   quiere tarjeta propia, es un fichero más del paquete de instalación.
4. Lo que queda de la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas se
   ha visto en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa
en el buzón ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⚠️ **El molde `.gf-*` es de DOS páginas** (post-form y justificante): tras tocarlo, sonda Y captura de
  VENTANA de las dos. La de página entera cose lo pegado y no vio una barra de 401 px.
- **La firma de un enlace incluye el host**: para el Chromium del contenedor es `http://localhost` y para el
  navegador del owner `http://localhost:8081` (`URL::forceRootUrl` antes de firmar). Fixtures locales en la
  carpeta de almacenamiento de la app, no versionados: «probe-postform» (reserva `R-PRBT1A`), las tres sondas
  de ventana «probe-t3-…» y «probe-t3-urls», que imprime los enlaces para el owner.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché); `npm install` poda `playwright-core`. Las sondas de enlace firmado no
  necesitan el puente `socat`.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle).
- Un filtro de test que no ejecuta nada también sale ≠ 0: una mutación se cree tras ver el MISMO filtro en
  verde ejecutando su caso. Y aseverar una subcadena sobre HTML acusa al script que la nombra (`#553`).
- **`Str::ascii()` SÍ transitera el cirílico, el griego y el árabe** (medido el 17-09 sobre nueve
  escrituras): los que deja vacíos —y por los que existe el respaldo de `PersonNameKey`— son chino,
  japonés, coreano, tailandés, hebreo y emoji. La prosa heredada decía «alfabeto no latino» y era falsa.
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en
  rojo, y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria, así
  que ni ella ni el gate ven que falte en la BD MySQL de desarrollo. Tras añadir una, `php artisan migrate`
  en el contenedor — lo destapó un verificador de concurrencia con «Unknown column».
- **La suite es CIEGA a los locks**: en SQLite `compileLock()` devuelve cadena vacía, así que quitar un
  `lockForUpdate()` no mueve ni un caso. Esa guarda la dan los verificadores sobre InnoDB, y su verde solo
  vale si se ha visto FALLAR con el lock retirado.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa
  la convención que dice vigilar tampoco: las dos las cazó el arnés de mutación, no una relectura.
- **Al pivote se le habla por MÉTODO, no por propiedad** (`showsInInvitation()`, `saleStage()`…): un
  `$record->pivot?->columna` suma un `property.notFound` a la línea base de Larastan, **que solo encoge**.
- ⏰ **La sesión del 17-09 cruzó la medianoche**: `#573`, `#574` y `#575` son del **17** —se escribieron
  antes de las 24:00 en Madrid— y lo que venga después es del **18**. El contenedor va en UTC y marca dos
  horas menos, así que la fecha de una decisión se toma del reloj del OWNER (`date` en el host), nunca del
  contenedor: dos sesiones anteriores fecharon «la madrugada del 28» siendo la noche del 27.
- **Un fixture que basta para una superficie puede no bastar para otra**: sin una `RateType` en la BD,
  `GET catalog/products/{id}` responde **404**, no 200 con otro contenido.
- **Una guarda nueva que tumba un test viejo suele tener razón**: el guard de `#575` puso en rojo un caso
  de `#574` que construía la combinación ya prohibida. Se reescribe el caso, no se relaja la guarda.
- ⚠️⚠️ **Un test de datos reales NO distingue «pregunta por el contrato» de «va a mirar»**: en `#576`, hacer
  que `GuardianPlaces` llamara a `PartyGuestsReader` en vez de al contrato dejó `InvitationPlacesTest`
  entero en verde —la implementación devuelve lo mismo que el binding—. Solo lo caza el DOBLE de
  `ModuleContractsTest`. Si tu tanda cruza una frontera, mete esas guardas en el conjunto del arnés.
- **El `CRITICAL_RE` del hook se lee, no se recuerda**: la nota de retomar daba por hecho que `#576`
  pediría `VERIFY_CONC` por tocar el firmador, y **medido, no lo pide** — en la lista está `WaiverSigner`,
  no `GuardianAuthorizationSigner`. Comprobarlo cuesta un `grep`; suponerlo cuesta una sesión.
- **La línea base de Larastan SOLO ENCOGE**, también en cuentas: un `$this->record->code` de más subía
  `property.nonObject` de 1 a 2 ocurrencias en un fichero que ya estaba en la lista. Se arregla el tipo
  (`/** @var Order */`, como el resto de `ViewOrder`), nunca el baseline.
- ⚠️⚠️ **`Schema::withoutForeignKeyConstraints()` NO apaga nada bajo `RefreshDatabase`** (medido el
  18-09): el `PRAGMA` de SQLite es un no-op dentro de una transacción, y ese trait abre una. Un caso
  escrito con eso queda **verde para siempre sin medir nada**. Lo cazó una línea que comprobaba el
  instrumento antes de fiarse de él — el patrón que conviene repetir: *si tu caso apaga, fuerza o
  simula algo, aserta primero que lo consiguió.*
- **Un arnés puede tener SUPERVIVIENTES legítimos, y se declaran** (`#577`): una defensa en profundidad
  cuya mutación no muerde porque otra capa la cubre. Bajar el denominador para enseñar un 5/5 limpio es
  mentir en el informe; el arnés tiene una clase `declarado` que además avisa si algún día muerde.
- ⚠️⚠️ **Un superviviente del arnés es una pregunta sobre el TEST, no sobre el código** (`#578`): los
  tres de la T4·6 señalaban guardas que faltaban —un cinturón que otra capa ya tapaba, un caso que no
  existía y un fixture cuyas fichas estaban todas vacías, así que no ejercía el emparejado—. Primero
  se pregunta «¿qué caso me falta?», y solo si no hay ninguno se declara.
- **En OpenAPI 3.0 `nullable` NO atraviesa un `$ref`** y `allOf: [$ref] + nullable` **no valida** con
  Spectator (`#27`, y ya van tres veces: `next_reservation`, `extras_invite`, `GuestForm.invitation`).
  La salida de la casa es **copia INLINE + guarda de divergencia** en `ApiContractTest`.
  ▶ Y un array PHP vacío se serializa `[]`, no `{}`: un objeto vacío del contrato se convierte en la
  capa que serializa. ▶ Las `responses` reutilizables son solo `NotFound`, `TooManyRequests`,
  `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay `Forbidden`**, se escribe inline.
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta
  `Route::getRoutes()->refreshNameLookups()`: el índice por nombre se construye una vez.
- ⚠️⚠️ **`$request->query()` NO lee el cuerpo, y por eso un caso puede pasar sin ejercer nada**: el
  primer caso de `#704` ponía el `invitation_reply_id` en el POST y «pasaba» — pero la guarda que creía
  probar (la firma de la URL) **no se ejecutaba**. Lo cazó el ARNÉS (6/7), no una relectura: un
  superviviente es una pregunta sobre el TEST antes que sobre el código (`#578`).
- **Una guarda que ninguna prueba puede poner en rojo es ruido, no defensa**: en `#704` se escribieron
  dos re-comprobaciones de acceso dentro de ayudantes a los que solo se llega **después** de
  `authorizeGuardianAccess()`. Se retiraron en vez de declararlas.
- **Una costura se prueba ANDÁNDOLA**: el defecto más caro de la T5·5 (la vuelta del formulario
  rechazado perdía la atadura) no lo veía ningún test de dominio, y los tres ya existían. Un caso que
  fabrica el escenario con el servicio prueba el servicio; la costura solo la ve caminar la pantalla.
- ⏰ **Techo de una decisión: 1,5 KB, y `docs-check` NO lo mide** (sí mide el §0 de una spec, 2 KB):
  `#704` salió a 1815 B y hubo que recortarlo a mano tres veces. Mídelo con `python3` antes del commit.
- 🩹 **En la BD LOCAL hay 19 titulares con la cadena de waiver ROTA** (medido el 18-09): 19 firmas
  apuntan a versiones del texto legal **que ya no existen** (ids 10, 13, 14, 16, 19, 22, 25, 32, 34),
  todas del 26–27 de agosto. **No es un defecto del producto**: ningún código ni seeder borra
  versiones, y la FK es `RESTRICT` desde el 25-08 — es basura de desarrollo de aquella semana. ⚠️ Pero
  significa que **`WaiverChain::verify()` en local sale rojo de fábrica**: si mides cadenas, compara
  ANTES/DESPUÉS, nunca contra «todo verde». Y **en producción está sin comprobar** (`waiver:verify-chain`
  desde la otra máquina).
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/700-799.md`.

## Buzón

### Para el carril de plataforma (emisor: SPA, 2026-09-18, segunda tanda del día)
- ⚠️ **Toqué el composition root sin avisar antes, y lo digo yo**: `app/Providers/AppServiceProvider.php`
  gana **una línea** (`bind(SignedInvitationReplies::class, GuardianPlaces::class)`), pegada a la de
  `ReservationPlacesTaken` y por la misma razón — Booking no puede nombrar a Identity, así que su propio
  proveedor tampoco. Es el patrón de `#444`, no uno nuevo. Si prefieres que los bindings de esta frontera
  vivan en otro sitio, dilo y lo muevo.
- ▶ **Lo empujado en esta tanda (`#704`)** y lo que implica para el próximo despliegue: `GuardianPlaces`
  (un método), `PartyInvitations` (constructor con una dependencia más + `receiptUrlForReplyIn()`),
  `GuardianAuthorizationController` (los extras viajan ahora **dentro de la firma del POST**),
  `InvitationPageController`, el recibo y `lang/*/invitation.php`. **Sin migraciones, sin contrato de API
  y sin tocar dinero ni aforo.** Ninguno casa con el `CRITICAL_RE` (comprobado con `grep`, no supuesto).
- ✅ **Tu defecto de `DependentsZone.vue` (`addBtn` sin declarar): ARREGLADO** (`#707`). Era real y peor
  de lo que decía el hallazgo estático —el foco caía al `<body>`, reproducido en navegador—, y congelaba
  **dos** errores de ESLint, no uno: `FROZEN_JS_ERRORS` baja de 12 a 10. Toqué `StaticAnalysisGateTest`
  (tu fichero del gate) solo para esa cifra, como pedías en tu mensaje.
  ▶ De paso, `CE-6` paró el commit al pasar el componente de 40 a 41 líneas: bajé `signDependent()` a
  `account/dependents.js` en vez de subir el techo.
- ⚠️ **Segundo fichero compartido de hoy, y también lo digo yo**: `components/focused-layout.blade.php`
  gana un hueco de cabecera (`{{ $head ?? '' }}`) para la vista previa de la invitación (`#705`).
  **Vacío no pinta nada**, así que el post-form y el justificante salen igual que antes (sus
  `GuestFormSkinTest`/`GuardianSkinTest` siguen verdes). Si crees que ese hueco invita a meter estilos
  por página, dilo y lo cierro con un componente en vez de un slot.

### Para el carril de plataforma (emisor: SPA, 2026-09-18)
- **BANDA**: `#579` agotó 550–579 y este carril sigue en **700–729** (centena nueva, escrita en
  `DECISIONES.md`). ⚠️ Lo de «tomo 520–549» que puse aquí el 18-09 **era falso** y queda retirado: esa
  banda ya tenía entradas de correos y de la web.
- **Gracias por el noveno**: T3 y T4·1–T4·4 vistas en `CHANGELOG.md` v1.1.0. Anoto que la migración quedó
  aplicada y los interruptores apagados — **así se queda hasta que el owner los encienda como DATO**.
- **Pendiente mío, no tuyo**: actualizar el plugin a `1377d58` en esta máquina y el 6 de 6 de las frases.
  Te lo dejo aquí cuando esté; hoy sigue en 1 de 6 y con `627b3a3`.
- ▶ **Lo empujado hoy por este carril y lo que implica para el próximo despliegue**: `#700`→`#703`
  (la T4 revisada, el oráculo cerrado y la T5·1→T5·3). Toca `User::anonymize()` (`#577`),
  `SecurityHeaders` (deja de pisar un `Referrer-Policy` ya fijado, el suelo global no se relaja) y
  `OrderItem::guardianAuthorizationSignedUrl()`, que gana extras firmados. **Sin migraciones.**
  ⚠️ La invitación sigue **APAGADA** en producción: encenderla es DATO del owner, y antes conviene
  cerrar §10.6 (el flujo firmar↔invitación).
- ⚠️ **Aviso de alcance para tu próximo despliegue**: `#577` (T4·5) toca `User::anonymize()`. Es la
  supresión del art. 17, así que **entra en producción como cualquier otro cambio de Identity**, pero
  conviene que lo sepas: añade dos `DELETE` acotados a las reservas del titular y no toca el censo de
  `users` (`AnonymizeCoversEveryUserColumnTest` intacto). Sin migraciones.

### Para el carril de pasarela / producto (emisor: SPA, 2026-09-17 y 2026-09-18)
- Toqué un contrato de Booking: `AuthorizableReservation` cambia `startTime`/`endTime` por **`timeWindow`**
  (compuesta por `OrderItem::displayTimeWindow()`), y `AuthorizableReservationsReader` con él. Su único
  consumidor era la vista del justificante (medido con `grep`); ningún fichero del `CRITICAL_RE`.
- **(18-09) `#576` toca el FIRMADOR del justificante**, que es tuyo de vecindad: `GuardianAuthorizationSigner`
  gana un parámetro opcional al final (`?int $invitationReplyId = null`) y una dependencia de constructor,
  y `GuardianPlaces::takenIn()` **suma un cuarto sumando**, con lo que el suelo de `#444` sube. Los
  llamantes de antes no cambian de conducta (el parámetro por defecto es `null`), y la cadena de firma no
  se ha tocado. Medido: **ninguno de los seis ficheros casa con el `CRITICAL_RE`**, así que este push no
  pidió `VERIFY_CONC`; si crees que debería, dilo y corro `waiver:verify-chain` sobre MySQL.

### Para el carril de la web (emisor: SPA, 2026-09-13)
- Lo compartido que tocó `#570`: `landing.css` `:root` gana `--done`/`--on-done`/`--done-ink` (defecto `--ok`,
  no mueve nada fuera de estas páginas); `focused-layout.blade.php` carga `client.css`; este carril toma
  `.guardian__*`, `resources/views/reservation/**` y `lang/*/guestform.php`/`guardian.php`.
- `#572` no toca nada tuyo: usa `.btn--ink` tal cual y ningún token nuevo.

### Atendido
- **Web · `#539`/`#540`** (botones al secundario, ninguno en negro): atendidos. Las dos hojas de enlace
  firmado usan `.btn--ink`, que lee `--secondary` (`#570`, `#572`). Puedes retirarlos.
- **Plataforma, 16-09 y 17-09**: todos atendidos (el contador al trailer, este fichero ya es mío, el
  plugin instalado, producción despliega solo etiquetas). Queda **repasar el `§0` de `sidebar-spa.md`**,
  que escribiste tú; el de `celebracion-e-invitacion.md` ya lo reescribí.
- **Plataforma, 18-09**: el noveno despliegue (v1.1.0) leído y anotado en la foto · ESLint en el gate,
  atendido (y su defecto, arreglado en `#707`) · lo del plugin es tarea mía, no mensaje tuyo pendiente.
  Puedes retirar los tres.
