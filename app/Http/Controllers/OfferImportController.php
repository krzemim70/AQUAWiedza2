<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Actions\PreviewOfferImport;
use App\Domain\Shops\Actions\StartOfferImport;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\OfferImport\Csv;
use App\Filament\Shop\Pages\OfferImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferImportController
{
    public function __construct(private Access $access) {}

    public function template(Request $request, Shop $shop): Response
    {
        $this->access->authorizeMember($request->user(), $shop->id);

        return response(Csv::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wzor-ofert.csv"',
        ]);
    }

    public function preview(Request $request, Shop $shop, Settings $settings, PreviewOfferImport $preview): RedirectResponse
    {
        $actor = $request->user();
        $this->access->authorizeMember($actor, $shop->id);
        $request->validate(['file' => ['required', 'file', 'extensions:csv']], [
            'file.required' => 'Wybierz plik CSV.',
            'file.uploaded' => 'Nie udało się przesłać pliku. Sprawdź jego rozmiar.',
            'file.extensions' => 'Wybierz plik z rozszerzeniem .csv.',
        ]);
        $file = $request->file('file');
        $limit = (int) $settings->get(SettingKey::ShopCsvImport)['max_bytes'];
        if ($file->getSize() > $limit) {
            throw ValidationException::withMessages(['file' => "Plik może mieć najwyżej $limit bajtów."]);
        }
        $batch = $preview->handle($actor, $shop, $file->getContent());

        return redirect(OfferImport::getUrl(['import' => $batch->id], panel: 'shop', tenant: $shop));
    }

    public function apply(Request $request, Shop $shop, int $batch, StartOfferImport $start): RedirectResponse
    {
        $start->handle($request->user(), $shop, $batch, $request->boolean('confirmed'));

        return redirect(OfferImport::getUrl(['import' => $batch], panel: 'shop', tenant: $shop))
            ->with('offer_import_started', true);
    }

    public function errors(Request $request, Shop $shop, int $batch): StreamedResponse
    {
        $actor = $request->user();
        $this->access->authorizeMember($actor, $shop->id);
        $report = ImportBatch::forActor($actor, $shop)->findOrFail($batch, ['report'])->report;
        $errors = $report['errors'];
        ksort($errors, SORT_NUMERIC);

        return response()->streamDownload(function () use ($errors) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['wiersz', 'powod'], ';', escape: '');
            foreach ($errors as $line => $reasons) {
                fputcsv($out, [$line, implode(' ', $reasons)], ';', escape: '');
            }
            fclose($out);
        }, 'bledy-importu.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
