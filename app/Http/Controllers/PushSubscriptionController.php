<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\Actions\DeletePushSubscription;
use App\Domain\Notifications\Actions\SaveNotificationPreference;
use App\Domain\Notifications\Actions\SavePushSubscription;
use App\Domain\Notifications\Data\PushSubscriptionData;
use App\Domain\Notifications\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController
{
    public function offline(): Response
    {
        return response()->view('offline')->header('X-Robots-Tag', 'noindex');
    }

    public function store(
        Request $request,
        SavePushSubscription $save,
        SaveNotificationPreference $savePreference,
    ): JsonResponse|RedirectResponse {
        $endpoint = $request->input('endpoint');
        $p256dh = $request->input('keys.p256dh', $request->input('p256dh'));
        $authToken = $request->input('keys.auth', $request->input('auth_token'));
        $contentEncoding = $request->input('content_encoding', 'aes128gcm');

        $save->handle($request->user(), new PushSubscriptionData(
            endpoint: is_string($endpoint) ? $endpoint : '',
            p256dh: is_string($p256dh) ? $p256dh : '',
            authToken: is_string($authToken) ? $authToken : '',
            contentEncoding: is_string($contentEncoding) ? $contentEncoding : 'aes128gcm',
        ));

        if ($request->boolean('enable_reminders', true)) {
            $savePreference->handle($request->user(), Topic::Reminders, push: true);
        }

        if ($request->expectsJson()) {
            return response()->json(['subscribed' => true, 'push_enabled' => true], 201);
        }

        return back()->with('status', 'Włączono powiadomienia push.');
    }

    public function destroy(
        Request $request,
        DeletePushSubscription $delete,
        SaveNotificationPreference $savePreference,
    ): JsonResponse|RedirectResponse {
        $endpoint = $request->input('endpoint');
        $delete->handle($request->user(), is_string($endpoint) ? $endpoint : null);
        $savePreference->handle($request->user(), Topic::Reminders, push: false);

        if ($request->expectsJson()) {
            return response()->json(['subscribed' => false, 'push_enabled' => false]);
        }

        return back()->with('status', 'Wyłączono powiadomienia push.');
    }
}
