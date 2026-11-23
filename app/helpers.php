<?php

if (! function_exists('site_url')) {
    /**
     * URL public absolut care pastreaza slash-ul final (url() din Laravel il elimina).
     * WooCommerce foloseste peste tot adrese cu slash final, iar acestea raman canonice.
     */
    function site_url(string $path = '/'): string
    {
        $root = app()->runningInConsole() && ! app()->runningUnitTests() ? rtrim((string) config('app.url'), '/') : request()->root();

        return $root.'/'.ltrim($path, '/');
    }
}

if (! function_exists('money')) {
    /** Suma in bani (intreg) afisata ca in magazinul vechi: 1.234,50 lei. */
    function money(?int $minor, bool $currency = true): string
    {
        $value = number_format(($minor ?? 0) / 100, 2, ',', '.');

        return $currency ? $value.' lei' : $value;
    }
}
