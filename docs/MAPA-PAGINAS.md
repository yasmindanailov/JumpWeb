# Mapa de páginas y rutas

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).
> **Verificado contra el código: 2026-08-12** — todas las filas reconciliadas contra
> `routes/web.php` + `app/Filament/` (105 rutas reales vía `php artisan route:list`).

Todas las páginas de la web, qué muestran y quién puede acceder. Las URLs marcadas **(v1)**
eran diseño original del origen que **no se materializó**: se indica dónde vive hoy ese
contenido. Los slugs públicos están en español; su futuro (configurables vs neutros+i18n)
es decisión pendiente de Fase 1 (`00-REFACTOR.md`).

Vocabulario («parque», «zonas», «atracciones», «cumpleaños», «entradas», «waiver») =
vocabulario del sector origen; su generalización se decide en `00-REFACTOR.md` Fase 1/2.

> Acceso: 🌐 público · 🔑 requiere iniciar sesión · 🛠️ solo administrador
> Tipo: 📄 página (con URL propia) · 🪟 modal/panel (ventana sobre la página)

---

## Principios de UX (heredados, decididos en el origen)

Pocas páginas y acciones rápidas en **modales/paneles**, sin saturar al usuario con gestión
de datos.

- **Modales 🪟** para: login, registro (+waiver), editar mis datos.
- **Flujo de compra/reserva = SIDEBAR (panel deslizante) con asistente paso a paso**
  (decisión #66 del origen): se abre **sobre la página actual sin navegar** (no se
  redirige); `/entradas` es un **enlace profundo** que lo abre. Tamaño contenido (≤440px en
  escritorio; pantalla completa en móvil); cada paso muestra **solo lo relevante**
  (día → hora → entradas → login → pagar). **El pago de Redsys sale al banco** (redirección
  de página completa) y vuelve **reabriendo el sidebar** en el paso «Resultado».
- **Páginas 📄 obligatorias** (no pueden ser modal): el **pago** (redirección a Redsys),
  **mis pedidos** (el QR se abre desde el email), las páginas de **marketing/info/legal**
  (SEO y enlaces) y el **panel admin**.
- **«Mi cuenta» mínima:** pedidos (QR), 4 datos básicos, RGPD y cerrar sesión.

> Nota técnica: el modo de pago de Redsys es **redirección a página completa** (lo simple y
> robusto). Por eso el pago nunca es un modal.

---

## A. Páginas públicas (marketing e información) 🌐 📄

| Ruta | Qué es |
|---|---|
| `/` | **Home / Landing** — todas las secciones (hero, zonas, atracciones, precios, eventos, info, galería, FAQ, CTA). Página principal de venta. |
| `/servicios` | Página CMS data-driven de servicios (`LandingService`) — el sucesor real de la «/actividades» de la v1. |
| `/precios` | Tipos de entrada y precios. |
| `/cumpleanos` | Packs de cumpleaños y eventos (qué incluyen). |
| `/normas` | Normas del recinto. |
| `/contacto` | Formulario de contacto (throttle + Turnstile). |
| `/aviso-legal` · `/privacidad` · `/cookies` · `/condiciones` · `/waiver` | Las **5** páginas legales (la v1 listaba 4; `waiver` es la quinta). |
| `/sitemap.xml` | Sitemap SEO. |
| `/lang/{locale}` | Cambio de idioma (redirect seguro al mismo host — invariante SEC). |
| `/up` | Healthcheck del framework. |

> **(v1) no materializadas:** `/actividades`, `/actividades/{zona}`, `/grupos`, `/info`,
> `/faq` — su contenido vive como secciones de la home y en `/servicios`; el contacto
> genérico es `/contacto`. No crear estas rutas sin decisión nueva.

## B. Compra y reserva

| Ruta | Qué es | Acceso · Tipo |
|---|---|---|
| `/entradas` | **Deep-link** que abre el flujo de compra en el sidebar: día → hora → entradas → login → pago. La reserva de packs va por el mismo sidebar **sin URL propia** (la `/cumpleanos/reservar` de la v1 no existe). | 🌐 → 🔑 al pagar · 🪟 sidebar |
| `/pago/redsys/retorno-ok` · `/pago/redsys/retorno-ko` | Retorno del banco (GET\|POST). El paso «Resultado» se muestra **reabriendo el sidebar** (la `/compra/confirmacion` de la v1 no existe). ⚠️ SIN middleware `auth` a propósito: la vuelta del banco puede llegar sin cookie de sesión (POST cross-site); la autenticidad la da `Ds_Signature`. Añadir `auth` rompería la vuelta. | 🌐 (vuelta del banco) · 📄 |
| `/pago/redsys/notificacion` | Notificación server-to-server de Redsys (POST; idempotente). | Redsys · 📄 |
| `/reserva/{reservation}/datos-invitados` | **Post-form de invitados** (GET/POST, URL firmada con caducidad — ver `sistemas/POSTFORM-INVITADOS.md`). | enlace firmado · 📄 |
| `POST /cookies/consentimiento` | Persistencia del consentimiento de cookies (art. 7.1). | 🌐 |

## C. Cuenta de usuario 🔑

| Ruta | Qué es | Acceso · Tipo |
|---|---|---|
| `/registro` | Alta: datos mínimos + aceptar privacidad, términos y **waiver**. | 🌐 · 🪟 modal |
| `/login` · `POST /logout` | Iniciar / cerrar sesión. | 🌐 · 🪟 modal |
| `/recuperar-contrasena` | Petición de recuperación (nombre de ruta `password.request`). | 🌐 · 🪟 modal |
| `/restablecer-contrasena/{token}` | Form de reset desde el email. | enlace email · 📄 |
| `/email/verificar` (+ `/{id}/{hash}` + `POST …/reenviar`) | Verificación de email. | 🔑 · 📄 |
| `/mi-cuenta` | Resumen del área privada (mínima). | 🔑 · 📄 |
| `/mi-cuenta/pedidos` | **«Mis pedidos»** — unifica las «entradas» y «reservas» de la v1 (QR incluidos). `POST /mi-cuenta/pedidos/{code}/reintentar-pago` = reintento de pago. | 🔑 · 📄 |
| `/mi-cuenta/exportar` | Export JSON de portabilidad RGPD. | 🔑 · 📄 |
| `/mi-cuenta/email/confirmar/{id}/{hash}` | Confirmación de cambio de email (enlace firmado). | enlace email · 📄 |

> **(v1):** «editar mis datos» (`/mi-cuenta/datos`) es un **modal Livewire sin URL propia**.

## D. Panel de administración 🛠️ — bajo `/admin` (Filament)

Los slugs de los Resources se declararon en **inglés** (salvo `incidencias`); las Pages
custom (`calendario`, `crear-pedido`, `horario`) y las rutas admin custom quedaron en
español — otra entrada para la decisión de slugs de Fase 1.

| Ruta real | Qué es (ruta v1) |
|---|---|
| `/admin` · `/admin/login` · `POST /admin/logout` | Dashboard: ventas, próximas reservas, ocupación. |
| `/admin/calendario` | **Calendario unificado** (FullCalendar), color por zona + estado «preparado». Feed JSON `/admin/calendario/eventos`; PDF resumen del día `/admin/calendario/resumen-dia`. Permiso `calendar.view`. |
| `/admin/crear-pedido` | Pedido manual de back-office: efectivo/datáfono + alta por invitación. Permiso `orders.create_manual`. |
| `/admin/users` | Gestión de usuarios *(v1: `/admin/usuarios`)*. |
| `/admin/orders` | Pedidos: ver, reembolsar, marcar usada, reprogramar — las «reservas» se gestionan AQUÍ dentro (la `/admin/reservas` de la v1 no existe). Hoja de reserva PDF: `/admin/pedidos/{order}/items/{item}/imprimir` — nota: usa «pedidos» en español mientras el Resource vive en `/admin/orders`, misma incoherencia que las demás rutas admin custom (`puerta/validar`, `calendario/*`, `lang`). *(v1: `/admin/pedidos`)* |
| `/admin/slots` · `/admin/slot-templates` · `/admin/seasons` · `/admin/special-dates` · `/admin/horario` | Franjas, plantillas, temporadas, días especiales, horario semanal *(v1: `/admin/sesiones`)*. |
| `/admin/zones` · `/admin/attractions` · `/admin/rate-types` · `/admin/catalog` · `/admin/faqs` · `/admin/park-rules` · `/admin/pages` · `/admin/landing-services` · `/admin/offers` | Contenido y catálogo *(v1: `/admin/contenido`)*. |
| `/admin/settings` | Negocio, contacto, pagos (Redsys), idiomas, **tema visual**, textos legales *(v1: `/admin/configuracion`)*. |
| `/admin/roles` | Roles y permisos (matriz server-side — invariante SEC). |
| `/admin/incidencias` | Visor de incidencias/auditoría (`AuditLogResource`, solo lectura). |
| `/admin/maintenance` | Kill-switch de mantenimiento del sitio. |
| `/admin/puerta/validar` | Validación de registros en puerta (Livewire fuera del shell Filament). |

> **(v1) sin materializar:** `/admin/informes`. Detalle real del panel (recursos, permisos,
> sub-páginas): `PANEL-ADMIN.md`.

---

## Notas
- **Idiomas:** el mismo árbol en ES/EN/FR **sin prefijo de URL**: detección por middleware +
  cambio explícito vía `/lang/{locale}` (público) y `POST /admin/lang/{locale}` (panel).
- **El flujo de compra/reserva** empieza siendo público (mirar, elegir) y solo exige
  cuenta en el momento de pagar.
- **Data-driven:** info, horarios, legales y datos del negocio se editan desde el panel;
  en un despliegue nuevo de JumpWeb muestran placeholders hasta sembrar datos propios.
