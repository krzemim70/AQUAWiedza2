<?php

namespace App\Domain\Shops\Notifications;

use App\Domain\Shops\Models\Order;
use App\Models\User;
use App\Notifications\QueuedMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Wiadomość o zamówieniu: `submitted` do sklepu (bez danych klienta, tylko link do panelu),
 * `confirmed`, `rejected` i `ready` do klienta.
 */
class OrderNotice extends Notification implements ShouldQueueAfterCommit
{
    use QueuedMail, SerializesModels;

    public function __construct(#[WithoutRelations] public Order $order, public string $kind) {}

    public function shouldSend(User $notifiable, string $channel): bool
    {
        if ($this->kind === 'submitted') {
            return $this->order->shop->members()->where('user_id', $notifiable->id)->exists();
        }

        return $this->order->user_id === $notifiable->id && $this->order->contactVisible();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $mail = new MailMessage;
        $orderUrl = route('orders.show', $o->id);

        return match ($this->kind) {
            'submitted' => $mail->subject('Nowe zamówienie do rozpatrzenia')
                ->line("W sklepie {$o->shop->name} czeka nowe zamówienie nr {$o->id}.")
                ->action('Rozpatrz zamówienie', route('filament.shop.resources.zamowienia.view', ['tenant' => $o->shop_id, 'record' => $o->id])),
            'confirmed' => $mail->subject('Zamówienie potwierdzone')
                ->line("Sklep {$o->shop->name} potwierdził Twoje zamówienie.")
                ->line('Kwota do zapłaty: '.number_format($o->total_grosze / 100, 2, ',', ' ').' zł.')
                ->line("Wpisz {$o->ean13} w tytule przelewu. Dane do wpłaty znajdziesz w zamówieniu.")
                ->action('Zobacz zamówienie', $orderUrl),
            'rejected' => $mail->subject('Zamówienie odrzucone')
                ->line("Sklep {$o->shop->name} odrzucił Twoje zamówienie (numer sprawy {$o->ean13}).")
                ->line('Powód: '.($o->rejection_code === 'inne' ? $o->note : (Order::REJECTION_CODES[$o->rejection_code] ?? '—')))
                ->action('Zobacz zamówienie', $orderUrl),
            'ready' => $mail->subject('Zamówienie gotowe do odbioru')
                ->line("Twoje zamówienie {$o->ean13} jest gotowe do odbioru.")
                ->line("Miejsce odbioru: {$o->location->name}, {$o->location->address}, {$o->location->city}.")
                ->action('Zobacz zamówienie', $orderUrl),
            default => throw new InvalidArgumentException('Nieznany rodzaj powiadomienia o zamówieniu.'),
        };
    }
}
