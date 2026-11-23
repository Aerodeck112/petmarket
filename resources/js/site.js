import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { initBuddies } from './buddies';
import { initFun } from './fun';
import { shopShell, searchBox, newsletter, rail, heroSlides, filters, productPage, checkout } from './shop';

window.Alpine = Alpine;
Alpine.plugin(focus);

Alpine.data('shopShell', shopShell);
Alpine.data('searchBox', searchBox);
Alpine.data('newsletter', newsletter);
Alpine.data('rail', rail);
Alpine.data('heroSlides', heroSlides);
Alpine.data('filters', filters);
Alpine.data('productPage', productPage);
Alpine.data('checkout', checkout);

// Acord pentru masurare: Google Tag Manager / GA4 / Google Ads / Meta se incarca doar dupa accept.
Alpine.data('consent', () => ({
    visible: false,
    init() {
        const saved = localStorage.getItem('pm-consent');
        if (saved === 'yes') this.load();
        else if (saved !== 'no') this.visible = true;
    },
    choose(accepted) {
        localStorage.setItem('pm-consent', accepted ? 'yes' : 'no');
        this.visible = false;
        if (accepted) this.load();
    },
    load() {
        const cfg = window.hvTracking || {};
        window.gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
        const add = (src) => { const s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); };
        if (cfg.gtm) {
            window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            add('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(cfg.gtm));
        } else if (cfg.ga4 || cfg.ads) {
            add('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4 || cfg.ads));
            window.gtag('js', new Date());
            if (cfg.ga4) window.gtag('config', cfg.ga4);
            if (cfg.ads) window.gtag('config', cfg.ads);
        }
        if (cfg.meta) {
            /* eslint-disable */
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            window.fbq('init', cfg.meta); window.fbq('track', 'PageView');
        }
    },
}));

document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-track]');
    if (el && window.dataLayer) window.dataLayer.push({ event: el.dataset.track });
});

Alpine.start();

initBuddies();
initFun();

if (document.querySelector('[data-wall]')) {
    import('./mural').then((m) => m.initMural());
}
