{{--
    La vista previa de un correo enviado (`specs/correos-salientes.md` §4.2, `#794`): el HTML TAL CUAL salió, dentro de un
    `iframe` con `srcdoc` y `sandbox` VACÍO —ni scripts, ni formularios, ni ventanas nuevas, ni navegar fuera—. ⚠️ Ese
    `sandbox` NO impide que el marco navegue dentro de sí mismo: por eso el HTML llega DESACTIVADO (`EmailSendTable::inert()`,
    `#796`: `<base target="_blank">`, que sin `allow-popups` se bloquea, y sin la marca del envío), y un enlace pulsado aquí
    ni abre nada ni cuenta como clic del cliente. `{{ }}` escapa el HTML para el atributo y el navegador lo devuelve entero al
    pintarlo. Livewire sirve esta respuesta con `no-store` (`RGPD-04`).

    @var string $html
--}}
<div data-email-preview>
    <iframe
        title="{{ __('admin.email_sends.preview') }}"
        sandbox=""
        referrerpolicy="no-referrer"
        srcdoc="{{ $html }}"
        class="w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
        style="height: 70vh;"
    ></iframe>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.email_sends.preview_inert') }}</p>
</div>
