<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Import\LegacyWordPress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

class LegacyWordPressTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_wp_config_without_executing_it(): void
    {
        $dir = sys_get_temp_dir().'/pm-wp-'.uniqid();
        mkdir($dir.'/wp-content/uploads', 0777, true);
        file_put_contents($dir.'/wp-config.php', "<?php\ndefine( 'DB_NAME', 'cpuser_wp' );\ndefine('DB_USER', \"cpuser_wpu\");\ndefine( 'DB_PASSWORD', 'p@ss\\'w0rd' );\ndefine( 'DB_HOST', 'localhost:3307' );\n\$table_prefix = 'pm7_';\nexit('nu se executa');\n");

        $c = LegacyWordPress::credentials($dir);
        $this->assertSame(['host' => 'localhost', 'port' => 3307, 'database' => 'cpuser_wp', 'username' => 'cpuser_wpu', 'password' => "p@ss'w0rd", 'prefix' => 'pm7_'], $c);

        config(['petmarket.legacy_uploads_path' => $dir.'/wp-content/uploads']);
        $this->assertSame($dir, LegacyWordPress::root());
        $this->assertSame($dir.'/wp-content/uploads', LegacyWordPress::uploadsPath());
    }

    public function test_syncs_prices_stock_and_status_from_woocommerce_tables(): void
    {
        $a = $this->product(['wc_product_id' => 101, 'regular_price' => 5000, 'manage_stock' => true, 'stock_qty' => 50]);
        $b = $this->product(['wc_product_id' => 102, 'regular_price' => 9000, 'manage_stock' => false, 'stock_qty' => null]);

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE wp_posts (ID INTEGER, post_type TEXT, post_status TEXT)');
        $pdo->exec('CREATE TABLE wp_postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT)');
        $pdo->exec("INSERT INTO wp_posts VALUES (101,'product','publish'),(102,'product','draft'),(103,'product','publish')");
        foreach ([[101, '_regular_price', '55.90'], [101, '_sale_price', '49.90'], [101, '_manage_stock', 'yes'], [101, '_stock', '137'],
            [102, '_regular_price', '90'], [102, '_manage_stock', 'no'], [102, '_stock_status', 'outofstock']] as [$id, $k, $v]) {
            $pdo->prepare('INSERT INTO wp_postmeta VALUES (?,?,?)')->execute([$id, $k, $v]);
        }

        $stats = app(LegacyWordPress::class)->syncPricesAndStock($pdo, 'wp_');

        $this->assertSame(['updated' => 2, 'missing' => 1, 'unpublished' => 1], $stats);
        $a->refresh();
        $this->assertSame([5590, 4990, 4990, true, 137], [$a->regular_price, $a->sale_price, $a->price, $a->on_sale, $a->stock_qty]);
        $b->refresh();
        $this->assertSame(['draft', 'outofstock'], [$b->status, $b->stock_status->value]);
        $this->assertSame(1, \App\Models\StockMovement::query()->where('reason', 'import')->count());
        $this->assertSame(2, Product::query()->count());
    }
}
