@php
    /**
     * @var \App\Domain\Identity\Models\User $record
     *
     * Fase 6 · menores a cargo, tanda 5 (D·3, `specs/menores-a-cargo.md` §9.10): los menores que este
     * titular tiene DECLARADOS, solo lectura. Aquí no se declara ni se retira a nadie: eso lo hace el
     * cliente desde su cuenta («Menores a cargo» del cajón) y el operador al asignar entradas en la
     * ficha del pedido. Esta sección solo RESPONDE «¿quién viene con este cliente y tiene la exención
     * firmada?».
     *
     * Todo lo que se enseña viene ya COMPUESTO por `HolderDependents` — una celda, un solo eco —:
     * con una condicional dentro de una celda, Livewire intercala marcadores de bloque y el texto
     * deja de poder afirmarse (ni leerse) de una pieza.
     */
    use App\Filament\Resources\Users\Support\HolderDependents;

    // Cuenta anonimizada: no se lista NADA (`RGPD-01`). Lo que sobrevive a `anonymize()` está bajo el
    // régimen restringido de su firma y su sitio es la acción «Registro del waiver», con permiso
    // propio y auditada. El read-model lo comprueba también, a propósito (guarda de la guarda).
    $anonymized = $record->isAnonymized();
    $dependents = $anonymized ? [] : HolderDependents::forHolder($record);

    // La exención solo tiene respuesta en modo interno; y la columna de la retirada solo se pinta si
    // hay alguna fila retirada (si no, sería una columna de guiones en el 95 % de las cuentas).
    $showWaiver = HolderDependents::showsWaiver();
    $showRemoved = collect($dependents)->contains(fn (array $row): bool => $row['is_removed']);
@endphp

@if ($anonymized)
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.users.dependents.anonymized') }}</p>
@elseif ($dependents === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.users.dependents.empty') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="py-1 pr-3 font-medium">{{ __('admin.users.dependents.col_name') }}</th>
                    {{-- `#236`: la relación del titular con el menor. En el panel sí se enseña —el
                         operador atiende una incidencia y necesita saber quién es quién—; la
                         pantalla de puerta no la lleva. Las fichas de antes de `#236` la tienen
                         vacía y se pinta «—»: no se inventa. --}}
                    <th class="py-1 pr-3 font-medium">{{ __('admin.users.dependents.col_relationship') }}</th>
                    <th class="py-1 pr-3 font-medium">{{ __('admin.users.dependents.col_age') }}</th>
                    @if ($showWaiver)
                        <th class="py-1 pr-3 font-medium">{{ __('admin.users.dependents.col_waiver') }}</th>
                    @endif
                    <th class="py-1 pr-3 font-medium">{{ __('admin.users.dependents.col_since') }}</th>
                    @if ($showRemoved)
                        <th class="py-1 font-medium">{{ __('admin.users.dependents.col_removed') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($dependents as $row)
                    <tr data-dependent-row="{{ $row['id'] }}"
                        @if ($row['is_removed']) data-dependent-removed="{{ $row['id'] }}" @endif
                        @class(['opacity-60' => $row['is_removed']])>
                        <td class="py-1.5 pr-3 font-medium text-gray-800 dark:text-gray-200">{{ $row['name'] }}</td>
                        <td class="py-1.5 pr-3 text-gray-600 dark:text-gray-300" data-dependent-relationship="{{ $row['relationship'] !== null ? 'yes' : 'none' }}">{{ $row['relationship'] ?? '—' }}</td>
                        <td class="py-1.5 pr-3 text-gray-600 dark:text-gray-300" data-dependent-age="{{ $row['years'] }}">{{ $row['age'] }}</td>
                        @if ($showWaiver)
                            <td class="py-1.5 pr-3 text-gray-600 dark:text-gray-300" data-dependent-waiver="{{ $row['waiver'] }}">{{ $row['waiver_label'] ?? '—' }}</td>
                        @endif
                        <td class="py-1.5 pr-3 text-xs text-gray-500 dark:text-gray-400">{{ $row['since'] }}</td>
                        @if ($showRemoved)
                            <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $row['removed'] ?? '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
