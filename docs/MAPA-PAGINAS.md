# Mapa de páginas y rutas

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §Verificación).

Todas las páginas de la web, qué muestran y quién puede acceder. **Las URLs son las del
diseño v1 del origen** («de ejemplo, se afinan luego»): el código real puede diferir —
contrasta siempre con `routes/web.php` / `php artisan route:list` (Sail, web en
`http://localhost:8081`). Multiidioma: el mismo árbol de páginas en ES/EN/FR.

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
  la **confirmación con QR**, **mis entradas/reservas** (el QR se abre desde el email),
  las páginas de **marketing/info/legal** (SEO y enlaces) y el **panel admin**.
- **«Mi cuenta» mínima:** entradas (QR), reservas, 4 datos básicos, RGPD y cerrar sesión.

> Nota técnica: el modo de pago de Redsys es **redirección a página completa** (lo simple y
> robusto). Por eso el pago nunca es un modal.

---

## A. Páginas públicas (marketing e información) 🌐 📄

| Ruta | Qué es |
|---|---|
| `/` | **Home / Landing** — todas las secciones del mockup (hero, zonas, atracciones, precios, eventos, info, galería, FAQ, CTA). Página principal de venta. |
| `/actividades` | Listado de zonas y atracciones. |
| `/actividades/{zona}` | Detalle de una zona con sus atracciones. |
| `/precios` | Tipos de entrada y precios. |
| `/cumpleanos` | Packs de cumpleaños y eventos (qué incluyen). |
| `/grupos` | Grupos, empresas y colegios *(formulario de contacto)*. |
| `/info` | Horarios, dirección, mapa, cómo llegar, contacto. |
| `/normas` | Normas del recinto. |
| `/faq` | Preguntas frecuentes. |
| `/contacto` | Formulario de contacto. |
| `/aviso-legal`, `/privacidad`, `/cookies`, `/condiciones` | Páginas legales. |

> La Home incluye estas secciones como bloques (con anclas), y además existen como páginas
> propias para dar detalle y mejorar el SEO.

## B. Compra y reserva

| Ruta | Qué es | Acceso · Tipo |
|---|---|---|
| `/entradas` | **Flujo de compra**: día → hora → entradas (carrito de visitas) → login → pago. | 🌐 → 🔑 al pagar · 🪟 sidebar |
| `/cumpleanos/reservar` | **Flujo de reserva** de pack de cumpleaños/evento. | 🌐 → 🔑 al pagar · 🪟 sidebar |
| `/pago/redsys` (ida y vuelta) | Redirección a Redsys y retorno OK/KO. | 🔑 · 📄 |
| `/compra/confirmacion` | Resumen final + acceso a las entradas con QR. | 🔑 · 📄 |

## C. Cuenta de usuario 🔑

| Ruta | Qué es | Acceso · Tipo |
|---|---|---|
| `/registro` | Alta: datos mínimos + aceptar privacidad, términos y **waiver**. | 🌐 · 🪟 modal |
| `/login` | Iniciar sesión. | 🌐 · 🪟 modal |
| `/recuperar-contrasena` | Recuperación de contraseña. | 🌐 · 🪟 modal |
| `/mi-cuenta` | Resumen del área privada (mínima). | 🔑 · 📄 |
| `/mi-cuenta/entradas` | «Mis entradas» con sus QR. | 🔑 · 📄 |
| `/mi-cuenta/reservas` | «Mis reservas» de eventos. | 🔑 · 📄 |
| `/mi-cuenta/datos` | Editar datos + RGPD (exportar / eliminar mi cuenta). | 🔑 · 🪟 modal |

## D. Panel de administración 🛠️ — bajo `/admin`

| Ruta | Qué es |
|---|---|
| `/admin` | Panel: ventas, próximas reservas, ocupación. |
| `/admin/calendario` | **Calendario unificado**: vista mes/semana/día de los productos vendidos (FullCalendar), color por zona + estado «preparado»; click → detalle del producto + pedido. Feed JSON en `/admin/calendario/eventos`. Permiso `calendar.view`. |
| `/admin/crear-pedido` | Pedido manual de back-office: efectivo/datáfono + alta por invitación. Permiso `orders.create_manual`. |
| `/admin/usuarios` | Gestión de usuarios. |
| `/admin/pedidos` | Entradas vendidas: ver, reembolsar, marcar usada. |
| `/admin/reservas` | Reservas de eventos: aceptar, reprogramar, cancelar. |
| `/admin/sesiones` | Franjas, aforo, calendario, días cerrados. |
| `/admin/contenido` | Zonas, atracciones, precios, packs, FAQ, normas, galería. |
| `/admin/configuracion` | Negocio (nombre, NIF…), contacto, pagos (Redsys), idiomas, **tema visual**, textos legales. |
| `/admin/informes` | Informes y exportaciones. |

> Detalle real del panel (recursos, permisos, sub-páginas): `PANEL-ADMIN.md` y el código
> (el panel creció después de esta v1; esta tabla es el esqueleto original).

---

## Notas
- **Idiomas:** la web sirve el mismo árbol en ES/EN/FR. La estrategia técnica exacta
  (prefijo `/en/…` vs detección) quedaba abierta en esta v1 — verificar la implementada en
  el código (rutas/middleware).
- **El flujo de compra/reserva** empieza siendo público (mirar, elegir) y solo exige
  cuenta en el momento de pagar.
- **Data-driven:** info, horarios, legales y datos del negocio se editan desde el panel;
  en un despliegue nuevo de JumpWeb muestran placeholders hasta sembrar datos propios.
