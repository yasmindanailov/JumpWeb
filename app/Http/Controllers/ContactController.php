<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\Setting;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        // Anti-spam: honeypot. Si el campo oculto viene relleno, es un bot:
        // respondemos como si todo fuera bien, pero no enviamos nada.
        if (filled($request->input('website'))) {
            return redirect()->route('contacto')->with('contact_sent', true);
        }

        // Anti-bot Turnstile (auditoría Fase 1, A4): no-op si no hay claves configuradas (la web
        // funciona igual); con claves, un token ausente/ inválido se descarta como el honeypot. La
        // ruta lleva además `throttle:5,1` como tope de tasa. Espejo de `Register`.
        if (! Turnstile::verify((string) $request->input('cf-turnstile-response'), (string) $request->ip())) {
            return redirect()->route('contacto')->with('contact_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        // Fase 7.5 (decisión #180): el formulario de contacto SOLO envía un email
        // al administrador (al setting `contact.email`); ya NO persiste en BD (la
        // bandeja de contacto del panel se descartó). Si el setting está vacío, el
        // formulario sigue respondiendo OK sin enviar (no rompe la web pública).
        $to = Setting::value('contact.email');
        if ($to) {
            Mail::to($to)->send(new ContactMessageMail([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'message' => $data['message'],
                'locale' => app()->getLocale(),
            ]));
        }

        return redirect()->route('contacto')->with('contact_sent', true);
    }
}
