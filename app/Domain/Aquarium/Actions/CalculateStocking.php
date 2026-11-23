<?php

namespace App\Domain\Aquarium\Actions;

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
        $columns = array_map(fn (string $name): array => is_array($input[$name] ?? null) ? $input[$name] : [], ['species', 'quantity', 'length']);
        [$ids, $quantities, $lengths] = $columns;
        $indexes = array_unique([...array_keys($ids), ...array_keys($quantities), ...array_keys($lengths)]);

        $rows = [];
        $data = ['volume' => CalculatorLimits::norm($input['volume'] ?? '')];
        $rules = ['volume' => $this->limits->rules('volume_l', required: false)];
        $names = ['volume' => 'Litraż netto'];
        foreach ($indexes as $i) {
            $row = [
                'species' => (string) ($ids[$i] ?? ''),
                'quantity' => CalculatorLimits::norm($quantities[$i] ?? ''),
                'length' => CalculatorLimits::norm($lengths[$i] ?? ''),
            ];
            if ($row['species'] === '' && $row['quantity'] === '' && $row['length'] === '') {
                continue;
            }
            $n = (int) $i + 1;
            if ($row['species'] !== '' && ! isset($species[$row['species']])) {
                throw ValidationException::withMessages(["species.$i" => "Wybierz gatunek z listy (pozycja $n)."]);
            }
            $data['quantity'][$i] = $row['quantity'];
            $rules["quantity.$i"] = $this->limits->rules('quantity', integer: true, required: false);
            $names["quantity.$i"] = "Ilość (pozycja $n)";
            $data['length'][$i] = $row['length'];
            $rules["length.$i"] = $this->limits->rules('fish_length_cm', required: false);
            $names["length.$i"] = "Długość (pozycja $n)";
            $rows[$i] = $species[$row['species']]->name ?? 'Własna pozycja';
        }
        $v = Validation::validate($data, $rules, $names);
        if ($rows === [] || $v['volume'] === '' || in_array('', $v['quantity'], true) || in_array('', $v['length'], true)) {
            return new R(null, 'cm/l', notes: ['Podaj litraż netto oraz liczbę i długość dorosłych ryb w każdej uzupełnianej pozycji.']);
        }

        $items = [];
        $steps = [];
        foreach ($rows as $i => $label) {
            $qty = (int) $v['quantity'][$i];
            $len = (float) $v['length'][$i];
            $items[] = ['quantity' => $qty, 'length' => $len];
            $steps[] = "$label: $qty × ".R::fmt($len).' cm = '.R::fmt($qty * $len).' cm';
        }

        $liters = (float) $v['volume'];
        $total = StockingLoad::totalCm($items);
        $value = round(StockingLoad::cmPerLiter($total, $liters), 2);
        $steps[] = 'Razem '.R::fmt($total).' cm ÷ '.R::fmt($liters).' l = '.R::fmt($value, 2).' cm/l';

        return new R($value, 'cm/l', $steps, ['Orientacyjny wskaźnik z podanych długości dorosłych ryb i litrażu netto.'], ['total_cm' => $total]);
    }
}
