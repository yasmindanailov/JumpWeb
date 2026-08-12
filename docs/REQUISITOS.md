# Requisitos: roles + inventario de funcionalidades

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

Define **quién** usa la web y **qué** puede hacer. Era la lista cerrada de alcance (v1) del
proyecto origen; el origen llegó a *feature-complete*, así que **todos los módulos listados
están construidos en la base heredada**. Las marcas `[SUPUESTO]`/`[PENDIENTE]` son del diseño
inicial: la mayoría se resolvieron después — se anota la resolución conocida; ante la duda,
**el código manda**.

Vocabulario: «parque», «zonas», «atracciones», «cumpleaños», «waiver», «entradas» =
vocabulario del sector origen (parque de ocio/saltos); su generalización se decide en
`00-REFACTOR.md` Fase 1/2.

---

## 1. Roles (tipos de usuario)

| Rol | Quién es | Qué puede hacer (resumen) |
|---|---|---|
| **Visitante** | Cualquiera sin cuenta | Ver toda la web pública, info, precios; iniciar una compra |
| **Cliente** | Usuario registrado | Todo lo del visitante + comprar entradas, reservar eventos, ver su historial y datos |
| **Administrador** | Dueño del negocio | Todo: configuración, contenido, usuarios, ventas, reservas, informes |
| **Empleado (staff)** | Personal del negocio | Acceso limitado al panel (validar entradas, ver reservas del día…) |

- El rol Empleado estaba `[PENDIENTE]` en esta v1; **se resolvió después**: base de roles con
  tablas propias (`roles`, `permissions`, pivote `permission_role`), sin paquete externo;
  `admin` = super-admin vía Gate global; permisos finos por recurso en el panel
  (p. ej. `calendar.view`, `orders.create_manual`). Ver `PANEL-ADMIN.md` y el código.

---

## 2. Inventario de funcionalidades por módulo

*(El origen los construyó por fases de un roadmap ya histórico; se omiten las etiquetas de
fase: todo lo listado existe en la base heredada.)*

### Módulo 1 — Sitio público
- 1.1 Landing con todas las secciones del mockup (hero, zonas, atracciones, precios,
  eventos, info, normas, galería, FAQ, CTA).
- 1.2 Multiidioma ES/EN/FR con selector.
- 1.3 Páginas de detalle: zonas y atracciones.
- 1.4 Página de info práctica (horarios, dirección, mapa, contacto).
- 1.5 Página de normas / FAQ.
- 1.6 Páginas legales (aviso legal, privacidad, cookies, condiciones).
- 1.7 Formulario de contacto.
- 1.8 SEO básico (títulos, descripciones, sitemap) y rendimiento.

### Módulo 2 — Cuenta de usuario
- 2.1 Registro con verificación por email.
- 2.2 Inicio / cierre de sesión.
- 2.3 Recuperación de contraseña.
- 2.4 Área privada: ver y editar datos personales.
- 2.5 Consentimientos RGPD (aceptar privacidad; exportar y eliminar mi cuenta).
- 2.6 Sin guardar tarjetas de pago (más seguro; lo gestiona la pasarela Redsys).

### Módulo 3 — Venta de entradas
- 3.1 Catálogo de tipos de entrada con precios (editables desde el panel).
- 3.2 Selección de fecha y **franja horaria** *(decidido)*.
- 3.3 **Control de aforo por franja** — no vender más plazas de las que hay *(decidido)*.
- 3.4 Carrito y resumen de compra.
- 3.5 Pago con Redsys.
- 3.6 Confirmación + email + entrada con **código QR**.
- 3.7 «Mis entradas» en el área privada.
- 3.8 Compra permitida **solo a usuarios registrados** *(decidido)*.

### Módulo 4 — Reserva de eventos / cumpleaños
- 4.1 Catálogo de packs (qué incluye cada uno; editable desde el panel).
- 4.2 Selección de fecha, franja y sala *(la existencia de salas privadas estaba `[PENDIENTE]`
  en esta v1 — verificar el modelo real en `MODELO-DATOS.md` y el código)*.
- 4.3 Formulario del evento (n.º invitados, edad del cumpleañero, extras).
- 4.4 Pago con Redsys. La disyuntiva señal-vs-total estaba `[PENDIENTE]`; el origen diseñó
  después un sistema de **señal/depósito** (pago parcial del pack; resto presencial) —
  verificar su estado de implementación en el código.
- 4.5 Confirmación + email.
- 4.6 «Mis reservas» + consulta/gestión.
- 4.7 Solicitud para grupos/empresas/colegios → quedó como formulario de contacto (`/grupos`).

### Módulo 5 — Panel de administración
- 5.1 Acceso seguro de administrador.
- 5.2 Resumen: ventas, próximas reservas, ocupación.
- 5.3 Gestión de usuarios (ver, buscar, bloquear).
- 5.4 Gestión de entradas/pedidos (ver, reembolsar, marcar entrada como usada).
- 5.5 Gestión de reservas de eventos (aceptar, reprogramar, cancelar).
- 5.6 Calendario, horarios y aforo (definir franjas, plazas, días cerrados).
- 5.7 Informes y exportaciones (ventas, asistencia).

### Módulo 6 — Plataforma / configuración (el «motor» white-label) *(transversal)*
*Lo que hace la web configurable y reutilizable — el corazón del producto JumpWeb.*
- 6.1 Configuración del negocio: nombre, NIF, razón social, dirección, contacto, horarios.
- 6.2 Gestión de contenido: zonas, atracciones, precios, packs, FAQ, normas, textos.
- 6.3 Sistema de temas: colores y tipografías (tokens CSS + tema en BD).
- 6.4 Gestión de traducciones ES/EN/FR del contenido.
- 6.5 Gestión de imágenes/galería.
- 6.6 Configuración de pagos (claves de Redsys) y de email.
- 6.7 Textos legales editables.

---

## 3. Fuera de alcance en el origen
*Lista v1 del origen; en JumpWeb algunas pueden reabrirse como decisiones de producto
(→ `00-REFACTOR.md`).*
- App móvil nativa (la web es responsive, no una app).
- Programa de fidelización / puntos.
- Venta de productos físicos (tienda/merchandising).
- **Multi-negocio simultáneo (SaaS)** — el origen lo descartó y optó por «plantilla
  reutilizable» (= el fork que es JumpWeb). Si JumpWeb quiere multi-tenant real, es una
  decisión nueva del refactor.
- Integración con redes sociales más allá de enlaces.

---

## 4. Decisiones heredadas (estado a fecha de la adaptación)
1. **Modelo de entrada** ✅ → franjas horarias con aforo (sesiones con plazas limitadas).
2. **Compra** ✅ → solo usuarios registrados (no invitado).
3. **Rol Empleado** ✅ → resuelto (ver §1: roles + permisos finos en el panel).
4. **Eventos: señal y salas** → señal/depósito diseñado en el origen; salas privadas sin
   confirmación en este doc — **verificar ambos contra el código** antes de asumirlos.
