<?php

namespace App\Providers;

use App\Enums\Role;
use App\Support\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Cand adresa site-ului este HTTPS, toate linkurile generate sunt HTTPS, chiar daca serverul
        // primeste cererea prin HTTP de la un proxy (situatie obisnuita pe hosting shared).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Super Admin are toate permisiunile, inclusiv cele adaugate ulterior.
        Gate::before(fn ($user, $ability) => $user->hasRole(Role::SuperAdmin->value) ? true : null);

        // Pachetele de actualizare pot depasi limita implicita de 12 MB a incarcarilor Livewire; limita reala o da PHP (upload_max_filesize).
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:204800']]);

        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised(0));

        // Emailul de setare / resetare a parolei, in romana.
        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

            return (new MailMessage)
                ->subject('Setează parola contului Pet Market')
                ->greeting('Bună, '.($user->first_name ?: $user->name).'!')
                ->line('Ai cerut setarea unei parole noi pentru contul tău Pet Market.')
                ->action('Setează parola', $url)
                ->line('Linkul este valabil 60 de minute. Dacă nu ai cerut tu acest lucru, ignoră mesajul.')
                ->salutation('Hiko, Raza și echipa Pet Market');
        });

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(6)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));

        // Datele magazinului, meniul de categorii si cosul sunt disponibile in toate sabloanele publice.
        View::composer(['site.*', 'shop.*', 'account.*', 'layouts.*', 'partials.*', 'errors::*'], function ($view) {
            $shop = request()->attributes->get('pm.shop') ?? [
                'name' => Settings::get('shop.name'), 'byline' => Settings::get('shop.byline'), 'legal_name' => Settings::get('shop.legal_name'),
                'cui' => Settings::get('shop.cui'), 'reg_com' => Settings::get('shop.reg_com'),
                'phone' => Settings::get('shop.phone'), 'email' => Settings::get('shop.email'), 'hours' => Settings::get('shop.hours'),
                'address' => Settings::get('shop.address'), 'city' => Settings::get('shop.city'), 'county' => Settings::get('shop.county'), 'tagline' => Settings::get('shop.tagline'),
                'instagram' => Settings::get('shop.instagram'), 'facebook' => Settings::get('shop.facebook'), 'tiktok' => Settings::get('shop.tiktok'),
                'announcement' => Settings::get('marketing.announcement'), 'free_shipping' => (int) Settings::get('commerce.free_shipping_threshold'),
                'gtm_id' => Settings::get('marketing.gtm_id'), 'ga4_id' => Settings::get('marketing.ga4_id'), 'meta_pixel_id' => Settings::get('marketing.meta_pixel_id'),
                'verification' => Settings::get('marketing.search_console_verification'),
                'postcode' => Settings::get('shop.postcode'), 'capital' => Settings::get('shop.capital_social'), 'delivery_time' => Settings::get('commerce.delivery_time') ?: '1–4 zile lucrătoare',
                'vmp_logo_url' => Settings::get('legal.vmp_logo_url'), 'vmp_logo_image' => Settings::get('legal.vmp_logo_image'),
            ];
            request()->attributes->set('pm.shop', $shop);
            $view->with('shop', $shop);
        });
    }
}
