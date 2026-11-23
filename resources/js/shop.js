// Componentele magazinului (Alpine): cos lateral, cautare cu sugestii, filtre, pagina de produs,
// finalizarea comenzii. Toate preturile vin de la server; aici doar se afiseaza.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const post = (url, data) => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data),
    credentials: 'same-origin',
});
const base = () => document.documentElement.dataset.base || '';
export const money = (minor) => (minor === null || minor === undefined) ? '' :
    new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(minor / 100) + ' lei';
const track = (event, ecommerce) => {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({ event, ecommerce });
};

export function shopShell(initialCount) {
    return {
        count: initialCount, drawer: false, menuOpen: false, mini: '', toast: '', toastTimer: null,
        closeAll() { this.drawer = false; this.menuOpen = false; },
        say(text) {
            this.toast = text;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = ''), 2600);
        },
        bump() {
            document.querySelectorAll('.cart-count').forEach((el) => { el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump'); });
        },
        async openCart() {
            this.drawer = true;
            // Rezumatul cosului vine de la server (acelasi calcul ca la finalizare).
            const r = await post(base() + '/cos/actualizeaza', {}).catch(() => null);
            if (r) { const j = await r.json(); this.mini = j.mini; this.count = j.count; }
        },
        async addToCart(productId, variantId, qty, el) {
            el?.setAttribute('disabled', '');
            try {
                const r = await post(base() + '/cos/adauga', { product_id: productId, variant_id: variantId, qty });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) { this.say(j.message || 'Nu am putut adăuga produsul.'); return false; }
                this.count = j.count; this.mini = j.mini; this.bump();
                el?.classList.add('is-added');
                setTimeout(() => el?.classList.remove('is-added'), 1600);
                this.drawer = true;
                track('add_to_cart', { currency: 'RON', items: [{ item_id: String(productId), quantity: qty }] });
                return true;
            } finally { el?.removeAttribute('disabled'); }
        },
        async removeItem(id) {
            const r = await post(base() + '/cos/sterge/' + id, {});
            const j = await r.json();
            this.count = j.count; this.mini = j.mini;
        },
        async toggleFav(productId, el) {
            const r = await post(base() + '/favorite', { product_id: productId });
            const j = await r.json().catch(() => ({}));
            if (r.status === 401) { this.say(j.message); setTimeout(() => (location.href = j.login), 1200); return; }
            el?.setAttribute('aria-pressed', j.saved ? 'true' : 'false');
            this.say(j.saved ? 'Salvat la favorite. Raza a notat.' : 'Scos de la favorite.');
        },
    };
}

export function searchBox() {
    return {
        term: '', open: false, loading: false, active: -1, results: { products: [], categories: [], all: '#' }, ctrl: null,
        async fetch() {
            if (this.term.trim().length < 2) { this.open = false; this.results.products = []; return; }
            this.ctrl?.abort(); this.ctrl = new AbortController(); this.loading = true;
            try {
                const r = await fetch(base() + '/cautare/sugestii?q=' + encodeURIComponent(this.term.trim()), { signal: this.ctrl.signal, headers: { Accept: 'application/json' } });
                this.results = await r.json(); this.active = -1; this.open = true;
            } catch (e) { /* cerere anulata */ } finally { this.loading = false; }
        },
        move(d) { const n = this.results.products.length; if (!n) return; this.active = (this.active + d + n) % n; },
        go(e) { const p = this.results.products[this.active]; if (this.open && p) { e.preventDefault(); location.href = p.url; } },
        track() { track('search', { search_term: this.term }); },
    };
}

export function newsletter() {
    return {
        busy: false, msg: '',
        async send(form) {
            this.busy = true;
            const data = Object.fromEntries(new FormData(form));
            const r = await post(form.action, data).catch(() => null);
            const j = r ? await r.json().catch(() => ({})) : {};
            this.msg = r?.ok ? j.message : (j.message || 'Verifică emailul și bifează acordul.');
            this.busy = false;
            if (r?.ok) form.reset();
        },
    };
}

export function rail() {
    return {
        atStart: true, atEnd: false,
        init() { this.$nextTick(() => this.update()); },
        update() { const t = this.$refs.track; this.atStart = t.scrollLeft < 8; this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 8; },
        go(d) { const t = this.$refs.track; t.scrollBy({ left: d * t.clientWidth * 0.85, behavior: 'smooth' }); },
    };
}

export function heroSlides(count) {
    return {
        i: 0, timer: null,
        init() { if (count > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => (this.i = (this.i + 1) % count), 6500); },
        go(n) { this.i = n; clearInterval(this.timer); },
    };
}

// Filtrele din catalog: se aplica fara reincarcarea paginii, cu URL-ul actualizat (se poate trimite oricui).
export function filters() {
    return {
        open: false, loading: false,
        init() {
            this.$el.addEventListener('click', (e) => {
                const a = e.target.closest('[data-filter-link]');
                if (a && !e.metaKey && !e.ctrlKey) { e.preventDefault(); this.reload(a.href); }
            });
        },
        group(e, ref) {
            const input = this.$refs[ref] || this.$el.querySelector(`[x-ref="${ref}"]`);
            const set = new Set((input.value || '').split(',').filter(Boolean));
            e.target.checked ? set.add(e.target.value) : set.delete(e.target.value);
            input.value = [...set].join(',');
            this.apply();
        },
        apply() {
            const params = new URLSearchParams();
            for (const [k, v] of new FormData(this.$refs.form)) if (v !== '') params.append(k, v);
            const url = location.pathname + (params.toString() ? '?' + params : '');
            this.reload(url);
        },
        sort(value) {
            const params = new URLSearchParams(location.search);
            params.set('orderby', value); params.delete('page');
            this.reload(location.pathname + '?' + params);
        },
        async reload(url, push = true) {
            this.loading = true;
            const u = new URL(url, location.origin);
            u.searchParams.set('partial', '1');
            try {
                const html = await (await fetch(u, { headers: { Accept: 'text/html' } })).text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const part = (n) => doc.querySelector(`template[data-part="${n}"]`)?.innerHTML ?? '';
                this.$refs.results.innerHTML = part('results');
                this.$refs.chips.innerHTML = part('chips');
                const count = document.querySelector('.list-count'); if (count) count.textContent = part('count').trim();
                u.searchParams.delete('partial');
                if (push) history.pushState({}, '', u.pathname + u.search);
                if (window.innerWidth < 960) this.open = false;
                window.scrollTo({ top: this.$el.offsetTop - 140, behavior: 'smooth' });
            } catch (e) { location.href = url; } finally { this.loading = false; }
        },
    };
}

export function productPage(cfg) {
    return {
        ...cfg, index: 0, zoom: false, qty: cfg.min || 1, sel: {}, variant: null, busy: false, priceMax: null,
        get current() { return this.images[this.index] || { src: '', alt: '' }; },
        init() {
            this.base = { price: cfg.price, regular: cfg.regular, onSale: cfg.onSale, stock: cfg.stock, qtyLeft: cfg.qty, sku: cfg.sku, lowest30: cfg.lowest30 };
            if (this.variable && this.variants.length) {
                this.priceMax = Math.max(...this.variants.map((v) => v.price || 0));
                // Daca exista o singura valoare pe atribut, se preselecteaza.
                this.attrs.forEach((a) => { if (a.values.length === 1) this.sel[a.slug] = a.values[0].slug; });
                this.resolve();
            }
            track('view_item', { currency: 'RON', value: (this.price || 0) / 100, items: [{ item_id: this.sku || String(this.id), item_name: this.name, item_brand: this.brand, item_category: this.category, price: (this.price || 0) / 100 }] });
        },
        fmt: money,
        show(i) { this.index = i; },
        labelFor(slug) { const a = this.attrs.find((x) => x.slug === slug); return a?.values.find((v) => v.slug === this.sel[slug])?.name || ''; },
        pick(slug, value) { this.sel[slug] = this.sel[slug] === value ? undefined : value; this.resolve(); },
        possible(slug, value) {
            return this.variants.some((v) => v.values[slug] === value && Object.entries(this.sel).every(([k, s]) => !s || k === slug || v.values[k] === s));
        },
        resolve() {
            const complete = this.attrs.every((a) => this.sel[a.slug]);
            this.variant = complete ? this.variants.find((v) => this.attrs.every((a) => v.values[a.slug] === this.sel[a.slug])) || null : null;
            const v = this.variant;
            this.price = v ? v.price : this.base.price;
            this.regular = v ? v.regular : this.base.regular;
            this.onSale = v ? v.onSale : this.base.onSale;
            this.stock = v ? v.stock : this.base.stock;
            this.qtyLeft = v ? v.qty : this.base.qtyLeft;
            this.sku = v ? v.sku : this.base.sku;
            this.lowest30 = v ? v.lowest30 : this.base.lowest30;
            if (v?.image) { const at = this.images.findIndex((i) => i.src === v.image); if (at >= 0) this.index = at; else { this.images.unshift({ src: v.image, alt: this.name }); this.index = 0; } }
        },
        maxQty() { return Math.max(1, Math.min(this.max || 999, this.qtyLeft ?? 999)); },
        stockText() {
            if (this.variable && !this.variant) return 'Alege o variantă';
            if (this.stock === 'outofstock') return 'Stoc epuizat';
            if (this.stock === 'onbackorder') return 'La comandă: livrare în 5–10 zile lucrătoare';
            if (this.qtyLeft !== null && this.qtyLeft !== undefined && this.qtyLeft <= this.low) return 'Ultimele ' + this.qtyLeft + ' bucăți';
            return 'În stoc, gata de livrare';
        },
        canBuy() { return this.stock !== 'outofstock' && this.price !== null && (!this.variable || this.variant); },
        async add() {
            if (!this.canBuy()) return;
            this.busy = true;
            await this.addToCart(this.id, this.variant?.id || null, Math.min(this.qty, this.maxQty()), this.$el.querySelector('.btn-buy'));
            this.busy = false;
        },
    };
}

export function checkout(cfg) {
    return {
        ...cfg, email: '', createAccount: false, submitting: false, timer: null,
        init() { this.email = document.getElementById('email')?.value || ''; this.refresh(); track('begin_checkout', { currency: 'RON' }); },
        refresh() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                const r = await post(base() + '/finalizare/calcul', { shipping_method_id: this.method, payment_method: this.payment, email: this.email, county: this.county }).catch(() => null);
                if (r?.ok) { const j = await r.json(); this.$refs.totals.innerHTML = j.html; }
            }, 120);
        },
    };
}
