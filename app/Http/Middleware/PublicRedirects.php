<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Redirecturi 301 administrabile si URL canonic cu slash final, ca pe site-ul WordPress. */
class PublicRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true) || $request->is('admin*', 'livewire*', 'install*', 'build/*', 'media/*', 'uploads/*')) {
            return $next($request);
        }

        // O singura adresa pentru Google: www.pet-market.ro (si orice alias al domeniului) -> domeniul din APP_URL, cu 301.
        // Schema (http -> https) se forteaza din gazduire („HTTPS Enforce”), unde se stie sigur daca cererea a venit criptat.
        $canonical = parse_url((string) config('app.url'));
        $canonicalHost = strtolower((string) ($canonical['host'] ?? ''));
        $host = strtolower($request->getHost());
        if ($canonicalHost && $host !== $canonicalHost && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $canonicalHost)) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        $path = '/'.trim($request->path(), '/');
        $key = $path === '/' ? '/' : $path.'/';

        try {
            $map = Cache::remember('redirects.map', 600, fn () => Redirect::query()->get(['id', 'from_path', 'to_path', 'status_code'])
                ->mapWithKeys(fn ($r) => ['/'.trim($r->from_path, '/').'/' => [$r->id, $r->to_path, $r->status_code]])->all());
        } catch (\Throwable) {
            $map = [];
        }

        if (isset($map[$key])) {
            [$id, $to, $code] = $map[$key];
            Redirect::query()->whereKey($id)->increment('hits');

            return redirect()->away(str_starts_with($to, 'http') ? $to : site_url($to), $code);
        }

        // Cautarea WordPress (/?s=...&post_type=product) duce la cautarea noua.
        if ($path === '/' && $request->filled('s')) {
            return redirect()->away(site_url('/cautare/').'?q='.rawurlencode((string) $request->query('s')), 301);
        }
        // Linkurile vechi /?p=ID sau /?product=slug.
        if ($path === '/' && $request->filled('product')) {
            return redirect()->away(site_url('/produs/'.rawurlencode((string) $request->query('product')).'/'), 301);
        }
        if ($path === '/' && ctype_digit((string) $request->query('p'))) {
            $product = \App\Models\Product::query()->where('wc_product_id', (int) $request->query('p'))->first();
            if ($product) {
                return redirect()->away(site_url($product->path()), 301);
            }
        }

        $raw = $request->getPathInfo();
        // Paginile publice au slash final canonic, ca in WooCommerce; rutele functionale (JSON, contul) raman cum sunt.
        // (Clientul HTTP din testele automate elimina mereu slash-ul final, deci acolo regula ar produce o bucla.)
        $canonical = substr_count(trim($raw, '/'), '/') === 0 || preg_match('#^/(produs|categorie-produs|brand|eticheta-produs)/#', $raw);
        if (! app()->runningUnitTests() && $raw !== '/' && ! str_ends_with($raw, '/') && ! str_contains(basename($raw), '.') && $canonical
            && ! $request->is('cautare/sugestii', 'contul-meu/*')) {
            $query = $request->getQueryString();

            return redirect()->away($request->root().$raw.'/'.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
