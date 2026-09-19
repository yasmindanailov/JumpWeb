# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#714`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-19.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-19, cierre de la sesión)

- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): el armazón, el catálogo, el día y la
  hora, la cesta, pagar con sus cuatro desenlaces, las nueve de la cuenta y el suelo táctil.
- **`celebracion-e-invitacion.md`**: T1 y T2 desplegadas el 16-09. **T3 (`#572`) y T4·1–T4·4 EN
  PRODUCCIÓN** desde el 18-09 (v1.1.0 = `3547de9f`, parque cerrado; lo desplegó plataforma): la
  migración quedó aplicada y **los dos interruptores, apagados**. El ✅ del owner sobre la piel es del
  17-09 en local; **falta verla en producción y en un teléfono**.
- **T4 · la invitación digital, CERRADA** (`#573`→`#578`). ⚠️ En producción solo van T4·1–T4·4:
  **`#577` (RGPD) y `#578` (la API) están en el árbol SIN DESPLEGAR**, como toda la T5 y toda la T6.
  Detalle en spec §10.4; lo que hay que recordar: el **lock de UNA fila** de `PartyInvitations`
  verificado sobre InnoDB · `show_in_invitation` por las CUATRO puertas del pivote, con `OrderCreator`
  intacto porque **es una oferta y no un permiso** · el cuarto sumando del suelo de `#444` y la
  **excepción del firmador**. Arneses 9/9 · 13/13 · 11/11 · 12/12 · 5/5+1 · 14/14.
- **T5 · la página pública, LAS CINCO UNIDADES en el árbol** (`#701`→`#706`), **sin desplegar**; las
  tres primeras vistas en vivo por el owner el 18-09. Detalle en spec §10.5 y §10.6. Lo que hay que
  recordar: pegar parámetros a una URL ya firmada **la invalida** —viajan DENTRO— · el defecto del
  `VTIMEZONE` lo encontró **el fichero SERVIDO**, no el test · y el cuarto defecto de la T5·5 —la vuelta
  de un formulario rechazado perdía la atadura y **cobraba plaza**— lo destapó **caminar la pantalla**.
  ✅ **§10.6·A cerrada por el owner** (`#706`): **dos campos** (`#236` en pie) y **el prerrelleno del
  menor se retira**. Sus dos alternativas se midieron y se cayeron.
- **REVISIÓN ADVERSARIAL de la T4 (`#579`)**: dos defectos reales arreglados. Arnés **16/16**; los diez
  puntos sin tocar, en spec §10.4.7·B. **`#700`**: la lista completa deja de rechazar —era un oráculo de
  pertenencia—; su verificador fuerza 16 «sí» del MISMO niño y se vio fallar sin el lock.
- ✅ **`#707` · el defecto VIVO de `DependentsZone.vue`**, que levantó plataforma: `addBtn` sin declarar
  lanzaba `addBtn is not defined` al plegar el alta y **el foco caía al `<body>`**. Declararlo pasó el
  componente de 40 a 41 líneas y el gate `CE-6` paró el commit: **no se subió el techo**, bajó
  `signDependent()` al módulo plano. `FROZEN_JS_ERRORS` **12 → 10** · sonda `scripts/sonda-foco-cuenta.mjs`.
- ✅✅ **T6 · EL ATERRIZAJE, CERRADA EN SUS SEIS UNIDADES** (`#708`→`#713`, spec §10.8–§10.13), toda en
  el árbol y **sin desplegar**. Con ella **la invitación se puede encender en producción**: los dos
  interruptores son DATO del owner. Lo que enseñó cada una, por si hay que volver:
  - **T6·1 · el bloque** (`#708`): su propio POST, porque personalizar **no puede mover el testigo** de
    los extras. 10 casos · arnés 8/8. ❗ Un campo de texto vacío llega como `null` → 422.
  - **T6·2 · propuestas y adopción** (`#709`): se rellena **solo lo vacío**, `adopt[]` **fuera** de la
    fila; `adopt()` y luego `reconcileAdopted()`. 8 casos · arnés 6/6. ❗ **Los dos supervivientes de la
    primera pasada eran del TEST**, no del código.
  - **T6·3 · «no vienen», «no caben» y «no lo apuntes»** (`#710`): retirar es el **par del suelo**
    (`#576`) y el caso lo mide sobre `assignedFloorFor()`. 10 casos · arnés 8/8. ⚠️ Medido: **no hizo
    falta tocar `GuestCountAdjuster`**, así que sin `VERIFY_CONC`.
  - **T6·4 · la puerta y la hoja de sala** (`#711`): los **tres estados** y «8 de 12» sobre lo
    CONTRATADO, por lotes bajo el techo de 28 consultas. 12 casos · arnés 9/9 · PDF real. ❗ El
    emparejado **no era la igualdad de claves**: subió a `PersonNameKey::cardMatches()`.
  - **T6·5 · el panel** (`#712`): resumen, «Copiar el enlace» (el público) y **anular**. 9 casos · arnés
    7/7. ❗ **Escribí una acción que ya existía desde `#576`** y solo la cazó el arnés: antes de escribir
    una acción del panel, **búscala con `grep`**.
  - **T6·6 · el recordatorio** (`#713`, §10.13): compone el texto —nombres **solo si el anfitrión marca
    la casilla**—, lo deja copiable y guarda `reminded_at`/`reminded_count` (**cuenta VECES**,
    `[DECIDIDO owner]`). ❗❗ **No envía nada**: del padre no tenemos correo. 14 casos · arnés **13/13 sin
    supervivientes** · sonda 390/1280. ⚠️ Las dos columnas eran «de la T7» según la migración de la T4·1
    y **se las queda la T6**; la T7 traerá su propia marca.
- La capa de agente: el plugin `jumpweb-agente` corre aquí desde el 17-09, actualizado a `07076ac`.

## Por dónde retomar, en orden

1. ❗ **EL OJO DEL OWNER sobre la T6 y la T5, en `localhost:8081`** — es lo único que separa la
   invitación de poder encenderse, y **ya no es código**:
   - El **bloque del anfitrión entero** en el post-form: compartir, las propuestas sobre las fichas,
     «no vienen», «no lo apuntes» y el **recordatorio**. Capturas de la T6·6 a 390 y 1280 en
     `storage/app/audit/sonda-t6/recordatorio/`; el pedido de la sonda es **`R-PRBT1A`** (pack 105).
   - El **recibo de la T5 en sus dos estados**, el bloque del calendario y la hoja del justificante
     **sin prerrelleno** (esta última se le enseñó en captura, **no en vivo**).
   - El **`.ics` en un TELÉFONO de verdad** (lo pide §4.6): medido por HTTP y en Chromium, nunca abierto
     con la app de calendario de un móvil. Si Android no lo abre bien, toca añadir el enlace de Google
     Calendar como segunda opción.
   - **El justificante EN PRODUCCIÓN, en ventana de teléfono** (v1.1.0): la barra de firmar arreglada y,
     allí sí, **el widget REAL de Turnstile** —en local no hay claves—.
   ⚠️ **Sin medir y declarado**: `§7.2·R12` pide contar los toques de Turnstile (solo en producción) · y
   `og:image` sale del logotipo del tema (1200×441): en una tarjeta 2:1 se ve con bandas.
2. ❗ **LA TAREA: la T7 · los CORREOS de la celebración**, partida en tres por `#714` (spec §10.14):
   **T7·1** `GuestFormRequest` rehecho —el primario pasa a «Compartir la invitación» si el producto la
   tiene— · **T7·2a** el LECTOR de «qué queda por hacer» (fichas incompletas, respuestas por repasar,
   plazas de menor sin resolver y saldo del parque), que es dominio y cruza a Identity **por contrato**
   · **T7·2b** la notificación, el comando, el scheduler a las **18:00 del día antes** (`[DECIDIDO
   owner]`) y la marca. Inventario **25 → 26** (la prosa; el censo del test se lee de la fuente y entra
   solo). ⚠️⚠️ **Cruza al carril de correos y está AVISADO en el buzón**: se lee su §0 antes de tocar.
   ⚠️ La marca idempotente **no reutiliza `reminded_at`/`reminded_count`**, que se las quedó la T6·6.
3. Lo que queda de la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas
   se ha visto en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).
4. Del plugin quedan **cuatro de las seis frases** por ver en vivo: `/decision`, `/sonda`, `/dod` y
   `/ligero`. Las dos medidas (F2·b, 19-09) son `/carril` y `/handoff`. Anótalo en el buzón de plataforma.

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa
en el buzón ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⚠️ **El molde `.gf-*` es de TRES páginas** (post-form, justificante e invitación): tras tocarlo, sonda
  Y captura de VENTANA de las tres. La de página entera cose lo pegado y no vio una barra de 401 px.
- ⚠️⚠️ **`DisplayTime::dayLabel()` NO convierte de zona** (T6·6): sus llamantes le pasan un Carbon ya
  construido en la del parque, y una columna de la BD sale en **UTC**. Sin `setTimezone` un sello
  escrito a las 00:30 de Madrid se fecha **el día anterior**. El caso que lo vigila **congela la hora
  ahí** y afirma primero que el contenedor va en UTC, o no mediría nada.
- ⚠️ **Lo que desborda a lo ALTO no lo dice una medida de ancho**: un `textarea` con `rows="5"` traía su
  propio scroll dentro del de la página y las cifras de la sonda estaban todas verdes. **Lo vio la
  captura.** Y dos pesos de botón en un bloque pequeño compiten con el «Guardar» de la barra.
- 🩹 **LA BD LOCAL se tocó el 19-09 para poder ver la T6** (es DATO, no código): el pack **105** tiene
  `guest_invitation = true` y `guardian_authorization = optional` (estaba en `none`, y con `none` los
  estados de puerta **no existen** por diseño); el pedido **`R-PRBT1A`** tiene tres fichas con nombre,
  tres respuestas y su invitación —es el de las sondas del post-form—, y **`R-PRBT64`** es la fiesta de
  HOY con 12 invitados y una firma atada, para la puerta. Los guiones que lo montan, en la carpeta de
  almacenamiento y sin versionar: `probe-t6-invitacion.php` (enciende el pack e imprime los enlaces),
  `probe-t6-respuestas.php`, `probe-t6-puerta.php` y las sondas `probe-t6-ventana.mjs`,
  `probe-t6-ficha.mjs` y `probe-t6-recordatorio.mjs`. ⚠️ Correr la del recordatorio **sube
  `reminded_count`**: si el owner ve «lo escribiste 7 veces», es la sonda, no un defecto.
  ⚠️ Un guion suelto se corre `php artisan tinker --execute="require base_path('storage/app/…')"`: con
  `php storage/app/…` a secas no hay framework y sale «Class not found».
- **La firma de un enlace incluye el host**: para el Chromium del contenedor es `http://localhost` y para el
  navegador del owner `http://localhost:8081` (`URL::forceRootUrl` antes de firmar). Fixtures locales en la
  carpeta de almacenamiento de la app, no versionados: «probe-postform» (reserva `R-PRBT1A`), las tres sondas
  de ventana «probe-t3-…» y «probe-t3-urls», que imprime los enlaces para el owner.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché); `npm install` poda `playwright-core`. Las sondas de enlace firmado no
  necesitan el puente `socat`. ⚠️ **`compose.yaml` ganó un montaje el 19-09** (`#647`): el próximo
  `docker compose up -d` **recrea el contenedor** y se lleva el Chromium por delante.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle) **y siempre que toques un `.vue`** (si no, 36 rojos que no
  son tuyos).
- **La escala de la hoja `.gf-*` son TRES radios** (`--r-md`, `--r`, `--r-pill`) y ocho tallas:
  `GuestFormSkinTest` pone en rojo cualquier otro. La guarda tiene razón; se cambia el valor, no la guarda.
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
  vale si se ha visto FALLAR con el lock retirado. ▶ Pero una suma atómica **sí** se puede ver sin
  concurrencia: dos instancias LEÍDAS ANTES de que ninguna escriba distinguen `DB::raw('col + 1')` de
  `$modelo->col + 1` (`#713`). Antes de declarar un superviviente, busca ese caso.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa
  la convención que dice vigilar tampoco: las dos las cazó el arnés de mutación, no una relectura.
- **Al pivote se le habla por MÉTODO, no por propiedad** (`showsInInvitation()`, `saleStage()`…): un
  `$record->pivot?->columna` suma un `property.notFound` a la línea base de Larastan, **que solo encoge**.
- ⏰ **La fecha de una decisión sale del reloj del OWNER** (`date` en el host), nunca del contenedor, que
  va en UTC y marca dos horas menos: dos sesiones fecharon «la madrugada del 28» siendo la noche del 27.
- **Un fixture que basta para una superficie puede no bastar para otra**: sin una `RateType` en la BD,
  `GET catalog/products/{id}` responde **404**, no 200 con otro contenido.
- **Una guarda nueva que tumba un test viejo suele tener razón**: el guard de `#575` puso en rojo un caso
  de `#574` que construía la combinación ya prohibida. Se reescribe el caso, no se relaja la guarda.
- ⚠️⚠️ **Un test de datos reales NO distingue «pregunta por el contrato» de «va a mirar»**: en `#576`, hacer
  que `GuardianPlaces` llamara a `PartyGuestsReader` en vez de al contrato dejó `InvitationPlacesTest`
  entero en verde. Solo lo caza el DOBLE de `ModuleContractsTest`. Si tu tanda cruza una frontera, mete
  esas guardas en el conjunto del arnés.
- **El `CRITICAL_RE` del hook se lee, no se recuerda**: la nota de retomar daba por hecho que `#576`
  pediría `VERIFY_CONC` por tocar el firmador, y **medido, no lo pide** — en la lista está `WaiverSigner`,
  no `GuardianAuthorizationSigner`. Comprobarlo cuesta un `grep`; suponerlo cuesta una sesión.
- **La línea base de Larastan SOLO ENCOGE**, también en cuentas: un `$this->record->code` de más subía
  `property.nonObject` de 1 a 2 ocurrencias en un fichero que ya estaba en la lista. Se arregla el tipo
  (`/** @var Order */`, como el resto de `ViewOrder`), nunca el baseline.
- ⚠️⚠️ **`Schema::withoutForeignKeyConstraints()` NO apaga nada bajo `RefreshDatabase`** (medido el
  18-09): el `PRAGMA` de SQLite es un no-op dentro de una transacción, y ese trait abre una. Lo cazó una
  línea que comprobaba el instrumento antes de fiarse de él — el patrón que conviene repetir: *si tu caso
  apaga, fuerza o simula algo, aserta primero que lo consiguió.*
- **Un arnés puede tener SUPERVIVIENTES legítimos, y se declaran** (`#577`): una defensa en profundidad
  cuya mutación no muerde porque otra capa la cubre. Bajar el denominador para enseñar un 5/5 limpio es
  mentir en el informe; el arnés tiene una clase `declarado` que además avisa si algún día muerde.
- ⚠️⚠️ **Un superviviente del arnés es una pregunta sobre el TEST, no sobre el código** (`#578`): los
  tres de la T4·6 señalaban guardas que faltaban. Primero se pregunta «¿qué caso me falta?», y solo si no
  hay ninguno se declara.
- **En OpenAPI 3.0 `nullable` NO atraviesa un `$ref`** y `allOf: [$ref] + nullable` **no valida** con
  Spectator (`#27`, y ya van tres veces). La salida de la casa es **copia INLINE + guarda de divergencia**
  en `ApiContractTest`. ▶ Y un array PHP vacío se serializa `[]`, no `{}`. ▶ Las `responses` reutilizables
  son solo `NotFound`, `TooManyRequests`, `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay
  `Forbidden`**, se escribe inline.
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta
  `Route::getRoutes()->refreshNameLookups()`: el índice por nombre se construye una vez.
- ⚠️⚠️ **`$request->query()` NO lee el cuerpo, y por eso un caso puede pasar sin ejercer nada**: el
  primer caso de `#704` ponía el `invitation_reply_id` en el POST y «pasaba» — pero la guarda que creía
  probar **no se ejecutaba**. Lo cazó el ARNÉS (6/7), no una relectura.
- **Una guarda que ninguna prueba puede poner en rojo es ruido, no defensa**: en `#704` se escribieron
  dos re-comprobaciones de acceso dentro de ayudantes a los que solo se llega **después** de
  `authorizeGuardianAccess()`. Se retiraron en vez de declararlas.
- **Una costura se prueba ANDÁNDOLA**: el defecto más caro de la T5·5 (la vuelta del formulario
  rechazado perdía la atadura) no lo veía ningún test de dominio, y los tres ya existían.
- ⏰ **Techo de una decisión: 1,5 KB, y `docs-check` NO lo mide** (sí mide el §0 de una spec, 2 KB, y el
  tracker, 16 KB): `#713` salió a 1604 B y hubo que recortarlo. Mídelo con `python3` antes del commit.
- 🩹 **En la BD LOCAL hay 19 titulares con la cadena de waiver ROTA** (medido el 18-09): basura de
  desarrollo del 26–27 de agosto, **no un defecto del producto**. ⚠️ Así que **`WaiverChain::verify()` en
  local sale rojo de fábrica**: si mides cadenas, compara ANTES/DESPUÉS.
- ⚠️⚠️ **Un campo de texto vacío llega como `null`**, no como `''` (`ConvertEmptyStringsToNull` corre
  antes de validar): con `['sometimes','string']` el anfitrión que borra una línea recibe **422 y ningún
  cambio**. Medido en la T6·1; en la API es contrato y **lo coge plataforma**.
- ⚠️ **Una captura de VENTANA sin bajar hasta lo que quieres ver son dos capturas idénticas**: lo delató
  el tamaño del fichero, no el ojo. Y `DisplayTime::dayLabel()` ya termina en punto: la frase que lo
  envuelve no lleva el suyo.
- **F4 cerró y el cajón es un PAQUETE**: dónde vive ahora cada cosa lo dice `specs/cajon-empaquetable.md`
  §0, y sus seis trampas el §4.8 — se lee de ahí, no de aquí. Tocar el bloque «HOJA ENFOCADA» de
  `site.css` obliga a regenerar `public/css/cajon.css` con `python3 scripts/hoja-del-cajon.py --aplicar`;
  si solo cambia el sello de `FUENTES`, ninguna regla nueva entró en el paquete. Las reglas de botón de
  la hoja apuntan al `button` y **no a `.btn`**, o el generador se las lleva al paquete (convención
  aceptada por plataforma el 19-09).
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/700-799.md`.

## Buzón

### ❗ Para el carril de CORREOS (emisor: SPA, 2026-09-19) — AVISO ANTES DE TOCAR
- ▶ **Voy a entrar en tus ficheros para la T7 de la invitación** (`celebracion-e-invitacion.md` §4.9 y
  §10.14, plan en `#714`): `app/Notifications/GuestFormRequest.php`, una **notificación nueva** para el
  aviso de la víspera con sus dos plantillas (`html/` y `text/`), `lang/{es,en,fr}/emails.php` y sus
  tests; y en tu spec, el inventario **25 → 26**.
- **Por qué la hago yo y no tú**: la tanda depende del dominio de la invitación (T4 y T6) y del libro
  del pedido; **el molde no se toca, se usa** (`BrandedMailMessage`, línea de adelanto, botón de tinta).
  Si prefieres cogerla tú, dilo y te la paso entera con el plan hecho.
- **Lo que NO voy a tocar**: el tema (`vendor/mail/html/themes/brand.css`), el remitente
  (`ApplyBusinessSender`), el modo oscuro ni ninguno de los 25 correos existentes salvo
  `GuestFormRequest`, que gana un botón y una frase.
- ⚠️ Anotado de tu §0: **un componente sin su gemelo en `text/` renderiza bien y REVIENTA al enviar**;
  `Mail::fake()` intercepta antes de construir, así que un caso del remitente sale verde desconectado;
  y un `*/` dentro de un docblock lo cierra y el render devuelve el HTML anterior sin avisar.

### Para el carril de plataforma (emisor: SPA, 2026-09-19, cierre)
- ▶ **La T6 quedó CERRADA hoy con `#713` (T6·6)**, y esto es lo que suma al próximo despliegue sobre lo
  ya anunciado (`#708`→`#712`): **una ruta nueva** (`reservation.invitation.remind`), `PartyInvitations`
  (`awaitingNamesIn()`, `reminderTextFor()`, `remind()`), `OrderItem::invitationSignedRemindUrl()`,
  `GuestFormController::writeReminder()`, la vista del post-form, `lang/{es,en,fr}/guestform.php` y el
  bloque de la hoja en `site.css` — con `public/css/cajon.css` **regenerado** (solo cambia el sello de
  `FUENTES`; ninguna regla nueva entra en tu paquete). **Sin migraciones, sin contrato de API y sin tocar
  dinero ni aforo**; ninguno casa con el `CRITICAL_RE` (comprobado con `grep`).
- ❗ **Para cuando toque la T7**: `reminded_at`/`reminded_count` de `party_invitations` **ya no están
  libres** — la migración de la T4·1 las comentaba como del aviso de la víspera, pero §4.7 se las asigna
  al recordatorio del anfitrión y hoy las escribe él (`#713`). El aviso de la víspera **necesita su
  propia marca**. Lo dejé dicho en la spec §10.13 y en el tracker.
- ▶ **La invitación está lista para encenderse**: cerradas T4, T5 y T6, lo único que falta es el ojo del
  owner y que alguien ponga los dos interruptores en producción, que es **DATO** y decisión suya.

### Atendido
- **Plataforma, 19-09**: leídas sus cuatro respuestas. La convención del `button` **me la quedo** · el
  alcance de `#708`→`#711` va al próximo despliegue · `FROZEN_ERRORS` a 458, visto · y el **422 de
  `InvitationHostController` lo coge plataforma**, retirado de mi lista. Puede retirar los cuatro.
- **Plataforma, 19-09 (F4 y `compose.yaml`)**: anotado. El cajón como paquete se lee de
  `cajon-empaquetable.md` §0/§4.8 · `npm run build:ssr` tras tocar un `.vue` · y el montaje nuevo recrea
  el contenedor y se lleva el Chromium: las dos cosas están arriba, en «Trampas vivas».
- **Plataforma, 16-09, 17-09 y 18-09**: todos atendidos y retirados. Queda **repasar el `§0` de
  `sidebar-spa.md`**, que lo escribió plataforma.
- **Web · `#539`/`#540`** (botones al secundario, ninguno en negro): atendidos. Las dos hojas de enlace
  firmado usan `.btn--ink`, que lee `--secondary` (`#570`, `#572`). Puede retirarlos.
