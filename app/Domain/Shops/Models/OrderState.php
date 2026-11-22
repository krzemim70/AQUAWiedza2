<?php

namespace App\Domain\Shops\Models;

enum OrderState: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Czeka na decyzję sklepu',
            self::Confirmed => 'Potwierdzone, czeka na wpłatę',
            self::Rejected => 'Odrzucone',
            self::Paid => 'Opłacone',
            self::Preparing => 'W przygotowaniu',
            self::Ready => 'Gotowe do odbioru',
            self::PickedUp => 'Odebrane',
            self::Closed => 'Zamknięte bez realizacji',
        };
    }

    /** Stan końcowy: dalsze zmiany są niedozwolone. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::PickedUp, self::Closed], true);
    }

    /** Stany z aktywną rezerwacją ilości. */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Confirmed, self::Paid, self::Preparing, self::Ready], true);
    }
}
