<?php

namespace App\Domain\Shops\Notifications;

use App\Domain\Shops\Models\ImportBatch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferImportNotice extends Notification
{
    public function __construct(private ImportBatch $batch) {}

    /** @return list<string> */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        $counts = $this->batch->counts;

        return (new MailMessage)
            ->subject('Wynik importu ofert')
            ->line("Dodane: {$counts['added']}. Zaktualizowane: {$counts['updated']}. Bez zmian: {$counts['unchanged']}. Pominięte wiersze z błędami: {$counts['errors']}.")
            ->action('Otwórz raport importu', route('filament.shop.pages.import-ofert', ['tenant' => $this->batch->shop_id, 'import' => $this->batch->id]));
    }
}
