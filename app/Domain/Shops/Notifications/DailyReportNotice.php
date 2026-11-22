<?php

namespace App\Domain\Shops\Notifications;

use App\Domain\Shops\Models\DailyReport;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Raport dobowy sklepu: tylko agregaty, bez danych klientów. */
class DailyReportNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Shop $shop, public DailyReport $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->report->data;
        $mail = (new MailMessage)->subject("Raport dobowy: {$this->shop->name}, {$this->report->business_date->format('d.m.Y')}");
        foreach (OrderState::cases() as $state) {
            $mail->line("{$state->label()}: {$data['orders'][$state->value]}");
        }

        return $mail
            ->line("Sztuki zarezerwowane: {$data['pieces']['reserved']}, zwolnione: {$data['pieces']['released']}, wydane: {$data['pieces']['issued']}")
            ->line('Potwierdzone wpływy: '.number_format($data['payments_grosze'] / 100, 2, ',', ' ').' zł')
            ->action('Otwórz panel sklepu', url("/panel-sklepu/{$this->shop->id}/zamowienia"));
    }
}
