{{--
    Fase 6 · waiver — PDF del REGISTRO probatorio de una firma (`docs/specs/waiver-probatorio.md` §4.5).

    Se compone del SNAPSHOT (`legal_document_versions`) y de la fila de firma, nunca del CMS: editar
    la página del waiver NO cambia este documento (guarda §6·2). Es el informe LEGIBLE de un
    registro, no un documento firmado digitalmente — y lo dice al pie. Determinista: sin fecha de
    generación (la fecha que prueba es la de la aceptación).

    Renderizado con dompdf: solo el subconjunto de CSS que soporta (tablas, nada de flex/grid).
    Fuente DejaVu Sans (acentos). Idioma = el del texto firmado (lo fija el controlador).

    @var \App\Domain\Identity\Services\WaiverProof $proof
--}}
@php
    $t = fn (string $key, array $replace = []): string => __('waiver.proof.'.$key, $replace);
@endphp
<!DOCTYPE html>
<html lang="{{ $proof->locale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t('title') }} · {{ $proof->versionLabel() }}</title>
    <style>
        @page { margin: 16mm 15mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #14130f; font-size: 10.5px; line-height: 1.45; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }
        .wordmark { font-size: 16px; font-weight: bold; letter-spacing: .3px; }
        .doc-title { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
        .version { font-size: 20px; font-weight: bold; letter-spacing: .5px; text-align: right; }
        .version-sub { font-size: 9px; color: #6b7280; text-align: right; }
        .rule { border: none; border-top: 1.5px solid #e5e7eb; margin: 10px 0; }
        .sec { margin-top: 12px; }
        .sec-title { font-size: 10px; font-weight: bold; color: #374151; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin-bottom: 6px; }
        .kv td { padding: 2px 0; }
        .kv .k { color: #6b7280; width: 34%; }
        .kv .v { font-weight: bold; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5px; word-break: break-all; }
        .text-box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
        .text-title { font-size: 14px; font-weight: bold; margin: 0 0 8px; }
        .text-h { font-size: 11px; font-weight: bold; margin: 8px 0 2px; }
        .text-p { margin: 0 0 4px; }
        .note { font-size: 9px; color: #6b7280; }
        .declared { border: 1px solid #fcd34d; background: #fffbeb; border-radius: 8px; padding: 8px 11px; margin-top: 8px; }
        .declared-title { font-size: 11px; font-weight: bold; color: #92400e; }
        .declared-text { font-size: 10px; color: #92400e; }
        .ok { color: #047857; font-weight: bold; }
        .ko { color: #991b1b; font-weight: bold; }
        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8.5px; color: #6b7280; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <div class="wordmark">{{ $proof->businessName() }}</div>
                <div class="doc-title">{{ $t('title') }}</div>
            </td>
            <td style="width: 40%;">
                <div class="version">{{ $proof->versionLabel() }}</div>
                <div class="version-sub">{{ $t('published_at', ['date' => $proof->publishedAtLabel()]) }}</div>
            </td>
        </tr>
    </table>
    <hr class="rule">

    {{-- El texto firmado, ÍNTEGRO, desde el snapshot. --}}
    <div class="sec">
        <div class="sec-title">{{ $t('signed_text') }}</div>
        <div class="text-box">
            <p class="text-title">{{ $proof->title() }}</p>
            @foreach ($proof->sections() as $section)
                @if ($section['h'] !== '')
                    <p class="text-h">{{ $section['h'] }}</p>
                @endif
                @if ($section['p'] !== '')
                    <p class="text-p">{{ $section['p'] }}</p>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Quién. Datos declarados por el titular, no verificados (§4.5): se dice. --}}
    <div class="sec">
        <div class="sec-title">{{ $t('holder') }}</div>
        <table class="kv">
            <tr><td class="k">{{ $t('holder_name') }}</td><td class="v">{{ $proof->holderName() }}</td></tr>
            <tr><td class="k">{{ $t('holder_email') }}</td><td class="v">{{ $proof->holderEmail() }}</td></tr>
            <tr><td class="k">{{ $t('subject') }}</td><td class="v">{{ $proof->isForHolder() ? $t('subject_holder') : $t('subject_dependent', ['id' => $proof->subjectId()]) }}</td></tr>
        </table>
        {{-- §10.6 (WAI-07): en una firma de mostrador los datos los tecleó el operador — se dice. --}}
        <p class="note">{{ $t($proof->isDeclared() ? 'holder_note_declared' : 'holder_note') }}</p>
        @if ($proof->holderIsAnonymised())
            <p class="note">{{ $t('holder_anonymised') }}</p>
        @endif
    </div>

    {{-- Cuándo, desde dónde, por qué canal. --}}
    <div class="sec">
        <div class="sec-title">{{ $t('acceptance') }}</div>
        <table class="kv">
            <tr><td class="k">{{ $t('accepted_at') }}</td><td class="v">{{ $proof->acceptedAtLabel() }} ({{ $proof->acceptedTz() }}) · UTC {{ $proof->acceptedAtUtc() }}</td></tr>
            <tr><td class="k">{{ $t('channel') }}</td><td class="v">{{ $t('channels.'.$proof->channel()) }}</td></tr>
            {{-- §10.6 (WAI-07): en mostrador la IP y el navegador son del PUESTO, no de la persona. --}}
            <tr><td class="k">{{ $t($proof->isDeclared() ? 'ip_declared' : 'ip') }}</td><td class="v">{{ $proof->ip() }}</td></tr>
            <tr><td class="k">{{ $t($proof->isDeclared() ? 'user_agent_declared' : 'user_agent') }}</td><td class="v mono">{{ $proof->userAgent() }}</td></tr>
        </table>
        @if ($proof->isDeclared())
            {{-- §8.4: sustancialmente más débil que una firma del titular, y se dice con todas las letras. --}}
            <div class="declared">
                <div class="declared-title">{{ $t('declared_title') }}</div>
                <div class="declared-text">{{ $t('declared_text', ['operator' => $proof->declaredByName() ?? '—']) }}</div>
            </div>
        @else
            <p class="note">{{ $t('presented_note') }}</p>
        @endif
    </div>

    {{-- Integridad: lo que un auditor pregunta primero. --}}
    <div class="sec">
        <div class="sec-title">{{ $t('integrity') }}</div>
        <table class="kv">
            <tr><td class="k">{{ $t('document_hash') }}</td><td class="v mono">{{ $proof->documentHash() }}</td></tr>
            <tr><td class="k">{{ $t('signature_hash') }}</td><td class="v mono">{{ $proof->signatureHash() }}</td></tr>
            <tr><td class="k">{{ $t('prev_hash') }}</td><td class="v mono">{{ $proof->prevHash() ?? $t('first_link') }}</td></tr>
            <tr><td class="k">{{ $t('canonical') }}</td><td class="v">v{{ $proof->canonicalVersion() }}</td></tr>
            <tr><td class="k">{{ $t('verification') }}</td><td class="v {{ $proof->integrityOk() ? 'ok' : 'ko' }}">{{ $proof->integrityOk() ? $t('verified_yes') : $t('verified_no') }}</td></tr>
        </table>
        {{-- §10.6 (WAI-02): el PDF no puede decir más de lo que el diseño garantiza (§4.7: sin sello externo). --}}
        <p class="note">{{ $t('verification_note') }}</p>
    </div>

    <div class="sec">
        <div class="sec-title">{{ $t('retention') }}</div>
        <p class="text-p">{{ $proof->retainUntilLabel() !== null ? $t('retention_until', ['date' => $proof->retainUntilLabel()]) : $t('retention_none') }}</p>
    </div>

    <div class="footer">{{ $t('footer_note') }}</div>
</body>
</html>
