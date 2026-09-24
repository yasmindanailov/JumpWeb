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
