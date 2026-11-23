@if ($shop['gtm_id'] || $shop['ga4_id'] || $shop['meta_pixel_id'] || \App\Support\Settings::get('marketing.google_ads_id'))
<div class="consent no-print" x-data="consent" x-show="visible" x-cloak role="dialog" aria-label="{{ __('Acord pentru cookie-uri') }}">
    <x-buddy class="consent-buddy buddy--left" kind="dog" pose="peek" :say="['Fursecuri? Eu prefer recompense.', 'Alegi tu. Eu oricum dau din coadă.', 'Eu sunt Hiko. Păzesc și cookie-urile.']" />
    <p><strong>{{ __('Cookie-uri de măsurare.') }}</strong> Le folosim doar cu acordul tău, pentru statistici și reclame relevante. Magazinul funcționează complet și fără ele. Detalii în <a href="{{ site_url('/politica-cookies/') }}">politica de cookies</a>.</p>
    <div class="consent-actions">
        <button class="btn btn-ghost btn-sm" type="button" @click="choose(false)">{{ __('Refuz') }}</button>
        <button class="btn btn-dark btn-sm" type="button" @click="choose(true)">{{ __('Accept') }}</button>
    </div>
</div>
@endif
