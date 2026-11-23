<?php

namespace App\Http\Controllers;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Notifications\Actions\SaveNotificationPreference;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Topic;
use App\Domain\Shops\Actions\ListRecordWatches;
use App\Domain\Shops\Actions\UnwatchRecord;
use App\Domain\Shops\Actions\WatchRecord;
use App\Http\AdvisorReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecordWatchController
{
    public function index(Request $request, ListRecordWatches $list, Preferences $preferences): View
    {
        $userId = $request->user()->id;

        return view('watches.index', [
            'watches' => $list->handle($userId, max(1, $request->integer('page', 1))),
            'emailEnabled' => $preferences->allowsEmail($userId, Topic::Availability),
        ]);
    }

    public function store(Request $request, Area $area, string $id, WatchRecord $watch): RedirectResponse
    {
        $returnTo = AdvisorReturn::card($request->input('return_to'))
            ?? route('catalog.show', [$area->value, $id], absolute: false);
        $record = [$area->value, $id];
        try {
            $watch->handle($request->user(), $area, $id);
        } catch (ValidationException $error) {
            return redirect($returnTo)->withErrors($error->errors(), 'recordWatch')->with('watch_error', $record);
        }

        return redirect($returnTo)->with('watch_saved', $record);
    }

    public function destroy(Request $request, int $watchId, UnwatchRecord $unwatch): RedirectResponse
    {
        abort_unless($unwatch->handle($watchId, $request->user()->id), 404);

        return to_route('watches.index')->with('status', 'Obserwacja została zakończona.');
    }

    public function updatePreference(Request $request, SaveNotificationPreference $save): RedirectResponse
    {
        $request->validate(['availability_email' => ['required', 'boolean']]);
        $save->handle($request->user(), Topic::Availability, $request->boolean('availability_email'));

        return to_route('watches.index')->with('status', 'Zapisano ustawienia powiadomień.');
    }

    public function unsubscribe(Request $request, int $watchId, string $activation, UnwatchRecord $unwatch): Response
    {
        abort_unless($request->isMethod('GET'), 405, '', ['Allow' => 'GET']);

        return response()->view('watches.unsubscribe', [
            'removed' => $unwatch->viaSignedLink($watchId, $activation),
        ])->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
