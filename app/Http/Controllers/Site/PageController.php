<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function home(Seo $seo)
    {
        $page = Page::query()->where('slug', 'home')->first();
        $seo->title = $page?->seo_title ?: Settings::get('shop.name').' '.Settings::get('shop.byline').' – Hrană și accesorii pentru câini și pisici';
        if ($page?->seo_description) {
            $seo->description = $page->seo_description;
        }
        $seo->schema(Seo::storeSchema());
        $seo->schema(['@type' => 'WebSite', 'name' => Settings::get('shop.name'), 'url' => Seo::canonicalUrl('/'),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => site_url('/cautare/').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']]);

        $with = fn ($q) => $q->published()->with(['mainImage', 'brand']);
        $data = Cache::remember('home.products', 300, fn () => [
            'deals' => $with(Product::query())->inStock()->where('on_sale', true)->orderByDesc('sold_count')->limit(12)->get(),
            'bestsellers' => $with(Product::query())->inStock()->orderByDesc('sold_count')->orderByDesc('rating_avg')->limit(12)->get(),
            'newest' => $with(Product::query())->inStock()->orderByDesc('published_at')->limit(12)->get(),
            'featured' => $with(Product::query())->inStock()->where('is_featured', true)->limit(12)->get(),
        ]);

        return view('site.home', $data + [
            'seo' => $seo, 'page' => $page, 'b' => $page?->blocks ?? [],
            'slides' => Banner::query()->live('hero')->get(),
            'promos' => Banner::query()->live('promo')->limit(3)->get(),
            'categories' => Category::query()->visible()->whereNull('parent_id')->orderByDesc('is_featured')->orderBy('sort')->orderBy('name')->limit(12)->get(),
            'brands' => Brand::query()->whereNotNull('logo')->orderByDesc('is_featured')->orderBy('sort')->limit(18)->get(),
            'posts' => Post::published()->latest('published_at')->limit(3)->get(),
        ]);
    }

    /** Pagini si articole cu slug de prim nivel, ca in WordPress (/contact/, /termeni-si-conditii/, /articol/). */
    public function show(Request $request, Seo $seo, string $slug)
    {
        if ($post = Post::published()->where('slug', $slug)->first()) {
            $seo->page($post->seo_title ?: $post->title, $post->seo_description ?: $post->excerpt, $post->image)
                ->crumbs([['Acasă', '/'], ['Blog', '/blog/'], [$post->title, $post->path()]])
                ->schema(['@type' => 'Article', 'headline' => $post->title, 'datePublished' => $post->published_at?->toIso8601String(),
                    'dateModified' => $post->updated_at?->toIso8601String(), 'author' => ['@type' => 'Organization', 'name' => Settings::get('shop.name')],
                    'image' => $post->image ? asset($post->image) : null, 'mainEntityOfPage' => Seo::canonicalUrl($post->path())]);
            $seo->type = 'article';

            return view('site.post', ['seo' => $seo, 'post' => $post,
                'more' => Post::published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get()]);
        }

        $page = Page::query()->where('slug', $slug)->first();
        $preview = $page && ! $page->isPublished() && $request->user()?->can('cms.manage');
        abort_unless($page && ($page->isPublished() || $preview), 404);

        $seo->page($page->seo_title ?: $page->title, $page->seo_description ?: ($page->lead ?: $page->body), $page->og_image ?: $page->hero_image)
            ->crumbs([['Acasă', '/'], [$page->title, $page->path()]]);
        $seo->noindex = $page->noindex || $preview;

        $data = ['seo' => $seo, 'page' => $page, 'b' => $page->blocks ?? [], 'preview' => $preview];

        return match ($page->template) {
            'faq' => view('site.faq', $data + ['faqs' => tap(Faq::query()->where('is_published', true)->orderBy('sort')->get(), function ($faqs) use ($seo) {
                if ($faqs->isNotEmpty()) {
                    $seo->schema(['@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($f->answer))]])->all()]);
                }
            })]),
            'blog' => view('site.blog', $data + ['posts' => Post::published()->latest('published_at')->paginate(9)]),
            'contact' => view('site.contact', $data),
            'legal' => view('site.legal', $data),
            default => view('site.page', $data),
        };
    }
}
