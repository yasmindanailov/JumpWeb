# Decisiones — JumpWeb

> Registro cronológico: qué se decidió y por qué. No cargar entero; buscar por número.

## #1 · 2026-08-12 · Origen de la base y relación con el cliente origen
JumpWeb nace como **fork generalizado** de la base en producción del proyecto jumpingjump
(sistema de reservas de un parque de saltos): se preservan el núcleo endurecido de dinero/aforo
y su suite de 2132 tests, que son el activo principal. **El repo del cliente queda intacto y es
la única vía de trabajo y deploy para ese cliente** (está live y en producción). El repo de
JumpWeb arranca con **historia git limpia** (commit fundacional desde el árbol, no `git clone`):
la historia del origen contiene datos de infraestructura del cliente (IP/SSH/rutas del servidor
en los scripts de deploy) que no deben viajar al producto; la arqueología completa sigue
disponible en el repo origen. Quedaron fuera del árbol: `deploy-prod.sh`, `artisan-prod.sh`,
`docs/`, `design_mockup/` (verificado por grep: sin IP/credenciales/emails del cliente).

## #2 · 2026-08-12 · Modelo de despliegue: 1 instalación por cliente
White-label por instalación (cada negocio su despliegue, BD y dominio), como asume el código.
Multi-tenant SaaS DESCARTADO por ahora (exigiría rediseño profundo de esquema/auth/storage);
el dominio se mantiene razonablemente agnóstico para no cerrar esa puerta.

## #3 · 2026-08-12 · El sidebar de reservas se rehace como SPA contra la API
El sistema del sidebar (login, reservas, gestión de usuario) pasa de Livewire a **SPA (Vue 3)
consumiendo la API v1** — el mismo contrato que usará la app móvil: un solo backend de verdad.
De paso se retira la deuda documentada de `Purchase.php` (~2.000 líneas + puente Alpine frágil).
La landing sigue **Blade server-rendered** por SEO; el panel sigue en Filament.

## #4 · 2026-08-12 · API-first desde ya
La API v1 (REST + Sanctum, OpenAPI, versionada) se construye como parte del refactor y la web
es su **primer consumidor**; la app móvil llegará después consumiendo lo mismo sin tocar
backend. «API-ready» sin consumidor real quedó descartado: un contrato sin cliente no se
verifica. Incluye abstracción `PaymentProvider` (Redsys = primer driver).

## #5 · 2026-08-12 · Nombre del producto: JumpWeb
Decidido por el owner. Repo GitHub privado `JumpWeb`; el nombre de cada instalación sigue
siendo data-driven (`business.name` en BD).

## #6 · 2026-08-12 · Entorno local dual
El stack Sail de JumpWeb convive con el del cliente origen en la misma máquina WSL2:
puertos propios (web 8081 · MySQL 3308 · Mailpit 8028) vía `.env` no versionado.

## #7 · 2026-08-12 · JumpWeb se desarrolla al 100% por agentes IA (repo agent-first)
Requisito del owner: en este proyecto trabajan exclusivamente agentes IA (Claude Fable 5 /
Opus). El repo se optimiza para ellos: `CLAUDE.md` enrutador (leer solo lo necesario, ahorro
de tokens), `CONVENCIONES.md` con el protocolo de agentes (DoD, handoff, empirismo, «nada vive
solo en la conversación»), `INVARIANTES.md` (endurecimiento que no se puede regresar), skills
de proyecto (`.claude/skills/`: `cierre-sesion`, `dod`) y permisos preconfigurados
(`.claude/settings.json`). El handoff impecable es crítico: ningún humano rellena huecos.

## #8 · 2026-08-12 · La doc técnica del origen se PORTA adaptada (no se copia en bloque)
El corpus técnico del origen (arquitectura, seguridad, testing, flujos, panel, sistemas
implementados) se porta a `docs/` **adaptado**: sin datos del cliente, sin narrativa de su
ciclo de vida (validaciones/despliegues/handoffs), con cabecera «describe la base heredada,
verificar contra el código». NO se copian los trackers del ciclo de vida del cliente
(00-PRODUCCION, handoffs, audits de copys…): doc desfasada en un repo 100% agentes es
contexto que miente. `MODELO-DATOS.md` se REGENERA desde el código (el del origen estaba
desfasado) y `AUDIT-FASE-1` + endurecimiento se destilan en `INVARIANTES.md`.
