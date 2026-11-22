<?php

namespace App\Filament\Pages;

use App\Domain\Settings\ChangeSetting;
use App\Domain\Settings\ChangeSettingData;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class SettingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Ustawienia';

    protected static ?string $slug = 'ustawienia';

    protected string $view = 'filament.pages.settings';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-settings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->paginated(false)
            ->columns([
                TextColumn::make('group')->label('Grupa'),
                TextColumn::make('label')->label('Ustawienie')->description(fn (array $record) => $record['key']),
                TextColumn::make('value')->label('Wartość')->html(),
                TextColumn::make('next')->label('Zaplanowana zmiana')->html()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('change')
                    ->label('Zmień')
                    ->authorize(fn () => Gate::allows('manage-settings'))
                    ->fillForm(fn (array $record) => [
                        'value' => self::input(app(Settings::class)->get(SettingKey::from($record['key']))),
                        'effective_from' => now(),
                    ])
                    ->schema(fn (array $record) => [
                        self::valueField(SettingKey::from($record['key'])),
                        DateTimePicker::make('effective_from')->label('Obowiązuje od (UTC)')->seconds(false)->required(),
                        TextInput::make('reason')->label('Powód zmiany')->required()->maxLength(255),
                    ])
                    ->action(function (array $record, array $data, Action $action): void {
                        $key = SettingKey::from($record['key']);
                        try {
                            app(ChangeSetting::class)->handle(new ChangeSettingData(
                                auth()->user(), $key, self::parse($key, $data['value']), $data['reason'], Carbon::parse($data['effective_from'], 'UTC'),
                            ));
                        } catch (ValidationException $e) {
                            $action->failureNotificationTitle(collect($e->errors())->flatten()->implode(' '))->failure();
                            $action->halt();
                        }
                        $action->success();
                    })
                    ->successNotificationTitle('Zapisano nową wersję ustawienia'),
                Action::make('history')
                    ->label('Historia')
                    ->modalHeading(fn (array $record) => 'Historia: '.$record['label'])
                    ->modalContent(fn (array $record) => self::history(SettingKey::from($record['key'])))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zamknij'),
            ]);
    }

    /** @return array<string, array<string, string>> */
    private function rows(): array
    {
        $settings = app(Settings::class);
        $future = SettingVersion::where('effective_from', '>', now())->orderBy('effective_from')->get()->unique('key')->keyBy('key');

        $rows = [];
        foreach (SettingKey::cases() as $key) {
            $next = $future->get($key->value);
            $rows[$key->value] = [
                'key' => $key->value,
                'group' => $key->group(),
                'label' => $key->label(),
                'value' => self::show($settings->get($key)),
                'next' => $next ? e($next->effective_from->format('Y-m-d H:i')).' UTC'.self::show($next->value) : '',
            ];
        }

        return collect($rows)->sortBy('group')->all();
    }

    private static function show(mixed $value): string
    {
        return '<pre class="text-xs whitespace-pre-wrap">'.e(is_bool($value)
            ? ($value ? 'tak' : 'nie')
            : (is_scalar($value) ? (string) $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))).'</pre>';
    }

    private static function input(mixed $value): mixed
    {
        return is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $value;
    }

    private static function valueField(SettingKey $key): Textarea|Toggle|TextInput
    {
        $default = $key->default();
        $field = match (true) {
            is_bool($default) => Toggle::make('value'),
            is_array($default) => Textarea::make('value')->rows(10)->helperText('JSON'),
            is_int($default) || is_float($default) => TextInput::make('value')->numeric(),
            default => TextInput::make('value'),
        };

        return $field->label('Wartość')->required(! is_bool($default));
    }

    private static function parse(SettingKey $key, mixed $input): mixed
    {
        $default = $key->default();

        return match (true) {
            is_array($default) => json_decode((string) $input, true),
            is_int($default) || is_float($default) => is_numeric($input) ? $input + 0 : $input,
            default => $input,
        };
    }

    private static function history(SettingKey $key): HtmlString
    {
        $rows = SettingVersion::with('author')->where('key', $key->value)->orderByDesc('effective_from')->get()
            ->map(fn (SettingVersion $v) => '<tr><td>'.e($v->effective_from->format('Y-m-d H:i')).' UTC</td><td>'
                .e($v->author->name ?? '—').'</td><td>'.e($v->reason).'</td><td>'.self::show($v->value).'</td></tr>')
            ->implode('');

        return new HtmlString($rows === ''
            ? '<p>Brak zmian, obowiązuje wartość domyślna.</p>'
            : '<table class="w-full text-sm"><tr><th>Od</th><th>Autor</th><th>Powód</th><th>Wartość</th></tr>'.$rows.'</table>');
    }
}
