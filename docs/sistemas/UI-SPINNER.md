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
  a `landing.css`/`site.css`. Es copia fiel del mockup de diseño del origen (el mockup no está
  en este repo): **no se modifica**. Va estático —no por Vite— porque el minificador de Vite
  rompe `backdrop-filter`, que usa el overlay (decisión heredada del origen).
- **Añadidos propios de integración** (velo local sobre un panel, ajustes del spinner dentro
  de un botón) → **`public/css/site.css`** (nunca en `spinner.css`).
- **Componentes Blade reutilizables**: `resources/views/components/ui/` → `<x-ui.spinner>`
  (inline) y `<x-ui.loading-overlay>` (velo con spinner + etiqueta).
- **Textos** (i18n es/en/fr): `lang/{es,en,fr}/ui.php` (p. ej. `ui.loading` = «Cargando»).
  Los botones reutilizan sus propios textos de «enviando» (p. ej. `account.login.submitting`).

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

⚠️ **Ese mecanismo YA NO EXISTE y su hueco está ABIERTO** (4.7·2b·3, 2026-08-21). Retirado el
componente de compra, el cajón lo monta Vue con un `await import()` desde
`app.js::bootSpaEngine()`, y ahí **no se pinta nada mientras el chunk viaja**: la bandera
`spaLoading` es solo guarda de reentrada y no llega a ninguna vista, y el velo `.jj-loading` no
puede taparlo porque vive DENTRO de la app Vue que aún no ha montado. Está en `DEUDA.md`; el arreglo
acotado es pintar el velo **dentro** de `#sidecart-spa` en `layout.blade.php`, que Vue reemplaza al
montar. Un componente Livewire `lazy` nuevo sí seguiría usando `placeholder()`.

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
| Cajón de compra — abrir | 2 velo local | ❌ **hueco abierto**: el motor SPA se trae con `import()` y no pinta nada durante la espera (4.7·2b·3, ver §niveles y `DEUDA.md`) |
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
