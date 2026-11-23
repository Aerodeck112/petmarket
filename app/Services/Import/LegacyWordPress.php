<?php

namespace App\Services\Import;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Magazinul WordPress vechi aflat pe acelasi cont cPanel (de exemplu redenumit in public_html_vechi la
 * comutare). Gaseste singur directorul de imagini si datele bazei din wp-config.php, iar sincronizarea
 * aduce preturile, reducerile, stocul exact si starea produselor direct din baza WooCommerce.
 */
class LegacyWordPress
{
    public const CANDIDATES = ['public_html_vechi', 'public_html_old', 'public_html.old', 'public_html_wp', 'public_html'];

    /** Directorul WordPress vechi (cel care contine wp-config.php), cautat langa aplicatie. */
    public static function root(): ?string
    {
        $configured = config('petmarket.legacy_uploads_path');
        if ($configured && is_file(dirname(rtrim($configured, '/'), 2).'/wp-config.php')) {
            return dirname(rtrim($configured, '/'), 2);
        }
        $home = dirname(base_path());
        foreach (self::CANDIDATES as $dir) {
            $path = $home.'/'.$dir;
            if (is_file($path.'/wp-config.php') && realpath($path) !== realpath(public_path())) {
                return $path;
            }
        }

        return null;
    }

    public static function uploadsPath(): ?string
    {
        if ($configured = config('petmarket.legacy_uploads_path')) {
            return is_dir($configured) ? rtrim($configured, '/') : null;
        }
        $root = self::root();

        return $root && is_dir($root.'/wp-content/uploads') ? $root.'/wp-content/uploads' : null;
    }

    /** Datele de conectare din wp-config.php (fara a executa fisierul). @return array{host:string,port:int,database:string,username:string,password:string,prefix:string}|null */
    public static function credentials(?string $root = null): ?array
    {
        $root ??= self::root();
        if (! $root || ! is_file($root.'/wp-config.php')) {
            return null;
        }
        $src = (string) file_get_contents($root.'/wp-config.php');
        $define = fn (string $name) => preg_match("/define\\(\\s*['\"]{$name}['\"]\\s*,\\s*(['\"])(.*?)\\1\\s*\\)/s", $src, $m) ? stripcslashes($m[2]) : null;
        $prefix = preg_match('/\\$table_prefix\\s*=\\s*([\'"])(.*?)\\1/', $src, $m) ? $m[2] : 'wp_';
        $host = $define('DB_HOST') ?? 'localhost';
        $port = 3306;
        if (preg_match('/^(.*):(\\d+)$/', $host, $m)) {
            [$host, $port] = [$m[1], (int) $m[2]];
        }
        if (! $define('DB_NAME') || ! $define('DB_USER')) {
            return null;
        }

        return ['host' => $host, 'port' => $port, 'database' => $define('DB_NAME'), 'username' => $define('DB_USER'), 'password' => (string) $define('DB_PASSWORD'), 'prefix' => $prefix];
    }

    public static function connect(array $c): PDO
    {
        return new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']), $c['username'], $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
    }

    /**
     * Actualizeaza preturile si stocul produselor importate, dupa ID-ul WooCommerce.
     *
     * @return array{updated:int,missing:int,unpublished:int}
     */
    public function syncPricesAndStock(?PDO $pdo = null, ?string $prefix = null): array
    {
        if (! $pdo) {
            $c = self::credentials() ?? throw new RuntimeException('Nu am găsit wp-config.php al magazinului vechi lângă aplicație.');
            $pdo = self::connect($c);
            $prefix = $c['prefix'];
        }
        $prefix ??= 'wp_';
        $keys = ['_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to', '_stock', '_stock_status', '_manage_stock', '_backorders', '_sku'];
        $rows = $pdo->query("SELECT p.ID, p.post_status, m.meta_key, m.meta_value FROM {$prefix}posts p LEFT JOIN {$prefix}postmeta m ON m.post_id = p.ID AND m.meta_key IN ('".implode("','", $keys)."') WHERE p.post_type = 'product'")
            ->fetchAll(PDO::FETCH_ASSOC);
        $wp = [];
        foreach ($rows as $r) {
            $wp[(int) $r['ID']]['status'] = $r['post_status'];
            if ($r['meta_key']) {
                $wp[(int) $r['ID']][$r['meta_key']] = $r['meta_value'];
            }
        }

        $stats = ['updated' => 0, 'missing' => 0, 'unpublished' => 0];
        $known = Product::withTrashed()->whereNotNull('wc_product_id')->pluck('id', 'wc_product_id');
        foreach (array_chunk($wp, 300, true) as $chunk) {
            DB::transaction(function () use ($chunk, $known, &$stats) {
                foreach ($chunk as $id => $m) {
                    if (! isset($known[$id])) {
                        $stats['missing'] += $m['status'] === 'publish' ? 1 : 0;

                        continue;
                    }
                    $product = Product::withTrashed()->find($known[$id]);
                    $minor = fn ($v) => $v === null || $v === '' ? null : (int) round(((float) str_replace(',', '.', (string) $v)) * 100);
                    $manage = ($m['_manage_stock'] ?? 'no') === 'yes' && ($m['_stock'] ?? '') !== '';
                    $before = $product->stock_qty;
                    $product->fill([
                        'regular_price' => $minor($m['_regular_price'] ?? null) ?? $product->regular_price,
                        'sale_price' => $minor($m['_sale_price'] ?? null),
                        'sale_starts_at' => ! empty($m['_sale_price_dates_from']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $m['_sale_price_dates_from']) : null,
                        'sale_ends_at' => ! empty($m['_sale_price_dates_to']) ? \Illuminate\Support\Carbon::createFromTimestamp((int) $m['_sale_price_dates_to']) : null,
                        'manage_stock' => $manage, 'stock_qty' => $manage ? (int) $m['_stock'] : null,
                        'allow_backorders' => in_array($m['_backorders'] ?? 'no', ['yes', 'notify'], true),
                        'status' => $m['status'] === 'publish' ? 'published' : ($m['status'] === 'private' ? 'private' : 'draft'),
                    ]);
                    if (! $manage) {
                        $product->stock_status = StockStatus::tryFrom((string) ($m['_stock_status'] ?? 'instock')) ?? StockStatus::InStock;
                    }
                    $stats['unpublished'] += $m['status'] === 'publish' ? 0 : 1;
                    if ($product->isDirty()) {
                        $product->save();
                        $stats['updated']++;
                        if ($manage && $before !== $product->stock_qty) {
                            StockMovement::query()->create(['product_id' => $product->id, 'change' => (int) $product->stock_qty - (int) $before,
                                'stock_after' => $product->stock_qty, 'reason' => 'import', 'note' => 'Sincronizare din WooCommerce', 'user_id' => auth()->id()]);
                        }
                    }
                }
            });
        }

        return $stats;
    }
}
