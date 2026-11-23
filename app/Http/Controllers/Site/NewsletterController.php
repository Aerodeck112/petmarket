<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        if ($request->filled('website')) {
            return back();
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:190', 'consent' => 'accepted'], ['consent.accepted' => 'Bifează acordul pentru a primi newsletterul.']);
        $sub = NewsletterSubscriber::query()->firstOrNew(['email' => mb_strtolower($data['email'])]);
        $sub->fill(['source' => $sub->exists ? $sub->source : 'footer', 'token' => $sub->token ?: Str::random(40), 'unsubscribed_at' => null])->save();
        $message = 'Te-ai abonat! Hiko îți va aduce ofertele direct în inbox.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('newsletter_ok', $message);
    }

    public function unsubscribe(string $token)
    {
        NewsletterSubscriber::query()->where('token', $token)->update(['unsubscribed_at' => now()]);

        return redirect()->away(site_url('/'))->with('newsletter_ok', 'Te-ai dezabonat de la newsletter.');
    }
}
