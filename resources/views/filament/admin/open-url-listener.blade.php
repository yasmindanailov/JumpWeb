{{-- Listener global del panel (decisión #184): abre en PESTAÑA NUEVA la URL de un
     evento Livewire `open-url-new-tab`. Lo usa el botón "Imprimir resumen del día"
     (la acción no puede usar un <a target="_blank"> porque lleva un formulario en
     modal; emite el evento tras enviar). Robustez: si el navegador BLOQUEA el popup,
     cae a navegación en la misma pestaña → la acción nunca se pierde.

     Registrado en HEAD_END (se ejecuta antes de que Livewire arranque, así el
     listener queda registrado para `livewire:init`). --}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('open-url-new-tab', (event) => {
            const url = (event && event.url)
                || (Array.isArray(event) && event[0] ? event[0].url : null);
            if (! url) return;
            // NO pasar 'noopener' en los features de window.open: con noopener el método
            // devuelve SIEMPRE null (por especificación), aunque la pestaña SÍ se abra. Eso
            // hacía que el fallback de «popup bloqueado» de abajo se disparase también en el
            // caso normal → la URL se abría DOS veces (pestaña nueva + misma pestaña). En su
            // lugar abrimos normal y anulamos `opener` a mano (misma protección anti
            // reverse-tabnabbing que noopener) y solo caemos a la misma pestaña cuando el
            // popup fue REALMENTE bloqueado (win === null).
            const win = window.open(url, '_blank');
            if (win) {
                win.opener = null;           // seguridad: sin referencia inversa a esta pestaña
            } else {
                window.location.href = url;  // popup bloqueado → misma pestaña (fallback)
            }
        });
    });
</script>
