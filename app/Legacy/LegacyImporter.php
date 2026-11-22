<?php

namespace App\Legacy;

use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Models\TankType;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import ze starego WordPressa (połączenie `legacy`, tylko odczyt). Każda grupa encji w osobnej transakcji,
 * mapa `legacy_id_map` chroni przed dublami przy ponownym uruchomieniu. Raport zawiera same liczby.
 * Dane osobowe nie trafiają do raportu ani do logów.
 */
class LegacyImporter
{
    private const IMPORTED = 'zaimportowano';

    private const TANK_TYPES = [
        'nano' => TankType::Nano, 'community' => TankType::General, 'planted' => TankType::Planted,
        'biotope_hard' => TankType::HardwaterBiotope, 'specialist' => TankType::Specialist,
    ];

    private const REMINDER_TYPES = ['water_change' => 'water_change', 'parameter_check' => 'water_test', 'filter_cleaning' => 'filter_cleaning', 'fertilization' => 'fertilization'];

    private const OFFER_STATES = ['aktywna' => 'published', 'wygasla' => 'expired', 'odrzucona' => 'withdrawn', 'usunieta' => 'withdrawn'];

    private const PHOTO_AREAS = ['FN' => 'fauna', 'FL' => 'flora', 'SP' => 'equipment', 'CH' => 'care'];

    /** @var array<string, array<string, int>> */
    private array $report = [];

    private Connection $old;

    /** @var array<string, array<int|string, int|null>> encja => id źródłowe => id docelowe */
    private array $map = [];

    /** @return array<string, array<string, int>> raport: encja => wynik => liczba */
    public function run(bool $dryRun): array
    {
        $this->report = [];
        $this->map = [];
        $this->old = DB::connection('legacy');

        // próba: wszystko w jednej transakcji, wycofanej na końcu (wewnętrzne transakcje to savepointy)
        DB::beginTransaction();
        try {
            $this->users();
            $this->tanks();
            $this->measurements();
            $this->journal();
            $this->reminders();
            $this->shops();
            $this->offers();
            $this->photos();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $dryRun ? DB::rollBack() : DB::commit();

        return $this->report;
    }

    private function count(string $entity, string $outcome = self::IMPORTED, int $n = 1): void
    {
        $this->report[$entity][$outcome] = ($this->report[$entity][$outcome] ?? 0) + $n;
    }

    private function mapped(string $entity, int|string $legacyId): ?int
    {
        return $this->map[$entity][$legacyId] ??= DB::table('legacy_id_map')->where('entity', $entity)->where('legacy_id', (string) $legacyId)->value('new_id');
    }

    private function remember(string $entity, int|string $legacyId, int $newId): void
    {
        DB::table('legacy_id_map')->insert(['entity' => $entity, 'legacy_id' => (string) $legacyId, 'new_id' => $newId]);
        $this->map[$entity][$legacyId] = $newId;
    }

    /** Liczy sieroty: wiersze, których klucz nie wskazuje istniejącego rodzica w źródle. */
    private function orphans(string $entity, string $table, string $column, string $parent, string $parentKey): void
    {
        $n = $this->old->table($table)->whereNotIn($column, $this->old->table($parent)->select($parentKey))->count();
        $n > 0 && $this->count($entity, 'pominięto: osierocone (brak rodzica w źródle)', $n);
    }

    private function users(): void
    {
        $this->orphans('subskrypcje', 'wp_aqs_subscriptions', 'user_id', 'wp_users', 'ID');
        $this->orphans('usermeta', 'wp_usermeta', 'user_id', 'wp_users', 'ID');

        $meta = $this->old->table('wp_usermeta')->whereIn('meta_key', ['aqs_full_name', 'aqs_account_type', 'wp_capabilities'])->get()->groupBy('user_id');
        $subs = $this->old->table('wp_aqs_subscriptions')->where('role', 'aqs_user')->orderBy('id')->get()->keyBy('user_id');

        DB::transaction(function () use ($meta, $subs) {
            foreach ($this->old->table('wp_users')->orderBy('ID')->cursor() as $u) {
                if ($this->mapped('user', $u->ID) !== null) {
                    $this->count('użytkownicy', 'pominięto: już zaimportowano');

                    continue;
                }
                $email = trim((string) $u->user_email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->count('użytkownicy', 'pominięto: nieprawidłowy e-mail');

                    continue;
                }
                if (DB::table('users')->where('email', $email)->exists()) {
                    $this->count('użytkownicy', 'pominięto: e-mail już istnieje w v2');

                    continue;
                }

                $m = ($meta[$u->ID] ?? collect())->pluck('meta_value', 'meta_key');
                $sub = $subs[$u->ID] ?? null;
                $status = $sub->status ?? 'brak';
                $ends = $status === 'active' ? $sub->trial_end : null;
                $status === 'active' && $ends === null && $this->count('poziomy', 'Tank: aktywna subskrypcja bez daty końca');
                $suspended = $status === 'suspended' ? ($sub->updated_at ?? now()) : null;
                $registered = $u->user_registered ?: now();

                $id = DB::table('users')->insertGetId([
                    'name' => Str::limit(($m['aqs_full_name'] ?? '') ?: ($u->display_name ?: $u->user_login), 255, ''),
                    'email' => $email,
                    // konto, które istniało i logowało się w WordPressie, traktujemy jako zweryfikowane
                    'email_verified_at' => $registered,
                    'password' => $u->user_pass,
                    'type' => ($m['aqs_account_type'] ?? '') === 'shop' ? 'shop' : 'aquarist',
                    'is_admin' => str_contains($m['wp_capabilities'] ?? '', '"administrator"'),
                    'fishroom_ends_at' => $ends, 'suspended_at' => $suspended,
                    'created_at' => $registered, 'updated_at' => now(),
                ]);
                DB::table('access_changes')->insert([
                    'user_id' => $id, 'kind' => 'imported', 'fishroom_ends_at' => $ends,
                    'reason' => 'Import z WordPressa (status subskrypcji: '.$status.')', 'created_at' => now(),
                ]);
                $this->remember('user', $u->ID, $id);
                $this->count('użytkownicy');
                $this->count('poziomy', $suspended ? 'zawieszone' : ($ends ? 'Fishroom' : 'Tank'));
            }
        });
    }

    /**
     * Stare pasma to etykiety słowników: etykieta => kod.
     *
     * @param  list<TankBand|FishBand|TemperatureBand|PhBand>  $cases
     * @return array<string, string>
     */
    private function codes(array $cases): array
    {
        return array_column(array_map(fn ($c) => ['label' => $c->label(), 'code' => $c->value], $cases), 'code', 'label');
    }

    private function tanks(): void
    {
        $maps = [
            'size_band' => $this->codes(TankBand::cases()), 'fish_size_band' => $this->codes(FishBand::cases()),
            'temp_band' => $this->codes(TemperatureBand::cases()), 'ph_band' => $this->codes(PhBand::cases()),
        ];

        DB::transaction(function () use ($maps) {
            foreach ($this->old->table('wp_aqs_user_tanks')->orderBy('id')->cursor() as $t) {
                if (($existing = $this->mapped('tank', $t->id)) !== null) {
                    $this->count('zbiorniki', 'pominięto: już zaimportowano');
                    // uzupełnienie pasma przy ponownym uruchomieniu (zbiorniki zaimportowane wcześniej bez pasma)
                    $band = $t->volume_liters ? TankBand::forLiters((int) $t->volume_liters)->value : null;
                    $band !== null && DB::table('tanks')->where('id', $existing)->whereNull('size_band')->update(['size_band' => $band]) && $this->count('zbiorniki', 'pasmo wyliczone z litrażu');

                    continue;
                }
                if (($userId = $this->mapped('user', $t->user_id)) === null) {
                    $this->count('zbiorniki', 'pominięto: właściciel nieistniejący lub niezaimportowany');

                    continue;
                }
                if (! isset(self::TANK_TYPES[$t->tank_type])) {
                    $this->count('zbiorniki', 'pominięto: nieznany typ');

                    continue;
                }

                $row = [];
                foreach ($maps as $col => $codes) {
                    $row[$col] = $codes[$t->{$col} ?? ''] ?? null;
                    $t->{$col} !== null && $row[$col] === null && $this->count('zbiorniki', 'pasmo nieodwzorowane (do uzupełnienia)');
                }
                if ($row['size_band'] === null && $t->volume_liters) {
                    $row['size_band'] = TankBand::forLiters((int) $t->volume_liters)->value;
                    $this->count('zbiorniki', 'pasmo wyliczone z litrażu');
                }
                $row['size_band'] === null && $this->count('zbiorniki', 'bez pasma wielkości (do uzupełnienia)');

                $id = DB::table('tanks')->insertGetId([
                    'user_id' => $userId, 'name' => Str::limit((string) $t->name, 100, ''), 'type' => self::TANK_TYPES[$t->tank_type]->value,
                    'notes' => $t->notes, 'legacy_volume_liters' => $t->volume_liters ?: null,
                    'created_at' => $t->created_at, 'updated_at' => $t->updated_at ?? $t->created_at,
                ] + $row);
                $this->remember('tank', $t->id, $id);
                $this->count('zbiorniki');
            }
        });
    }

    /**
     * Wspólna pętla dla dzieci zbiornika: pomija sieroty i rekordy innego właściciela niż zbiornik.
     * `$insert(wiersz, id zbiornika)` zwraca nowe ID albo tekst powodu pominięcia.
     */
    private function forTankChildren(string $entity, string $table, callable $insert): void
    {
        $this->orphans($entity, $table, 'tank_id', 'wp_aqs_user_tanks', 'id');
        $owners = $this->old->table('wp_aqs_user_tanks')->pluck('user_id', 'id');

        DB::transaction(function () use ($entity, $table, $owners, $insert) {
            foreach ($this->old->table($table)->orderBy('id')->cursor() as $r) {
                $skip = match (true) {
                    $this->mapped($entity, $r->id) !== null => 'już zaimportowano',
                    ! isset($owners[$r->tank_id]) => null,
                    $owners[$r->tank_id] != $r->user_id => 'właściciel niezgodny ze zbiornikiem',
                    ($tankId = $this->mapped('tank', $r->tank_id)) === null => 'zbiornik nie został zaimportowany',
                    default => false,
                };
                if ($skip === null) {
                    continue; // sierota, policzona wcześniej
                }
                if ($skip !== false) {
                    $this->count($entity, 'pominięto: '.$skip);

                    continue;
                }
                $new = $insert($r, $tankId);
                if (is_string($new)) {
                    $this->count($entity, 'pominięto: '.$new);

                    continue;
                }
                $this->remember($entity, $r->id, $new);
                $this->count($entity);
            }
        });
    }

    private function measurements(): void
    {
        $cols = ['ph', 'temp_c', 'kh', 'gh', 'no3', 'po4'];
        $this->old->getSchemaBuilder()->hasColumn('wp_aqs_user_parameters', 'no2') && $cols[] = 'no2'; // starsze zrzuty nie mają NO2

        $this->forTankChildren('pomiary', 'wp_aqs_user_parameters', function ($r, $tankId) use ($cols) {
            $values = [];
            foreach ($cols as $c) {
                $values[$c] = $r->{$c};
            }
            if (collect($values)->every(fn ($v) => $v === null)) {
                return 'pusty pomiar';
            }

            return DB::table('water_measurements')->insertGetId(['tank_id' => $tankId, 'measured_at' => $r->measured_at.' 00:00:00', 'created_at' => $r->created_at] + $values);
        });
    }

    private function journal(): void
    {
        $this->forTankChildren('dziennik', 'wp_aqs_user_journal', function ($r, $tankId) {
            if (trim((string) $r->content) === '') {
                return 'pusty wpis';
            }

            return DB::table('journal_entries')->insertGetId([
                'tank_id' => $tankId, 'entry_date' => $r->entry_date, 'content' => $r->content, 'created_at' => $r->created_at, 'updated_at' => $r->created_at,
            ]);
        });
    }

    private function reminders(): void
    {
        $this->forTankChildren('przypomnienia', 'wp_aqs_user_reminders', function ($r, $tankId) {
            if ($r->next_due === null) {
                return 'brak terminu';
            }
            $interval = $r->is_recurring && $r->interval_days > 0 ? (int) $r->interval_days : null;
            $done = (bool) $r->is_done;
            $id = DB::table('reminders')->insertGetId([
                'tank_id' => $tankId, 'type' => self::REMINDER_TYPES[$r->reminder_type] ?? 'other', 'label' => $r->label, 'starts_on' => $r->next_due,
                'interval_days' => $interval, 'state' => $done && $interval === null ? 'completed' : 'active',
                'created_at' => $r->created_at, 'updated_at' => $r->updated_at ?? $r->created_at,
            ]);
            // źródło nie zna dokładnej daty wykonania: używamy daty ostatniej zmiany wiersza
            DB::table('reminder_occurrences')->insert([
                'reminder_id' => $id, 'due_on' => $r->next_due, 'state' => $done ? 'done' : 'pending',
                'completed_at' => $done ? ($r->updated_at ?? $r->created_at ?? now()) : null,
            ]);
            if ($done && $interval !== null) {
                DB::table('reminder_occurrences')->insert(['reminder_id' => $id, 'due_on' => Carbon::parse($r->next_due)->addDays($interval)->toDateString(), 'state' => 'pending']);
            }

            return $id;
        });
    }

    private function shops(): void
    {
        $verifications = $this->old->table('wp_aqss_verifications')->orderBy('id')->get()->groupBy('shop_id');
        $members = $this->old->table('wp_aqss_shop_members')->get()->groupBy('shop_id');

        DB::transaction(function () use ($verifications, $members) {
            foreach ($this->old->table('wp_aqss_shops')->orderBy('id')->cursor() as $s) {
                if ($this->mapped('shop', $s->id) !== null) {
                    $this->count('sklepy', 'pominięto: już zaimportowano');

                    continue;
                }
                $nip = $verifications->get($s->id, collect())->where('decision', 'zatwierdzona')
                    ->map(fn ($v) => preg_replace('/\D/', '', (string) $v->nip))->first(fn ($n) => strlen($n) === 10);
                if ($nip === null) {
                    $this->count('sklepy', 'pominięto: brak zatwierdzonej weryfikacji z poprawnym NIP');

                    continue;
                }
                if (DB::table('shops')->where('nip', $nip)->exists()) {
                    $this->count('sklepy', 'pominięto: NIP już istnieje w v2');

                    continue;
                }
                if (($ownerId = $this->mapped('user', $s->owner_id)) === null) {
                    $this->count('sklepy', 'pominięto: właściciel nieistniejący lub niezaimportowany');

                    continue;
                }

                $active = $s->status === 'aktywny';
                $shopId = DB::table('shops')->insertGetId([
                    'name' => Str::limit((string) $s->name, 255, ''), 'slug' => Str::slug((string) $s->name).'-'.$nip, 'nip' => $nip,
                    'state' => $active ? 'active' : 'preparation', 'activated_at' => $active ? $s->data_aktywacji : null,
                    'created_at' => $s->created_at, 'updated_at' => $s->updated_at ?? $s->created_at,
                ]);
                // stary model nie miał placówek: jedna placówka z danymi sklepu (miasto do uzupełnienia)
                $locationId = DB::table('shop_locations')->insertGetId([
                    'shop_id' => $shopId, 'name' => 'Główna', 'address' => (string) $s->address, 'city' => '', 'phone' => $s->phone,
                    'opening_hours' => $s->hours, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->remember('shop', $s->id, $shopId);
                $this->remember('shop_location', $s->id, $locationId);
                $this->count('sklepy');

                $roles = [$ownerId => 'owner'];
                foreach ($members->get($s->id, collect()) as $m) {
                    if (($uid = $this->mapped('user', $m->user_id)) === null) {
                        $this->count('członkowie sklepów', 'pominięto: użytkownik nie został zaimportowany');
                    } elseif (! isset($roles[$uid])) {
                        $roles[$uid] = $m->role === 'wlasciciel' ? 'owner' : 'staff';
                    }
                }
                foreach ($roles as $uid => $role) {
                    DB::table('shop_members')->insert(['shop_id' => $shopId, 'user_id' => $uid, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
                    $this->count('członkowie sklepów');
                }
            }
        });
    }

    private function offers(): void
    {
        $areas = ['FAUNA' => 'fauna', 'FLORA' => 'flora', 'SPRZET' => 'equipment'];

        DB::transaction(function () use ($areas) {
            foreach ($this->old->table('wp_aqss_offers')->orderBy('id')->cursor() as $o) {
                $skip = match (true) {
                    $this->mapped('offer', $o->id) !== null => 'już zaimportowano',
                    ($shopId = $this->mapped('shop', $o->shop_id)) === null => 'sklep nieistniejący lub niezaimportowany',
                    ! isset($areas[$o->aqs_db]) || ! isset(self::OFFER_STATES[$o->status]) => 'nieznany obszar lub stan',
                    ! DB::table('catalog_records')->where('area', $areas[$o->aqs_db])->where('legacy_id', $o->aqs_record_id)->exists() => 'brak pozycji w katalogu',
                    $o->price <= 0 || $o->valid_to < $o->valid_from => 'nieprawidłowa cena lub ważność',
                    default => null,
                };
                if ($skip !== null) {
                    $this->count('oferty', 'pominięto: '.$skip);

                    continue;
                }

                $variant = trim((string) $o->variant);
                $key = Str::limit(Str::lower(Str::squish($variant)), 120, '');
                $locationId = $this->mapped('shop_location', $o->shop_id);
                $identity = ['shop_id' => $shopId, 'location_id' => $locationId, 'area' => $areas[$o->aqs_db], 'legacy_id' => $o->aqs_record_id, 'variant_key' => $key];
                if (DB::table('shop_offers')->where($identity)->exists()) {
                    $this->count('oferty', 'pominięto: duplikat pozycji i wariantu');

                    continue;
                }

                $reserved = min((int) $o->pool_reserved, (int) $o->pool_qty);
                $free = (int) $o->pool_qty - $reserved;
                $id = DB::table('shop_offers')->insertGetId($identity + [
                    'variant' => Str::limit($variant, 120, ''), 'price_grosze' => (int) round($o->price * 100),
                    'valid_from' => $o->valid_from.' 00:00:00', 'valid_to' => $o->valid_to.' 23:59:59', 'state' => self::OFFER_STATES[$o->status],
                    'free_qty' => $free, 'reserved_qty' => $reserved, 'created_at' => $o->created_at, 'updated_at' => $o->updated_at ?? $o->created_at,
                ]);
                DB::table('shop_stock_movements')->insert([
                    'shop_id' => $shopId, 'offer_id' => $id, 'kind' => 'opening', 'delta_free' => $free, 'delta_reserved' => $reserved,
                    'free_after' => $free, 'reserved_after' => $reserved, 'reason' => 'Import z WordPressa', 'occurred_at' => now(),
                ]);
                $this->remember('offer', $o->id, $id);
                $this->count('oferty');
            }
        });
    }

    private function photos(): void
    {
        $rows = $this->old->table('wp_postmeta as s')->leftJoin('wp_posts as p', 'p.ID', '=', 's.post_id')
            ->where('s.meta_key', '_aquasmart_species_id')->orderBy('s.post_id')->get(['s.post_id', 's.meta_value as species', 'p.guid']);
        $meta = $this->old->table('wp_postmeta')->whereIn('post_id', $rows->pluck('post_id'))
            ->whereIn('meta_key', ['_wp_attached_file', '_aquasmart_photo_author', '_aquasmart_photo_license', '_aquasmart_photo_source_url'])
            ->get()->groupBy('post_id')->map(fn ($g) => $g->pluck('meta_value', 'meta_key'));

        DB::transaction(function () use ($rows, $meta) {
            foreach ($rows as $r) {
                $m = $meta[$r->post_id] ?? collect();
                $area = self::PHOTO_AREAS[substr((string) $r->species, 0, 2)] ?? null;
                $recordId = $area ? DB::table('catalog_records')->where('area', $area)->where('legacy_id', $r->species)->value('id') : null;
                $file = $m['_wp_attached_file'] ?? null;
                $skip = match (true) {
                    $this->mapped('photo', $r->post_id) !== null => 'już zaimportowano',
                    $recordId === null => 'brak pozycji w katalogu',
                    $r->guid === null && $file === null => 'brak pliku',
                    $this->photoExists($recordId, basename((string) ($file ?? $r->guid))) => 'zdjęcie już jest w katalogu',
                    default => null,
                };
                if ($skip !== null) {
                    $this->count('zdjęcia', 'pominięto: '.$skip);

                    continue;
                }
                $id = DB::table('catalog_photos')->insertGetId([
                    'record_id' => $recordId, 'url' => $r->guid, 'file_path' => $file, 'source_url' => $m['_aquasmart_photo_source_url'] ?? null,
                    'author' => $m['_aquasmart_photo_author'] ?? null, 'licence' => $m['_aquasmart_photo_license'] ?? null,
                    'verified' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->remember('photo', $r->post_id, $id);
                $this->count('zdjęcia');
            }
        });
    }

    private function photoExists(int $recordId, string $basename): bool
    {
        return DB::table('catalog_photos')->where('record_id', $recordId)
            ->where(fn ($q) => $q->where('url', 'like', '%/'.$basename)->orWhere('file_path', 'like', '%/'.$basename))->exists();
    }
}
