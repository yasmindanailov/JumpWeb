@php
    /** @var \App\Models\User $record */
@endphp
<span class="flex flex-wrap items-center gap-2">
    <span>{{ $record->name }}</span>

    @foreach ($record->roles as $role)
        <x-filament::badge :color="$role->name === 'admin' ? 'danger' : ($role->name === 'staff' ? 'warning' : 'primary')">
            {{ __('admin.users.roles.'.$role->name) }}
        </x-filament::badge>
    @endforeach

    @if ($record->isAnonymized())
        <x-filament::badge color="gray">{{ __('admin.users.status.anonymized') }}</x-filament::badge>
    @endif
</span>
