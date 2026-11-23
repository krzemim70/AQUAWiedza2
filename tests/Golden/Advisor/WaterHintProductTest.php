<?php

use App\Domain\Aquarium\Actions\BuildWaterHints;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Actions\UpdateDescription;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\Settings\SettingKey;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->freezeSecond();
    $this->batch = app(ImportPackage::class)->handle(kbPackage())->batch;
    $this->user = aquarist();
    $this->tank = tankFor($this->user);
});

function addWaterHintFauna(int $tankId, string ...$ids): void
{
    foreach ($ids as $id) {
        Livestock::create(['tank_id' => $tankId, 'area' => Area::Fauna, 'catalog_id' => $id, 'quantity' => 1]);
    }
}

it('shows the full evidence for CH0089 at the inclusive freshness limit', function () {
    addWaterHintFauna($this->tank->id, 'FN0029');
    measure($this->user, $this->tank->id, ['ph' => '7.9'], now()->subHours(24));

    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    $quote = 'Acid Buffer pozwala bezpiecznie obniżać pH w połączeniu z Alkaline Buffer, przy regularnej kontroli testami.';
    expect($report->product->name)->toBe('Acid Buffer')
        ->and($report->product->evidence->value)->toBe($quote)
        ->and($report->product->evidence->recordId)->toBe('CH0089')
        ->and($report->product->evidence->batch)->toBe($this->batch->id)
        ->and($report->hints[0]->evidence[1]->value)->toBe('pH około 6,8–7,5')
        ->and($report->productBlockReason)->toBeNull();

    $this->actingAs($this->user)->get(route('aquarium.tank', ['tank' => $this->tank->id, 'karta' => 'pomiary']))
        ->assertOk()->assertSee('pH 7,9 — powyżej zakresu podanego w katalogu dla wszystkich gatunków w zbiorniku.')
        ->assertSeeHtml('<blockquote>„'.$quote.'”</blockquote>')
        ->assertSee('CH0089')->assertSee('partia bazy '.$this->batch->id)
        ->assertSeeHtml(route('catalog.show', ['area' => 'care', 'id' => 'CH0089']))
        ->assertSeeHtml(route('catalog.show', ['area' => 'care', 'id' => 'CH0089', 'oferty' => 'okolica']).'#oferty');
});

it('keeps the comparison and gives a reason when the product gates fail', function (array $ids, string $ph, int $offset, string $reason, string $comparison) {
    addWaterHintFauna($this->tank->id, ...$ids);
    measure($this->user, $this->tank->id, ['ph' => $ph], now()->addSeconds($offset));

    $report = app(BuildWaterHints::class)->handle($this->user, $this->tank->id);
    expect($report->product)->toBeNull()
        ->and($report->productBlockReason)->toContain($reason)
        ->and($report->hints[0]->message)->toContain($comparison);
})->with([
    'górna granica pH' => [['FN0029'], '7.5', 0, 'nie przekracza', 'w zakresie podanym w katalogu'],
    'niskie pH' => [['FN0029'], '6.5', 0, 'nie przekracza', 'poniżej zakresu'],
    'gupik' => [['FN0001'], '7.9', 0, 'zakresy pH z opisu i tabeli się różnią', 'brak danych'],
    'Amano' => [['FN0150'], '7.9', 0, 'zakresy pH z opisu i tabeli się różnią', 'powyżej zakresu'],
    'pomiar starszy o sekundę' => [['FN0029'], '7.9', -86401, 'więcej niż 24 godz.', 'powyżej zakresu'],
    'data przyszła' => [['FN0029'], '7.9', 1, 'w przyszłości', 'powyżej zakresu'],
    'brak rekordu fauny' => [['FN0029', 'FN9999'], '7.9', 0, 'brak danych katalogowych', 'dla części obsady'],
    'pusta obsada' => [[], '7.9', 0, 'brak danych katalogowych', 'dla tej obsady'],
    'odczyt z ostrzeżeniem' => [['FN0029'], '15', 0, 'odczyt pH ma ostrzeżenie', 'powyżej zakresu'],
]);

it('uses the configured age and never picks a pH reading from an earlier session', function () {
    addWaterHintFauna($this->tank->id, 'FN0029');
    shopSetting(SettingKey::WaterHintProductMaxAgeHours, 2);
    measure($this->user, $this->tank->id, ['ph' => '7.9'], now()->subHours(3));
    $action = app(BuildWaterHints::class);
    expect($action->handle($this->user, $this->tank->id)->productBlockReason)->toBe('pomiar ma więcej niż 2 godz.');

    measure($this->user, $this->tank->id, ['temp_c' => '26']);
    $report = $action->handle($this->user, $this->tank->id);
    expect($report->product)->toBeNull()->and($report->productBlockReason)->toBe('brak odczytu pH');
});

it('matches an unchanged description in later batches and logs changed evidence even if it repeats the full quote', function () {
    addWaterHintFauna($this->tank->id, 'FN0029');
    measure($this->user, $this->tank->id, ['ph' => '7.9']);
    $directory = kbPackage();
    $file = $directory.'/baza_biotop_end2.txt.php';
    file_put_contents($file, str_replace('Ultramax 1000', 'Ultramax 1001', file_get_contents($file)));
    $unchanged = app(ImportPackage::class)->handle($directory)->batch;
    $action = app(BuildWaterHints::class);
    $log = Activity::query()->where('description', 'Pominięto preparat po pomiarze: brak zgodnego dowodu w opisie rekordu lub niewłaściwa kategoria.');
    expect($unchanged->id)->not->toBe($this->batch->id)
        ->and($action->handle($this->user, $this->tank->id)->product->evidence->batch)->toBe($unchanged->id)
        ->and($log->count())->toBe(0);

    $file = $directory.'/baza_pielegnacja_end1.txt.php';
    file_put_contents($file, str_replace('przy regularnej kontroli testami.', 'przy regularnej kontroli testami. To stwierdzenie jest nieaktualne.', file_get_contents($file)));
    $changed = app(ImportPackage::class)->handle($directory)->batch;
    $report = $action->handle($this->user, $this->tank->id);
    expect($report->product)->toBeNull()
        ->and($report->productBlockReason)->toBe('brak preparatu z potwierdzonym opisem w katalogu')
        ->and($log->sole()->subject_id)->toBe($changed->id)
        ->and($log->sole()->getProperty('record'))->toBe('CH0089');
});

it('rechecks a description edited after activation', function () {
    addWaterHintFauna($this->tank->id, 'FN0029');
    measure($this->user, $this->tank->id, ['ph' => '7.9']);
    $action = app(BuildWaterHints::class);
    expect($action->handle($this->user, $this->tank->id)->product)->not->toBeNull();

    $record = Record::query()->where('legacy_id', 'CH0089')->sole();
    app(UpdateDescription::class)->handle(User::factory()->admin()->create(), $record, 'Acid Buffer nie obniża pH.', null);

    expect($action->handle($this->user, $this->tank->id)->product)->toBeNull();
});

it('excludes substrates and test devices even if their records are listed in the setting', function () {
    addWaterHintFauna($this->tank->id, 'FN0029');
    measure($this->user, $this->tank->id, ['ph' => '7.9']);
    shopSetting(SettingKey::WaterHintProducts, [
        ['id' => 'CH0069', 'direction' => 'ph_down', 'quote' => 'ProScape PlantSoil to aktywne podłoże dla wymagających aranżacji roślinnych, obniżające pH i twardość.'],
        ['id' => 'CH0099', 'direction' => 'ph_down', 'quote' => 'pH Control to sterownik z sondą pH, który może także pracować jako dokładny miernik odczynu wody.'],
    ]);

    expect(app(BuildWaterHints::class)->handle($this->user, $this->tank->id)->product)->toBeNull();
});
