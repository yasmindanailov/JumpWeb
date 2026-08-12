<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('admin.weekly_schedule.intro') }}
    </p>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                {{ __('admin.weekly_schedule.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
