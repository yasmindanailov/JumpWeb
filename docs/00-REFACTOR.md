# JumpWeb — Refactor de generalización (tracker VIVO)

> Tracker activo del refactor. Última actualización: **2026-08-12**.
> Leyenda: ⬜ pendiente · 🟦 en curso · ✅ hecho · ❗ bloqueado.
> Regla: **la suite en verde es la red** — ninguna fase se cierra con tests rotos.

## Visión

Convertir la base heredada (sistema de reservas de un parque de saltos, en producción)
en **JumpWeb**: plataforma white-label de reservas **multi-sector**, con tres superficies
bien separadas sobre un núcleo único, y preparada para app móvil.

```
┌─ Landing pública ─┐  ┌─ Sidebar de reservas ─┐  ┌─ Panel admin ─┐  ┌─ App móvil ─┐
│ Blade SSR (SEO)   │  │ SPA (login, reservas, │  │ Filament      │  │ (futura)    │
│ contenido del CMS │  │ cuenta de usuario)    │  │               │  │             │
└────────┬──────────┘  └──────────┬────────────┘  └──────┬────────┘  └──────┬──────┘
         │ capa de contenido      │        API v1 (REST · Sanctum · OpenAPI)│
         └───────────┬────────────┴───────────────┬──────┴───────────────────┘
                     ▼                            ▼
     ┌──────────────────────────────────────────────────────────────┐
     │  NÚCLEO DE DOMINIO (monolito modular)                        │
     │  Catalog&Booking · Content/CMS · Identity · Payments · Platform │
     └──────────────────────────────────────────────────────────────┘
```

**Lo que se preserva a toda costa:** el núcleo endurecido de dinero/aforo
(`OrderCreator` con locks anti-sobreventa verificados, `RedsysReturnHandler` idempotente,
`SlotOffer` fuente única de oferta, `AddonResolver`) y la **suite de 2132 tests**.

## Fases

### Fase 0 — Fundación 🟦
- [x] Exportar la base (árbol versionado + fix de aislamiento de la suite) SIN datos del
      cliente origen (deploy scripts con IP/SSH, docs, mockup) — verificado por grep.
- [x] Doc fundacional propia (README · CLAUDE.md · este tracker · ESTADO · DECISIONES).
- [x] Repo GitHub privado `JumpWeb` creado (`yasmindanailov/JumpWeb`) y push del commit fundacional.
- [x] Entorno local propio levantado (web 8081 · MySQL 3308 · Mailpit 8028), conviviendo con el
      stack origen (ambos responden 200 a la vez; verificado 2026-08-12).
- [x] **Suite completa en verde en el repo nuevo** + Pint + `npm run build` (✓ built).
      Único fallo del primer run: `MailThemeTest` asertaba el literal de la marca origen →
      hecho brand-agnostic (aserta `config('app.name')`). 2132 esperados.
- [x] **CI = gate local de pre-push** (2026-08-12, `DECISIONES #9`): GitHub Actions
      DESCARTADO (el owner no pagará facturación de GitHub); `ci.yml` eliminado. Hook
      versionado `.githooks/pre-push` (Pint repo completo + suite `--parallel`); activación
      por clon: `git config core.hooksPath .githooks`.
- [x] **Capa agent-first** (2026-08-12, `DECISIONES #7`): el repo lo desarrollan al 100%
      agentes IA → `CLAUDE.md` enrutador con tabla de contexto, `CONVENCIONES.md` (protocolo
      de agentes: DoD, handoff, empirismo), skills `/cierre-sesion` y `/dod`
      (`.claude/skills/`), permisos preconfigurados (`.claude/settings.json`).

### Fase 1 — Desbranding y generalización superficial ⬜
- [ ] Quitar «jumpingjump» del código (~161 apariciones: app 26 · resources 18 · lang 28 ·
      database 18 · tests 60 · public 7 · config 2 · package.json 1). La marca visible ya es
      data-driven (`business.name`); esto es limpieza de residuos.
- [ ] Renombrar artefactos con marca: tema mail `resources/views/vendor/mail/html/themes/jumpingjump.css`
      (+ `config('mail.markdown.theme')`), comandos `jj:*` → `app:*`, claves i18n con la marca
      (`.env*.example` ya genericizado en Fase 0).
- [ ] **Wordmark de los correos → data-driven:** el header usa `config('app.name')`; debe leer
      `business.name` (BD) como el resto de la marca (hallazgo del run fundacional).
- [ ] Semilla demo neutra (negocio de ejemplo) separada del fixture de tests; sin datos del cliente origen.
- [ ] Decidir slugs de rutas públicas (hoy en español: `/mi-cuenta`, `/cumpleanos`…):
      ¿configurables por instalación o neutros + i18n? → `DECISIONES`.
- [x] **Doc técnica portada y adaptada** (2026-08-12, `DECISIONES #8`; workflow de 14 agentes
      + verificación por grep): ARQUITECTURA · SEGURIDAD · TESTING · FLUJOS · PANEL-ADMIN ·
      REQUISITOS · MAPA-PAGINAS · OPERATIVA-SECTOR-ORIGEN · 8 docs de `sistemas/` ·
      **`MODELO-DATOS.md` regenerado desde el código** (31 modelos · 70 migraciones) ·
      **`INVARIANTES.md`** destilado (~45 invariantes de no-regresión). Sin datos del cliente
      (verificado); cabecera «base heredada, verificar contra código» en todos. Índice en
      `docs/README.md` + tabla de enrutado en `CLAUDE.md`.
- [ ] Revisar vocabulario de dominio específico del sector (park/attraction/birthday/puerta/waiver)
      y decidir qué se generaliza en BD/código y qué queda como config de sector → diseño en Fase 2.

### Fase 2 — Modularización del dominio ⬜
- [ ] Diseño de contextos (con revisión multi-agente): **Catalog&Booking** (productos, tarifas,
      franjas/aforo, pedidos, tickets) · **Content/CMS** (páginas, secciones, servicios, ofertas,
      FAQs, normas, tema) · **Identity** (auth, cuentas, RGPD/consents) · **Payments** (proveedores,
      pagos, reembolsos, señal) · **Platform** (settings, i18n, auditoría, mantenimiento).
- [ ] Migrar `app/Support/` (~60 clases) a los módulos, con la suite como red (sin big-bang:
      módulo a módulo, imports actualizados por fases).
- [ ] Romper los god-class documentados: `Livewire/Tickets/Purchase.php` (~2.000 líneas; muere
      con la SPA en Fase 4) y `Filament/.../ViewOrder.php` (~4.500 líneas).
- [ ] Contratos entre módulos explícitos (el panel y la web solo hablan con servicios de
      aplicación, nunca con modelos de otro módulo directamente).

### Fase 3 — API v1 (API-first) ⬜
- [ ] Autenticación por tokens (Sanctum) + flujo SPA (cookie) y móvil (token).
- [ ] Endpoints v1: auth/registro/perfil · catálogo · disponibilidad (fechas/franjas) ·
      carrito/pedido · pago (init + retorno; la notificación server-to-server ya existe) ·
      mis reservas · post-form de invitados · contenido (para la app).
- [ ] **Abstracción `PaymentProvider`** (driver Redsys primero; deja el enchufe para Stripe u
      otros — imprescindible para multi-sector fuera de España).
- [ ] Especificación **OpenAPI** versionada + tests de contrato (la doc de la API es artefacto
      de primera clase: la app móvil se construye contra ella).
- [ ] Rate-limiting, formato de errores único, paginación y convenciones de la API → `DECISIONES`.

### Fase 4 — Sidebar SPA ⬜
- [ ] SPA embebida (Vue 3 + Vite) para el sistema completo del sidebar: login/registro,
      compra/reserva, gestión de cuenta y reservas. **Primer consumidor real de la API v1.**
- [ ] Paridad funcional con el sidebar Livewire actual ANTES de retirarlo (feature-flag por
      instalación para poder convivir/comparar).
- [ ] Retirar `Purchase.php` + puente Alpine frágil cuando la paridad esté validada.

### Fase 5 — Capa de contenido profesional ⬜
- [ ] Sustituir el composer global `'*'` por **query services de contenido** con caché
      etiquetada e invalidación por evento de modelo (hoy: memo por request tras el W1).
- [ ] Theming como paquete coherente (tokens CSS + tema BD + assets por instalación).
- [ ] Contenido consumible también vía API (para que la app móvil pinte lo mismo que la landing).

### Fase 6 — Móvil + features nuevas ⬜
- [ ] Congelar contrato API v1; guía de integración móvil (auth, refresh, push, deep-links a pago).
- [ ] Features nuevas y modificaciones sobre el sistema actual (backlog a definir con el owner).

## Relación con el proyecto origen
El cliente origen (jumpingjump) sigue vivo en **su** repo con su canal de deploy; este repo no
le despliega nada. Mejoras de JumpWeb aplicables allí se portan **solo por decisión explícita**,
como cambios independientes en aquel repo (ver `DECISIONES #1`).
