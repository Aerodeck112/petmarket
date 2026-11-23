<?php

namespace App\Services\Shipping;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Clientul API-ului Ecolet (https://panel.ecolet.ro/api/documentation, v1.0.3). Autentificarea este
 * OAuth2 cu metoda „password” (recomandata de Ecolet pentru integrarea cu propriul cont): aplicatia
 * OAuth creata in panoul Ecolet da Client ID / Client Secret, iar tokenul JWT este valabil un an.
 */
class EcoletClient
{
    public const BASE = 'https://panel.ecolet.ro/api/v1';

    public static function configured(): bool
    {
        return (bool) Settings::get('ecolet.enabled') && filled(Settings::get('ecolet.client_id')) && filled(Settings::get('ecolet.client_secret'))
            && filled(Settings::get('ecolet.username')) && filled(Settings::get('ecolet.password'));
    }

    /** Tokenul de acces, cerut o data si pastrat criptat pana aproape de expirare. */
    public function token(bool $fresh = false): string
    {
        $cached = Settings::get('ecolet.token');
        if (! $fresh && is_array($cached) && ($cached['expires'] ?? 0) > time() + 3600) {
            return $cached['access'];
        }

        $form = ['client_id' => Settings::get('ecolet.client_id'), 'client_secret' => Settings::get('ecolet.client_secret'), 'scope' => ''];
        $response = null;
        if (! $fresh && is_array($cached) && ! empty($cached['refresh'])) {
            $response = Http::asForm()->acceptJson()->timeout(20)->post(self::BASE.'/oauth/token', $form + ['grant_type' => 'refresh_token', 'refresh_token' => $cached['refresh']]);
        }
        if (! $response?->successful()) {
            $response = Http::asForm()->acceptJson()->timeout(20)->post(self::BASE.'/oauth/token', $form + [
                'grant_type' => 'password', 'username' => Settings::get('ecolet.username'), 'password' => Settings::get('ecolet.password'),
            ]);
        }
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Autentificarea la Ecolet a eșuat ('.$response->status().'). Verifică Client ID, Client Secret, emailul și parola contului Ecolet.');
        }
        Settings::set('ecolet.token', ['access' => $response->json('access_token'), 'refresh' => $response->json('refresh_token'),
            'expires' => time() + (int) $response->json('expires_in', 3600)], true);

        return $response->json('access_token');
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->asJson()->timeout(30)->withHeaders(['Accept-Language' => 'ro']);
    }

    /** Cerere cu reautentificare o singura data la 401. */
    public function send(string $method, string $path, array $data = []): Response
    {
        $response = $this->http()->send($method, self::BASE.$path, $data ? ['json' => $data] : []);
        if ($response->status() === 401) {
            $this->token(true);
            $response = $this->http()->send($method, self::BASE.$path, $data ? ['json' => $data] : []);
        }

        return $response;
    }

    public function me(): array
    {
        $response = $this->send('GET', '/me');
        if (! $response->successful()) {
            throw new RuntimeException('Ecolet a răspuns cu eroarea '.$response->status().'.');
        }

        return (array) $response->json();
    }

    /** ID-ul localitatii in nomenclatorul Ecolet (obligatoriu pentru Romania), cautat dupa nume si judet. */
    public function localityId(string $city, string $county): ?int
    {
        $key = 'ecolet.loc.'.md5(mb_strtolower($city.'|'.$county));

        return Cache::remember($key, now()->addDays(30), function () use ($city, $county) {
            $search = trim(\Illuminate\Support\Str::ascii($city));
            if (mb_strlen($search) < 3) {
                return null;
            }
            $response = $this->send('GET', '/locations/ro/localities/'.rawurlencode($search.' '.\Illuminate\Support\Str::ascii($county)));
            $list = collect($response->json('localities', []));
            if ($list->isEmpty()) {
                $list = collect($this->send('GET', '/locations/ro/localities/'.rawurlencode($search))->json('localities', []));
            }
            $norm = fn ($s) => mb_strtolower(trim(\Illuminate\Support\Str::ascii((string) $s)));
            $match = $list->first(fn ($l) => $norm($l['name'] ?? '') === $norm($city) && $norm($l['county']['name'] ?? '') === $norm($county))
                ?? $list->first(fn ($l) => $norm($l['county']['name'] ?? '') === $norm($county))
                ?? ($list->count() === 1 ? $list->first() : null);

            return $match['id'] ?? null;
        });
    }
}
