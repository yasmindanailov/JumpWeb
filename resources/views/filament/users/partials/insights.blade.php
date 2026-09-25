@php
    /**
     * @var \App\Domain\Identity\Models\User $record
     *
     * **La 360 del cliente** (`docs/specs/analitica.md` §4.6, T4a): lo que sabemos de esta persona como cliente,
     * en dos bloques —lo del CONTRATO, que sale siempre de los pedidos, y lo de la NAVEGACIÓN, que solo existe
     * en el régimen identificado—. Todo viene COMPUESTO por `CustomerInsights` (una celda, un solo eco); los
     * números en crudo van en `data-*` para poder afirmarse sin depender de una traducción.
     */
    use App\Filament\Resources\Users\Support\CustomerInsights;

    $i = CustomerInsights::forCustomer($record);
    $dash = '—';
@endphp

@if ($i['anonymized'])
    <p class="text-sm text-gray-500 dark:text-gray-400" data-insights="anonymized">{{ __('admin.users.insights.anonymized') }}</p>
@else
    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-3" data-insights="contract" data-insights-orders="{{ $i['orders'] }}" data-insights-identified="{{ $i['identified'] ? '1' : '0' }}">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.orders') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['orders'] }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.sold') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['sold'] }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.collected') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['collected'] }}@if ($i['refunded'] !== null) <span class="text-xs text-gray-500 dark:text-gray-400">· {{ __('admin.users.insights.refunded', ['amount' => $i['refunded']]) }}</span>@endif</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.first_purchase') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['first_purchase'] ?? $dash }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.last_purchase') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['last_purchase'] ?? $dash }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.frequency') }}</dt>
            <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['frequency'] ?? $dash }}</dd>
        </div>
        <div class="col-span-2 md:col-span-3">
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.products') }}</dt>
            <dd class="text-gray-800 dark:text-gray-200">
                @if ($i['products'] === [])
                    {{ $dash }}
                @else
                    @foreach ($i['products'] as $product)
                        <span class="mr-3 inline-block" data-insights-product="{{ $product['units'] }}">{{ $product['name'] }} <span class="text-gray-500 dark:text-gray-400">×{{ $product['units'] }}</span></span>
                    @endforeach
                @endif
            </dd>
        </div>
    </dl>

    {{-- Las FIESTAS de este cliente (T3 de `specs/analitica-fiesta.md` §4.4): desde sus pedidos, régimen del contrato;
         y si vino invitado antes de comprar (su correo firmó un justificante de menor invitado). --}}
    @php($p = $i['parties'])
    <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
        <p class="mb-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.parties') }}</p>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-3" data-insights="parties" data-insights-parties="{{ $p['count'] }}" data-insights-signatures="{{ $p['signatures'] }}" data-insights-came-as-guest="{{ $p['came_as_guest'] === null ? '0' : '1' }}">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.parties_count') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['count'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.forms_completed') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['forms_completed'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.invitations') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['invitations'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.replies_yes') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['replies_yes'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.signatures') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['signatures'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.extras_after') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['extras_after'] }}</dd>
            </div>
            <div class="col-span-2 md:col-span-3">
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.came_as_guest') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $p['came_as_guest'] === null ? __('admin.users.insights.came_as_guest_no') : __('admin.users.insights.came_as_guest_yes', ['date' => $p['came_as_guest']]) }}</dd>
            </div>
        </dl>
    </div>

    {{-- Las ENCUESTAS de este cliente (T4 de `specs/encuestas.md` §4.4): cuántas contestó y la última, con su nota y
         su texto. Atadas a la persona con este permiso (`[DECIDIDO owner]` §7·1): es lo que permite llamar tras una
         mala visita. --}}
    @php($s = $i['surveys'])
    <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
        <p class="mb-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.surveys') }}</p>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-3" data-insights="surveys" data-insights-surveys="{{ $s['answered'] }}">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.surveys_answered') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $s['answered'] }}</dd>
            </div>
            @if ($s['last_on'] !== null)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.surveys_last') }}</dt>
                    <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $s['last_on'] }} · {{ __('admin.analytics.surveys.channel.'.$s['last_channel']) }} · {{ $s['last_survey'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.surveys_last_score') }}</dt>
                    <dd class="font-medium text-gray-800 dark:text-gray-200" data-insights-last-score="{{ $s['last_score'] ?? '' }}">{{ $s['last_score'] === null ? __('admin.analytics.parties.none') : $s['last_score'].' / 5' }}</dd>
                </div>
                @if ($s['last_text'] !== null)
                    <div class="col-span-2 md:col-span-3">
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.surveys_last_text') }}</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $s['last_text'] }}</dd>
                    </div>
                @endif
            @endif
        </dl>
    </div>

    {{-- Lo de la NAVEGACIÓN: solo en el régimen identificado (`analytics` consentida y sin oposición). Si la
         cuenta no tiene sesiones atadas, se dice y no se inventa (§4.6). --}}
    <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
        <p class="mb-2 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.navigation') }}</p>
        @if (! $i['identified'])
            <p class="text-sm text-gray-500 dark:text-gray-400" data-insights="not-identified">{{ __('admin.users.insights.not_identified') }}</p>
        @else
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm md:grid-cols-3" data-insights="navigation" data-insights-visits-before="{{ $i['visits_before'] }}" data-insights-contacts="{{ $i['contacts'] }}">
                <div class="col-span-2 md:col-span-1">
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.first_source') }}</dt>
                    <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['first_source'] ?? $dash }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.visits_before') }}</dt>
                    <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['visits_before'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.users.insights.contacts') }}</dt>
                    <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $i['contacts'] }}</dd>
                </div>
            </dl>
        @endif
    </div>
@endif
