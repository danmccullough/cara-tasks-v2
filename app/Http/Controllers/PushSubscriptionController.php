<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($request->string('endpoint'));

        return response()->noContent();
    }
}
