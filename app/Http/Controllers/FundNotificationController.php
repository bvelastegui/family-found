<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('fund/Notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20)
                ->through(fn ($notification): array => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'],
                    'body' => $notification->data['body'],
                    'url' => $notification->data['url'],
                    'created_at' => $notification->created_at,
                    'read_at' => $notification->read_at,
                ]),
            'vapidPublicKey' => (string) config('webpush.vapid.public_key'),
            'subscribedEndpoints' => $request->user()->pushSubscriptions()->pluck('endpoint'),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
