<?php

namespace App\Console\Commands;

use App\Services\Import\CatalogImporter;
use App\Services\Import\ImageDownloader;
use Illuminate\Console\Command;

class ImportCatalog extends Command
{
    protected $signature = 'petmarket:import {--products-only : Sare peste categorii, branduri și pagini} {--images : Copiază și imaginile produselor} {--image-batch=0 : Doar un lot de N imagini (pentru cron)}';

    protected $description = 'Importă catalogul magazinului WooCommerce (categorii, branduri, produse, pagini) și, opțional, imaginile.';

    public function handle(CatalogImporter $importer, ImageDownloader $images): int
    {
        if ($batch = (int) $this->option('image-batch')) {
            [$ok, $failed] = $images->run($batch);
            $this->line("Imagini copiate: {$ok}, eșuate: {$failed}, rămase: ".$images->pending());

            return self::SUCCESS;
        }

        if (! $importer->available()) {
            $this->error('Lipsesc fișierele catalogului din database/seeders/data/catalog.');

            return self::FAILURE;
        }
        if (! $this->option('products-only')) {
            $stats = $importer->importTaxonomies();
            $this->info('Categorii, branduri, etichete, pagini: '.json_encode($stats, JSON_UNESCAPED_UNICODE));
        }
        $total = $importer->totalProducts();
        $bar = $this->output->createProgressBar($total);
        for ($offset = 0; $offset < $total; $offset += 250) {
            $bar->advance($importer->importProducts($offset, 250));
        }
        $bar->finish();
        $this->newLine();
        \App\Models\Category::query()->each(fn ($c) => $c->updateQuietly(['product_count' => $c->products()->count()]));
        $this->info("Produse importate: {$total}");

        if ($this->option('images')) {
            $this->info('Copiez imaginile ('.$images->pending().')…');
            while ($images->pending() > 0) {
                [$ok, $failed] = $images->run(50);
                $this->line("  +{$ok} (eșuate {$failed}), rămase ".$images->pending());
                if ($ok === 0 && $failed === 0) {
                    break;
                }
            }
        }

        return self::SUCCESS;
    }
}
