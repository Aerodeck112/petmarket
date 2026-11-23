<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\NewsletterController;
use App\Http\Controllers\Site\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap']);
Route::get('/sitemap-{type}-{page}.xml', [SitemapController::class, 'part'])->where(['type' => 'produse|categorii|pagini', 'page' => '[0-9]+']);
Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/feed/google-merchant.xml', [FeedController::class, 'google'])->middleware('throttle:30,1');

// Catalog, cu adresele WooCommerce pastrate (SEO).
Route::get('/magazin', [CatalogController::class, 'shop'])->name('shop');
Route::get('/categorie-produs/{path}', [CatalogController::class, 'category'])->where('path', '[a-z0-9\-/%]+')->name('category');
Route::get('/produs/{slug}', [ProductController::class, 'show'])->where('slug', '[A-Za-z0-9\-%]+')->name('product');
Route::post('/produs/{slug}/recenzie', [ProductController::class, 'review'])->middleware(['auth', 'throttle:forms']);
Route::get('/branduri', [CatalogController::class, 'brands'])->name('brands');
Route::get('/brand/{slug}', [CatalogController::class, 'brand'])->name('brand');
Route::get('/eticheta-produs/{slug}', [CatalogController::class, 'tag'])->name('tag');
Route::get('/cautare', [CatalogController::class, 'search'])->name('search');
Route::get('/cautare/sugestii', [CatalogController::class, 'suggest'])->middleware('throttle:90,1');

// Cos si finalizare comanda.
Route::get('/cos', [CartController::class, 'show'])->name('cart');
Route::post('/cos/adauga', [CartController::class, 'add'])->middleware('throttle:60,1');
Route::post('/cos/actualizeaza', [CartController::class, 'update'])->middleware('throttle:60,1');
Route::post('/cos/sterge/{item}', [CartController::class, 'remove'])->whereNumber('item')->middleware('throttle:60,1');
Route::post('/cos/cupon', [CartController::class, 'coupon'])->middleware('throttle:20,1');
Route::get('/finalizare', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/finalizare/calcul', [CheckoutController::class, 'preview'])->middleware('throttle:60,1');
Route::post('/finalizare', [CheckoutController::class, 'place'])->middleware('throttle:checkout');
Route::get('/comanda/{token}', [CheckoutController::class, 'received'])->where('token', '[A-Za-z0-9]{40,64}')->name('order.received');

Route::post('/favorite', [AccountController::class, 'toggleWishlist'])->middleware('throttle:60,1');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:forms');
Route::get('/newsletter/dezabonare/{token}', [NewsletterController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{40}');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:forms');

// Contul de client (adresele WooCommerce: /contul-meu/...).
Route::get('/contul-meu/autentificare', [AuthController::class, 'access'])->name('login');
Route::post('/contul-meu/autentificare', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/contul-meu/inregistrare', [AuthController::class, 'register'])->middleware('throttle:forms');
Route::post('/contul-meu/iesire', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/contul-meu/parola-pierduta', [AuthController::class, 'forgot'])->name('password.request');
Route::post('/contul-meu/parola-pierduta', [AuthController::class, 'sendReset'])->middleware('throttle:forms')->name('password.email');
Route::get('/contul-meu/parola-noua/{token}', [AuthController::class, 'reset'])->name('password.reset');
Route::post('/contul-meu/parola-noua', [AuthController::class, 'updatePassword'])->middleware('throttle:forms')->name('password.update');

Route::middleware('auth')->prefix('contul-meu')->controller(AccountController::class)->group(function () {
    Route::get('/', 'dashboard')->name('account');
    Route::get('/comenzi', 'orders')->name('account.orders');
    Route::get('/comenzi/{number}', 'order')->name('account.order');
    Route::post('/comenzi/{number}/din-nou', 'reorder')->middleware('throttle:forms');
    Route::get('/adrese', 'addresses')->name('account.addresses');
    Route::post('/adrese', 'saveAddress')->middleware('throttle:forms');
    Route::post('/adrese/{address}/sterge', 'deleteAddress')->whereNumber('address');
    Route::get('/detalii', 'details')->name('account.details');
    Route::post('/detalii', 'saveDetails')->middleware('throttle:forms');
    Route::get('/favorite', 'wishlist')->name('account.wishlist');
});

// Documente din admin (factura proforma / bon de livrare), autorizate pe permisiune.
Route::middleware('auth')->prefix('admin/documente')->group(function () {
    Route::get('/comanda/{order}/{kind}', [\App\Http\Controllers\Admin\OrderDocumentController::class, 'show'])->whereIn('kind', ['proforma', 'aviz'])->name('admin.order.document');
});
