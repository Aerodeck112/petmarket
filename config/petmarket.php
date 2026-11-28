<?php

return [
    // Versiunea platformei, afisata in admin si verificata de sistemul de actualizari.
    'version' => '0.1.3',

    // Cheia publica Ed25519 (base64) cu care sunt semnate pachetele de actualizare.
    'update_public_key' => env('PETMARKET_UPDATE_PUBLIC_KEY', 'lfFS4typhQKcrpYvx1XPAg2X/JgvTyxdDE017XTxs8A='),

    // Magazinul vechi (WooCommerce), sursa importului de catalog si a imaginilor.
    'legacy_url' => env('PETMARKET_LEGACY_URL', 'https://pet-market.ro'),

    // Optional: directorul wp-content/uploads al magazinului vechi pe acelasi server (de exemplu
    // /home/UTILIZATOR/public_html/wp-content/uploads). Imaginile se copiaza de pe disc, fara descarcare.
    'legacy_uploads_path' => env('PETMARKET_LEGACY_UPLOADS_PATH'),

    // In afara productiei aplicatia se considera instalata (dezvoltare, teste automate).
    'assume_installed' => env('APP_ENV', 'production') !== 'production',
    'install_lock' => null,
    'install_env_path' => null,

    // Folosit doar de testele automate, pentru a aplica o actualizare intr-un director de proba.
    'update_base_path' => null,
];
