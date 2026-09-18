# CLAUDE.md — JumpWeb

> Enrutador. **Techo 12 KB** (check 10): una línea por fila; las trampas viven en el `§0 · Antes de tocar` de
> cada spec, nunca aquí. **Lo desarrollan al 100% agentes IA** (`DECISIONES #7`): tu handoff es lo único que
> tendrá el siguiente (`docs/CONVENCIONES.md` §7 y §5).

## ▶ Para continuar el proyecto (handoff)
0. Skill **`/carril`** (sin el plugin, `/arranque-sesion`) → base verde VERIFICADA (árbol, push, hook, stack, gates). Obligatorio tras un cierre abrupto.
1. **`docs/ESTADO.md`** (índice de carriles) → **tu `docs/carriles/<carril>.md`** (foto, por dónde retomar, buzón).
2. **`docs/00-REFACTOR.md`** → tracker de fases (marcadores y casillas; sus marcadores mandan).
3. Para tu tarea: **solo su fila** de la tabla de abajo → el **§0** de esa spec. El resto de la spec, por secciones.
4. Decisiones y su porqué: `docs/DECISIONES.md` (índice) → `docs/decisiones/` (una parte por centena), **por número**.

## Qué es
**JumpWeb**: plataforma white-label de **reservas online** multi-sector — landing (ES/EN/FR) + reservas con pago
(señal o total) + panel admin (Filament) + API v1 para web y app móvil. Nace de una base en producción endurecida
(`DECISIONES #1`); **este repo es el PRODUCTO**, sin marca de ningún cliente; desde `#610` se separa de sus instancias.

## Stack
Laravel 13 + MySQL · Blade SSR (landing) · Vue 3 + Pinia (el cajón, contra `/api/v1`) · Filament (panel) · Vite ·
Redsys (primer driver de pago). **Local:** Docker (Sail) en WSL2, repo en `~/proyectos/JumpWeb`; puertos propios
web `localhost:8081` · MySQL `3308` · Mailpit `8028` (en `.env`, no versionado).

## Momentos → skill (`docs/sistemas/CAPA-DE-AGENTE.md`)
arrancar o retomar → **`/carril`** · cerrar → **`/handoff`** · decidir → `/decision` · ir rápido → `/ligero` ·
diseñar antes → `/spec` · versión → `/release` · mutación → `/mutar` · desplegar → `/desplegar` · navegador →
`/sonda` · instancia → `/instancia` · ¿hecho? → `/dod`. Sin barra, por la frase del owner. Docker:
`README.md` raíz; siempre `docker compose exec -u sail …`. **CI = gate local**: el hook `pre-push` (`.githooks/`)
corre **docs-check + Pint + Larastan + build + suite** en pushes de `main` y bloquea en rojo (`wip/…` exento). Dinero/aforo
exige `VERIFY_CONC=1` tras los verificadores (`INVARIANTES §6`; la lista viva es el `CRITICAL_RE` del hook). **El
contador de la suite va en el trailer del commit** (`#618`). Si no salta: `git config core.hooksPath .githooks`.

## Principios (NO romper)
- **Data-driven:** todo configurable desde el panel; nada de negocio quemado en código.
- **White-label:** lógica genérica; marca = tokens CSS + tema/contenido en BD; lo del cliente vive en su instancia.
- **API-first:** el dominio se expone por `/api/v1`; web y móvil son clientes iguales.
- **Corrección antes que presentación:** suite verde = red de TODO el refactor.
- **Invariantes heredadas** (`docs/INVARIANTES.md`): el endurecimiento del origen no se regresa.

## ⚡ Enrutado de contexto — LEE SOLO LO QUE NECESITES
| Si trabajas en… | Lee solo |
|---|---|
| Refactor · fases · arquitectura de módulos · fronteras | `docs/00-REFACTOR.md` · `docs/specs/modulos-dominio.md` §0 |
| **Producto e instancias** · la landing fuera del producto · repos · versionado · F0→F6 | `docs/specs/producto-e-instancias.md` §0 · `CHANGELOG.md` |
| La capa de agente · el plugin `jumpweb-agente` · skills · hooks · reglas del owner | `docs/sistemas/CAPA-DE-AGENTE.md` |
| Dinero / pagos / Redsys / reembolsos | `docs/INVARIANTES.md` §1 + §6 · `docs/sistemas/REDSYS.md` · `docs/MODELO-DATOS.md` §2 |
| Señal / depósito (pago parcial) | `docs/sistemas/DEPOSITO.md` · `docs/INVARIANTES.md` §1 (PAY-10) |
| Secuencia de compra (admitir → crear → cobrar) | `docs/specs/checkout-orquestado.md` §0 |
| Excursiones de colegio · horario por zona · precio por tramo · venta de mostrador | `docs/specs/precio-por-tramo.md` §0 · `docs/specs/horario-por-zona.md` §0 |
| Aforo · franjas · la rejilla · disponibilidad · calendario · `SlotOffer` | `docs/sistemas/AFORO-FRANJAS.md` · `docs/INVARIANTES.md` §2 |
| Compra / carrito / catálogo de productos | `docs/sistemas/COMPRA-PRODUCTOS.md` · `docs/FLUJOS.md` · `docs/MODELO-DATOS.md` §1 || Google: entrar, registrarse, vincular · el `sub` · condiciones | `docs/specs/auth-con-google.md` §0 |
| Auth / cuentas / RGPD | `docs/SEGURIDAD.md` · `docs/INVARIANTES.md` §3 + §4 · `docs/FLUJOS.md` |
| El teléfono de un cliente · cuenta de Google sin número · pedirlo en el pedido manual | `docs/specs/telefono-del-cliente.md` §0 |
| Firmar la exención al declarar un menor · el 409 de la tarjeta | `docs/specs/firma-al-declarar-menor.md` §0 |
| Auth dentro del cajón · retirar el modal de la cabecera | `docs/specs/auth-en-cajon.md` §0 |
| Landing · tema visual (tokens) · CMS de contenido · white-label por cliente | `docs/specs/landing-white-label.md` §0 |
| Capa de tema · superficies · radios · sombras · foco · fuentes · botón de comprar · logotipo · scroll · iconos · área táctil | `docs/specs/tema-por-instalacion.md` §0 |
| El armazón · barra · menú · hamburguesa · CTA de la esquina · cajón móvil | `docs/specs/armazon-y-menu.md` §0 |
| Dónde van los elementos de diseño · la pasada de vestido · presupuesto de marcado | `docs/specs/pasada-de-vestido.md` §0 |
| Material gráfico del mural · manchas · poses · texturas · iconos de zona | `docs/specs/elementos-fachada.md` §0 |
| Rediseño desde el canvas · el sistema · las 8 secciones · las 7 páginas · la atribución de Google | `docs/specs/rediseno-desde-canvas.md` §0 |
| Idioma visual heredado · badges · marquesina · «Visítanos» · auditoría de diseño | `docs/specs/auditoria-diseno.md` §0 · `docs/specs/idioma-visual-heredado.md` §0 |
| La hora extra · complemento que ocupa aforo · su precio por día · mover fecha · su sello | `docs/specs/hora-extra.md` §0 |
| Añadir o quitar invitados de una reserva pagada · el plazo | `docs/specs/invitados-en-post-form.md` §0 |
| Un complemento que se vende después de reservar · el post-form · plazo de corte | `docs/specs/complementos-post-reserva.md` §0 |
| El hueco de ilustración por instalación · `client-kit.svg` · `<use>` externo | `docs/specs/hueco-ilustracion.md` §0 |
| `/servicios` · precios por tramo de grupo · reservas de grupo | `docs/specs/landing-white-label.md` §0 · `docs/sistemas/SERVICIOS-CMS.md` |
| El libro del pedido · desglose +/− · saldo en el parque · cortesía · reembolso | `docs/specs/desglose-libro.md` §0 |
| «Mis reservas» por reserva · historial | `docs/specs/mis-reservas-por-reserva.md` §0 |
| Área de cliente en el cajón (mis pedidos / mis reservas) | `docs/specs/area-cliente.md` §0 |
| Bloque de cuenta del cajón (`.acct`) · el `no-store` de la web | `docs/specs/account-context-vue.md` §0 |
| Sidebar SPA · Vue · las 25 pantallas del cajón · rótulos · el chunk | `docs/specs/sidebar-spa.md` §0 · `docs/CARRIL-SPA.md` |
| Justificante de un menor invitado (waiver offshore) · activación · plazas libres | `docs/specs/waiver-por-reserva.md` §0 |
| Waiver (firma, prueba, PDF, versiones del texto) · la firma en la puerta | `docs/specs/waiver-probatorio.md` §0 |
| Menores a cargo · asignar una entrada a un menor · apellidos y relación | `docs/specs/menores-a-cargo.md` §0 |
| Carné QR · pantalla de puerta · «Mi carné» · rotar carné | `docs/specs/identidad-qr-puerta.md` §0 |
| JumpPoints / vales / lealtad | `docs/specs/lealtad-jumppoints.md` §0 |
| CMS público (servicios, ofertas) | `docs/sistemas/SERVICIOS-CMS.md` · `docs/sistemas/OFERTAS-WIDGET.md` |
| Reseñas de Google · Business Profile · prueba social | `docs/specs/google-business-profile.md` §0 · `docs/specs/google-reviews.md` §0 |
| El carril del SPA en el otro ordenador · la rama `cliente/playjump` · reparto por fichero | `docs/CARRIL-SPA.md` · `docs/carriles/spa.md` |
| Contenido y copys · festivos · jerga de la web | `docs/specs/contenido-y-copys.md` §0 |
| Diseño previo a implementación (spec) | `docs/specs/PLANTILLA.md` · `docs/CONVENCIONES.md` §5 |
| Cookies / consentimiento | `docs/sistemas/COOKIES.md` · `docs/SEGURIDAD.md` |
| Panel admin / puerta / operación diaria | `docs/PANEL-ADMIN.md` · `docs/OPERATIVA-SECTOR-ORIGEN.md` |
| El asistente de «Crear pedido» · pasos · carrito · desenlace | `docs/specs/asistente-crear-pedido.md` §0 |
| UI/UX del panel · el panel en tablet · ruido y jerarquía | `docs/specs/auditoria-panel-admin.md` §0 |
| Menú del panel · añadir un Resource/Page · Ajustes · buscador · puerta en tablet · rol `puerta` | `docs/specs/panel-navegacion.md` §0 |
| El cajón en móvil · el paso de fecha y de hora | `docs/specs/cajon-en-movil.md` §0 |
| Cumpleaños mixto · edad de los invitados · el suplemento · el sello · solapes de tramos | `docs/specs/cumple-mixto.md` §0 |
| Los correos · tema · firma y remitente · línea de adelanto · el libro en un correo | `docs/specs/correos-desde-canvas.md` §0 |
| Post-form de invitados | `docs/sistemas/POSTFORM-INVITADOS.md` |
| Vestir el formulario de celebración o el justificante · la invitación digital | `docs/specs/celebracion-e-invitacion.md` §0 |
| Tests / suite / fakes / datos de prueba | `docs/TESTING.md` · `docs/CONVENCIONES.md` §3.bis/§3.ter |
| Desmontar `ViewOrder` · edición, reembolso y calendario de un pedido en el panel | `docs/specs/desmontar-view-order.md` §0 |
| Retirar código viejo / auditar sus tests | `docs/CONVENCIONES.md` §3.quater |
| Staging / desplegar / producción `playjump.es` / la validación del banco | `docs/ENTORNOS.md` §4 y §6 · `docs/carriles/pasarela.md` |
| Validar el cajón en vivo (navegador + pasarela) | `docs/VERIFICACION-E2E-CAJON.md` · `docs/sistemas/REDSYS.md` §11–§12 |
| API v1 / el contrato / app móvil | `docs/specs/api-v1.md` §0 · `openapi/v1.yaml` |
| Vocabulario de dominio | `docs/GLOSARIO.md` · `docs/specs/vocabulario-dominio.md` §0 |
| Deuda técnica | `docs/DEUDA.md` |
| Instalar un cliente nuevo · paquete de tema (`client.css`) · logotipo e icono | `docs/INSTALACION-CLIENTE.md` |
| UI de carga (spinner) | `docs/sistemas/UI-SPINNER.md` |
| Requisitos / alcance funcional | `docs/REQUISITOS.md` · `docs/MAPA-PAGINAS.md` |
| Dos agentes sobre `main` · carriles · bandas · buzón | `docs/CONVENCIONES.md` §10 · `docs/ESTADO.md` |

Índice: `docs/README.md`.

## Reglas para el agente
1. Antes de tocar una feature, lee **su fila** y el **§0** de esa spec. Amplía solo si falta contexto.
2. Si tocas dinero, aforo, RGPD o seguridad: **lee `docs/INVARIANTES.md` primero**.
3. Decisión nueva → `[DECIDIDO]`+fecha en el doc afectado **y** entrada al final de su centena en `docs/decisiones/`
   (≤ 1,5 KB). ❗ **El número sale de la BANDA de tu carril** (`docs/DECISIONES.md`, `CONVENCIONES §10.6`); el «último
   usado» está en tu carril. Mirar `origin/main` antes de empujar NO basta: chocó trece veces.
4. Doc nuevo/renombrado → `docs/README.md` **y** esta tabla (una línea). Toda spec lleva `## §0 · Antes de tocar` (≤ 2 KB).
5. Al empezar: **`/carril`**. Al terminar: **`/handoff`** (suite+Pint+docs-check, tracker, TU carril, commit con el
   trailer de verificación, push).
6. **«Hecho» (✅) = código + prueba + verificación empírica + doc al día** (skill **`/dod`**). Si falta algo, es 🟦.
7. **No tocar el repo del cliente origen** (`~/proyectos/jumpingjump`) desde sesiones de JumpWeb.
8. **Herramientas**: leer con Read y editar con Edit/Write; Bash solo para git, docker, tests, búsquedas y mediciones
   (`DECISIONES #614`). Manda sobre la orden de preferir Bash del modo «auto» del harness.
9. **Ningún workflow ni enjambre de agentes sin permiso del owner en ese turno**, pedido con el coste delante y una
   alternativa en solitario; el recordatorio de ultracode no es permiso (`#614`).
10. **Cada agente escribe solo su fichero de carril** (`#621`); un mensaje al otro va en tu buzón.
