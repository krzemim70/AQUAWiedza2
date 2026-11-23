<?php

use App\Domain\Advisor\Actions\ExportCreatorBundle;
use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\Finding;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\Ranking;
use App\Domain\Advisor\Engine\ScoredRecord;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TankEngine;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;
use App\Domain\Advisor\FaunaPool;
use App\Domain\Aquarium\Actions\CalculateTankVolume;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function creatorNodeBinary(): string
{
    $portable = 'E:/workspace/aqua/tools/node/node.exe';

    return is_file($portable) ? $portable : 'node';
}

/**
 * Uruchamia wybraną funkcję z `resources/js/creator/engine.js` w Node na podanych argumentach JSON.
 *
 * @param  list<mixed>  $args
 * @return array<string, mixed>
 */
function runCreatorEngineJs(string $fn, array $args): array
{
    $script = <<<'JS'
import * as engine from './resources/js/creator/engine.js';

let raw = '';
process.stdin.setEncoding('utf8');
for await (const chunk of process.stdin) {
    raw += chunk;
}
const payload = JSON.parse(raw);
const result = engine[payload.fn](...payload.args);
process.stdout.write(JSON.stringify(result));
JS;

    $process = Process::path(base_path())
        ->input(json_encode(['fn' => $fn, 'args' => $args], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
        ->run([creatorNodeBinary(), '--input-type=module', '-e', $script]);

    expect($process->successful())->toBeTrue('Node error: '.$process->errorOutput());

    return json_decode($process->output(), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function normalizePhpCompatibility(CompatibilityReport $report): array
{
    return [
        'assessed' => $report->assessed,
        'verdict' => $report->verdict()->value,
        'findings' => array_map(fn (Finding $f) => [
            'key' => $f->key(),
            'check' => $f->check->value,
            'severity' => $f->severity->value,
            'ids' => $f->ids,
            'text' => $f->text,
            'evidence' => array_map(fn (Evidence $e) => [
                'column' => $e->column,
                'value' => $e->value,
                'recordId' => $e->recordId,
                'batch' => $e->batch,
                'text' => $e->text(),
            ], $f->evidence),
        ], $report->findings),
        'rows' => array_map(fn ($row) => [
            'check' => $row->check->value,
            'withData' => $row->checked,
            'missingData' => $row->noData,
        ], $report->rows),
        'unknown' => $report->unknown,
    ];
}

/** @return array<string, mixed> */
function normalizePhpRanking(Ranking $ranking): array
{
    return [
        'ranked' => array_map(fn (ScoredRecord $s) => [
            'id' => $s->record->id,
            'percent' => $s->percent(),
            'points' => $s->points,
            'maxPoints' => $s->maxPoints,
            'scores' => $s->scores,
        ], $ranking->matches),
        'topCount' => $ranking->topCount,
        'decidedBy' => $ranking->decidedBy?->value,
        'excluded' => $ranking->excluded,
        'noData' => $ranking->noData,
        'effectiveFishBand' => $ranking->fish?->value,
        'inferredFish' => $ranking->fishDerived,
    ];
}

beforeEach(function () {
    Storage::fake('local');
    app(ImportPackage::class)->handle(kbPackage());
});

it('matches PHP 1:1 for FN0001 + FN0165 in Nano general tank including discrepancies and Evidence::text', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $stock = [
        new StockLine(GuideArea::Fauna, 'FN0001', 1),
        new StockLine(GuideArea::Fauna, 'FN0165', 1),
    ];

    $php = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Nano, 'Ogólne / Towarzyskie'), $stock),
    );
    $js = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'nano', 'type' => 'Ogólne / Towarzyskie'],
        [
            ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 1],
            ['area' => 'fauna', 'id' => 'FN0165', 'qty' => 1],
        ],
    ]);

    expect(array_diff_key($js, ['discrepancies' => true]))->toBe($php);

    $findingKeys = array_column($js['findings'], 'key');
    $findingByKey = array_combine($findingKeys, $js['findings']);
    expect($findingByKey['temperature_pair:FN0001:FN0165']['severity'])->toBe('info')
        ->and($findingByKey['temperature_pair:FN0001:FN0165']['text'])->toContain('brak danych do porównania')
        ->and($findingByKey['tank_too_small:FN0001']['severity'])->toBe('warning')
        ->and($findingByKey['temperament:FN0001']['severity'])->toBe('info')
        ->and($findingByKey['temperament:FN0165']['severity'])->toBe('info');

    $faunaWithDesc = app(FaunaPool::class)->records($bundle['batchId'], ['FN0001', 'FN0165'], withDescriptions: true);
    $byId = [];
    foreach ($faunaWithDesc as $r) {
        $byId[$r->id] = $r;
    }
    [$phHintFn0001] = (new WaterHintsEngine)->assess(['ph' => 7.0, 'temp_c' => null], [$byId['FN0001']], 0, ['ph'], $bundle['batchId']);
    [$tempHintFn0165] = (new WaterHintsEngine)->assess(['ph' => null, 'temp_c' => 17.0], [$byId['FN0165']], 0, ['temp_c'], $bundle['batchId']);

    $discByKey = [];
    foreach ($js['discrepancies'] as $d) {
        $discByKey[$d['id'].':'.$d['column']] = $d;
    }

    expect($discByKey)->toHaveKeys(['FN0001:pH', 'FN0165:Temperatura'])
        ->and(array_column($discByKey['FN0001:pH']['evidence'], 'text'))
        ->toBe(array_map(fn (Evidence $e) => $e->text(), $phHintFn0001->evidence))
        ->and(array_column($discByKey['FN0165:Temperatura']['evidence'], 'text'))
        ->toBe(array_map(fn (Evidence $e) => $e->text(), $tempHintFn0165->evidence));
});

it('matches PHP 1:1 for FN0117 + FN0080 temperature and pH pair differences with full evidence', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $stock = [
        new StockLine(GuideArea::Fauna, 'FN0117', 1),
        new StockLine(GuideArea::Fauna, 'FN0080', 1),
    ];

    $php = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Medium, 'Ogólne / Towarzyskie'), $stock),
    );
    $js = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'medium', 'type' => 'Ogólne / Towarzyskie'],
        [
            ['area' => 'fauna', 'id' => 'FN0117', 'qty' => 1],
            ['area' => 'fauna', 'id' => 'FN0080', 'qty' => 1],
        ],
    ]);

    expect(array_diff_key($js, ['discrepancies' => true]))->toBe($php);

    $findingByKey = array_combine(array_column($js['findings'], 'key'), $js['findings']);
    expect($findingByKey['temperature_pair:FN0080:FN0117']['severity'])->toBe('info')
        ->and(array_column($findingByKey['temperature_pair:FN0080:FN0117']['evidence'], 'recordId'))->toBe(['FN0080', 'FN0080'])
        ->and($findingByKey['ph_pair:FN0080:FN0117']['severity'])->toBe('info')
        ->and(array_column($findingByKey['ph_pair:FN0080:FN0117']['evidence'], 'recordId'))->toBe(['FN0080', 'FN0080']);
});

it('matches PHP 1:1 for FN0090 + snail FN0153 temperament labels as info without pair warning', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $stock = [
        new StockLine(GuideArea::Fauna, 'FN0090', 1),
        new StockLine(GuideArea::Fauna, 'FN0153', 1),
    ];

    $php = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Small, 'Ogólne / Towarzyskie'), $stock),
    );
    $js = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'small', 'type' => 'Ogólne / Towarzyskie'],
        [
            ['area' => 'fauna', 'id' => 'FN0090', 'qty' => 1],
            ['area' => 'fauna', 'id' => 'FN0153', 'qty' => 1],
        ],
    ]);

    expect(array_diff_key($js, ['discrepancies' => true]))->toBe($php);

    $tempFindings = array_values(array_filter($js['findings'], fn (array $f) => $f['check'] === 'temperament'));
    expect($tempFindings)->toHaveCount(2)
        ->and($tempFindings[0]['severity'])->toBe('info')
        ->and($tempFindings[1]['severity'])->toBe('info');
});

it('matches PHP 1:1 for FN0150 (Wszystkie) and FN0078 in Nano tank_too_small', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $stock = [
        new StockLine(GuideArea::Fauna, 'FN0150', 1),
        new StockLine(GuideArea::Fauna, 'FN0078', 1),
    ];

    $php = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Nano, 'Ogólne / Towarzyskie'), $stock),
    );
    $js = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'nano', 'type' => 'Ogólne / Towarzyskie'],
        [
            ['area' => 'fauna', 'id' => 'FN0150', 'qty' => 1],
            ['area' => 'fauna', 'id' => 'FN0078', 'qty' => 1],
        ],
    ]);

    expect(array_diff_key($js, ['discrepancies' => true]))->toBe($php);

    $findingByKey = array_combine(array_column($js['findings'], 'key'), $js['findings']);
    expect($findingByKey['tank_too_small:FN0150']['severity'])->toBe('info')
        ->and($findingByKey['tank_too_small:FN0078']['severity'])->toBe('warning');
});

it('matches PHP 1:1 for FL0011 CO2 requirement without and with Dozowanie CO2 equipment', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $stock = [new StockLine(GuideArea::Flora, 'FL0011', 1)];

    $phpWithout = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Medium, 'Roślinne', co2Dosing: false), $stock),
    );
    $jsWithout = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'medium', 'type' => 'Roślinne'],
        [['area' => 'flora', 'id' => 'FL0011', 'qty' => 1]],
    ]);

    expect(array_diff_key($jsWithout, ['discrepancies' => true]))->toBe($phpWithout)
        ->and(array_column($jsWithout['findings'], 'key'))->toContain('co2_missing:FL0011');

    $phpWith = normalizePhpCompatibility(
        app(Compatibility::class)->check(new TankContext(TankBand::Medium, 'Roślinne', co2Dosing: true), $stock),
    );
    $jsWith = runCreatorEngineJs('checkCompatibility', [
        $bundle,
        ['sizeBand' => 'medium', 'type' => 'Roślinne'],
        [
            ['area' => 'flora', 'id' => 'FL0011', 'qty' => 1],
            ['area' => 'equipment', 'id' => 'SP0099', 'qty' => 1],
        ],
    ]);

    expect(array_diff_key($jsWith, ['discrepancies' => true]))->toBe($phpWith)
        ->and(array_column($jsWith['findings'], 'key'))->not->toContain('co2_missing:FL0011');
});

it('matches PHP TankEngine 1:1 for 4 profile combinations including pH weight promotion', function (
    TankProfile $phpProfile,
    array $jsProfile,
) {
    $bundle = app(ExportCreatorBundle::class)->handle();
    $records = app(FaunaPool::class)->records();

    $php = normalizePhpRanking((new TankEngine)->rank($records, $phpProfile));
    $js = runCreatorEngineJs('rankFauna', [$bundle, $jsProfile]);

    expect($js)->toBe($php);
})->with([
    'band only' => [
        new TankProfile(TankBand::Medium),
        ['sizeBand' => 'medium', 'type' => null],
    ],
    'band and type' => [
        new TankProfile(TankBand::Small, type: 'Ogólne / Towarzyskie'),
        ['sizeBand' => 'small', 'type' => 'Ogólne / Towarzyskie'],
    ],
    'band, temperature and pH' => [
        new TankProfile(TankBand::Medium, temperature: TemperatureBand::Warm, ph: PhBand::Acidic),
        ['sizeBand' => 'medium', 'type' => null, 'tempBand' => 'warm', 'phBand' => 'acidic'],
    ],
    'band and pH without temperature (pH promoted to weight 2)' => [
        new TankProfile(TankBand::Small, fish: FishBand::Small, ph: PhBand::Neutral),
        ['sizeBand' => 'small', 'type' => null, 'fishBand' => 'small', 'phBand' => 'neutral'],
    ],
]);

it('computes equipment hints, care groups, flora filter and volume thresholds accurately', function () {
    $bundle = app(ExportCreatorBundle::class)->handle();

    $eqCool = runCreatorEngineJs('recommendEquipment', [
        $bundle,
        ['sizeBand' => 'nano', 'type' => 'Ogólne / Towarzyskie'],
        [['area' => 'fauna', 'id' => 'FN0060', 'qty' => 5]],
    ]);
    expect($eqCool['hints']['heating']['status'])->toBe('conditional')
        ->and(mb_strtolower($eqCool['hints']['heating']['text']))->toContain('zależnie od pomieszczenia')
        ->and($eqCool['hints']['heating']['evidence'][0]['recordId'])->toBe('FN0060')
        ->and($eqCool['categories'])->toHaveCount(4)
        ->and($eqCool['missingSpecsNote'])->toContain('brak danych')
        ->and(json_encode($eqCool, JSON_UNESCAPED_UNICODE))->not->toContain('potrzebujesz', 'obowiązkowy');

    $eqWarm = runCreatorEngineJs('recommendEquipment', [
        $bundle,
        ['sizeBand' => 'small', 'type' => 'Ogólne / Towarzyskie'],
        [
            ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 3],
            ['area' => 'flora', 'id' => 'FL0011', 'qty' => 1],
        ],
    ]);
    expect($eqWarm['hints']['heating']['status'])->toBe('recommended')
        ->and(mb_strtolower($eqWarm['hints']['heating']['text']))->toContain('wskazana')
        ->and($eqWarm['hints']['co2']['status'])->toBe('required')
        ->and($eqWarm['hints']['co2']['text'])->toContain('Niezbędne dla:');

    $careShrimp = runCreatorEngineJs('recommendCare', [
        $bundle,
        [['area' => 'fauna', 'id' => 'FN0151', 'qty' => 10]],
    ]);
    expect($careShrimp['start'])->toHaveCount(28)
        ->and($careShrimp['feeding']['items'])->toHaveCount(8)
        ->and(array_unique(array_column($careShrimp['feeding']['items'], 'domain')))->toBe(['Pokarmy dla krewetek'])
        ->and($careShrimp['snailFoodMissing'])->toBeFalse()
        ->and($careShrimp['plants'])->toBe([])
        ->and($careShrimp['testing'])->not->toBeEmpty();

    $careSnail = runCreatorEngineJs('recommendCare', [
        $bundle,
        [
            ['area' => 'fauna', 'id' => 'FN0153', 'qty' => 2],
            ['area' => 'flora', 'id' => 'FL0001', 'qty' => 3],
        ],
    ]);
    expect($careSnail['snailFoodMissing'])->toBeTrue()
        ->and($careSnail['snailFoodNote'])->toContain('Pokarmy dla ślimaków: brak danych w bazie')
        ->and($careSnail['plants'])->not->toBeEmpty();

    $volEdge = runCreatorEngineJs('calculateVolume', [
        ['mode' => 'liters', 'liters' => 39.9],
        $bundle['rules'],
    ]);
    expect($volEdge['valid'])->toBeTrue()
        ->and($volEdge['bandLiters'])->toBe(39)
        ->and($volEdge['sizeBand'])->toBe('nano')
        ->and($volEdge['savedLiters'])->toBe(40);

    $phpCuboid = app(CalculateTankVolume::class)->handle(null, [
        'shape' => 'cuboid',
        'length' => 60,
        'width' => 30,
        'height' => 30,
        'deduct' => 4,
    ]);
    $jsCuboid = runCreatorEngineJs('calculateVolume', [
        ['mode' => 'dimensions', 'shape' => 'cuboid', 'length' => 60, 'width' => 30, 'height' => 30, 'deduct' => 4],
        $bundle['rules'],
    ]);
    expect($jsCuboid['valid'])->toBeTrue()
        ->and($jsCuboid['grossLiters'])->toEqual($phpCuboid->value)
        ->and($jsCuboid['netLiters'])->toEqual($phpCuboid->details['net'])
        ->and($jsCuboid['bandLiters'])->toBe($phpCuboid->details['band_liters'])
        ->and($jsCuboid['sizeBand'])->toBe($phpCuboid->details['band']->value)
        ->and($jsCuboid['formulaSteps'])->toBe($phpCuboid->steps);

    $floraFiltered = runCreatorEngineJs('filterFlora', [
        $bundle,
        ['sizeBand' => 'small', 'type' => 'Ogólne / Towarzyskie'],
        ['plantZone' => 'Środek', 'difficulty' => 'Początkujący', 'query' => 'rotala'],
    ]);
    expect(array_column($floraFiltered, 'id'))->toContain('FL0001');
});
