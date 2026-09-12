<h2>Nuevo mensaje de contacto</h2>

<p><strong>Nombre:</strong> {{ $contact['name'] }}</p>
<p><strong>Email:</strong> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
@if (! empty($contact['phone']))
    <p><strong>Teléfono:</strong> {{ $contact['phone'] }}</p>
@endif
{{-- El tema es opcional (`#535`): sin elegir no se escribe la línea, porque una categoría por
     defecto sería un dato que nadie ha dicho. En el idioma del PARQUE, como el asunto. --}}
@if (! empty($contact['topic']))
    <p><strong>Tema:</strong> {{ __('site.contact_topics.'.$contact['topic'], [], config('app.locale')) }}</p>
@endif

<p><strong>Mensaje:</strong></p>
<p>{{ $contact['message'] }}</p>

<hr>
<p style="color:#626A72; font-size:12px">
    Enviado desde el formulario de contacto · idioma: {{ ['es' => 'Español', 'en' => 'Inglés', 'fr' => 'Francés'][$contact['locale'] ?? ''] ?? ($contact['locale'] ?? '—') }} · {{ now()->format('d/m/Y H:i') }}
</p>
