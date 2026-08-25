# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> **El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza.** Última actualización: **2026-08-25**.
>
> ❗ **Lo primero que tienes que saber**: la **tanda A de `specs/landing-white-label.md` está
> CERRADA** (`#138`→`#143`). Lo siguiente es la **tanda B** (contenido y el segundo cliente como
> primer paquete de tema). Redis ya no bloquea nada (`#137`) y el desglose de dinero está CERRADO
> (`#127`→`#134`).
>
> ⚠️ **Y este documento ADELGAZÓ el 2026-08-25, de 824 líneas a menos de la mitad.** Se retiró el
> índice de la Fase 4 —83 líneas que duplicaban el tracker de una fase CERRADA—, se movió el mapa del
> cajón a `specs/sidebar-spa.md` §8 y se borró el histórico de deltas de tests, que ya vive en cada
> `DECISIONES`. `CONVENCIONES` dice que esta foto **resume** el tracker y nunca lo contradice: con 824
> líneas eso era imposible de sostener, y de hecho **había contradicciones vivas** —afirmaba que la
> Fase 5 seguía bloqueada por falta de Redis horas después de haberlo activado y verificado—.

## ▶ Dónde estamos

**Fase 0 ✅ · 1 ✅ · 2 ✅ · 3 (API v1) ✅ · 4 (sidebar SPA) ✅** — el detalle paso a paso de cada una
está en `00-REFACTOR.md`, que es el tracker. Aquí solo la foto.

🟩 **CERRADO y sin nada pendiente:** el cajón SPA como motor único con `Purchase.php` retirado
(`#111`, `#112`) · el área de cliente entera, incluidas la auth y `account-context` (`#66`, `#120`,
`#122`, `#123`) · «Mis reservas» por reserva (`#126`) · **el desglose de dinero del cliente**, las tres
tandas y los cuatro defectos de lectura (`#127`→`#134`, `specs/desglose-dinero-cliente.md`).
⚠️ **No se resume aquí lo que pasó en cada uno**: está en el tracker y en su decisión. Repetirlo en la
foto viva es crear una segunda verdad que envejece sola — ya pasó con la revisión de staging y con el
contador de tests JS.

🟦 **EN CURSO: la landing white-label** (`specs/landing-white-label.md`, `#136`). Nace del mockup del
**segundo cliente**. La línea: **data-driven el DATO, no la PÁGINA**.
▶ ✅ **Tanda A CERRADA** (`#138`→`#143`): el acento de zona ya no viaja por el nombre de la clase, la
paleta del primer cliente sale del producto, el icono por producto ya no es un booleano, **los 144
literales que repetían un token existente pasan a `var()`/`color-mix`**, el **spinner es sustituible
por instalación** y —lo que faltaba para que todo lo anterior sirviera— **existe el hueco por donde
entra el paquete de tema del cliente**.
⚠️ **Lo que la tanda A enseñó y no se puede no saber**: tres afirmaciones de la propia spec eran
falsas y las tres se creyeron hasta medirlas. La última (`#143` §8): **el «76 colores en crudo» salió
de un `grep` línea a línea que no veía ni los `rgba()` ni los valores multilínea — y el primer
instrumento que se escribió para corregirlo tenía EL MISMO defecto.** Eran 234, y en las 12 que
faltaban estaban las dos fugas de marca del hero.
▶ **B** (contenido y copy) y **C** (servicios como producto real) sin empezar. ⚠️ **C toca AFORO y PAY
y necesita spec propia.**
⏸️ **APARCADO por el owner**: zonas y cupos se quedan como están hasta ver cómo se comportan las
reservas de packs distintos con gente real (`#139`).

✅ **Redis: requisito DURO y solo para CACHÉ** (`#137`), activado y verificado en staging. Sus dos
obligaciones —el pase de Redsys fuera de la caché y Redis en el stack local y en la suite— están
**hechas**. Detalle de la máquina en `ENTORNOS.md` §4.

✅ **STAGING SIRVE `e551851`** desde el 2026-08-25 (`#123` → `#135`), auto-verificado y comprobado a
mano. Canal: `scripts/deploy.sh`, dry-run por defecto.
⚠️ **Lo que hay que recordar del canal**: los assets se construyen AQUÍ y se suben compilados —en
staging no hay node/npm— y el `.env` **nunca viaja**: se lee y se valida.
⚠️ **El único aviso del despliegue**: el script no ve ningún demonio cron, así que el crontab instalado
puede no ejecutarse nunca. Se comprueba en el panel del hosting (`#115`). No es nuevo.
❗ **LA REGLA QUE ESTA LÍNEA PAGÓ DOS VECES: la revisión NO se copia, se MIDE.** Llegó a decir «no hay
diferencia de código» con 68 ficheros de diferencia. Antes de creerte lo de arriba:
`git log --oneline e551851..HEAD` y
`git diff --stat e551851..HEAD -- . ':(exclude)docs' ':(exclude)*.md'`, sustituyendo `e551851` por lo
que sirva staging de verdad.

- Suite **2797 en verde** (16.199 aserciones, `--parallel` **~33 s** medidos el 2026-08-25) ·
  ▶ **+14 sobre el cierre anterior, y ninguno cubre código nuevo**: son las guardas que faltaban para
  que el registro de un pedido se pueda leer (`DECISIONES #145`). **5** de `AuditActionCatalogTest`
  —catálogo ↔ etiquetas en las dos direcciones—, **5** de `OrderAuditReadabilityTest` —que
  **renderiza el modal**, cosa que ninguno de los once casos que ya había hacía—, **3** del bloque de
  totales y **1** del cableado del contexto al mover la fecha.
  **671 tests JS** (`node --test`) · Pint limpio (852 ficheros) · `docs-check` verde ·
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK.
  ⚠️ Sale con **1 `PHPUnit Notice`** que **NO es de ningún trabajo reciente**: viene de antes y es del
  runner (ver `TESTING.md`). No lo persigas creyéndolo nuevo.
  ✅ **El contador de PHP tiene GUARDA**: el `pre-push` compara lo que acaba de dar la suite con lo que
  declara esta línea y **corta si no cuadran** (`#116`). Antes derivó tres veces en un solo día.
  ⚠️ **El de JS NO la tiene**, y por eso llegó a llevar **22 cierres de retraso**: si dudas, mídelo con
  `npm run test:js` en vez de sumar deltas.
  ⚠️ **Éste es el ÚNICO sitio donde vive el contador**: duplicarlo en otro documento crea una copia que
  no guarda nadie.
  ⚠️ **Y puede BAJAR a propósito**: `/mi-cuenta/…` se llevó 63 casos y el modal de auth 42, ninguno por
  descuido —se midió por mutación cuáles cazaba también la API antes de borrar—. **Un contador que solo
  puede subir acaba premiando al test que no se retira.**
  ▶ El histórico de qué aportó cada corte vive en su entrada de `DECISIONES`, no aquí.

- ⚠️ **La suite NO está auditada contra la FECHA, y ya mordió DOS veces** (`DECISIONES #64`, `#97`):
  tres casos amanecieron rojos sin que nadie tocara nada, y el **2026-08-16 a las 00:02 de Madrid** el
  `pre-push` cayó con **1 fallo** en el cruce de medianoche; el reintento salió verde.
  ⚠️ **Y no se supo cuál era**: la salida del gate no se capturó y se perdió. **Si el `pre-push` cae,
  vuelca su salida a fichero antes de reintentar** — un rojo transitorio sin nombre no se puede
  arreglar. Están arreglados congelando el reloj, pero **nadie ha
  barrido el resto**. Si te encuentras un rojo que no viene de tu cambio, **guarda el árbol y prueba en
  el commit anterior antes de tocar nada** — es lo que separó el diagnóstico en minutos de una sesión
  perdida. Ficha en `DEUDA.md`.
- **El gate son SEIS pasos** —docs-check · Pint · `npm run build` · `npm run build:ssr` ·
  `npm run test:js` · suite—, y `PrePushGateTest` los vigila uno a uno, incluido que el build vaya
  ANTES que la suite (se añadió tras un fallo real: un manifest a 0 bytes tumbó la web entera).
- ⚠️ **`SidebarDomContractTest` compara contra un ARTEFACTO** (`storage/ssr/render-sidebar.js`). Tiene
  guarda contra bundle rancio (`#69`) porque un bundle viejo daba **verde falso**; ha saltado **tres
  veces en tres días** —la última tumbando sus 30 casos de golpe (`#78`)—. Si tocas un módulo del cajón,
  o lo mutas y lo restauras, `npm run build:ssr` **antes** de leer ningún resultado.
- **Auditorías de dependencias = verificación de CIERRE, no de instalación** (`#25`): el árbol npm pasó
  de 0 a 5 avisos en unas horas sin que el lock cambiara. Correrlas en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers).
  **La lista viva de lo que exige `VERIFY_CONC=1` es el `CRITICAL_RE` de `.githooks/pre-push`** — no se
  copia aquí para que no envejezca, y `CriticalPathGateTest` vigila que siga cubriendo lo que debe.
  ❗❗ **«Verdes» NO quiere decir «cubren todo»**: `purchase:verify-oversell` **solo ejercita ENTRADAS**.
  El aforo de PACKS —pool propio, dos topes por franja— **no lo prueba ningún verificador**
  (`DEUDA.md`, medido el 2026-08-25). Ver «Lo que está ABIERTO».
  ✅ **Y desde `#141` los dos CONTADORES de aforo disparan el gate**: `SlotAvailability` y
  `PackAvailability` llevaban fuera desde el principio — el gate vigilaba a quien LLAMA y no a quien
  CUENTA. Verificado por mutación, y con `ProductAvailability` como control negativo declarado.
  ✅ **Y desde la tanda 3 el gate ya no deja fuera ninguna superficie de dinero** (`#120(u)`): el
  reintento web —el último que quedaba sin cubrir— se retiró con la página que lo servía, porque el
  cajón reintenta por `POST /api/v1/orders/{code}/payment`, que sí entra por el `CRITICAL_RE`. (La
  compra la había dejado antes `#112`, al retirar `Livewire\Tickets\Purchase`.)
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ✅ **El segundo objeto-dios está DESMONTADO** (`DECISIONES #119`, 2026-08-22). `Sidebar.vue` pasó de
  **614 líneas y 11 llamadas a la API** a **16 y 0**: el embudo vive en `sections/PurchaseSection.vue`
  y el estado, en `stores/` —la reorganización creó **nueve** y el área de cliente ha ido añadiendo
  los suyos, uno por dominio—. Lo siguen guardando `SidebarComponentBudgetTest`
  (techo por componente + excepción declarada que solo encoge) y una guarda de que **la raíz no vuelve
  a pintar pantallas**. ⚠️ **Las cifras vivas están en su `EXCEPTIONS`**, no aquí: copiarlas a este
  documento es drift en espera, y ya pasó una vez.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vive dentro del cajón: sus entradas y reservas, y las gestiones de cuenta.
✅ **De los tres sitios en que estaba repartido, quedan DOS**: las páginas `/mi-cuenta/…` se retiraron
(`#120(u)`) y el **modal de auth de la cabecera** sigue siendo la puerta de entrada de quien no tiene
sesión. Ése es el último trozo, y su ficha está en `DEUDA.md`.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**.
  ✅ **Turnstile ya no ata nada** (4.4b·2, 2026-08-20): el cajón monta su propio widget y la delegación
  en el modal de la cabecera **está retirada**. ⚠️ Pero el modal **no se retira aquí ni en `4.7·2b·3`**:
  vive en `layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web
  fuera del cajón. Retirarlo es trabajo del área de cliente.
- ✅ **El terreno ya está preparado** (`DECISIONES #119`, 2026-08-22), y lo que hay que saber es dónde
  NO meter la cuenta:
  · el grafo del embudo es `FUNNEL_TRANSITIONS` y está **cerrado con guarda**: colgar ahí una pantalla
    de cuenta pone el test en rojo. Un área de cliente **no es un embudo** — sus pantallas se navegan
    libremente— así que va con su propio modelo, no con el de la compra;
  · el embudo es una **sección** (`sections/PurchaseSection.vue`) y la raíz son 16 líneas que solo
    enrutan: **la cuenta entra al lado, no dentro**;
  · el estado de cada dominio ya tiene su store, así que una sección nueva pide el suyo y no necesita
    que la raíz le pase nada por props.
  ✅ **Y el modelo de navegación está DISEÑADO, VALIDADO por el owner y CONSTRUIDO** (`#120(d)`,
  `specs/area-cliente.md` §3.1): índice + zonas libres con pila de retorno, con su propio modelo y sin
  tocar el grafo del embudo. Añadir una zona es **una línea** en `ZONES` más su rótulo.
- ✅ **El servidor está COMPLETO para las cinco gestiones** (`#120(a)` lo midió endpoint por endpoint,
  y por eso el área fue en tandas): **leer** —`/auth/*`, `/me`, `/me/orders`, `/me/reservations`,
  `/me/reservation-eligibility` y el post-form por firma, desde Fase 3— y **gestionar**: contraseña,
  sesiones, perfil (`PUT /me/password`, `POST /me/sessions/revoke-others`, `PATCH /me` y los dos del
  correo pendiente) y, desde el paso 8, los **dos derechos RGPD** (`DELETE /me` y `GET /me/export`).
  ⚠️ **`DELETE /me` NO borra la fila**: llama a `anonymize()` (`RGPD-01`). El pedido y su historia
  contable se conservan sin PII, porque la FK es `RESTRICT` y la factura tiene que seguir vinculada.
  ⚠️ **`GET /me/export` es el cuerpo con más PII del producto** —lleva `event_data` en claro: nombre
  y alergias de un menor, art. 9— y por eso `RGPD-04` exige `no-store`, que en `/api/v1` va por
  defecto en toda respuesta autenticada.
- ✅ **`account-context` YA ES VUE** (`#123`, 2026-08-23): el bloque lo pinta
  `sidebar/account/AccountPanel.vue`, teletransportado al hueco que emite el layout, y la frontera
  Livewire↔Vue del cajón **desaparece**. ⚠️ **Pero Livewire NO se puede retirar**: sigue trayendo
  Alpine, así que `@livewireScripts` se queda — lo que cambia es que ahora es la **fuente única**, y
  su guarda por fin discrimina (medido: retirarla la pone roja; hasta hoy no).
- ✅ **La puerta de entrada de quien no tiene sesión es el CAJÓN** desde `#122` (2026-08-23): el modal
  de la cabecera se retiró y las tres pantallas de auth son zonas de la sección de cuenta.

## ▶ Próximo paso

# ❗ LO SIGUIENTE: LA **TANDA B** DE `specs/landing-white-label.md` — el CONTENIDO

**La tanda A ya dejó el terreno con qué pintar** (`#143`): hay tokens que sobrevivan a un cambio de
paleta, un spinner sustituible y —lo que faltaba— **un hueco por donde entra el paquete del cliente**
(`public/css/client.css`, cargado el último; `INSTALACION-CLIENTE.md` §4).

**Tanda B, tres cortes:**
1. **Los dos modelos que faltan** — números del hero y testimonios (spec §4.2).
2. **El copy que baja de `lang/` al CMS** — **142 claves**, hoy en el repo (spec §4.3).
   ⚠️ **NO todo el copy baja**: el apartado dice cuál se queda y por qué.
3. **La landing del segundo cliente como PRIMER PAQUETE DE TEMA**, que es su medida de éxito y está
   escrita en la spec §6·6: **montarla tiene que costar CERO migraciones y CERO líneas de dominio.**
   Si no es cero, la línea de §4.1 está mal puesta y **se corrige la spec antes que el código**.

▶ **Después**: tanda **C** (servicios como producto real).
⚠️⚠️ **C necesita SPEC PROPIA antes de una línea de código**: toca `AFORO-01/02/03` y las identidades
de `PAY`, exige `VERIFY_CONC=1` y **no se puede verificar ni en SQLite ni en staging** (MariaDB).

⚠️⚠️ **Antes de tocar el tema, lee la spec §4.5.1 y §4.5.2 — LAS DOS LLEVAN UNA CORRECCIÓN.** La
primera afirmaba «cero variables se inyectan desde BD» (falso: el tema sí se inyecta desde
`theme.brand`). La segunda, «cambiar el dibujo del spinner es sustituir un fichero; no hay que
construir nada» (falso: eran dos pseudo-elementos y un `@keyframes`, y la doc del propio sistema decía
que esa hoja no se modifica).
❗ **La lección que esta semana se ha pagado CUATRO veces, y la última en el mismo trabajo que la
escribió**: un `grep` que no encuentra no demuestra que no exista, y **un instrumento que no ve una
parte del corpus da un inventario que parece completo y no lo es**. Cuando dos medidas del mismo
corpus no coinciden, la que sobra **no es la que da más: es la que no puede explicar la diferencia**.

---

### Lo que la tanda A dejó montado, y hay que saber ANTES de tocar CSS

- 🆕 **`public/css/client.css` es el paquete de tema de la instalación** (`#143`). Se carga **el
  último**, **no se versiona** y **`deploy.sh` lo excluye del `rsync --delete`**. ⚠️ **Las tres cosas
  son el mecanismo**, no tres detalles: por delante de `site.css` carga y no pinta nada; sin la
  exclusión, el primer despliegue lo borra **en silencio**. `ClientThemePackageTest`.
- 🆕 **Un literal que repita un token existente ya no puede entrar**: `RawColourIsNotATokenTest`
  compara **por VALOR RGB, no por nombre de token** — que es el hueco por el que dos acentos del
  primer cliente sobrevivieron a `#139` escritos en decimal dentro de un degradado.
  ⚠️ Su lista de excepciones (`ALLOWED_SELECTORS`) **solo encoge**, y hay un caso que tumba una
  entrada que se quede sin sujeto.
- 🆕 **`spinner.css` tiene dos mitades y la frontera es un marcador de máquina** (`>>> SPINNER:… >>>`).
  §A contrato, §B dibujo. Meter geometría en §A pone `SpinnerTest` en rojo, y con razón: es lo que
  hace sustituible el dibujo.
- ⚠️ **Blanco y negro NO se tokenizaron, y es decisión del owner**: el blanco de papel no es `--bg`
  (crema) y el blanco sobre acento no es `--on-brand` (sobre un acento claro es tinta oscura).
  Convertirlos **cambia píxeles**. Ficha con los tres grupos en `DEUDA.md`.
- 🐛 **Y queda un defecto de coherencia de una línea**: `.addons-mini__badge` pinta `color: var(--ok)`
  sobre un fondo verde de otra familia. Cambiar `--ok` mueve el texto y deja el fondo quieto. Está
  en `DEUDA.md` porque arreglarlo cambia píxeles: es decisión de producto, no refactor.

## ▶ Lo que está ABIERTO y no es de la tanda A

❗ **0.bis · `[PENDIENTE: owner]` — el rastro que falta cuando la bajada NO deja ajuste**
(`DECISIONES #145`, séptimo defecto). En un pedido **pagado íntegro online**, mover la fecha a un día
más barato **no crea ninguna fila de ajuste**: la bajada aflora como «pendiente de devolución». Y el
marcador de reducción que existe justo para dejar rastro solo se dispara
`if isset($itemEditContext['quantity_change'])` — **otra condición que `PAY-18` dejó atrás**, como el
filtro del contexto que `#145` sí arregló. Consecuencia: en ese caso el desglose no tiene ninguna fila
que explique la bajada (el registro del pedido sí la tiene, desde `#145`).
▶ **No se arregla de oficio**: extenderlo añade una fila de 0 € donde hoy no hay ninguna, y eso cambia
lo que ve el operador en **todos** los pedidos pagados íntegros cuya fecha se mueva. Es decisión de
producto.

🟦 **0 · La VISIÓN DE PRODUCTO de la app está DISEÑADA y sin implementar** (`DECISIONES #142`,
2026-08-25). Cuatro subsistemas en Fase 6, ordenados por **dependencia**: waiver probatorio →
menores a cargo → carné QR y pantalla de puerta → JumpPoints. **No es lo siguiente** y **no toca la
landing**; se anota aquí para que no se pierda. Detalle en el tracker; las cuatro specs, en
`docs/specs/` y en la tabla de enrutado de `CLAUDE.md`.
⚠️ **Dos cosas de ahí tienen consecuencias fuera de su alcance**: el waiver **modifica `RGPD-01`**
(el registro firmado se conserva al borrar la cuenta, restringido y con plazo `[PENDIENTE: owner]`)
y el carné QR **entra en `User::revokeAllAccess()`** desde el primer commit — es el modo de fallo
exacto que `RGPD-06` existe para impedir.
❗ **Ninguna de las cuatro está aprobada**: esperan revisión adversarial por otro agente
(`CONVENCIONES` §5).

❗ **1 · El aforo de PACKS nunca se ha probado bajo concurrencia** (ficha en `DEUDA.md`, medida el
2026-08-25). `purchase:verify-oversell` siembra **una entrada** con `online_capacity = 1` y forka N
compras; los packs se cuentan por **otro camino entero** —`PackAvailability`, con pool propio y los dos
topes por franja— y **ningún verificador lo ejecuta**.
▶ Traducido: **no hay ninguna evidencia de que dos cumpleaños simultáneos por la última plaza no se
vendan los dos.** La suite lo da por bueno porque SQLite no reproduce esas carreras. La receta está en
la ficha: un `--scenario=pack` que reutilice el `forkWorkers`/`evaluate` que ya existen.
✅ Lo que sí se cerró el 2026-08-25 (`#141`): los dos contadores de aforo **ya disparan el gate** del
`pre-push`, con su control negativo y verificado por mutación.

❗ **2 · Pendiente del OWNER: un `Ds_Response=0900` REAL de Redsys.** Exige un pago de prueba con
tarjeta en el sandbox desde el navegador (staging) y después `redsys:verify-sandbox --gateway-order=…`.
Todo lo demás de la cadena está verificado con sus credenciales. ⚠️ Una medición anterior dio el
sandbox por inalcanzable y **era un error de medida**: se probó el 443 y Redsys sirve el suyo en el
**25443**.

⚠️ **3 · El `redis.conf` de staging sigue de fábrica**, aplazado a propósito por el owner: sin techo de
memoria y **deja de aceptar escrituras si falla un volcado**. Contenido acordado y riesgo medido en su
ficha de `DEUDA.md`; aplicarlo exige reiniciar el contenedor PHP desde el panel.

---

## ▶ El estado de la BD de desarrollo, antes de mirar nada

- **Hay 25 pedidos**, uno por acción accionable, construidos por los **flujos REALES** (`OrderCreator`
  → vuelta de Redsys FIRMADA → acciones del panel por Livewire). Titular:
  `cliente.demo@jumpweb.test`. Los 58 anteriores **se borraron** el 2026-08-24 y no hay copia.
- **Los 25 cuadran**, así que el aviso de «desglose que no cierra» (`#132`) **no se puede ver en
  pantalla con estos datos**: para verlo hay que romper uno a mano.
- **El índice de los 25, con su código y su acción, está en `specs/desglose-dinero-cliente.md` §22.**
  ⚠️ Y §22.3 recoge cuatro trampas para conducir el panel desde un test — la peor: el cambio de FECHA
  lo mueve el CALENDARIO y no el formulario, y con `slot_date` solo la acción **no da error y no cambia
  nada**.
- La sonda que los creó **no está en el repo** a propósito (instrumento de medida, no guarda). Su
  receta sí, en §22.3.
- ⚠️ Dos productos llevan icono propio desde `#140` (tirolina → confeti, calcetines → calcetines); el
  resto usa el de su tipo.

---

### El estado del cajón, para lo que venga

🟩 **EL CAJÓN ESTÁ COMPLETO, PULIDO Y VERIFICADO EN NAVEGADOR.** El bloque de cuenta es Vue (`#123`),
el layout no renderiza **ningún** componente Livewire, los nueve retoques que el owner pidió están
hechos (`#124`) y el guion de navegador que quedaba **se recorrió el 2026-08-23** (`#125`). Las cuatro
specs del cajón están ✅ EJECUTADAS.

⚠️⚠️ **LO QUE ESE TRABAJO ENCONTRÓ, y condiciona lo que venga. Léelo antes de tocar el cajón:**
- **El `no-store` de TODAS las páginas web lo ponía un accidente de Livewire** —un hook de componente
  encendía el flag que usaba un middleware global del paquete—, así que retirar el último componente
  lo habría borrado del sitio entero **con la suite en verde**: ninguna de sus 12 aserciones miraba
  una página del layout. Hoy lo pone `NoStoreWebResponses` (global, con puerta para `/api/v1`).
- **`route('logout')` aparece UNA sola vez en toda la aplicación**, y vive como **suelo servido dentro
  del hueco** del bloque: colapsado e invisible mientras todo va bien, a la vista si el motor no
  llega. Desmiente la premisa escrita de `#120(t)`, ya corregida.
- **Ocho clases se emitían sin una sola regla** —entre ellas la tarjeta de «Mis reservas»—, porque la
  transcripción a Vue **inventó nombres** en vez de reutilizar los de las páginas retiradas. Ahora lo
  vigila `SidebarStyleWiringTest`, que además dejó **seis huecos del EMBUDO declarados con nombre**:
  `cart__pending`, `catalog__per`, los tres de complementos y el motivo del pago denegado. **Están sin
  arreglar a propósito** —son pantallas ya validadas— y son el candidato natural a un pulido del
  embudo.
- **`SidebarIconParityTest` estaba ciego a 10 de los 32 `.vue`** (`**` no es recursivo en `glob()`).

⚠️ **Antes de añadir NADA al cajón, mira su presupuesto.** Es la holgura más estrecha de todo el
ledger, y **la cifra viva NO se copia aquí**: vive en `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB`
con su ledger al lado —ya envejeció una vez en esta tabla—. El techo subió dos veces el 2026-08-23
(`#125` y `#126`), las dos con su medición y su párrafo, y las dos por **corrección**, no por features:
ése es el único caso en que debe ceder.

🟩 **«MIS RESERVAS» SE LISTA POR RESERVA** (`DECISIONES #126`, `specs/mis-reservas-por-reserva.md` ✅):
una tarjeta por reserva con la referencia de su pedido, las vivas de la más próxima a la más lejana, el
historial en su propia zona tras un CTA y atenuado, **5 por página ordenadas en el SERVIDOR**
(`GET /api/v1/me/reservations/{scope}`).
⚠️⚠️ **Lo que no se puede no saber antes de tocarlo**: los dos ámbitos son **los dos lados de UN
predicado** (`where`/`whereNot` sobre la misma expresión), **no dos consultas**. Si alguien las separa,
una reserva puede **no salir en ninguna de las dos pantallas** — y eso no falla, no avisa y no se ve:
una lista a la que le falta una fila se lee perfectamente. Lo sostiene `Sales\CustomerReservationsPageTest`,
que asevera la PROPIEDAD y no una lista de casos.
⚠️ **El ledger NO viaja con la tarjeta**: es del pedido y se repetiría tantas veces como reservas tenga.
Se pide con `GET /orders/{code}` al desplegar «Ver pedido».

⚠️ Y sigue abierta la decisión aplazada de `specs/area-cliente.md` §3.4: **si la zona activa cambia la
URL**. Hoy el «atrás» del navegador no hace nada dentro del cajón y una zona no se puede enlazar.

✅ **EL GUION DE NAVEGADOR ESTÁ AL DÍA** (2026-08-23, `#125`): `V17`, `V18` y `V23` —los tres que
`#122` y `#124` dejaron sin recorrer— **están HECHOS y en 60/60** tras arreglar los dos fallos reales
que destaparon. El qué pasó, en el tracker; el guion, en `VERIFICACION-E2E-CAJON.md` §5.septies.
⚠️ **Lo único que queda pide un DISPOSITIVO**: `V23·3` en **móvil real** (el scroll que arrastraba la
página) y `V20·6` con «reducir movimiento». Los dos necesitan staging — que **no lleva `#123`–`#126`**.

### Dónde está hoy «Mi cuenta»

🟩 **ENTERA en el cajón.** `/mi-cuenta` y `/mi-cuenta/pedidos` ya no pintan nada: sirven la home y el
cajón se abre solo en su zona (`Http\Sidebar\AccountDoor`).

| Zona del cajón | Qué cubre |
|---|---|
| `ORDERS` | «Mis reservas»: historial, ledger financiero completo, reintento y **las respuestas del pack bajo demanda** |
| `PROFILE` · `PASSWORD` · `SESSIONS` | Tus datos con el ciclo del correo pendiente · contraseña · cerrar las demás sesiones |
| `PRIVACY` | Consentimientos · descargar mis datos (art. 20) · borrar la cuenta (art. 17) |
| `HOME` | El índice, con su próxima reserva |

⚠️ **«Cerrar sesión» NO está en el índice, y es una decisión** (`#120(t)`, owner): sigue vigente.
⚠️⚠️ **Pero su premisa era FALSA y se corrigió el 2026-08-23**: decía «ya existe dos veces fuera, en el
nav y en el bloque `.acct`». Medido: **existe UNA**, la del bloque — `route('logout')` sale una sola
vez en toda la aplicación, y con sesión el nav es un botón que solo abre el cajón. La decisión no
cambia; el riesgo sí, y lo resuelve `specs/account-context-vue.md` §4.8.

⚠️ **Lo único que sobrevive de la web**: `GET /mi-cuenta/exportar`, que es una **DESCARGA** y no una
vista. Sirve el MISMO documento que `GET /api/v1/me/export`, y hay un test que los compara campo a
campo — es lo que impide que vuelvan a divergir.

### Lo hecho, en una línea por tanda

- 🟩 **Tanda 1 (leer)**: cinco pasos, `V4`–`V7`. `#120(g)`–`(m)`.
- 🟩 **Tanda 2 (gestionar)**: contraseña y sesiones, perfil, y los dos derechos RGPD. `V8`–`V10`.
  `#120(n)`–`(s)`. ▶ **Su efecto de fondo**: de los **cuatro** sitios de la web que reconfirmaban
  contraseña **sin techo**, no queda ninguno — y no se escribió una línea de limitador en la web.
- 🟩 **Tanda 3 (retirar)**: primero se publicó lo que solo sabía la página —el desglose financiero y
  los consentimientos— y **después** se borró. `V11`–`V13`. `#120(t)`, `#120(u)`.
  ▶ **La auditoría fue la mitad del trabajo**: encontró **dos huecos reales** que nadie vigilaba —el
  reintento de la API sin techo comprobado y las líneas fantasma que solo la API publicaba— y ambos
  se cerraron antes de borrar nada.

### Cinco cosas que condicionan lo que toques aquí

| | |
|---|---|
| **Las PUERTAS** | `/mi-cuenta` y `/mi-cuenta/pedidos` abren el cajón en su zona. ⚠️ La zona se aplica en `bootSpaEngine()` **y no en `open()`**: el cajón que llega por una puerta **nace abierto**. Es el camino que ya dejó un hueco vacío en `#59(b)` y volvió a morder en `#120(u)` |
| ⚠️ **La red** | **NO es el diff de árbol** (`#120(e)`): es paridad de DATOS contra la API + navegador. `render-sidebar.mjs` no importa la raíz |
| ⚠️ **La cadena flex** | `.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll` son **hijos DIRECTOS**: un envoltorio router la parte y **ningún test lo ve** (`specs/area-cliente.md` §4.9) |
| ⚠️ **Los presupuestos** | ⚠️⚠️ **NO se copian aquí los números: viven en su test y esta tabla ya envejeció una vez.** Decía 190/4.800 cuando el código llevaba un día en **199 KiB** y **5.720 B** —la sesión de la auth los subió y nadie refrescó esta fila (medido el 2026-08-23)—. Los VIVOS son `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y los dos techos de `SidebarMountTest` (anónimo y con sesión), cada uno con su ledger al lado. Lo siguiente que entre los sube **a propósito, con su medida y su párrafo**, y al cerrar **baja a lo medido** |
| ⚠️ **El techo de componentes** | 40 líneas por `.vue`. Ya obligó al rediseño correcto una vez (`#120(r)`): si vuelve a apretar, la pregunta es qué sobra ahí, no cuánto subirlo |

⚠️ **Y una regla de trabajo que esta fase dejó pagada con tres fallos**: en un refactor o una feature
del ORQUESTADOR, **el contrato de árbol no es red** —`render-sidebar.mjs` no importa la raíz y su
comentario dice por qué—. La red es el NAVEGADOR. Receta del andamio, con sus trampas medidas, en
`VERIFICACION-E2E-CAJON.md` §5.bis y §5.quater.

### ❗ Bloqueado, y lo desbloquea el owner

❗❗ **EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El crontab está instalado y correcto y
`schedule:run` funciona a mano, pero **no hay demonio cron en el contenedor del sitio**. Medido: 6
avisos con 24 h en `jobs` y `attempts = 0`, y un pedido 24 h sin caducar que `orders:expire` caducó al
instante al lanzarlo a mano.
⚠️ **RE-CONFIRMADO en los DOS despliegues del 2026-08-25** (y antes el 2026-08-23): el propio
`deploy.sh` reinstaló el crontab
—«1 entrada, sin duplicados»— y su verificación de salud volvió a avisar de que **no se ve ningún
demonio cron**. Las cinco tareas están REGISTRADAS en la app y no hay jobs varados, así que lo único
que falta es quien las dispare.
▶ **La entrada exacta que hay que poner en el panel de Enhance, y cómo comprobar que funciona, están
en `ENTORNOS.md` §4.** Mientras tanto se dispara a mano:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
⚠️ Obliga a matizar `#110`: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero **allí
nunca se ha ejercitado la caducidad de pedidos ni el envío diferido de correo**.

▶ Y luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el que se usó
para medir lo retiró el owner.

⚠️ **SIETE trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su
sitio y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`. ⚠️ **Y a veces el gate ni lo declara**: el contador
  de `SidebarComponentBudgetTest` miraba `api.get|post` y no los tres verbos que llegaron después
  (`#120(s)`). Al añadir una pieza, relee qué mide su guarda — no si sigue verde.
- **Un campo que NUNCA lleva valor se lee como un dato y no lo es**: el export publicaba
  `tickets[].code` con una columna que no existe, desde el commit fundacional (`#120(s)`).
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**: dos señales de
  salud en verde con el scheduler muerto (`#115`).
- **Que las piezas se llamen no significa que el valor LLEGUE**, y que los dos extremos estén probados
  no significa que el medio esté cableado: `#117`, `#118` y `#119(f)` son tres fallos vivos distintos
  de la misma familia, todos encontrados en un navegador y ninguno por la suite.
- ✅ **El modo `embedded` de `auth.login`/`auth.register` MURIÓ el 2026-08-23** (`#122`), como `#112(f)`
  anticipó: era la referencia de dos paridades de árbol y las dos se fueron con él. De sus 14 casos,
  **8 no comparaban superficies** y están mudados a `SidebarMountTest`, `Api\V1\AuthRegistrationTest`
  y `SidebarAntiBotTest` — uno de ellos llevaba dentro el techo del payload del montaje.

### Lo que NO depende de nosotros

- ✅ **Servidor de PRUEBAS**: `jumpweb.sites.aelium.app` (`#76`), **desplegado y sirviendo** (`#106`).
  **0 LIVE · 0 PRODUCCIÓN.** Las cuatro cosas que estaban atascadas por falta de URL pública
  —Turnstile, S2S, 3DS y móvil— **están verificadas** (`#110`).
  ⚠️ **Dos diferencias con local que siguen condicionando el trabajo** (`ENTORNOS.md` §4): la BD es
  **MariaDB 11.4, no MySQL 8.4** —«verificado en staging» **NO** equivale a «verificado en MySQL», y
  ninguna conclusión sobre concurrencia sale de ahí— y **no hay node/npm**, así que los assets se
  construyen fuera y se suben compilados.
  ⚠️ **El bucle de trabajo sigue siendo LOCAL**; staging se toca EN BLOQUE y con guion escrito
  (`VERIFICACION-E2E-CAJON.md` §5.ter).
- **Pendiente del owner** (❗), por gravedad:
  1. ❗❗ **ACTIVAR LAS TAREAS PROGRAMADAS** del sitio en el panel de Enhance — sin cron no hay envío de
     correo ni caducidad de pedidos (`#115`). La entrada exacta, en `ENTORNOS.md` §4.
  2. Un **token nuevo de la API del panel** para `scripts/provision.sh` (el de medir se retiró).
  3. ❗ **Una pasada por el SANDBOX de Redsys para el reembolso REST de punta a punta**: que
     `Redsys::executeRefund()` hable de verdad con la pasarela y su respuesta se parsee bien. La
     auditoría del desglose lo dobló a propósito —una auditoría de dinero no hace llamadas externas—
     y **local no lo puede probar**. Herramienta canónica: `php artisan redsys:verify-sandbox`
     (`PAY-08`). Es el único hueco de esa auditoría que no se cerró.
  3. 2FA del panel · backlog de producto de Fase 6.
  ✅ Resueltos: el acceso SSH del 2º puesto (2026-08-21) · **las dos comprobaciones de navegador que
  cerraban `4.7`** (2026-08-22) · **la spec del área de cliente**, validada el 2026-08-22 —modelo de
  navegación y modo `account`, `#120(d)`— · y las **cuatro decisiones de producto** que el área pidió
  sobre la marcha: publicar la entrada de verdad en el export (`#120(s)`), no llevar «Cerrar sesión»
  al índice, publicar los consentimientos y enseñar las respuestas del pack bajo demanda (`#120(t)`,
  `#120(u)`).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

## ▶ El MAPA del cajón SPA — **movido**

Vive en `docs/specs/sidebar-spa.md` §8 desde el 2026-08-25. Un mapa de ficheros es referencia para
quien toca el cajón, no «dónde estamos»: en la foto viva solo engordaba la carga obligatoria de cada
arranque.

## ▶ Lo que NO hay que reimplementar (el terreno del dinero está entero)

- **Precio** → `Booking\Contracts\CartPricing`. `CartPricerTest` compara sus importes con el pedido
  REAL: es el espejo verificado de `OrderCreator`.
- **Admisión** → `Booking\Contracts\ReservationAdmission`: pausa, tope de pendientes, frecuencia y la
  extensión atómica del hold. `POST orders` llama a `admitReservation()`, que CONSUME ficha; el
  reintento, a `admitPaymentRetry()`.
- **Creación** → `OrderCreator` (`AFORO-01`: el lock con `zone_id` literal es la PRIMERA sentencia de la
  transacción; no metas ningún SELECT antes).
- **Ida del pago** → `Booking\Contracts\PaymentInitiation` (`open()`/`reopen()`), implementado por
  `Payments\Services\PaymentInitiator`. Lanza `PaymentInitiationException`, que vive en
  `Payments\Contracts` porque es lo que lanza el puerto.
- **LA SECUENCIA** → `Booking\Contracts\ReservationCheckout` sobre `CheckoutOrchestrator` (`#37`). **Es
  el sitio ÚNICO donde vive el orden**: admitir consumiendo ficha → crear con la ventana de retención
  (`AFORO-10`) → abrir el cobro sobre el pedido persistido → soltarlo **solo** si era el primer intento.
  ⚠️ **No lo reescribas en una superficie nueva**: pide `start()`/`retry()` y traduce el resultado
  (`CheckoutSequenceTest` lo prohíbe ejecutablemente fuera de `app/Domain`).
  ⚠️ **No envuelvas la secuencia en una transacción**: el rastro de incidencia haría rollback (`PAY-05`)
  y el lock de franjas quedaría sostenido durante la firma (`AFORO-01`).
- **Oferta de fechas/horas** → `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer` (`AFORO-02`), con
  la cesta descontada. Publica DOS números: `available` para MOSTRAR y `max_quantity` para ACOTAR el
  selector — en un pack **no coinciden**.
- **Desenlace del pago** → `GET orders/{code}/payment-status`, con dos ejes (`order_status` ·
  `payment_status`) y el motivo del rechazo como código y como texto.
- **Errores de negocio** → `Http\Api\ReservationErrorMap`. Añadir un código es evolutivo; **partir uno
  existente rompe a todo cliente ramificado sobre él**.
- **La cesta que viaja por la API** → `Http\Api\CartPayload`, una sola forma para los tres endpoints.

⚠️ **Tres trampas de la API que la SPA pisa** (las **87** medidas están en `specs/api-v1.md` §10):
`Origin`/`Referer` hacen falta en TODAS las peticiones stateful, no solo en el login (§10.sexies 28) ·
la disponibilidad LLEVA la cesta y publica dos números (§10.nonies 46) · **la firma cubre la URL
EXACTA**, así que las URLs de API se firman aparte (§10.duodecies 64).

**Pendiente que hereda Fase 6** (`#35`): un cliente NATIVO averigua el desenlace del pago **solo
sondeando** `payment-status`, y eso exige `redsys_merchant_url` configurada — sin ella y con terminal
data-less, el pedido caducaría con la tarjeta ya cobrada (`PAY-02`).

## ▶ Índice de la Fase 4 — **retirado**

⚠️ Eran 83 líneas que **duplicaban `00-REFACTOR.md`**, y la Fase 4 está CERRADA. Verificado antes de
borrar: los once pasos (`4.0a` … `4.7`) están en el tracker, cada uno con más detalle del que había
aquí. Una foto viva que repite el tracker es una segunda verdad esperando a divergir —y `CONVENCIONES`
dice que ESTADO **resume** el tracker y nunca lo contradice—.

▶ Para el detalle paso a paso: `docs/00-REFACTOR.md`, sección **Fase 4**.
