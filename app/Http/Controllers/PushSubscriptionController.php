<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $existing = PushSubscription::findByEndpoint($data['endpoint']);
        if ($existing !== null && ! $request->user()->ownsPushSubscription($existing)) {
            throw ValidationException::withMessages(['endpoint' => 'Esta suscripción ya está asociada a otra cuenta.']);
        }

        $request->user()->updatePushSubscription(
            $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['content_encoding'] ?? 'aes128gcm',
        );

        return to_route('fund.notifications.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'starts_with:https://', 'max:1024']]);
        $request->user()->deletePushSubscription($data['endpoint']);

        return to_route('fund.notifications.index');
    }
}
