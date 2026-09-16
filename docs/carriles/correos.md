# Carril · Correos

> Máquina: **este ordenador** · Banda: **500–519** · Último usado: **`#508`** · Spec: `correos-desde-canvas.md`
> §0 · Actualizado: 2026-09-16 (escrito por el carril de plataforma en F1 desde el estado del 13-09).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB.

## Foto

- **El carril entero en el árbol y en producción con la web** (`#500`→`#508`): los 25 correos (no 23: los dos
  del framework se extendieron y hoy encolan), el molde (`BrandedMailMessage`), el remitente desde el panel, el
  modo oscuro por color, la bandeja (63 líneas de adelanto y los asuntos con el dato delante), la fiesta mixta.
  El inventario del artboard queda sin ningún RECHAZADO.

## Por dónde retomar, en orden

1. **Los cuatro ÁMBAR** del inventario (spec §16): la hora de caducidad en «pago denegado» y en «confirma tu
   email», qué queda vivo en «producto cancelado», y la contraseña temporal del alta por el parque.
2. **El OJO del owner en Gmail y Outlook**: nada de este carril se ha visto en un cliente de correo real.

## Ficheros de este carril

Las notificaciones y los mailables del producto, `resources/views/vendor/mail/**` (html y text, por partida
doble), `resources/views/emails/**`, `lang/*/emails.php` y sus tests. **Compartido**: el tema de correo lo
vuelve a inlinear Laravel, así que `client.css` no llega; lo data-driven va por Blade (`Setting::businessName()`,
el logotipo, `onAction()`).

## Trampas vivas

- Un componente de correo sin su gemelo en `text/` renderiza bien y REVIENTA al enviar.
- `Mail::fake()` intercepta antes de construir el mensaje: un caso del remitente sale verde desconectado.
- Un `*/` dentro de un docblock lo cierra y el render devuelve el HTML anterior sin avisar.
- Paso de despliegue del remitente: ponerlo en cada instalación con una dirección del dominio que firma
  SPF/DKIM.

## Buzón

### Para otros carriles
- Nada pendiente.

### Atendido
- Nada.
