# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) 🟦 — pasos 0 y 1a CERRADOS; toca el paso 1b (catálogo).**
- Suite **2242 en verde** (8398 aserciones, `--parallel` ~59 s) · Pint limpio (688 ficheros) ·
  `docs-check` verde · `composer audit` y `npm audit` en **0** · `npm run build` OK.
  El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner, no del
  código (ver `TESTING.md`).
- **Aviso para el próximo cierre** (`DECISIONES #25`): el árbol npm pasó de 0 a **5 avisos (2
  críticas, 3 altas)** en unas horas SIN que `package.json` ni el lock cambiaran — avisos
  publicados en el intervalo, todos en herramientas de build. Se sanearon con `npm audit fix` sin
  `--force` (Vite 8.2.1). La lección: `composer audit`/`npm audit` son verificación de CIERRE, no
  un trámite de instalación.
- **Los verificadores de concurrencia NO se han corrido en esta sesión y no hacía falta**: los pasos
  0 y 1a no tocan `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` ni ningún controlador de
  checkout —son cimientos y lectura—, y el gate del `pre-push` lo confirma. Su último verde sobre
  MySQL real es del 2026-08-13 (`DECISIONES #22`). En cuanto el paso 2 toque la política de
  admisión, vuelven a ser obligatorios.
- **Fase 2 (modularización) CERRADA** en 7 pasos, 2026-08-12: el dominio vive en
  `app/Domain/<Contexto>/` con 5 módulos (Platform · Content · Identity · Payments · Booking) y
  frontera EJECUTABLE. **No repitas esa lectura**: el detalle está en `00-REFACTOR.md`,
  `DECISIONES #13`–`#20` y `docs/specs/modulos-dominio.md`. Lo que sí necesitas al tocar código hoy:
  · **Herramienta**: `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES
    (llamadas a clases del mismo namespace, sin `use`). Fueron la única causa real de rotura.
  · **Criterio de frontera**: *recibir* una entidad de otro módulo es costura de BD; *consultar*
    sus datos o *repetir* sus reglas exige contrato.
  · **Baselines del arch-test**: `LEGACY`/`PENDING`/`DEFERRED` vacías; `SEAM` solo con costura
    documentada. Todas **solo encogen**: añadir una entrada es señal de que algo está mal hecho.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones deben correr **ANTES** de servir tráfico
  (la conversión del morphMap de Fase 2 es requisito, no comodidad: sin ella, leer un
  `payable`/`priceable`/`target` antiguo revienta) y hay que **drenar la cola + `queue:restart`**
  (los payloads serializados llevaban los FQCN viejos).
- Entorno local: web `8081` · MySQL `3308` · Mailpit `8028`; BD dev sembrada con SaltoPark
  (usuarios dev `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`; ojo: la BD
  dev arrastra ADEMÁS el par `…@jumpingjump.test` del import, así que `User::first()` devuelve uno
  del origen — usa el email completo al probar a mano).
  ⚠️ Corregido el 2026-08-13 en el `.env` local (no versionado): tenía `APP_URL=…:8080` con
  `APP_PORT=8081` — enlaces absolutos de correo, URLs firmadas y la derivación de CORS y de los
  dominios stateful de Sanctum salían con el puerto equivocado. **Si clonas de cero, comprueba que
  `APP_URL` coincide con `APP_PORT`.**

## ▶ Qué hay hecho de la API (pasos 0 y 1a — `DECISIONES #24` y `#26`)
Cimientos + la lectura de la cuenta. Lo que existe y funciona (verificado con `curl`, con la suite y
**contra MySQL real** ejerciendo el pipeline HTTP completo):
- `routes/api.php` bajo `/api/v1`, con **fuente única del prefijo** (`ApiSurface::PREFIX`).
- **Grupo `api` declarado pieza a pieza** en `bootstrap/app.php` — el orden ES el diseño y está
  comentado allí: `SecurityHeaders` → Sanctum stateful → `ApiLocale` → `EnsureSiteAvailable`
  (503 en JSON) → `no-store` autenticado → `throttle:api` → `SubstituteBindings`.
- **Sobre de error único** `{error:{code,message,params?,fields?}}` con códigos estables
  (`ApiErrorCode`) desacoplados de las claves i18n, y mensajes en `lang/<idioma>/api.php` (es/en/fr).
- `GET /api/v1/me` + **OpenAPI escrito a mano** en `openapi/v1.yaml`, con validación de la
  respuesta REAL (Spectator) y guardas de contrato en las dos direcciones.
- Sanctum instalado con caducidad de token (30 días) y poda semanal, **sin emisor todavía**.
- Guardas nuevas, las tres **verificadas por mutación**: `ApiBoundariesTest` (nada de negocio en un
  controlador), `ApiContractTest` (el documento manda) y `CriticalPathGateTest` (el `CRITICAL_RE`
  del `pre-push`, ampliado a los controladores de checkout de API).
- **Paso 1a**: `GET me/reservations` (sobre el contrato `CustomerReservations`, sin reimplementar su
  filtrado) y `GET me/orders` (paginado, **todos los estados**, líneas y complementos anidados).
  Nace `ApiCollection`: la forma única de lista `data` + `meta`.
- El contrato ya ha ganado su sueldo dos veces: destapó que apoyarse en `ResourceCollection` producía
  `data.data` con dos `meta`, y que el campo `online_due_cents` mentía en su nombre (es el importe
  que se cobra online, no lo pendiente → `online_amount_cents`).

## ▶ Próximo paso
**Fase 3 · paso 1b — CATÁLOGO por API** (`catalog/zones`, `catalog/products`,
`catalog/products/{id}`): read-model NUEVO en Booking, extraído de `Purchase::render()` (spec §4.4).

Es la parte difícil del paso 1 y por eso se separó. **Lo ya medido en la sesión del 2026-08-13, para
que no haya que redescubrirlo:**
- La fuente actual es `Purchase::catalogTypes()` (productos seleccionables: `entry` + `pack`, sobre
  `allSellableTypes()` con los scopes `sellable()` + `inOperationalZone()`) y
  `Purchase::catalogSection()`, que **mezcla dominio y presentación**. De sus campos, `search` (una
  cadena normalizada que filtra el buscador progresivo EN CLIENTE) y `zone_anchor` (el slug de zona
  solo en el primer ítem, para el ancla del deep-link de la landing) son artefactos de la web SSR y
  **no pertenecen a un read-model de dominio**: la API expone los datos y cada cliente construye su
  índice de búsqueda. Los demás sí son dominio: `from` (`fromPriceCents`, un «precio desde», NO un
  precio real — cuidado al nombrarlo), `badge`, `features`, `deposit_label`, `period_label`,
  `is_pack`, `featured`.
- La **config de complementos** NO hay que extraerla: `AddonResolver::viewModel()` ya es la fuente
  única y la comparte con el alta manual del panel. El read-model la consume, no la reescribe.
- `event_fields` sale de `TicketType::eventFields(?string $stage)`, con dos etapas
  (`EVENT_STAGE_BOOKING` / `EVENT_STAGE_POSTFORM`): decidir cuál expone el catálogo.
- **Dilema de nombre de controlador**: `Availability*` dispara el gate de concurrencia del
  `pre-push` (`#24i`). Un catálogo de solo lectura no debería exigir los verificadores; la
  disponibilidad del paso 4 sí. Mismo criterio que llevó a `MeOrdersController` (`#26e`).

**Antes de escribir código, lee `docs/specs/api-v1.md` §10 y §10.bis** («lo que el código enseñó»):
doce puntos medidos al implementar los pasos 0 y 1a, varios de ellos aplicables directamente al 1b
—todo esquema nuevo nace con `additionalProperties: false` + `required` completo, `null` va DENTRO
del `enum` en OpenAPI 3.0, y toda lista usa `ApiCollection`—.

Después: paso 2 (extraer política de admisión e ida de pago — **aquí vuelven `VERIFY_CONC=1` y los
dos verificadores**) · paso 3 (auth + revocación de tokens) · paso 4 (el dinero) · paso 5 (post-form).
El corte completo está en el spec §9.

**Pendiente del owner** (❗): 2FA del panel (sin plan — `DEUDA.md`) · mecanismo del primer admin de
producción (`INSTALACION-CLIENTE.md` §5) · backlog de producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- Si tocas `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator` **o un controlador de API de
  pedidos/pagos/checkout/quote/disponibilidad**: el push exige `VERIFY_CONC=1` tras correr los
  comandos de INVARIANTES §6.

## Herencia
Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys
en sandbox y suite **2132** verde al importarla.
Recuento VIVO (lo verifica `docs-check` contra el código): 30 modelos · 72 migraciones ·
17 Filament Resources · **2242** tests. La migración añadida es `personal_access_tokens` (Sanctum).
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
