<?php

namespace App\Filament\Resources\EmailSends\Pages;

use App\Filament\Resources\EmailSends\EmailSendResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailSends extends ListRecords
{
    protected static string $resource = EmailSendResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.email_sends.subheading');
    }
}
