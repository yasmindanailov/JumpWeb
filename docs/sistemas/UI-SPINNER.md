# Sistema: feedback de carga (spinner)

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> **Cuándo leer esto:** siempre que añadas una acción que llame al servidor (envío de
> formulario, acción Livewire, carga de panel, redirección de pago) y el usuario deba **saber
> que algo está pasando**. Única fuente de verdad del feedback de carga: qué componente usar
> y cuál de los **tres niveles** aplica.

## 1. Principio

**Ninguna petición al servidor deja al usuario sin respuesta visual.** El indicador es siempre
el mismo **spinner de marca**, adaptado al contexto (3 niveles). **No** se usa overlay a
pantalla completa en cada clic: parpadearía y molestaría en acciones rápidas (calendario,
cantidades).

## 2. El componente

Spinner autocontenido. Hereda el color del texto (`currentColor`) y respeta
`prefers-reduced-motion`. Accesible: `role="status"` + texto solo para lectores de pantalla.

| Concepto | Valor |
|---|---|
| Clase base | `.jj-spinner` |
| Tamaños | `--xs` (20px) · `--sm` (28px) · `--md` (36px, def.) · `--lg` (56px) · `--xl` (88px) |
| Tokens CSS | `--jj-spinner-size` · `--jj-spinner-color` (def. `currentColor`) · `--jj-spinner-speed` (def. `1.4s`) |
| Texto lector pantalla | `.jj-spinner__sr` |
| Variante con etiqueta | `.jj-spinner-with-label` + `.jj-spinner-label` |
| Overlay pantalla completa | `.jj-spinner-overlay` (usa `backdrop-filter: blur()`) |

> Prefijo `jj-*`: residuo de marca del origen; su renombrado cae en el desbranding
> (`00-REFACTOR.md` Fase 1).

## 3. Dónde vive el código

- **CSS del spinner = estático** en **`public/css/spinner.css`**, enlazado en el layout junto
  a `landing.css`/`site.css`. Va estático —no por Vite— porque el minificador de Vite
  rompe `backdrop-filter`, que usa el overlay (decisión heredada del origen).
  ⚠️ **Aquí decía «es copia fiel del mockup del origen: no se modifica»**, y esa frase bloqueaba
  precisamente el encargo de que el dibujo fuera sustituible por instalación. Desde `#143` el
  fichero **sí se toca**, pero solo por su mitad de arriba y con guarda (§3.bis).
- **Añadidos propios de integración** (velo local sobre un panel, ajustes del spinner dentro
  de un botón) → **`public/css/site.css`** (nunca en `spinner.css`).
- **Componentes Blade reutilizables**: `resources/views/components/ui/` → `<x-ui.spinner>`
  (inline) y `<x-ui.loading-overlay>` (velo con spinner + etiqueta).
- **Textos** (i18n es/en/fr): `lang/{es,en,fr}/ui.php` (p. ej. `ui.loading` = «Cargando»).
  Los botones reutilizan sus propios textos de «enviando» (p. ej. `account.login.submitting`).

## 3.bis El DIBUJO es sustituible por instalación (`DECISIONES #143`)

⚠️ **`specs/landing-white-label.md` §4.5.2 afirmaba que cambiar el dibujo «es sustituir un fichero»
y que «no hay que construir nada: hay que usarlo». Medido el 2026-08-25: era FALSO.** El dibujo no
era ningún fichero — son **dos pseudo-elementos y un `@keyframes`** dentro de `spinner.css`, y este
mismo documento decía que esa hoja no se modifica. No había punto de sustitución ninguno.

▶ **La hoja tiene ahora DOS MITADES**, separadas por un marcador que lee `SpinnerTest`:

| | Qué hay | ¿Lo toca una instalación? |
|---|---|---|
| **§A · `>>> SPINNER:CONTRACT >>>`** | la caja, los 3 tokens, los 5 tamaños, `.jj-spinner__sr`, el velo, la variante con etiqueta y la **garantía de «reducir movimiento»** | **No.** Son las 31 referencias repartidas por 9 ficheros |
| **§B · `>>> SPINNER:DRAWING >>>`** | `::before`, `::after` y `@keyframes jjSpinnerHop` — el punto que salta sobre el bloque de espuma, o sea la marca del PRIMER cliente | **Sí. Es el punto de sustitución** |

**Cómo se sustituye**, desde `public/css/client.css` (§4 de `INSTALACION-CLIENTE.md`), que el layout
carga la última y por eso gana en cascada:

```css
/* Un aro girando, en vez del salto */
.jj-spinner::after  { content: none; }              /* apagar la pieza que no se usa */
.jj-spinner::before {
    content: ""; position: absolute; inset: 0;
    border: calc(var(--jj-spinner-size) * 0.12) solid currentColor;
    border-top-color: transparent; border-radius: 50%;
    background: none;                                /* el dibujo del producto pinta fondo */
    animation: miGiro var(--jj-spinner-speed) linear infinite;
    translate: 0 0;                                  /* el producto centra con translate: -50% 0 */
}
@keyframes miGiro { to { transform: rotate(360deg); } }
```

⚠️ **Cuatro cosas que el dibujo sustituto tiene que respetar**, y las cuatro las vigila `SpinnerTest`:
1. **La geometría se compone sobre `--jj-spinner-size`** — así los cinco tamaños siguen funcionando
   sin escribir una regla por tamaño.
2. **El color sale de `var(--jj-spinner-color)`**, que por defecto es `currentColor`: es lo que hace
   que el spinner de un botón herede el color del botón. Un literal ahí lo desengancha de los 31 sitios.
3. **Los selectores del producto son los mínimos** (`.jj-spinner::before` / `::after`). Si el producto
   se diera especificidad de más, el paquete del cliente **cargaría y no pintaría** — y ese síntoma es
   indistinguible de un fichero que no carga.
4. **No hace falta declarar nada para «reducir movimiento»**: lo garantiza §A.

⚠️⚠️ **Y esa garantía se arregló al hacer esto.** Hasta el 2026-08-25 la regla era
`.jj-spinner::before { animation: none }` — exactamente la única pieza que anima **el dibujo del
primer cliente**. Un dibujo sustituto que animara `::after` o el propio elemento habría dejado a quien
pidió reducir movimiento viéndolo girar: sin fallo, sin aviso y sin forma de verlo desde el producto.
Hoy cubre `.jj-spinner`, `::before` y `::after` con `!important`, porque `client.css` carga DESPUÉS y
no debe poder reactivar la animación por descuido.

## 4. Las tres reglas de uso (por contexto)

> Regla transversal: **siempre con retardo** (`wire:loading.delay`, ~200 ms) para que en
> respuestas instantáneas el spinner ni llegue a aparecer → cero parpadeo.

| Nivel | Cuándo | Cómo se ve | Componente |
|---|---|---|---|
| **1. En el botón** | Envíos de formulario (login, registro, recuperar/restablecer, «Mi cuenta») | Spinner pequeño **dentro del botón** + botón deshabilitado | `<x-ui.spinner size="xs">` con `wire:loading` |
| **2. Velo local** | Carga o recálculo de un **panel** (cajón de compra: elegir día/hora/mes) | Velo translúcido **solo sobre ese panel** + spinner con etiqueta | `<x-ui.loading-overlay>` (panel en `position:relative`) |
| **3. Pantalla completa** | Saltos que **bloquean toda la página** (ir a pagar → Redsys) | Overlay a pantalla completa con spinner XL + etiqueta | `<x-ui.loading-overlay fixed>` (clase `.jj-spinner-overlay`) |

**Micro-acciones** (steppers `+`/`−` de cantidades): **sin overlay** — demasiado rápidas y
frecuentes —; basta con que el control quede brevemente inactivo.

## 5. Integración con Livewire

Se ata con `wire:loading` + `wire:target` (el método que dispara la petición). Los
componentes `<x-ui.*>` **reenvían atributos**: se les pasa `wire:*` directamente.

**Nivel 1 — botón de envío:**
```blade
<button type="submit" class="btn btn--zone" wire:loading.attr="disabled" wire:target="login">
    <x-ui.spinner size="xs" wire:loading.delay wire:target="login" />
    <span wire:loading.remove wire:target="login">{{ __('account.login.submit') }}</span>
    <span wire:loading.delay wire:target="login">{{ __('account.login.submitting') }}</span>
</button>
```

**Nivel 2 — panel que carga en diferido:** con Livewire lo resolvía el `placeholder()` del propio
componente `lazy`, que devolvía `components.ui.loading-overlay-placeholder`.

⚠️ **Retirado ese componente (4.7·2b·3), el hueco se abrió y se cerró el mismo día** (`#112(h)`). El
cajón lo monta Vue con un `await import()` desde `app.js::bootSpaEngine()`, y durante esa descarga no
se pintaba nada: `spaLoading` es solo guarda de reentrada, y el velo `.jj-loading` **no puede taparlo
porque vive DENTRO de la app Vue que aún no ha montado**.

**Cómo se resuelve hoy**: el velo va **estático dentro de `#sidecart-spa`** en `layout.blade.php`,
con el mismo marcado (`.purchase-loading`) que servía el `placeholder()` retirado. No necesita ni una
línea de JS para apagarse: **Vue limpia el contenedor al montar** (`app.mount()` hace
`container.textContent = ''`, verificado en el runtime instalado). Si el chunk no carga, el `catch` de
`bootSpaEngine()` lo vacía — un spinner eterno MIENTE. Lo vigila
`SpinnerTest::test_the_spa_mount_point_carries_a_loading_veil_until_vue_takes_over`, que asevera
**dentro del nodo** (con DOM, no con regex: la primera versión usaba una expresión regular y al mutarla
se vio que daba verde con el velo fuera del hueco).

Un componente Livewire `lazy` nuevo sí seguiría usando `placeholder()`.

**Nivel 2 — panel que recalcula** (avanzar/retroceder, elegir día/hora/mes): velo local
dentro del panel (`position:relative`), apuntando a **todas** las acciones que recargan el
panel (incluido `back`, para que retroceder tenga el mismo feedback que avanzar):
```blade
<x-ui.loading-overlay wire:loading.delay
    wire:target="selectDate, selectTime, prevMonth, nextMonth, back" />
```

**Nivel 3 — pantalla completa** (ir a pagar):
```blade
<x-ui.loading-overlay fixed wire:loading.delay wire:target="checkout" />
```

## 6. Accesibilidad (obligatorio)

- El spinner lleva `role="status"` y texto para lector de pantalla (incluido en `<x-ui.spinner>`).
- El velo decorativo va con `aria-hidden="true"` (el texto accesible lo da el spinner interno).
- La animación respeta `prefers-reduced-motion` (ya en `spinner.css`).

## 7. Inventario de aplicación

> ⚠️ Inventario según el doc origen en el momento de su escritura; el flujo de pago se
> completó después en el origen, así que la fila «Ir a pagar» puede estar desfasada.
> **Verifica contra el código** el estado real de cada punto.

| Punto | Nivel | Estado (origen) |
|---|---|---|
| Login — enviar | 1 botón | ✅ |
| Registro — enviar y reenviar verificación | 1 botón | ✅ |
| Recuperar contraseña — enviar | 1 botón | ✅ |
| Restablecer contraseña — enviar | 1 botón | ✅ |
| Mi cuenta — perfil / contraseña / cerrar otras sesiones / borrar cuenta | 1 botón | ✅ |
| Cajón de compra — abrir | 2 velo local (estático dentro de `#sidecart-spa`, lo retira Vue al montar) | ✅ |
| Sidebar de compra — avanzar / retroceder (día · hora · mes · volver) | 2 velo local | ✅ |
| Sidebar de compra — `+`/`−` cantidades | micro (sin overlay, por diseño) | ✅ |
| Ir a pagar (Redsys) | 3 pantalla completa | marcado «pospuesto» en el doc origen — verificar |
| Cambiar de página | — (recarga del navegador; no hay SPA `wire:navigate`) | n/a |

## 8. Cómo añadir feedback a una feature nueva (guía rápida)

1. ¿Envío de **formulario / botón**? → **Nivel 1** (spinner en el botón).
2. ¿**Recarga o recalcula un panel** sin cambiar de página? → **Nivel 2** (velo local).
3. ¿**Bloquea toda la página** o sale de la web (pago, redirección)? → **Nivel 3**.
4. ¿**Micro-acción** rápida y repetida (stepper, toggle)? → sin overlay; deshabilita el control.
5. En todos: `wire:loading.delay` + texto traducido en `lang/*/ui.php`.

## Referencias

- Layout y carga de CSS: `resources/views/components/layout.blade.php`.
- Al aplicar el feedback en un punto nuevo, actualiza el inventario (§7).

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«UI de carga (spinner) · rebrandear su DIBUJO»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/sistemas/UI-SPINNER.md`.

- `docs/sistemas/UI-SPINNER.md` (**§3.bis: la hoja tiene DOS mitades** —contrato y dibujo— y solo la segunda se sustituye ·
- ⚠️ §3 decía «no se modifica» y bloqueaba el propio encargo)
