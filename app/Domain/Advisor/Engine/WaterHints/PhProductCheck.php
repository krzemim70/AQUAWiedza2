<?php

namespace App\Domain\Advisor\Engine\WaterHints;

enum PhProductCheck: string
{
    case Eligible = '';
    case MissingReading = 'brak odczytu pH';
    case IncompleteStock = 'brak danych katalogowych dla całej obsady';
    case ConflictingSources = 'zakresy pH z opisu i tabeli się różnią';
    case UnconfirmedRanges = 'brak jednoznacznego potwierdzenia zakresów pH w opisach';
    case NoCommonRange = 'brak wspólnego zakresu pH dla obsady';
    case NotAboveAll = 'pH nie przekracza zakresu każdego gatunku';
}
