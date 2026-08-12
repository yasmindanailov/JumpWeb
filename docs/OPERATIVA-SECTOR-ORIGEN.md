# Operativa física del sector origen (parque de saltos)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §Verificación).

**Qué es este doc:** REFERENCIA del sector origen (parque de saltos). Explica **por qué el
dominio heredado es como es**: la web se diseñó para **convivir** con los sistemas físicos del
recinto, **no** para reemplazarlos. Al generalizar (white-label), no rompas estos supuestos sin
una decisión explícita — varios se materializaron en código (canje de tickets, aforo por franja).

Vocabulario del sector origen (zonas, pulseras, puerta, cumpleaños, waiver); su generalización
se decide en `00-REFACTOR.md` Fase 1/2.

---

## 1. Sistemas físicos que el recinto ya tenía

| Sistema | Qué hace | ¿Sirve para entradas? |
|---|---|---|
| **Cashlogy** (máquina de gestión de efectivo, producto de terceros) | Cobra y da cambio automáticamente. | ❌ No. Solo sabe de dinero, no de qué entrada/zona se vendió. Sin API pública. |
| **Pulseras (entradas)** | Pulseras con **QR** (fabricante asiático). Se **activan** con el sistema del fabricante y se vinculan a una **zona**. | ✅ Es el **control de acceso** real. API/datos: `[DESCONOCIDO]` en el origen. |
| **Control de acceso** | Al pasar la pulsera por un lector, sabe si la entrada está activada y su zona. | — |

**Venta presencial:** solo para el **mismo día**. (La web, en cambio, vende para fechas futuras.)

## 2. Cómo trabaja la web junto a esto

- La **web** vende entradas online (fechas futuras), gestiona **reservas** de cumpleaños/eventos
  y el **registro/waiver**.
- El recinto sigue usando su gestión de efectivo (Cashlogy) y sus **pulseras** (acceso).
- El puente entre ambos mundos es el **empleado en puerta** (proceso del §3).

## 3. Flujo del cliente online al llegar al recinto (canje en puerta)

1. El cliente muestra su **email de confirmación** (o el QR de «Mis entradas»).
2. El empleado lo **busca en el sistema** (por código de pedido, email o QR).
3. El sistema muestra **qué entrada es**: zona/tipo → **color de pulsera** que corresponde,
   día/franja y cantidad.
4. El empleado entrega/activa la **pulsera** de ese color y **marca la entrada como canjeada**
   (para que no se use dos veces).

> Racional: mismo flujo físico que ya tenía el operador (mirar el email a ojo), pero el empleado
> ve en pantalla **qué pulsera dar** y la entrada queda registrada como usada — sin errores y
> trazable. Este flujo existe en el panel heredado (validar/preparar/canjear; ver `PANEL-ADMIN.md`
> y `FLUJOS.md`).

## 4. Aforo con dos canales de venta (online futuro + presencial mismo día)

**Problema:** si la web vende plazas de una franja y, el mismo día, en puerta venden más, se
podría superar el aforo físico.

Cashlogy no lo resuelve (no sabe de entradas) e integrar con el sistema de pulseras dependería
de una API desconocida. Solución heredada, simple y sin integraciones:

- **Cupo online por franja [DECIDIDO en el origen]:** el operador decide cuántas plazas de cada
  franja se venden **online**; el resto quedan para **puerta**. Ej.: franja de 50 plazas →
  35 online, 15 en puerta. La web nunca vende de más.
- El operador puede **abrir/cerrar** la venta online de una franja en cualquier momento desde
  el panel (p. ej., si el recinto se llena).

⚠️ Supuesto de dominio clave: **el aforo que gestiona la web es solo el cupo online, no el aforo
físico total.** No conviertas el cupo en «aforo real» al generalizar sin revisar este supuesto.

## 5. Integraciones con hardware: descartadas en la base heredada

- **Sistema de pulseras:** solo viable si el fabricante ofrece API/documentación (desconocido en
  el origen). Riesgo alto; quedó como investigación futura.
- **Cashlogy:** no aporta datos de entradas; descartado para aforo.

> Conclusión heredada: web y sistemas físicos **conviven**; el aforo se protege con **cupo
> online**; la integración con hardware no bloquea nada. Para JumpWeb: cada cliente del sector
> puede traer hardware distinto — este patrón (cupo online + canje manual en puerta) es el que
> funciona sin depender de ninguno.
