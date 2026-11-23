<?php

namespace App\Domain\Shops\Notifications;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\PreferenceMail;
use App\Domain\Notifications\Topic;
use App\Domain\Shops\Contracts\RecordWatches;
use App\Domain\Shops\Data\PublicOffer;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class RecordAvailableNotice extends PreferenceMail
{
    public function __construct(
        public int $watchId,
        public string $activation,
        public string $label,
        public PublicOffer $offer,
    ) {}

    protected function topic(): Topic
    {
        return Topic::Availability;
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return parent::shouldSend($notifiable, $channel)
            && app(ResolveAccess::class)->handle($notifiable, Feature::RecordWatch)->allowed
            && app(RecordWatches::class)->isCurrent($this->watchId, $notifiable->id, $this->activation);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('watches.unsubscribe', [$this->watchId, $this->activation]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->offer;
        $price = number_format($offer->priceGrosze / 100, 2, ',', ' ');

        return (new MailMessage)
            ->subject('Dostępność — '.$this->label)
            ->greeting($this->label)
            ->line('Dane oferty w chwili wykrycia')
            ->line('Sklep: '.$offer->shopName)
            ->line('Miasto: '.(trim($offer->locationCity) !== '' ? $offer->locationCity : 'brak danych'))
            ->line("Cena: {$price} zł")
            ->line("Dostępna ilość: {$offer->freeQty}")
            ->action('Zobacz stronę produktu', route('catalog.show', [$offer->area->value, $offer->legacyId]))
            ->markdown('notifications::email', ['unsubscribeUrl' => $this->unsubscribeUrl()]);
    }
}
