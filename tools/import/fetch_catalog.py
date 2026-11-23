#!/usr/bin/env python3
"""Resume-safe exporter for the public WooCommerce Store API of pet-market.ro.

Raw responses are cached in tools/import/cache/ (one file per request; delete it for a fresh export).
Final compact JSON goes to database/seeders/data/catalog/ split in files <= 20 MB.
Usage (from the repository root): python3 tools/import/fetch_catalog.py
"""
import json, os, sys, time, urllib.request, urllib.error, urllib.parse

BASE = "https://pet-market.ro"
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36"
HERE = os.path.dirname(os.path.abspath(__file__))
CACHE = os.path.join(HERE, "cache")
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
OUT = os.path.join(ROOT, "database", "seeders", "data", "catalog")
PAGES_OUT = os.path.join(ROOT, "database", "seeders", "data", "pages")
MAX_BYTES = 20 * 1024 * 1024
PAUSE = 0.3
os.makedirs(CACHE, exist_ok=True)
os.makedirs(OUT, exist_ok=True)
os.makedirs(PAGES_OUT, exist_ok=True)


def get(path, cache_name, allow_404=False):
    """GET BASE+path, cache body+headers. Returns (data, headers)."""
    fn = os.path.join(CACHE, cache_name)
    if os.path.exists(fn):
        with open(fn, encoding="utf-8") as f:
            c = json.load(f)
        return c["data"], c["headers"]
    url = BASE + path
    for attempt in range(5):
        req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
        try:
            with urllib.request.urlopen(req, timeout=90) as r:
                body = r.read().decode("utf-8")
                headers = {k.lower(): v for k, v in r.headers.items()}
                data = json.loads(body)
                break
        except urllib.error.HTTPError as e:
            if e.code in (400, 401, 403, 404) and allow_404:
                data, headers = {"_error": e.code, "_body": e.read().decode("utf-8", "replace")[:2000]}, {}
                break
            print(f"HTTP {e.code} {url} (attempt {attempt})", file=sys.stderr)
            time.sleep(3 * (attempt + 1))
        except Exception as e:  # network / json
            print(f"ERR {e} {url} (attempt {attempt})", file=sys.stderr)
            time.sleep(3 * (attempt + 1))
    else:
        raise RuntimeError("giving up on " + url)
    with open(fn + ".tmp", "w", encoding="utf-8") as f:
        json.dump({"url": url, "headers": headers, "data": data}, f, ensure_ascii=False)
    os.replace(fn + ".tmp", fn)
    time.sleep(PAUSE)
    return data, headers


def slim_images(imgs):
    return [{"id": i.get("id"), "src": i.get("src"), "alt": i.get("alt", ""), "name": i.get("name")} for i in imgs or []]


def slim_product(p):
    p = dict(p)
    p.pop("_links", None)
    p["images"] = slim_images(p.get("images"))
    return p


def write_split(prefix, items):
    """Write items to prefix-001.json ... each <= MAX_BYTES (compact JSON)."""
    for old in os.listdir(OUT):
        if old.startswith(prefix + "-") and old.endswith(".json"):
            os.remove(os.path.join(OUT, old))
    chunk, size, n = [], 2, 1
    files = []
    def flush():
        nonlocal chunk, size, n
        fn = os.path.join(OUT, f"{prefix}-{n:03d}.json")
        with open(fn, "w", encoding="utf-8") as f:
            f.write("[" + ",".join(chunk) + "]")
        files.append(fn)
        chunk, size, n = [], 2, n + 1
    for it in items:
        s = json.dumps(it, ensure_ascii=False, separators=(",", ":"))
        b = len(s.encode("utf-8")) + 1
        if chunk and size + b > MAX_BYTES:
            flush()
        chunk.append(s)
        size += b
    if chunk or not files:
        flush()
    return files


def dump(name, obj, outdir=OUT):
    with open(os.path.join(outdir, name), "w", encoding="utf-8") as f:
        json.dump(obj, f, ensure_ascii=False, separators=(",", ":"))


def paged(path_tmpl, prefix, per_page=100, allow_404=False):
    out, page = [], 1
    while True:
        sep = "&" if "?" in path_tmpl else "?"
        data, h = get(f"{path_tmpl}{sep}per_page={per_page}&page={page}", f"{prefix}-p{page:04d}.json", allow_404)
        if isinstance(data, dict) and "_error" in data:
            return None if page == 1 else out
        if not isinstance(data, list):
            return out
        out.extend(data)
        tp = int(h.get("x-wp-totalpages", "1") or 1)
        if page >= tp or not data:
            break
        page += 1
    return out


def main():
    # ---- products
    products = paged("/wp-json/wc/store/v1/products?orderby=id&order=asc", "products")
    seen, uniq = set(), []
    for p in products:
        if p["id"] not in seen:
            seen.add(p["id"])
            uniq.append(slim_product(p))
    print("products:", len(uniq))
    files = write_split("products", uniq)
    print("wrote", files)

    # ---- variations
    var_ids = []
    for p in uniq:
        for v in p.get("variations") or []:
            var_ids.append((p["id"], v["id"] if isinstance(v, dict) else v))
    variations = []
    for parent, vid in var_ids:
        d, _ = get(f"/wp-json/wc/store/v1/products/{vid}", f"variation-{vid}.json", allow_404=True)
        if isinstance(d, dict) and "_error" not in d:
            d = slim_product(d)
            d["parent_id"] = parent
            variations.append(d)
    print("variations:", len(variations))
    write_split("variations", variations)

    # ---- categories
    cats = paged("/wp-json/wc/store/v1/products/categories", "categories")
    for c in cats:
        c.pop("_links", None)
    dump("categories.json", cats)
    print("categories:", len(cats))

    # ---- attributes + terms
    attrs, _ = get("/wp-json/wc/store/v1/products/attributes", "attributes.json")
    for a in attrs:
        a.pop("_links", None)
        terms = paged(f"/wp-json/wc/store/v1/products/attributes/{a['id']}/terms", f"attr-{a['id']}-terms") or []
        for t in terms:
            t.pop("_links", None)
        a["terms"] = terms
    dump("attributes.json", attrs)
    print("attributes:", len(attrs))

    # ---- tags
    tags = paged("/wp-json/wc/store/v1/products/tags", "tags", allow_404=True) or []
    for t in tags:
        t.pop("_links", None)
    dump("tags.json", tags)
    print("tags:", len(tags))

    # ---- brands (several possible endpoints)
    brands_out = {}
    for ep, key in [("/wp-json/wc/store/v1/products/brands", "store_brands"),
                    ("/wp-json/wp/v2/product_brand", "wp_product_brand"),
                    ("/wp-json/wp/v2/pwb-brand", "wp_pwb_brand"),
                    ("/wp-json/wp/v2/yith_product_brand", "wp_yith_brand"),
                    ("/wp-json/wp/v2/pa_brand", "wp_pa_brand")]:
        r = paged(ep, "brands-" + key, allow_404=True)
        if r:
            for t in r:
                t.pop("_links", None)
            brands_out[key] = r
    # brand values derived from local product attribute "Brand"
    derived = {}
    for p in uniq:
        for a in p.get("attributes") or []:
            if a.get("name", "").strip().lower() in ("brand", "marca", "producător", "producator"):
                for t in a.get("terms") or []:
                    nm = t["name"].strip()
                    derived[nm] = derived.get(nm, 0) + 1
        for b in p.get("brands") or []:
            nm = b.get("name", "").strip()
            if nm:
                brands_out.setdefault("_from_products_brands_field", {})
                brands_out["_from_products_brands_field"][nm] = brands_out["_from_products_brands_field"].get(nm, 0) + 1
    brands_out["_from_local_attribute_Brand"] = [{"name": k, "count": v} for k, v in sorted(derived.items(), key=lambda x: x[0].lower())]
    dump("brands.json", brands_out)
    print("brands derived:", len(derived), "endpoints:", [k for k in brands_out])

    # ---- pages & posts
    for typ in ["pages", "posts"]:
        items = paged(f"/wp-json/wp/v2/{typ}?_embed=wp:featuredmedia", "wp-" + typ, allow_404=True) or []
        res = []
        for it in items:
            fm = None
            try:
                fm = it["_embedded"]["wp:featuredmedia"][0].get("source_url")
            except Exception:
                pass
            res.append({
                "id": it.get("id"), "type": it.get("type"), "status": it.get("status"),
                "title": it.get("title", {}).get("rendered"), "slug": it.get("slug"),
                "link": it.get("link"), "parent": it.get("parent"), "menu_order": it.get("menu_order"),
                "date": it.get("date"), "modified": it.get("modified"),
                "template": it.get("template"),
                "excerpt": it.get("excerpt", {}).get("rendered"),
                "content": it.get("content", {}).get("rendered"),
                "featured_image": fm,
                "categories": it.get("categories"), "tags": it.get("tags"),
            })
        dump(f"{typ}.json", res, PAGES_OUT)
        print(typ, len(res))


if __name__ == "__main__":
    main()
