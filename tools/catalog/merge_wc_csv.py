#!/usr/bin/env python3
"""Exportul CSV din WooCommerce (Produse → Export) completeaza exportul Store API.

Store API publica doar produsele publicate si nu are stocul exact, greutatea, codul EAN, clasa de TVA si
marcajul „nu se vinde online”. Din CSV se scrie:
  database/seeders/data/catalog/wc-export.json   ID WooCommerce -> preturi, stoc, greutate, EAN, TVA, vanzare online
  database/seeders/data/catalog/products-002.json produsele nepublicate (ciorne), in formatul Store API, ca ciorne
Dupa asta nu mai e nevoie de baza WordPress pentru preturi si stoc.
Rulare: python3 -I tools/catalog/merge_wc_csv.py export.csv
"""
import csv, html, json, os, re, sys, unicodedata

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
D = os.path.join(ROOT, 'database', 'seeders', 'data', 'catalog')
csv.field_size_limit(10 ** 9)


def money(v):
    v = (v or '').strip().replace(' ', '')
    if not v:
        return None
    if ',' in v and '.' in v:
        v = v.replace('.', '').replace(',', '.')
    return int(round(float(v.replace(',', '.')) * 100))


def num(v):
    v = (v or '').strip().replace(',', '.')
    try:
        return float(v) if v else None
    except ValueError:
        return None


def slugify(s):
    s = unicodedata.normalize('NFKD', html.unescape(s)).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')[:180] or 'produs'


def main(path):
    rows = list(csv.DictReader(open(path, encoding='utf-8-sig')))
    for r in rows:
        for k in list(r):
            r[k] = r[k] or ''
    published = {}
    for f in sorted(os.listdir(D)):
        if f.startswith('products-') and f.endswith('.json') and f != 'products-002.json':
            for p in json.load(open(os.path.join(D, f))):
                published[p['id']] = p
    cats = json.load(open(os.path.join(D, 'categories.json')))
    byid = {c['id']: c for c in cats}

    def cat_path(c):
        chain = []
        while c:
            chain.append(html.unescape(c['name']).strip().lower()); c = byid.get(c['parent'])
        return ' > '.join(reversed(chain))
    cat_by_path = {cat_path(c): c for c in cats}

    out, drafts = {}, []
    slugs = {p['slug'] for p in published.values()}
    for r in rows:
        wid = int(r['ID'])
        stock = r['Stoc'].strip()
        gtin = (r['GTIN, UPC, EAN sau ISBN'] or r['Meta: cod_ean']).strip()
        out[str(wid)] = {
            'status': {'1': 'published', '0': 'private'}.get(r['Publicat'], 'draft'),
            'regular_price': money(r['Preț obișnuit']), 'sale_price': money(r['Preț promoțional']),
            'sale_from': r['Dată începere promoție'].strip() or None, 'sale_to': r['Dată încheiere promoție'].strip() or None,
            'stock_qty': int(float(stock)) if stock else None,
            'backorder': r['În stoc?'] == 'backorder', 'in_stock': r['În stoc?'] in ('1', 'backorder'),
            'weight_kg': num(r['Greutate (kg)']), 'gtin': re.sub(r'\s+', '', gtin)[:40] or None,
            'vat_rate': 11.0 if r['Clasă de impozitare'] == 'tarif-redus' else None,
            'no_online_sale': r['Meta: _pm_no_online_sale'] == 'yes' or r['Meta: _pm_requires_vet_prescription'] == 'yes',
            'delivery_estimate': r['Meta: _pm_delivery_estimate'].strip() or None,
            'purchase_note': r['Notă de cumpărare'].strip() or None,
        }
        if wid in published:
            continue
        # Ciorna: se construieste un rand in formatul Store API, ca sa treaca prin aceeasi clasificare si acelasi import.
        slug = base = slugify(r['Nume'])
        i = 2
        while slug in slugs:
            slug = f'{base}-{i}'; i += 1
        slugs.add(slug)
        categories = []
        for path_ in r['Categorii'].split(','):
            c = cat_by_path.get(' > '.join(x.strip().lower() for x in path_.split('>')))
            if c:
                categories.append({'id': c['id'], 'name': c['name'], 'slug': c['slug']})
        reg = money(r['Preț obișnuit'])
        drafts.append({
            'id': wid, 'name': r['Nume'], 'slug': slug, 'sku': r['SKU'], 'type': 'simple', 'status': 'draft',
            'short_description': r['Descriere scurtă'], 'description': r['Descriere'], 'on_sale': False,
            'prices': {'price': str(reg or ''), 'regular_price': str(reg or ''), 'sale_price': str(reg or '')},
            'images': [{'src': u.strip(), 'alt': ''} for u in r['Imagini'].split(',') if u.strip()],
            'categories': categories, 'tags': [], 'brands': [{'name': b.strip()} for b in r['Branduri'].split(',') if b.strip()],
            'csv_categories': [c.strip() for c in r['Categorii'].split(',') if c.strip()],
            'attributes': [], 'is_purchasable': True, 'is_in_stock': True, 'weight': r['Greutate (kg)'],
        })
    json.dump(out, open(os.path.join(D, 'wc-export.json'), 'w'), ensure_ascii=False, separators=(',', ':'))
    json.dump(drafts, open(os.path.join(D, 'products-002.json'), 'w'), ensure_ascii=False)
    print(len(out), 'produse din CSV;', len(drafts), 'ciorne;', sum(1 for v in out.values() if v['stock_qty'] is not None), 'cu stoc exact;',
          sum(1 for v in out.values() if v['weight_kg']), 'cu greutate;', sum(1 for v in out.values() if v['vat_rate']), 'cu TVA redus;',
          sum(1 for v in out.values() if v['no_online_sale']), 'fara vanzare online')


if __name__ == '__main__':
    main(sys.argv[1])
