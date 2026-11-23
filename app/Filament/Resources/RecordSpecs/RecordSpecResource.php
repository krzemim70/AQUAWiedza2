<?php

namespace App\Filament\Resources\RecordSpecs;

use App\Domain\KnowledgeBase\Actions\SaveRecordSpec;
use App\Domain\KnowledgeBase\Actions\VerifyRecordSpec;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\SaveRecordSpecData;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\RecordSpecStatus;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\KnowledgeBase\RecordSpecValidator;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Filament\Resources\RecordSpecs\Pages\CreateRecordSpec;
use App\Filament\Resources\RecordSpecs\Pages\EditRecordSpec;
use App\Filament\Resources\RecordSpecs\Pages\ListRecordSpecs;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Panel redakcji pól strukturalnych bazy wiedzy (szkice, weryfikacja, wyłączenia po imporcie). */
class RecordSpecResource extends Resource
{
    protected static ?string $model = RecordSpec::class;

    protected static ?string $slug = 'specyfikacje';

    protected static ?string $modelLabel = 'specyfikacja';

    protected static ?string $pluralModelLabel = 'Redakcja specyfikacji';

    protected static string|UnitEnum|null $navigationGroup = 'Baza wiedzy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 4;

    /** @return Builder<RecordSpec> */
    public static function getEloquentQuery(): Builder
    {
        return RecordSpec::query()->with(['record.activeEntry.description', 'verifier']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stan wpisu')
                ->visible(fn (?RecordSpec $record): bool => $record !== null)
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('status_label')
                        ->label('Stan')
                        ->state(fn (?RecordSpec $record): string => $record?->status->label() ?? '—'),
                    TextEntry::make('verified_info')
                        ->label('Weryfikacja')
                        ->state(fn (?RecordSpec $record): string => $record?->verified_at !== null
                            ? ($record->verifier->name ?? '—').' ('.$record->verified_at->timezone((string) config('app.display_timezone'))->format('d.m.Y H:i').')'
                            : 'Niezweryfikowane'),
                    TextEntry::make('disabled_reason_info')
                        ->label('Powód wyłączenia')
                        ->state(fn (?RecordSpec $record): string => $record->disabled_reason ?? '—'),
                ]),

            Section::make('Rekord katalogu i źródło')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('record_id')
                        ->label('Rekord katalogu')
                        ->required()
                        ->searchable()
                        ->live()
                        ->disabled(fn (?RecordSpec $record): bool => $record !== null)
                        ->dehydrated(fn (?RecordSpec $record): bool => $record === null)
                        ->getSearchResultsUsing(fn (string $search): array => self::searchRecords($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => self::recordLabel($value))
                        ->columnSpanFull(),

                    TextEntry::make('active_description')
                        ->label('Aktywny opis rekordu (źródło do cytowania)')
                        ->state(fn (Get $get, ?RecordSpec $record): string => self::activeDescriptionPreview($get, $record))
                        ->visible(fn (Get $get, ?RecordSpec $record): bool => self::resolveRecord($get, $record) !== null)
                        ->columnSpanFull(),

                    Select::make('source_kind')
                        ->label('Rodzaj źródła')
                        ->required()
                        ->live()
                        ->options(fn (Get $get, ?RecordSpec $record): array => self::sourceOptions(self::resolveArea($get, $record))),

                    TextInput::make('ean')
                        ->label('Kod EAN (opcjonalny)')
                        ->maxLength(32)
                        ->helperText('Od 8 do 14 cyfr z opakowania produktu.'),

                    Select::make('attributes.water_type')
                        ->label('Rodzaj wody')
                        ->options(RecordSpecValidator::WATER_TYPES)
                        ->placeholder('— nie określono —')
                        ->visible(fn (Get $get, ?RecordSpec $record): bool => in_array(self::resolveArea($get, $record), [Area::Care, Area::Equipment], true)),

                    TextInput::make('source_ref')
                        ->label('Referencja źródła')
                        ->maxLength(500)
                        ->required(fn (Get $get): bool => in_array($get('source_kind'), [
                            SpecSourceKind::ManufacturerLabel->value,
                            SpecSourceKind::Literature->value,
                        ], true))
                        ->helperText('Wymagane dla etykiety producenta (producent i nazwa karty) oraz literatury (publikacja lub stały odnośnik).')
                        ->columnSpanFull(),

                    Textarea::make('evidence_quote')
                        ->label('Cytat potwierdzający')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000)
                        ->helperText('Dla źródła „Katalog Aikwarium” cytat musi dosłownie występować w aktywnym opisie rekordu.')
                        ->columnSpanFull(),
                ]),

            Section::make('Parametry preparatu')
                ->description('Pola z etykiety lub opisu katalogowego. Zapis tworzy szkic do weryfikacji.')
                ->visible(fn (Get $get, ?RecordSpec $record): bool => self::resolveArea($get, $record) === Area::Care)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('attributes.parameter')
                        ->label('Parametr wody')
                        ->options(RecordSpecValidator::CARE_PARAMETERS)
                        ->placeholder('— brak / nie dotyczy —'),

                    Select::make('attributes.direction')
                        ->label('Kierunek działania')
                        ->options(RecordSpecValidator::CARE_DIRECTIONS)
                        ->placeholder('— wybierz —'),

                    TextInput::make('attributes.dose_amount')
                        ->label('Dawka z etykiety')
                        ->numeric()
                        ->step('any'),

                    Select::make('attributes.unit')
                        ->label('Jednostka')
                        ->options(RecordSpecValidator::CARE_UNITS)
                        ->placeholder('— wybierz —'),

                    TextInput::make('attributes.dose_per_liters')
                        ->label('Na ile litrów')
                        ->numeric()
                        ->step('any')
                        ->helperText('Pozostaw puste dla stałej porcji (np. karmy).'),

                    TextInput::make('attributes.package_amount')
                        ->label('Pojemność / masa opakowania')
                        ->numeric()
                        ->step('any'),

                    Repeater::make('attributes.contraindications')
                        ->label('Przeciwwskazania (każde z cytatem)')
                        ->addActionLabel('Dodaj przeciwwskazanie')
                        ->defaultItems(0)
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('target')
                                ->label('Dotyczy')
                                ->options(RecordSpecValidator::CONTRAINDICATIONS)
                                ->required(),
                            Textarea::make('quote')
                                ->label('Cytat potwierdzający przeciwwskazanie')
                                ->required()
                                ->rows(2)
                                ->maxLength(500),
                        ]),
                ]),

            Section::make('Zakresy parametrów wody gatunku')
                ->description('Zakresy min–max potwierdzone cytatem z opisu lub literatury.')
                ->visible(fn (Get $get, ?RecordSpec $record): bool => self::resolveArea($get, $record) === Area::Fauna)
                ->columns(2)
                ->columnSpanFull()
                ->schema(self::faunaRangeFields()),

            Section::make('Parametry sprzętu')
                ->description('Opcjonalne dane techniczne z etykiety lub opisu.')
                ->visible(fn (Get $get, ?RecordSpec $record): bool => self::resolveArea($get, $record) === Area::Equipment)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('attributes.power_w')
                        ->label('Moc (W)')
                        ->numeric()
                        ->step('any'),
                    TextInput::make('attributes.flow_l_h')
                        ->label('Wydajność (l/h)')
                        ->numeric()
                        ->step('any'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('record.area')
                    ->label('Obszar')
                    ->badge()
                    ->formatStateUsing(fn (Area $state): string => $state->label()),
                TextColumn::make('record.legacy_id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('record_name')
                    ->label('Nazwa rekordu')
                    ->state(fn (RecordSpec $r): string => $r->record->activeEntry?->label() ?? $r->record->legacy_id),
                TextColumn::make('status')
                    ->label('Stan')
                    ->badge()
                    ->formatStateUsing(fn (RecordSpecStatus $state): string => $state->label())
                    ->color(fn (RecordSpecStatus $state): string => match ($state) {
                        RecordSpecStatus::Draft => 'warning',
                        RecordSpecStatus::Verified => 'success',
                        RecordSpecStatus::Disabled => 'danger',
                    }),
                TextColumn::make('source_kind')
                    ->label('Źródło')
                    ->formatStateUsing(fn (SpecSourceKind $state): string => $state->label()),
                TextColumn::make('ean')
                    ->label('EAN')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('verifier.name')
                    ->label('Zweryfikował')
                    ->placeholder('—'),
                TextColumn::make('verified_at')
                    ->label('Data weryfikacji')
                    ->dateTime()
                    ->timezone((string) config('app.display_timezone'))
                    ->placeholder('—'),
                TextColumn::make('disabled_reason')
                    ->label('Powód wyłączenia')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stan')
                    ->options([
                        RecordSpecStatus::Draft->value => RecordSpecStatus::Draft->label(),
                        RecordSpecStatus::Verified->value => RecordSpecStatus::Verified->label(),
                        RecordSpecStatus::Disabled->value => RecordSpecStatus::Disabled->label(),
                    ]),
                SelectFilter::make('area')
                    ->label('Obszar')
                    ->options(collect(Area::cases())->mapWithKeys(fn (Area $a): array => [$a->value => $a->label()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $area) => $q->whereHas('record', fn ($r) => $r->where('area', $area)),
                    )),
                SelectFilter::make('source_kind')
                    ->label('Źródło')
                    ->options(collect(SpecSourceKind::cases())->mapWithKeys(fn (SpecSourceKind $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                EditAction::make()->label('Edytuj'),
                self::verifyAction(),
            ]);
    }

    public static function verifyAction(?Closure $after = null): Action
    {
        return Action::make('verify')
            ->label('Zweryfikuj')
            ->color('success')
            ->visible(function (RecordSpec $record): bool {
                $actor = self::actor();

                return $record->status !== RecordSpecStatus::Verified
                    && $actor !== null
                    && Gate::forUser($actor)->allows('verify', $record);
            })
            ->requiresConfirmation()
            ->modalHeading('Zweryfikuj specyfikację')
            ->modalDescription('Po weryfikacji dane strukturalne trafią do kontraktu odczytu. Potwierdź zgodność z podanym źródłem i cytatem.')
            ->modalSubmitActionLabel('Zweryfikuj')
            ->action(function (RecordSpec $record, Action $action) use ($after): void {
                $actor = self::actor();
                if ($actor === null) {
                    $action->failureNotificationTitle('Brak uprawnień.')->failure();
                    $action->halt();
                } else {
                    try {
                        app(VerifyRecordSpec::class)->handle($actor, $record);
                    } catch (ValidationException $e) {
                        $message = collect($e->errors())->flatten()->unique()->implode(' ');
                        Notification::make()->danger()->title($message)->send();
                        $action->failureNotificationTitle($message)->failure();
                        $action->halt();
                    } catch (AuthorizationException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->failureNotificationTitle($e->getMessage())->failure();
                        $action->halt();
                    }

                    if ($after !== null) {
                        $after($record);
                    }

                    $action->success();
                }
            })
            ->successNotificationTitle('Specyfikacja została zweryfikowana');
    }

    /** @param array<string, mixed> $data */
    public static function saveFromForm(array $data, ?RecordSpec $existing = null): RecordSpec
    {
        $actor = self::actor();
        if ($actor === null) {
            throw new AuthorizationException('Brak uprawnień administratora.');
        }

        try {
            return app(SaveRecordSpec::class)->handle($actor, self::toDto($data, $existing));
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Nie udało się zapisać specyfikacji')
                ->body(collect($e->errors())->flatten()->unique()->implode(' '))
                ->send();

            $prefixed = [];
            foreach ($e->errors() as $key => $messages) {
                $prefixed['data.'.$key] = $messages;
            }

            throw ValidationException::withMessages($prefixed);
        }
    }

    /** @param array<string, mixed> $data */
    public static function toDto(array $data, ?RecordSpec $existing = null): SaveRecordSpecData
    {
        $recordId = $existing !== null ? $existing->record_id : (int) ($data['record_id'] ?? 0);
        $record = $existing !== null ? $existing->record : Record::query()->find($recordId);
        $rawSource = $data['source_kind'] ?? SpecSourceKind::CatalogQuote->value;
        $sourceKind = $rawSource instanceof SpecSourceKind
            ? $rawSource
            : SpecSourceKind::from((string) $rawSource);

        $rawAttributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $attributes = self::extractAreaAttributes($record?->area, $rawAttributes);

        return new SaveRecordSpecData(
            recordId: $recordId,
            sourceKind: $sourceKind,
            sourceRef: self::nullableString($data['source_ref'] ?? null),
            evidenceQuote: is_string($data['evidence_quote'] ?? null) ? $data['evidence_quote'] : '',
            ean: self::nullableString($data['ean'] ?? null),
            attributes: $attributes,
        );
    }

    public static function actor(): ?User
    {
        $user = Filament::auth()->user() ?? auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecordSpecs::route('/'),
            'create' => CreateRecordSpec::route('/create'),
            'edit' => EditRecordSpec::route('/{record}/edit'),
        ];
    }

    /** @return list<TextInput> */
    private static function faunaRangeFields(): array
    {
        $fields = [];
        foreach (RecordSpecValidator::FAUNA_PARAMETERS as $param => $label) {
            $fields[] = TextInput::make("attributes.{$param}.min")
                ->label("{$label} — min")
                ->numeric()
                ->step('any');
            $fields[] = TextInput::make("attributes.{$param}.max")
                ->label("{$label} — maks.")
                ->numeric()
                ->step('any');
        }

        return $fields;
    }

    /** @return array<int, string> */
    private static function searchRecords(string $search): array
    {
        $needle = Catalog::fold($search);
        if (mb_strlen($needle) < 2) {
            return [];
        }

        return Entry::query()
            ->inActiveBatch()
            ->with('record')
            ->whereDoesntHave('spec')
            ->where(fn ($q) => $q
                ->where('search_text', 'like', '%'.addcslashes($needle, '%_\\').'%')
                ->orWhereHas('record', fn ($r) => $r->whereRaw('lower(legacy_id) like ?', ['%'.addcslashes(trim($needle), '%_\\').'%'])))
            ->orderBy('id')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Entry $entry): array => [
                $entry->record_id => "{$entry->record->area->label()} — {$entry->record->legacy_id} ({$entry->label()})",
            ])
            ->all();
    }

    private static function recordLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }
        $record = Record::query()->with('activeEntry')->find((int) $value);
        if ($record === null) {
            return null;
        }
        $name = $record->activeEntry?->label();

        return $name !== null
            ? "{$record->area->label()} — {$record->legacy_id} ({$name})"
            : "{$record->area->label()} — {$record->legacy_id}";
    }

    private static function resolveRecord(Get $get, ?RecordSpec $record): ?Record
    {
        if ($record !== null) {
            return $record->record;
        }

        $recordId = $get('record_id');

        return is_numeric($recordId)
            ? Record::query()->with(['activeEntry.description', 'description'])->find((int) $recordId)
            : null;
    }

    private static function resolveArea(Get $get, ?RecordSpec $record): ?Area
    {
        return self::resolveRecord($get, $record)?->area;
    }

    private static function activeDescriptionPreview(Get $get, ?RecordSpec $record): string
    {
        $resolved = self::resolveRecord($get, $record);
        $entry = $resolved?->activeEntry;
        if ($entry === null) {
            return 'Brak aktywnego wpisu rekordu w bieżącej partii katalogu.';
        }

        $texts = RecordSpecValidator::activeDescriptionTexts($entry);

        return $texts === [] ? 'Brak opisu dla tego rekordu.' : implode("\n\n", $texts);
    }

    /** @return array<string, string> */
    private static function sourceOptions(?Area $area): array
    {
        /** @var list<string> $enabled */
        $enabled = app(Settings::class)->get(SettingKey::CatalogSpecSources);
        $options = [];
        foreach (SpecSourceKind::cases() as $kind) {
            if (! in_array($kind->value, $enabled, true)) {
                continue;
            }
            if ($area !== null && ! $kind->allowedFor($area)) {
                continue;
            }
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function extractAreaAttributes(?Area $area, array $raw): array
    {
        if ($area === Area::Care) {
            $result = [];
            foreach (['parameter', 'direction', 'dose_amount', 'unit', 'dose_per_liters', 'package_amount', 'water_type'] as $key) {
                if (array_key_exists($key, $raw) && $raw[$key] !== null && $raw[$key] !== '') {
                    $result[$key] = $raw[$key];
                }
            }
            if (is_array($raw['contraindications'] ?? null) && $raw['contraindications'] !== []) {
                $result['contraindications'] = array_values($raw['contraindications']);
            }

            return $result;
        }

        if ($area === Area::Fauna) {
            $result = [];
            foreach (array_keys(RecordSpecValidator::FAUNA_PARAMETERS) as $param) {
                $pair = $raw[$param] ?? null;
                if (! is_array($pair)) {
                    continue;
                }
                $min = $pair['min'] ?? null;
                $max = $pair['max'] ?? null;
                $minBlank = $min === null || (is_string($min) && trim($min) === '');
                $maxBlank = $max === null || (is_string($max) && trim($max) === '');
                if ($minBlank && $maxBlank) {
                    continue;
                }
                $result[$param] = ['min' => $min, 'max' => $max];
            }

            return $result;
        }

        if ($area === Area::Equipment) {
            $result = [];
            foreach (['power_w', 'flow_l_h', 'water_type'] as $key) {
                if (array_key_exists($key, $raw) && $raw[$key] !== null && $raw[$key] !== '') {
                    $result[$key] = $raw[$key];
                }
            }

            return $result;
        }

        return [];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
