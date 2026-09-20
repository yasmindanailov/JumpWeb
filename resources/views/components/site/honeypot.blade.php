{{-- ══ EL HONEYPOT · el campo que un humano no ve y un bot rellena ══════════════════════════════
     Mecanismo del PRODUCTO (F5 · T2b, `DECISIONES #654`). Vivía en línea en `pages/contact.blade.php`,
     y el nombre del campo es un contrato con `ContactController` (`Platform\Services\Honeypot`): una
     landing de instancia que lo re-escribiera con otro nombre dejaría de filtrar bots SIN QUE NADA
     FALLARA. Por eso es un componente y no tres líneas que copiar: la instancia pone `<x-site.honeypot />`
     dentro de su `<form>` y no tiene que saber cómo se llama el campo.

     ⚠️ `display:none` para que el autocompletar no lo rellene, y `tabindex="-1"` para que el teclado no
     llegue. El rótulo existe para el lector de pantalla que sí lo alcance: dice que no se rellene. --}}
<div style="display:none" aria-hidden="true">
    <label>{{ __('site.contact_hp') }}<input type="text" name="{{ \App\Domain\Platform\Services\Honeypot::FIELD }}" tabindex="-1" autocomplete="off"></label>
</div>
