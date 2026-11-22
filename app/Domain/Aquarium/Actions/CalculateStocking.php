<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Calculators\StockingLoad;
use App\Domain\Aquarium\Data\CalculatorResult as R;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CalculateStocking
{
    public function __construct(private Access $access, private CalculatorLimits $limits, private Catalog $catalog) {}

    /**
     * Ryby z bazy (Typ = Ryba) po identyfikatorze.
     *
     * @return array<string, CatalogEntry>
     */
    public function species(): array
    {
        $fish = array_filter($this->catalog->snapshot(Area::Fauna), fn (CatalogEntry $e) => trim($e->fields['Typ'] ?? '') === 'Ryba');

        return array_column($fish, null, 'id');
    }

    /**
     * Obciążenie w cm ryby na litr: wynik obliczenia, bez progu i oceny.
     *
     * @param  array<string, mixed>  $input  volume oraz tablice species, quantity, length (wspólne indeksy wierszy)
     */
    public function handle(?User $actor, array $input): R
    {
        $this->access->authorize($actor, Feature::Calculators);

        $species = $this->species();
        $col = fn (string $name): array => is_array($input[$name] ?? null) ? $input[$name] : [];
        $indexes = array_unique([...array_keys($col('species')), ...array_keys($col('quantity')), ...array_keys($col('length'))]);

        $rows = [];
        $data = ['volume' => CalculatorLimits::norm($input['volume'] ?? '')];
        $rules = ['volume' => $this->limits->rules('volume_l')];
        $names = ['volume' => 'Litraż netto'];
        foreach ($indexes as $i) {
            $row = [
                'species' => (string) ($col('species')[$i] ?? ''),
                'quantity' => CalculatorLimits::norm($col('quantity')[$i] ?? ''),
                'length' => CalculatorLimits::norm($col('length')[$i] ?? ''),
            ];
            if ($row['species'] === '' && $row['quantity'] === '' && $row['length'] === '') {
                continue;
            }
            $n = (int) $i + 1;
            if ($row['species'] !== '' && ! isset($species[$row['species']])) {
                throw ValidationException::withMessages(["species.$i" => "Wybierz gatunek z listy (pozycja $n)."]);
            }
            if ($row['species'] === '' && $row['length'] === '') {
                throw ValidationException::withMessages(["species.$i" => "Wybierz gatunek albo wpisz własną długość (pozycja $n)."]);
            }
            $data['quantity'][$i] = $row['quantity'];
            $rules["quantity.$i"] = $this->limits->rules('quantity', integer: true);
            $names["quantity.$i"] = "Ilość (pozycja $n)";
            $data['length'][$i] = $row['length'];
            $rules["length.$i"] = $this->limits->rules('fish_length_cm', required: false);
            $names["length.$i"] = "Długość (pozycja $n)";
            $rows[$i] = $row;
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['quantity.0' => 'Dodaj co najmniej jedną rybę.']);
        }
        $v = Validation::validate($data, $rules, $names);

        $items = [];
        $steps = [];
        $notes = [];
        foreach ($rows as $i => $row) {
            $entry = $species[$row['species']] ?? null;
            $band = $entry->fields['Wielkość'] ?? 'brak danych';
            $qty = (int) $v['quantity'][$i];
            $label = $entry->name ?? 'Własna pozycja';
            if ($row['length'] !== '') {
                $len = (float) $row['length'];
                $source = $entry === null ? 'długość wpisana przez Ciebie' : "długość wpisana przez Ciebie; baza: Wielkość = $band";
            } else {
                $len = StockingLoad::maxLengthCm(FishBand::fromLabel($entry->fields['Wielkość'] ?? null));
                if ($len === null) {
                    $notes[] = "Pominięto: $label — brak danych o długości w bazie (Wielkość = $band). Wpisz własną długość, a pozycja zostanie policzona.";

                    continue;
                }
                $source = "baza: Wielkość = $band, górna granica pasma";
            }
            $items[] = ['quantity' => $qty, 'length' => (float) $len];
            $steps[] = "$label: $qty × ".R::fmt((float) $len).' cm = '.R::fmt($qty * $len)." cm ($source)";
        }
        $notes[] = 'To orientacyjna reguła praktyczna, a nie norma ani wynik badań. Kalkulator nie ocenia, czy obsada jest odpowiednia.';
        if ($items === []) {
            return new R(null, 'cm/l', $steps, $notes);
        }

        $liters = (float) $v['volume'];
        $total = StockingLoad::totalCm($items);
        $value = round(StockingLoad::cmPerLiter($total, $liters), 2);
        $steps[] = 'Razem '.R::fmt($total).' cm ÷ '.R::fmt($liters).' l = '.R::fmt($value, 2).' cm/l';

        return new R($value, 'cm/l', $steps, $notes, ['total_cm' => $total]);
    }
}
