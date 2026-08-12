# Deuda técnica — registro único

> Estado: vivo · Última actualización: 2026-08-12 ·
> Verificado contra código: 2026-08-12 (cada ítem re-medido; los ya inexistentes, retirados) ·
> Se invalida si: una fase retira un ítem sin actualizar su fila.

Vista de conjunto con severidad; el detalle vive en el doc citado (aquí no se duplica).
«Sin plan» = ninguna fase del tracker la retira hoy: candidatas a backlog o a decisión.

## Alta

| Ítem | Qué (medido) | Dónde muerde | La retira |
|---|---|---|---|
| God-class `ViewOrder` | `app/Filament/Resources/Orders/Pages/ViewOrder.php`, 5.028 líneas; concentra edición de pedido, reembolsos y `lockZoneDaySlots` | Toda operación de dinero/aforo del panel roza INVARIANTES §1/§2 | Fase 2 |
| God-class `Purchase` + puente Alpine | `app/Livewire/Tickets/Purchase.php`, 2.049 líneas; `Alpine.store('purchase')` sincronizado a mano con `$wire.step` | Todo el checkout web; el puente depende de sincronía manual JS↔Livewire | Fase 4 (muere con la SPA) |
| ~~Sin morphMap~~ | **RETIRADA en Fase 2** (2026-08-12): `enforceMorphMap` con alias para los 30 modelos + migración de datos + barrido de `::class` en columnas morph + `MorphMapTest` (verificado también en MySQL dev: 0 FQCN restantes) | — | ✅ hecha |
| Datos de negocio del cliente origen | `LegalContent` (jurisdicción quemada ES/EN/FR) + `ProductionSeeder` (seed real: dirección, mapa, tarifas) | Riesgo legal directo: una instalación nueva hereda textos legales de otro negocio | Fase 1 |

## Media

| Ítem | Qué (medido) | Dónde muerde | La retira |
|---|---|---|---|
| Suite ciega a carreras InnoDB | SQLite `:memory:`; doble-cobro/sobreventa solo en comandos on-demand; el pre-push exige el flag `VERIFY_CONC=1`, no ejecuta los comandos | Un cambio de locks pasa verde y sobrevende en producción | Sin plan (mitigada por guarda del hook) |
| `$guarded = []` masivo | 22 de 30 modelos abiertos; solo 6 con `$fillable`; `User` sin ninguno | Mass assignment en código nuevo; `preventSilentlyDiscardingAttributes` solo en no-prod | Sin plan |
| Branding residual | **RETIRADO en Fase 1** (2026-08-12): quedan solo 6 líneas intencionales (regla 7, guard-bash, cron de ejemplo neutro, y el `assertDontSee('jumpingjump.com')` que actúa de GUARD) + fixtures `JJ-…` autoconsistentes en tests (cosmético, sin valor de marca) y códigos forenses en comentarios | — | ✅ hecha |
| `app/Support` cajón desastre | 57 ficheros PHP (55 planos + 2 vendorizados) mezclando dinero, aforo, CMS, settings y reparaciones legacy | Sin fronteras de módulo: todo importa a todo | Fase 2 |
| Composer global `'*'` | `View::composer('*')` vigente, memoizado por request (sin memo: ~1.900 queries/GET) | El rendimiento de toda la web pende de un memo; acopla vistas al provider | Fase 5 |
| **2FA de admin inexistente** | Ninguna implementación; solo un comentario «queda para sus sub-fases» en la página Settings | Panel con PII de menores + reembolsos protegido solo por contraseña | **Sin plan — decisión de owner** |
| Hueco test: alcance del lock zona/día | `lockZoneDaySlots` sin assert de ALCANCE (SQLite no reproduce la carrera) | Fase 2 podría estrechar el lock sin que nada falle → sobrellenado | Sin plan (INVARIANTES AFORO-05 ⚠️) |
| Hueco test: no-legibilidad de secretos | Solo se asevera la escritura; nada asevera que el form no CARGUE `redsys_secret_key` | Una regresión pintaría el secreto en el HTML del panel | Sin plan (INVARIANTES SEC-11 ⚠️) |
| Una sola factory | `database/factories/` = solo `UserFactory` para 30 modelos y 213 ficheros de test | Fixtures a mano (~1.100 `::create`), helpers `admin()`/`staff()` duplicados ×36 | Sin plan (ver `TESTING.md` §datos) |
| Vocabulario de sector en código | `PuertaSettings`, `ParkSchedule`, `ParkRule`, waiver, zonas/cumpleaños en clases, rutas y BD | Multi-sector exige renombrar clases y valores morph (se encadena con morphMap) | Fase 1 (decisión) + Fase 2 |
| Migraciones con lógica de datos del origen | 9 de 70 migraciones con backfills/seeds + 4 clases `Legacy*`/`*Backfill` vivas en Support | Decisiones del origen incrustadas en el esquema; ruido en instalación limpia | Sin plan |

## Baja

| Ítem | Qué (medido) | Dónde muerde | La retira |
|---|---|---|---|
| Hueco test: throttle rutas Redsys | `throttle:120,1` vigente; 0 asserts | Quitarlo pasa verde → amplificación en el retorno de pago | Sin plan (PAY-15 ⚠️) |
| Hueco test: `after_commit` de la cola | `after_commit => true` vigente; 0 asserts | Si regresa: emails de pedidos cuya txn rollbackea | Sin plan (PAY-14) |
| Hueco test: frontera de fecha UTC↔Madrid | `DisplayTimeTest` no cruza medianoche UTC | Bug latente de «hoy operativo» 22:00–00:00 Madrid en verano | Sin plan (AFORO-09 ⚠️) |
| Hueco test: gate no-prod de `tableExists` | Código vigente; 0 asserts | +4 queries/request al hot path si regresa | Sin plan (PERF-03 ⚠️) |
| Hueco test: `hasPositivePrice` 0-queries | Solo cobertura indirecta (presupuesto de la home) | N+1 enmascarado hasta agotar el presupuesto | Sin plan (PERF-04) |
| `slots.seats_taken` muerta | Columna sin ningún escritor; el propio panel advierte «NUNCA seats_taken» | Trampa para agentes: parece la verdad del aforo y no lo es | Sin plan (candidata a drop) |
| `rooms` sin uso | Modelo+tabla+seed placeholder; cero consumo en flujos | Estructura muerta que confunde el diseño de Fase 2 | Sin plan (candidata a drop) |
| Ciclo de `tickets` amputado | Solo `STATUS_PURCHASED`; sin canje digital (`qr_token` sin lector) | Una puerta digital (Fase 6) parte de un ciclo mutilado | Sin plan (¿backlog F6?) |
| `Order::STATUS_REFUNDED` legacy | Constante + ~10 usos: 5 de dominio (`OrderRefundFlags`, `Order`) y 5 de presentación (badges/filtros Filament) | Doble semántica status vs `refunded_at` que todo lector debe conocer | Sin plan |
| FKs ausentes a propósito | `ticket_types.zone_id` y `order_items.parent_item_id` sin constraint (limitación ALTER de SQLite) | Integridad solo en app; huérfanos posibles ante bugs | Sin plan |
| `seasons.name` sin i18n | Sin cast ni `HasTranslations` (asumida etiqueta interna) | Inconsistencia con el resto del CMS | Sin plan |
| CSP laxa + HSTS pendientes | `'unsafe-inline'/'unsafe-eval'` (los exigen Alpine/Livewire); HSTS no emitido | En el primer despliegue real de JumpWeb | Condición: despliegue propio (`SEGURIDAD.md`) |
| Slugs públicos en español | `/mi-cuenta`, `/cumpleanos`… (`routes/web.php`) | Instalaciones no hispanohablantes | Fase 1 (decisión pendiente) |
| Wordmark de emails no data-driven | El header de mail usa `config('app.name')` ¡con fallback literal de la marca origen! | Emails con marca distinta a la del panel | Fase 1 |

## Ya no es deuda (verificado)
`prices.deposit_cents` (columna eliminada) · `event_packages` (absorbida por packs) ·
`contact_messages` (tabla+modelo+resource eliminados) · marcadores TODO/FIXME en `app/`
(0 reales: los matches son «TODO/TODOS» en español) · `ci.yml` (retirado a propósito,
`DECISIONES #9`).
