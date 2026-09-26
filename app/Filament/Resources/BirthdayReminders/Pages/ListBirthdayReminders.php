<?php

namespace App\Filament\Resources\BirthdayReminders\Pages;

use App\Filament\Resources\BirthdayReminders\BirthdayReminderResource;
use Filament\Resources\Pages\ListRecords;

class ListBirthdayReminders extends ListRecords
{
    protected static string $resource = BirthdayReminderResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.birthday_reminders.subheading');
    }
}
