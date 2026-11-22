<?php

namespace App\Domain\Shops\Notifications;

use App\Domain\Shops\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Wiadomość o zamówieniu: `submitted` do sklepu (bez danych klienta, tylko link do panelu),
 * `confirmed`, `rejected` i `ready` do klienta.
 */
class OrderNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $kind) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order->loadMissing(['shop', 'location']);
        $mail = new MailMessage;

        return match ($this->kind) {
            'submitted' => $mail->subject('Nowe zamówienie do rozpatrzenia')
                ->line("W sklepie {$o->shop->name} czeka nowe zamówienie nr {$o->id}.")
                ->action('Otwórz panel sklepu', url("/panel-sklepu/{$o->shop_id}/zamowienia")),
            'confirmed' => $mail->subject('Zamówienie potwierdzone')
                ->line("Sklep {$o->shop->name} potwierdził Twoje zamówienie.")
                ->line('Kwota do zapłaty: '.number_format($o->total_grosze / 100, 2, ',', ' ').' zł.')
                ->line("Tytuł przelewu: {$o->ean13}")
                ->action('Zobacz zamówienie', url("/zamowienia/{$o->id}")),
            'rejected' => $mail->subject('Zamówienie odrzucone')
                ->line("Sklep {$o->shop->name} odrzucił Twoje zamówienie (numer sprawy {$o->ean13}).")
                ->line('Powód: '.($o->rejection_code === 'inne' ? $o->note : (Order::REJECTION_CODES[$o->rejection_code] ?? '—')))
                ->action('Zobacz zamówienie', url("/zamowienia/{$o->id}")),
            default => $mail->subject('Zamówienie gotowe do odbioru')
                ->line("Twoje zamówienie {$o->ean13} jest gotowe do odbioru.")
                ->line("Miejsce odbioru: {$o->location->name}, {$o->location->address}, {$o->location->city}.")
                ->action('Zobacz zamówienie', url("/zamowienia/{$o->id}")),
        };
    }
}
