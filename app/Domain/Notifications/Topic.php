<?php

namespace App\Domain\Notifications;

enum Topic: string
{
    case Availability = 'availability';
    case Reminders = 'reminders';
    case Reviews = 'reviews';
    case LowStock = 'low_stock';
}
