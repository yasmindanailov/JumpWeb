# Estado del proyecto — foto viva

> Documento corto (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> Última actualización: **2026-08-13**.

## ▶ Dónde estamos
**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ CERRADA · Fase 3 (API v1) 🟦 — diseño v2 revisado y SIN bloqueantes; árbol saneado; listo para implementar el paso 0.**
- Suite **2186 en verde** (8186 aserciones, `--parallel` ~58s tras el saneado de dependencias) · Pint limpio · `docs-check`
  verde · `redsys:verify-concurrency` y `purchase:verify-oversell` EN VERDE sobre MySQL real.
  La corrida SECUENCIAL completa se verificó en el paso 2 (2157/2157 en 557s): los dos modos
  dan lo mismo. El contador «PHPUnit Notices: 1» sale solo en la paralela completa y es del
  runner, no del código (ver `TESTING.md`).
- **Fase 2 (modularización) CERRADA** en 7 pasos, 2026-08-12. Resumen: `app/Models` y
  `app/Support` **ya no existen**; el dominio vive en `app/Domain/<Contexto>/` con 5 módulos
  (Platform · Content · Identity · Payments · Booking) y frontera EJECUTABLE
  (`ModuleBoundariesTest` + `ModuleContractsTest`, guardas verificadas por mutación).
  **No repitas esta lectura**: el detalle paso a paso está en `00-REFACTOR.md` (checkboxes),
  `DECISIONES #13`–`#20` (el porqué de cada decisión) y `docs/specs/modulos-dominio.md`
  §4.bis–§4.octies («lo que el código enseñó» en cada paso).
  Lo que SÍ necesitas saber al tocar código hoy:
  · **Herramienta**: `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES
    (llamadas a clases del mismo namespace, sin `use`). Fueron la única causa real de rotura.
  · **Criterio de frontera**: *recibir* una entidad de otro módulo es costura de BD; *consultar*
    sus datos o *repetir* sus reglas exige contrato.
  · **Baselines del arch-test**: `LEGACY`/`PENDING`/`DEFERRED` vacías; `SEAM` solo con costura
    documentada. Todas **solo encogen**: añadir una entrada es señal de que algo está mal hecho.
  · **Deuda anotada, no resuelta**: `RedsysReturnHandler` conduce el ciclo de vida de la Order
    (`PAY-01`/`PAY-03`) y la supresión RGPD cruza contextos — ambos candidatos a evento de
    dominio, en `SEAM` con su porqué.
- ⚠️ **NOTA DE DESPLIEGUE permanente (Fase 2)**: las migraciones deben correr **ANTES** de servir
  tráfico —la conversión del morphMap dejó de ser comodidad y es **requisito**: sin ella, leer un
  `payable`/`priceable`/`target` antiguo revienta— y hay que **drenar la cola + `queue:restart`**
  (los payloads serializados llevaban los FQCN viejos).
- **Fase 1 cerrada** (2026-08-12): marca a 6 líneas intencionales, prefijo de pedidos =
  setting `sales.order_prefix` (default `R-`), semilla neutra «SaltoPark», jurisdicción
  legal por token, wordmark data-driven (`DECISIONES #12`).
- Sistema documental y protocolo completos (`DECISIONES #10`/`#11`): gate en pre-push (solo
  `main`), skills arranque/dod/cierre, permisos en 3 capas con `guard-bash`, invariantes con
  ID, GLOSARIO · DEUDA · INSTALACION-CLIENTE · TESTING §datos · specs/.
- Entorno local: web `8081` · MySQL `3308` · Mailpit `8028`; BD dev sembrada con SaltoPark
  (usuarios dev `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`).
  La BD dev ya corre la migración del morphMap.

## ▶ Próximo paso
**Fase 3 — API v1. El diseño está en `docs/specs/api-v1.md` (v2, 🟦), ya revisado.**

**Lo que pasó con la v1**: 3 revisores adversariales independientes la devolvieron con
**15 hallazgos GRAVE** (2 veredictos «insuficiente»). Tenía cuatro afirmaciones falsas y una
premisa errónea. La v2 los incorpora todos; **§8 del spec dice qué cambió y por qué** — léelo
antes que nada, porque varios hallazgos cambian el diseño, no el texto.

**Los tres que más cambian el trabajo:**
1. **Hay reglas de servidor FUERA del dominio**: la pausa de reservas (#218) y los topes
   anti-abuso (`MAX_PENDING_PER_USER`, `RESERVATIONS_PER_MINUTE`, que cierran el hallazgo E del
   origen: *agotar el aforo del día sin pagar*) viven en `Purchase.php`, no en `OrderCreator`.
   Exponer `POST /orders` sin extraerlas primero reabre las dos. Mismo patrón que
   `MAX_LINES_PER_CART` en Fase 2.
2. **La disponibilidad depende de la CESTA** (`SlotOffer::offerableTimes` descuenta tus propios
   ocupantes provisionales) → el endpoint la lleva, y el test de paridad debe usar cesta NO vacía:
   con cesta vacía pasaba por construcción.
3. **Falta el endpoint de presupuesto**: sin él el cliente no puede mostrar total ni señal sin
   crear un pedido que ya bloquea aforo.

**La fase va PARTIDA en 6 pasos** (spec §9), como se hizo con la modularización. El paso 0 son
cimientos sin negocio y cierra la instalación de dependencias.

**Decidido y listo (nada bloquea ya):**
- **Dependencias** (`DECISIONES #21`): Sanctum ^4.3 runtime + Spectator ^3.0 y `symfony/yaml` en
  dev. Scramble descartado (generar la doc desde el código invierte la relación de contrato).
- **Árbol saneado** (`DECISIONES #22`): los 26 avisos de seguridad → **0** (`composer audit` y
  `npm audit`), sin tocar restricciones ni añadir paquetes. Framework 13.25.0 · Filament 5.7.6 ·
  Livewire 4.4.0. Verificado con suite, Pint (sin churn), docs-check, los dos verificadores sobre
  MySQL, superficies y **generación real de PDF** con dompdf 3.1.6.
- **Anti-bot en cliente nativo** (`DECISIONES #23`): NO se relaja nada. El Turnstile del registro
  es `SEGURIDAD` regla 5 (no `SEC-06`) y es data-driven; el consumidor de Fase 3/4 es la SPA, que
  ES un navegador. La app nativa es Fase 6 y allí se decide, con las tres salidas ya escritas.

**Empieza por el PASO 0 del spec §9** (cimientos sin negocio): `api:` en `withRouting`, grupo de
middleware (§4.7, incluida la decisión escrita sobre el kill-switch de mantenimiento), Sanctum,
sobre de error, `GET me`, esqueleto OpenAPI + su test verificado por mutación, y **ampliar
`CRITICAL_RE` del pre-push** para que los controladores de checkout de API disparen `VERIFY_CONC`.

**Pendiente del owner** (❗): 2FA del panel (sin plan — `DEUDA.md`) · mecanismo del primer
admin de producción (`INSTALACION-CLIENTE.md` §5) · backlog de producto de Fase 6.

## Entorno (local)
- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` ·
  Mailpit `localhost:8028` · MySQL `localhost:3308`. Siempre `-u sail` en `exec`.
- Si tocas `OrderCreator`/`RedsysReturnHandler`/`SlotGenerator`: el push exige
  `VERIFY_CONC=1` tras correr los comandos de INVARIANTES §6.

## Herencia
Base heredada del origen: 30 modelos · 71 migraciones · 17 Filament Resources · Redsys
(sandbox) · suite **2132** verde al importarla (2026-08-12). Hoy: **2186** tests, con los de
Fases 1–2 (contratos, frontera de módulos, factories, morphMap).
Stack al día tras el saneado del 2026-08-13: Laravel **13.25** · Filament **5.7** · Livewire
**4.4** · PHPUnit 12.5 · 0 avisos de seguridad (`composer audit` y `npm audit`).
