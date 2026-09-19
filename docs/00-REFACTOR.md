# JumpWeb — tracker de fases (VIVO)

> Última actualización: **2026-09-18** · Leyenda: ⬜ pendiente · 🟦 en curso · ✅ hecho · ❗ bloqueado.
> Regla: **la suite en verde es la red** — ninguna fase se cierra con tests rotos. Los marcadores de cabecera
> MANDAN sobre cualquier otro documento (`docs-check`, check 7; `DECISIONES #10`). Techo 16 KB (check 10,
> `#621`): aquí van fases y casillas, no narrativa; el porqué está en la decisión que cita cada línea y en el
> §0 de su spec. El estado por carriles: `ESTADO.md` → `carriles/<carril>.md`. El histórico de este fichero:
> `git log -p docs/00-REFACTOR.md` hasta el commit de F1.

## Visión

Convertir la base heredada (reservas de un parque de saltos, en producción) en **JumpWeb**: plataforma
white-label de reservas multi-sector con tres superficies sobre un núcleo único —landing (Blade SSR), cajón
SPA (Vue 3 + Pinia contra `/api/v1`) y panel (Filament)— y preparada para app móvil. Se preserva a toda
costa el núcleo endurecido de dinero/aforo (`OrderCreator`, `RedsysReturnHandler`, `SlotOffer`,
`AddonResolver`, con sus verificadores sobre MySQL, `INVARIANTES.md` §6) y la suite. Desde `#610` el
programa «producto e instancias» separa el producto de sus instancias.

## Fases

### PRODUCTO E INSTANCIAS 🟦 — `specs/producto-e-instancias.md` §0 y §4.9 (`#610`→`#621`)
- [x] F0 · gobierno: spec ✅ owner, `#610`–`#616`, reglas 8 y 9 de `CLAUDE.md` (`d898c76a`)
- [x] F1 · doc caliente: decisiones por centenas · contador al trailer · §0 en las 46 specs · huella 974/974 · enrutador ≤ 12 KB · carriles y `ESTADO.md` índice · tracker ≤ 16 KB · comprobación 10 (`#617`→`#621`)
- [x] F2 · capa de agente (`#623`: plugin `jumpweb-agente` en GitHub, instalado en las dos máquinas; reglas `allow` del owner, `#626`) · los cuatro defectos del mapa de frases arreglados el 18-09 (arnés 52/52, mutación 9/9, control con 419 mensajes reales) · **cerrada por el owner el 2026-09-18 (`#633`)**, que dio por revisadas las frases; las tres skills viejas del repo, retiradas
- [x] Análisis estático en el gate (`#625`, `#629`): Larastan nivel 5 con línea base de 459 y ESLint (`flat/essential` de Vue) sobre el cajón con línea base de 12, las dos con trinquete; arnés 20/20 (2026-09-18) · deuda: bajar las dos líneas base (`DEUDA.md`; los 12 del cajón son del carril del SPA)
- [x] F3 · versión (`#624`): v1.0.0 = `1272cb93` etiquetada, `CHANGELOG.md`, guarda 8 del despliegue con su arnés (9/9) y producción DICE v1.0.0 (`storage/app/version`, 2026-09-17 22:05) · el noveno despliegue (v1.1.0, 18-09) estrenó la escritura y la relectura dentro del script ✓
- [x] F4 · cajón empaquetable y login por token, contrato 1.1.0 · **CERRADA el 2026-09-19**: una página que no es del producto monta el cajón, abre y COMPRA (criterio de salida de §2·1), con la landing del producto idéntica píxel a píxel. ⚠️ El **anfitrión mínimo** de §4.4 es de F5 por diseño (`#631`), no un pendiente; y la compra de la T5 está medida en navegador pero **sin ojo del owner** todavía. Dos mitades, las dos hechas: **el token** (`specs/token-bearer.md`, `#630`: `POST /auth/tokens` y `/rotate`, ability `api-v1` en toda ruta autenticada) y **el cajón en cinco tandas** (`specs/cajon-empaquetable.md` §4, `#631`→`#637`: arranque · apertura sin Alpine · carcasa con un dueño · montaje en página ajena · hoja GENERADA · cargador con ruta estable). En navegador: sonda **42/42** (201 + salto firmado a la pasarela) y juez de maquetación en 0 diferencias sobre 1.640 nodos. El detalle de cada tanda, en su spec
- [ ] F5 · instancia PlayJump y la landing fuera, v2.0.0 · **ABIERTA el 19-09 por el CENSO** (`specs/instancia-y-landing-fuera.md` ✅, `#639`: tres decisiones del owner contestadas; el censo, en su §1). **El MENÚ, SERVIDO** (`#640`→`#646`, contrato **1.9.0**, arnés 38/38): `/site` con lista blanca —y un secreto no sale ni declarándolo—, el horario en dos rutas por cómo se cachean, las normas, los legales interpolados, los precios por tarifa, la ficha de producto y zona —cuya foto se SUBE al hueco de la instalación (`#645`), no se escribe como ruta al repo— y la CIFRA de prueba social, **sin las reseñas: su fuente está a mitad de cambio y su forma cambia con ella** (`#646`, llegan en la T2 de `google-business-profile.md`). ▶ Sigue la **T2 de F5**: el paquete de la instancia
- [ ] F6 · app nativa: spec con pila y alcance · pila decidida (`#627`): React Native + Expo en TypeScript, a confirmar con la prueba corta

### Fase 0 — Fundación ✅
- [x] Base exportada sin infraestructura del cliente origen, doc fundacional, repo, entorno local, suite verde y el gate de pre-push como CI (`#9`, `#10`).

### Fase 1 — Desbranding y generalización superficial ✅
- [x] La marca, el tema y los textos del cliente origen fuera del código; los datos de negocio a BD/panel.

### Fase 2 — Modularización del dominio ✅
- [x] Los siete pasos: Catalog&Booking · Content · Identity · Payments · Platform, con las tres guardas de frontera (`specs/modulos-dominio.md` §0, `#13`).

### Fase 3 — API v1 ✅
- [x] Los seis pasos del corte y el checkout orquestado (`specs/api-v1.md` §0, `specs/checkout-orquestado.md` §0, `#24`→`#37`).
- [x] La emisión de tokens Bearer (`POST auth/tokens`): hecha en la F4 del programa (`#630`, 2026-09-18).

### Fase 4 — Sidebar SPA ✅
- [x] De 4.0a a 4.6: los once pasos en Vue 3 + Pinia, `Purchase.php` retirado, el área de cliente y la auth dentro del cajón (`specs/sidebar-spa.md` §0, `#38`→`#123`).

### Fase 5 — Capa de contenido profesional ⬜
- [ ] Sustituir el composer global `'*'` por query services de contenido con caché.
- [ ] Theming como paquete coherente (tokens CSS + tema BD + assets por instalación).
- [ ] Contenido consumible también vía API. ▶ El programa la reencuadra (`#611`): la landing sale del producto y consume una API pública de lectura (su F5).

### Fase 6 — Móvil + features nuevas 🟦
- [x] B · Waiver con valor probatorio: código completo, revisado dos veces (`specs/waiver-probatorio.md` §0, `#160`→`#183`).
- [x] C · Menores a cargo: tandas 1–5 (`specs/menores-a-cargo.md` §0, `#191`→`#208`, `#236`, `#320`).
- [x] A · Carné QR y pantalla de puerta: código completo (`specs/identidad-qr-puerta.md` §0, `#208`, `#212`, `#217`).
- [ ] A, B y C · el OJO del owner (guiones en `VERIFICACION-E2E-CAJON.md` §5), el texto definitivo del waiver y la retención.
- [ ] D · JumpPoints y vales: diseño revisado, pendiente del ✅ del owner (`specs/lealtad-jumppoints.md` §0).
- [ ] Segundo driver de pasarela sobre el puerto de pago (Stripe u otros).
- [ ] Tokens Bearer, guía de integración móvil y la app: F4 y F6 del programa.
- [ ] El selector de menores del embudo hace demasiado ruido (`[DECIDIDO owner]`, `specs/menores-a-cargo.md` §12).
- [ ] El cajón en móvil: unidades 1–3 hechas; queda la 5 (carrito e identificación) y el ✅ del owner (`specs/cajon-en-movil.md` §0).

### El DESGLOSE de dinero que ve el cliente ✅ — 📜 su modelo de dos ejes se retiró en la T3·4 del libro (`#315`)
- [x] Las tres tandas y los cuatro defectos de lectura (`#127`→`#134`); auditadas las 25 acciones (`#132`).
- [ ] Lo que queda de `PAY-08`: un `Ds_Response=0900` REAL, que exige una autorización previa en el terminal.
- [ ] Arreglar el verificador cuyo CONTROL NEGATIVO sale en verde (`report()`).

### El LIBRO del pedido 🟦 — `specs/desglose-libro.md` §0 (`#305`→`#318`)
- [x] T1 los hechos · T2 el libro · T3·1–T3·4 las superficies y la retirada del modelo viejo · T4 el motivo manda en el reembolso · el panel plegado.
- [ ] T3·4b · el OJO del owner sobre el panel, la hoja, la puerta y los correos (V18–V22 de `VERIFICACION-E2E-CAJON.md` §5.sexies).

### El CAMBIO DE PRECIO ✅ — la línea `#145`→`#155`, cerrada el 2026-08-25
- [x] El importe elegido en «Reembolsar», la reconstrucción por precio original, cancelados con deuda reembolsables, etiquetas EN/FR y el email de la bajada.
- [ ] Ver en navegador el modal nuevo de «Reembolsar» (reactividad del campo).

### La LANDING white-label 🟦 — `specs/landing-white-label.md` §0 (`#136`→`#144`)
- [x] Tanda A cerrada (`#138`→`#143`): el paquete de tema del cliente tiene hueco.
- [ ] B · `testimonials`: aplazado por el owner; hoy resuelto como mecanismo de reseñas (`#616`).
- [ ] B · el copy al CMS (244 claves): reencuadrado por el programa, la landing sale del producto (`#611`).
- [ ] C · los servicios como PRODUCTO REAL: toca AFORO y PAY, exige spec propia y `VERIFY_CONC=1`.
- [x] Promo «−20 % online» (`#628`, chapuza declarada): precios y badge como DATO en producción (17-09) · el precio de antes tachado y el recuadro (`promo.percent`, `promo.banner.*`) en v1.1.0, desplegada y con las filas escritas (18-09, 07:25; `ENTORNOS.md` §6) · al terminar la promo: subir precios, quitar badge y borrar las filas el mismo día · después, el sistema de ofertas (`archivo/promo-precio-anterior.md`).

### La CAPA DE TEMA 🟦 — `specs/tema-por-instalacion.md` §0 (`#192`→`#280`)
- [x] Los seis mecanismos (color y superficie · forma · hero · elevación · acción · movimiento), el armazón en los doce anchos (`specs/armazon-y-menu.md` §0) y la marca del 2.º cliente.
- [ ] La pasada de NAVEGADOR del owner sobre `#195` y `#196`.
- [ ] 3 · las secciones, pieza a pieza: superado por `specs/rediseno-desde-canvas.md`.

### LA WEB · rediseño desde el canvas 🟦 — `carriles/web.md` (`#469`→`#594`)
- [x] Fases 1 y 2 (la portada, ocho secciones), Fase 3 (siete páginas) salvo `/servicios`, contenido y copys T1–T5, la pasada de vestido (dos tandas): en producción desde el 13-09 con Redsys en `live`.
- [ ] T6 de contenido (en/fr) · el título de la pestaña · la pasada de copys por página.
- [ ] Google Business Profile (`#524`, código sin empezar) · las bandas · los tres ejes que quedan de la pasada de vestido.
- [ ] `/servicios` → «Grupos»: pausada por el owner hasta su artboard (`#534`).

### CORREOS 🟦 — `specs/correos-desde-canvas.md` §0 (`#500`→`#508`)
- [x] El carril entero: 25 correos, molde, remitente, modo oscuro, bandeja, fiesta mixta y los dos del framework.
- [ ] Los cuatro ámbar (§16) y el OJO del owner en Gmail y Outlook.

### EL CAJÓN · FASE 4 DEL DISEÑO 🟦 — `carriles/spa.md` (`#550`→`#572`, el otro ordenador)
- [x] Las 25 pantallas construidas (`#550`→`#568`) · T1 y T2 de `specs/celebracion-e-invitacion.md` (`#570`, `#571`).
- [x] Desplegado el arreglo del número de invitados del post-form (defecto en producción desde `#444`) con las cuatro líneas del `client.css`: octavo despliegue, 2026-09-16 (`ENTORNOS.md` §6).
- [x] T3 · la piel del justificante (`#572`), con el ✅ del owner en vivo (17-09).
- [x] Desplegada la T3 en **v1.1.0** (18-09, noveno despliegue): la barra de firmar ya no está rota en producción.
- [x] T4 · la invitación digital, **cerrada** en seis unidades verdes (spec §10.4, `#573`→`#578`; concurrencia verificada sobre InnoDB, RGPD al día, la API). ⚠️ En producción desde v1.1.0 **con los dos interruptores APAGADOS**: encenderlos es DATO del owner.
- [x] T5 · la página pública (§4.6 y §10.5–§10.6), **las cinco unidades en el árbol y sin desplegar** (`#701`→`#706`, arneses 8/8 y 7/7): la página y sus temas, contestar, el recibo de 2 h, el flujo firmar↔invitación y el calendario. Las tres primeras, vistas por el owner. ⚠️ El `.ics` **sin probar en un teléfono real**. **§10.6·A cerrada** (`#706`): dos campos y sin prerrelleno del menor.
- [x] T6 de la celebración, el aterrizaje, **CERRADA en seis unidades** (§10.8–§10.13, `#708`→`#713`): el bloque · propuestas y adopción · «no vienen» y «no lo apuntes» (el suelo medido, sin tocar `GuestCountAdjuster`) · puerta y hoja («8 de 12» bajo el techo de 28) · el panel · el recordatorio, que **NO envía nada**; arneses 8/8 · 6/6 · 8/8 · 9/9 · 7/7 · 13/13. ▶ Encender la invitación es DATO del owner.
- [ ] T7 · los correos, **partida en tres** (§10.14, `#714`; cruza al carril de correos, avisado): **T7·1 ✅** el correo del post-form (`#715`, §10.15, arnés 8/8) · **T7·2a** el lector de «qué queda por hacer» · **T7·2b** el aviso de la víspera a las **18:00 del día antes** (`[DECIDIDO owner]`), con comando, scheduler y marca. Inventario 25 → 26.
- [ ] El OJO del owner en un teléfono de verdad · el cuaderno de entrega del cajón · el botón del sistema.

### El PANEL: la exención del menor y el ROL DE PUERTA ✅ código — `#320`
- [x] La exención del menor solo se rotula cuando es excepción; nace el rol `puerta`; «Puerta» sale del menú del admin.
- [ ] El OJO del owner en navegador (panel y puerta).

### EL PANEL DE ADMIN · UI/UX 🟦 — `specs/auditoria-panel-admin.md` §0 · `specs/asistente-crear-pedido.md` §0 · `specs/panel-navegacion.md` §0
- [x] La auditoría (`#460`), el shell (`#461`), el crítico C1 y el asistente de «Crear pedido» de tres pasos a siete (`#462`→`#467`).
- [ ] D4 de la auditoría, pendiente del detalle del owner · su segunda pasada sobre el bloque del desenlace.
- [ ] «Crear pedido» para TABLET (`[DECIDIDO owner]`, `specs/panel-navegacion.md` §9–§11): la forma existe; queda su ✅.

### EXCURSIONES DE COLEGIO 🟦 — `specs/horario-por-zona.md` §0 · `specs/precio-por-tramo.md` §0 · `specs/waiver-por-reserva.md` §0
- [x] A (horario por zona, `#322`), B (precio por tramo, `#324`), C (`#327`) y P3 (el justificante del menor invitado, `#328`→`#401`) en el árbol.
- [ ] El OJO del owner sobre las tandas · el producto de excursiones en producción es DATO (dos packs, apagados) · los tres comandos de excursiones en producción los corre el owner.

### CUMPLEAÑOS MIXTO, HORA EXTRA, COMPLEMENTOS E INVITADOS 🟦 — `specs/cumple-mixto.md` §0 · `specs/hora-extra.md` §0 · `specs/complementos-post-reserva.md` §0 · `specs/invitados-en-post-form.md` §0
- [x] El mixto entero (T0–T6, `#243`→`#299`) · la hora extra (`#410`→`#448`) · los complementos de venta posterior (`#413`) · los invitados desde el post-form (`#444`).
- [ ] El ✅ del owner sobre lo desplegado · la T4 de `#448` (doc + ojo) · el alta del producto «hora extra» en producción (DATO) · una elección menor del owner (un extra cerrado que nunca se pidió).

### LANZAMIENTO Y OPERACIÓN del 2.º cliente 🟦 — `ENTORNOS.md` §6 (`#325`→`#341`, `#404`→`#431`)
- [x] En producción y verificada (`#325`, `#326`); la operación de los primeros días (`#329`→`#341`); el tercer despliegue y la rejilla de media hora (`#420`→`#431`); go-live de Redsys (`#594`); el octavo despliegue, el último por hash (`#571`, 16-09).
- [ ] El OJO del owner sobre los TPV (rol `puerta`) · imágenes en la columna derecha del menú (`[DECIDIDO owner]`: «las que sean, que no esté vacío») · refrescar el contexto de cuenta al volver a la pestaña (toca el SPA) · EN/FR de la descarga de responsabilidad.
- [ ] Trazabilidad de los correos que salen del panel (`DEUDA.md`) · los menores en la declaración de puerta (`[DECIDIDO owner]`: solo el titular, por ahora) · monitor y menús servidos en la hoja impresa (aparcado por el owner).

### GOOGLE AUTH ✅ — cerrado y en producción desde el 2026-09-02 (`specs/auth-con-google.md` §0, `#342`→`#354`)
- [x] T1→T8·d y los siete puntos del owner. ⛔ One Tap no se construye (`#354`).
- [ ] Del owner: su ✅ definitivo y el cliente de OAuth de DESARROLLO (el de producción existe).

### LA PORTADA · FASE 2 DEL DISEÑO ⏸️ 📜 — parada y archivada (`#452`, `docs/archivo/`)
- Sus prototipos se retiraron y el carril se reabrió con el canvas (`#469`); lo medido vale como dato.

## Relación con el proyecto origen

El repo del cliente origen (`~/proyectos/jumpingjump`) queda intacto y no se toca desde sesiones de JumpWeb;
los cruces de mejoras solo por decisión explícita del owner (`DECISIONES #1`).
