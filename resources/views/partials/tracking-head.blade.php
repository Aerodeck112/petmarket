{{-- Scripturile de masurare (GTM, GA4, Google Ads, Meta) se incarca doar dupa acordul vizitatorului (resources/js/site.js). --}}
@php $ads = \App\Support\Settings::get('marketing.google_ads_id'); @endphp
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
    window.hvTracking = { gtm: @json($shop['gtm_id']), ga4: @json($shop['ga4_id']), ads: @json($ads), meta: @json($shop['meta_pixel_id']) };
</script>
