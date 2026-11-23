<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Calculators\TankVolume;
use App\Domain\Aquarium\Data\CalculatorResult as R;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalculateTankVolume
{
    public function __construct(private Access $access, private CalculatorLimits $limits) {}

    /**
     * Litraż brutto z wymiarów; „po odjęciu” tylko, gdy użytkownik sam poda litry do odjęcia.
     *
     * @param  array<string, mixed>  $input  shape (cuboid|cylinder), length, width, height, diameter, deduct
     */
    public function handle(?User $actor, array $input): R
    {
        $this->access->authorize($actor, Feature::Calculators);

        $shape = (string) ($input['shape'] ?? 'cuboid');
        $fields = $shape === 'cylinder' ? ['diameter' => 'Średnica (cm)', 'height' => 'Wysokość lustra wody (cm)']
            : ['length' => 'Długość (cm)', 'width' => 'Szerokość (cm)', 'height' => 'Wysokość lustra wody (cm)'];
        $data = ['shape' => $shape, 'deduct' => CalculatorLimits::norm($input['deduct'] ?? '')];
        $rules = ['shape' => ['required', Rule::in(['cuboid', 'cylinder'])], 'deduct' => ['nullable', 'bail', 'numeric', 'min:0', 'max:'.$this->limits->max('volume_l')]];
        foreach ($fields as $name => $label) {
            $data[$name] = CalculatorLimits::norm($input[$name] ?? '');
            $rules[$name] = $this->limits->rules('dimension_cm');
        }
        $v = Validation::validate($data, $rules, $fields + ['shape' => 'Kształt', 'deduct' => 'Litry do odjęcia']);

        if ($shape === 'cylinder') {
            [$d, $h] = [(float) $v['diameter'], (float) $v['height']];
            $gross = TankVolume::cylinder($d, $h);
            $step = 'π × ('.R::fmt($d).' ÷ 2)² × '.R::fmt($h).' cm ÷ 1000 = '.R::fmt($gross, 2).' l';
        } else {
            [$l, $w, $h] = [(float) $v['length'], (float) $v['width'], (float) $v['height']];
            $gross = TankVolume::cuboid($l, $w, $h);
            $step = R::fmt($l).' × '.R::fmt($w).' × '.R::fmt($h).' cm = '.R::fmt($l * $w * $h).' cm³ ÷ 1000 = '.R::fmt($gross, 2).' l';
        }
        $gross = round($gross, 2);
        $steps = [$step];
        $net = null;
        if ((float) ($v['deduct'] ?? 0) > 0) {
            $deduct = (float) $v['deduct'];
            if ($deduct >= $gross) {
                throw ValidationException::withMessages(['deduct' => 'Litry do odjęcia muszą być mniejsze niż litraż brutto ('.R::fmt($gross, 2).' l).']);
            }
            $net = round($gross - $deduct, 2);
            $steps[] = R::fmt($gross, 2).' l − '.R::fmt($deduct).' l = '.R::fmt($net, 2).' l';
        }

        return new R($gross, 'l', $steps, [
            'To objętość brutto: wysokość to poziom lustra wody, a podłoże i dekoracje zmniejszają ilość wody. Litry do odjęcia wpisujesz sam.',
        ], ['net' => $net, 'save' => $net ?? $gross]);
    }
}
