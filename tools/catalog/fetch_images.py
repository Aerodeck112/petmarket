#!/usr/bin/env python3
"""Descarca imaginile produselor de pe site-ul vechi si le salveaza WebP (1000 px + miniatura -400), cu aceeasi cale ca in
wp-content/uploads. Reluabil (sare peste ce exista). Rulare: python3 -I tools/catalog/fetch_images.py lista-url.txt folder-iesire
Rezultatul se arhiveaza ca petmarket/public/uploads/products/wp/<cale>.webp (vezi docs/IMPORT.md, Pachetul de imagini)."""
import io, os, sys, time, threading, urllib.parse, queue
import urllib.request
from PIL import Image
urls, out = sys.argv[1], sys.argv[2]
todo = [u.strip() for u in open(urls) if u.strip()]
q = queue.Queue()
for u in todo: q.put(u)
lock = threading.Lock(); stats = {'ok': 0, 'skip': 0, 'fail': 0}; pause_until = [0]
def rel(u):
    p = urllib.parse.unquote(urllib.parse.urlparse(u).path)
    return p.split('/wp-content/uploads/', 1)[1]
def save(img, max_side, target):
    w, h = img.size; s = min(1, max_side / max(w, h))
    im = img.resize((max(1, round(w * s)), max(1, round(h * s))), Image.LANCZOS) if s < 1 else img
    os.makedirs(os.path.dirname(target), exist_ok=True)
    im.save(target + '.tmp', 'WEBP', quality=82, method=4); os.replace(target + '.tmp', target)
def flatten(img):
    if img.mode in ('RGBA', 'LA', 'P'):
        img = img.convert('RGBA'); bg = Image.new('RGB', img.size, (255, 255, 255)); bg.paste(img, mask=img.split()[-1]); return bg
    return img.convert('RGB')
def worker():
    while True:
        try: u = q.get_nowait()
        except queue.Empty: return
        r = rel(u); big = os.path.join(out, r + '.webp'); small = os.path.join(out, r + '-400.webp')
        if os.path.exists(big) and os.path.exists(small):
            with lock: stats['skip'] += 1
            continue
        for attempt in range(6):
            while time.time() < pause_until[0]: time.sleep(5)
            try:
                req = urllib.request.Request(u, headers={'User-Agent': 'Mozilla/5.0 (compatible; PetMarket-Import/1.0)', 'Accept': 'image/*'})
                with urllib.request.urlopen(req, timeout=40) as resp:
                    ct = resp.headers.get('Content-Type', ''); body = resp.read()
                if 'text/html' in ct: raise urllib.error.HTTPError(u, 429, 'html', None, None)
                img = flatten(Image.open(io.BytesIO(body)))
                save(img, 1000, big); save(img, 400, small)
                with lock: stats['ok'] += 1
                break
            except urllib.error.HTTPError as e:
                if e.code in (403, 429, 503):
                    with lock: pause_until[0] = time.time() + 90
                    print('pauza anti-bot', e.code, flush=True); continue
                if e.code == 404:
                    with lock: stats['fail'] += 1
                    print('404', u, flush=True); break
                time.sleep(5)
            except Exception as e:
                print('err', u, e, flush=True); time.sleep(5)
        else:
            with lock: stats['fail'] += 1
            print('FAIL', u, flush=True)
        time.sleep(0.15)
def report():
    while any(t.is_alive() for t in ts):
        time.sleep(60); print('progres', stats, 'ramase', q.qsize(), flush=True)
ts = [threading.Thread(target=worker) for _ in range(4)]
for t in ts: t.start()
threading.Thread(target=report, daemon=True).start()
for t in ts: t.join()
print('GATA', stats, flush=True)
