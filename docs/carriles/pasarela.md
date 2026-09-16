# Carril · Pasarela y producto (reservas)

> Máquina: **este ordenador** · Banda: **4xx** (último `#454`; libres `#455`–`#459`) · Docs: `sistemas/REDSYS.md`
> §14.bis · `ENTORNOS.md` §1 y §6 · Actualizado: 2026-09-16 (escrito por el carril de plataforma en F1 desde el
> estado del 13-09).
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB.

## Foto

- **Go-live hecho el 2026-09-13**: Redsys en `live` en producción (lo activó el owner en el panel), cobro y
  devolución reales probados de punta a punta (`R-VPCOHW`, `#594`); `redsys_merchant_url` puesta por el owner;
  la GUARDA 1 del despliegue admite `live` solo en producción.
- **Staging es el entorno de validación del banco** (`#453`): el catálogo de playjump.es (solo tablas de
  catálogo), el terminal de PRUEBAS de CaixaBank, la venta online abierta y dos cuentas (contraseñas en manos
  del owner). Dos compras aceptadas en headless (`R-7E76SN`, `R-ORKOAM`); la confirmación llega por la
  notificación S2S y la vuelta del navegador viene sin datos. **No re-sembrar staging** mientras dure.
- `#454`: el último intento decide, y `failed` devuelve el mismo rechazo que la vuelta firmada (su tarjeta
  «denegada» excepciona con `SIS0093` en vez de denegar).

## Por dónde retomar

- **Del owner**: la respuesta del banco al correo del 11-09 (plataforma «desarrollo propio», integración
  Hosted/Redirección, la URL de staging y el usuario de prueba) · mirar en sus Canales de pruebas que salen las
  operaciones · el contrato en CaixaBank Now.
- La lista de go-live de `REDSYS.md` §14.9 está cumplida en lo que dependía del repo.

## Ficheros de este carril

El módulo de pagos (`app/Domain/Payments/`), `sistemas/REDSYS.md`, `ENTORNOS.md`, `scripts/deploy.sh` y los
verificadores `redsys:verify-concurrency` y `purchase:verify-oversell`. Todo lo que toque
`RedsysReturnHandler`, `PaymentInitiator` u `OrderCreator` está en el `CRITICAL_RE` → `VERIFY_CONC=1`.

## Buzón

### Para otros carriles
- Nada pendiente.

### Atendido
- Nada.
