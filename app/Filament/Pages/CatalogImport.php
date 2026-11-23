<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Import\CatalogImporter;
use App\Services\Import\ImageDownloader;
use App\Support\Audit;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Importul catalogului din magazinul WooCommerce, din browser: fiecare pas ruleaza pe loturi mici
 * (o cerere Livewire pe lot), ca sa nu depaseasca limita de timp a gazduirii.
 */
class CatalogImport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Import catalog';

    protected static ?string $title = 'Import catalog din pet-market.ro';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.catalog-import';

    public ?string $running = null;

    public int $offset = 0;

    public array $log = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::IMPORT_RUN);
    }

    public function taxonomies(): void
    {
        $stats = app(CatalogImporter::class)->importTaxonomies();
        $this->log[] = now()->format('H:i:s').' Categorii, branduri, etichete, pagini: '.json_encode($stats, JSON_UNESCAPED_UNICODE);
        Audit::log('import.taxonomies', null, 'Import categorii și branduri');
        Notification::make()->title('Categoriile, brandurile și paginile au fost importate')->success()->send();
    }

    public function startProducts(): void
    {
        if (! Category::query()->exists()) {
            $this->taxonomies();
        }
        $this->running = 'products';
        $this->offset = 0;
        $this->log[] = now()->format('H:i:s').' Încep importul produselor…';
    }

    public function startImages(): void
    {
        $this->running = 'images';
        $this->log[] = now()->format('H:i:s').' Încep copierea imaginilor…';
    }

    public function retryFailed(): void
    {
        $n = app(ImageDownloader::class)->retryFailed();
        $this->log[] = now()->format('H:i:s')." {$n} imagini eșuate au fost puse din nou în coadă.";
    }

    public function stop(): void
    {
        $this->running = null;
        $this->log[] = now()->format('H:i:s').' Oprit.';
    }

    /** Apelat de wire:poll cat timp un pas ruleaza: proceseaza un lot. */
    public function tick(): void
    {
        @set_time_limit(120);
        if ($this->running === 'products') {
            $importer = app(CatalogImporter::class);
            $done = $importer->importProducts($this->offset, 300);
            $this->offset += $done;
            if ($done === 0) {
                $importer->refreshCounts();
                $this->running = null;
                $this->log[] = now()->format('H:i:s').' Produse importate: '.$this->offset.'.';
                Audit::log('import.products', null, 'Import produse: '.$this->offset);
                Notification::make()->title('Produsele au fost importate')->success()->send();
            }
        } elseif ($this->running === 'images') {
            [$ok, $failed, $blocked] = app(ImageDownloader::class)->run(20);
            if ($blocked) {
                $this->running = null;
                $this->log[] = now()->format('H:i:s')." Site-ul vechi a cerut o pauză după {$ok} imagini (protecție anti-bot). Reia peste câteva minute sau folosește copierea de pe disc (INSTALL_CPANEL.md §7).";

                return;
            }
            if ($failed) {
                $this->log[] = now()->format('H:i:s')." {$failed} imagini nu au putut fi copiate (rămân pe adresa veche).";
            }
            if ($ok + $failed === 0) {
                $this->running = null;
                $this->log[] = now()->format('H:i:s').' Imaginile au fost copiate.';
                Notification::make()->title('Imaginile au fost copiate pe server')->success()->send();
            }
        }
        $this->log = array_slice($this->log, -40);
    }

    protected function getViewData(): array
    {
        $importer = app(CatalogImporter::class);
        $images = ProductImage::query();

        return [
            'available' => $importer->available(),
            'sourceTotal' => $importer->available() ? $importer->totalProducts() : 0,
            'products' => Product::query()->whereNotNull('wc_product_id')->count(),
            'categories' => Category::query()->count(),
            'imgTotal' => (clone $images)->count(),
            'imgLocal' => (clone $images)->whereNotNull('path')->count(),
            'imgFailed' => (clone $images)->whereNull('path')->where('width', 0)->count(),
            'localUploads' => (bool) config('petmarket.legacy_uploads_path'),
        ];
    }
}
