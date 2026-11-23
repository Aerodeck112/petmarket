<?php

namespace App\Console\Commands;

use App\Services\Import\CatalogImporter;
use App\Services\Import\ImageDownloader;
use Illuminate\Console\Command;

class ImportCatalog extends Command
{
    protected $signature = 'petmarket:import {--products-only : Sare peste categorii, branduri și pagini} {--images : Copiază și imaginile produselor} {--image-batch=0 : Doar un lot de N imagini (pentru cron)} {--wp-sync : Aduce prețurile și stocul din baza WooCommerce de pe același server}';

    protected $description = 'Importă catalogul magazinului WooCommerce (categorii, branduri, produse, pagini) și, opțional, imaginile.';

    public function handle(CatalogImporter $importer, ImageDownloader $images): int
    {
        if ($batch = (int) $this->option('image-batch')) {
            [$ok, $failed, $blocked] = $images->run($batch);
            $this->line("Imagini copiate: {$ok}, eșuate: {$failed}, rămase: ".$images->pending().($blocked ? ' (site-ul vechi a cerut o pauză; continuă la următoarea rulare)' : ''));

            return self::SUCCESS;
        }

        if ($this->option('wp-sync')) {
            $stats = app(\App\Services\Import\LegacyWordPress::class)->syncPricesAndStock();
            $this->info('Din WooCommerce: '.json_encode($stats, JSON_UNESCAPED_UNICODE));

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
        $importer->refreshCounts();
        $this->info("Produse importate: {$total}");

        if ($this->option('images')) {
            $this->info('Copiez imaginile ('.$images->pending().')…');
            while ($images->pending() > 0) {
                [$ok, $failed, $blocked] = $images->run(50);
                $this->line("  +{$ok} (eșuate {$failed}), rămase ".$images->pending());
                if ($blocked) {
                    $this->warn('Site-ul vechi a cerut o pauză (protecție anti-bot). Aștept 60 de secunde…');
                    sleep(60);
                } elseif ($ok === 0 && $failed === 0) {
                    break;
                }
            }
        }

        return self::SUCCESS;
    }
}
