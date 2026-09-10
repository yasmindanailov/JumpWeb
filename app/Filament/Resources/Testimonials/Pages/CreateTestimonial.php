<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Testimonials\Concerns\InteractsWithTestimonialForm;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateTestimonial extends CreateRecord
{
    use InteractsWithTestimonialForm;

    protected static string $resource = TestimonialResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.testimonials.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareTestimonialData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Testimonial $record */
        $record = $this->record;

        // ⚠️ El autor SÍ entra en el registro: es un dato del contenido publicado, no PII de un
        // titular nuestro (`RGPD-02` mira los datos de clientes). El TEXTO no, que no aporta nada
        // a la trazabilidad y hace el log ilegible.
        AuditLogger::log('content.testimonial_created', $record, [
            'author' => (string) $record->author,
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return TestimonialResource::getUrl('index');
    }
}
