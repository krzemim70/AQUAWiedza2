<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Record;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function meta(string $html, string $pattern): string
{
    preg_match($pattern, $html, $m);

    return $m[1] ?? '';
}

it('gives every public page a unique title and description', function () {
    $urls = ['/', '/dobor', '/doradca', '/regulamin', '/prywatnosc', '/kontakt', '/dla-sklepow', '/katalog/fauna/FN0013', '/katalog/flora/'.Record::where('area', 'flora')->value('legacy_id')];
    $titles = $descriptions = [];
    foreach ($urls as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        $titles[] = meta($html, '#<title>(.*?)</title>#');
        $descriptions[] = meta($html, '#<meta name="description" content="(.*?)">#');
        expect($html)->not->toContain('noindex')->toContain('<meta property="og:locale" content="pl_PL">');
    }
    expect(array_filter($titles))->toHaveCount(count($urls))->and(array_unique($titles))->toHaveCount(count($urls))
        ->and(array_filter($descriptions))->toHaveCount(count($urls))->and(array_unique($descriptions))->toHaveCount(count($urls));
});

it('uses a canonical URL without query parameters and Open Graph tags', function () {
    $html = $this->get('/katalog/fauna/FN0013?wroc=%2Fdoradca&s=1')->assertOk()->getContent();

    expect(meta($html, '#<link rel="canonical" href="(.*?)">#'))->toBe(url('/katalog/fauna/FN0013'))
        ->and(meta($html, '#og:url" content="(.*?)"#'))->toBe(url('/katalog/fauna/FN0013'))
        ->and(meta($html, '#og:image" content="(.*?)"#'))->toBe(asset('images/aikwarium-akwarium-1600.jpg'))
        ->and($html)->toContain('og:title')->toContain('og:description');
});

it('uses the verified product photo as og:image', function () {
    Record::where('legacy_id', 'FN0013')->firstOrFail()->photos()->create(['url' => 'https://img.example/ryba.jpg', 'verified' => true]);

    expect(meta($this->get('/katalog/fauna/FN0013')->getContent(), '#og:image" content="(.*?)"#'))->toBe('https://img.example/ryba.jpg');
});

it('marks private and technical pages noindex', function (string $url, bool $login) {
    $login && $this->actingAs(aquarist());
    $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('rel="canonical"', false);
})->with([
    ['/logowanie', false], ['/rejestracja', false], ['/haslo/reset', false],
    ['/koszyk', true], ['/zamowienia', true], ['/moje-konto', true], ['/moje-akwarium', true],
]);

it('noindexes the whole site and disallows crawling when indexing is off', function () {
    config(['seo.indexable' => false]);

    $this->get('/')->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('rel="canonical"', false)
        ->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap');
});

it('serves robots.txt with the sitemap when indexing is on', function () {
    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false)->assertDontSee("Disallow: /\n", false);
});

it('lists pages and products in a valid sitemap and caches it per active batch', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    $doc = simplexml_load_string($xml);

    expect($doc)->not->toBeFalse()
        ->and($xml)->toContain('<loc>'.url('/').'</loc>', '<loc>'.url('/dobor').'</loc>', '<loc>'.url('/doradca').'</loc>', '<loc>'.url('/regulamin').'</loc>', '<loc>'.url('/katalog/fauna/FN0013').'</loc>', '<lastmod>');

    // Klucz cache zawiera ID aktywnej partii, więc nowa aktywacja unieważnia mapę.
    expect(cache()->has('sitemap.'.app(Catalog::class)->activeBatchId()))->toBeTrue();
});

it('adds Organization and WebSite JSON-LD on the home page and BreadcrumbList on products', function () {
    foreach (['/' => ['Organization', 'WebSite'], '/katalog/fauna/FN0013' => ['BreadcrumbList']] as $url => $types) {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $this->get($url)->getContent(), $m);
        $json = json_decode($m[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        expect(json_encode($json))->toContain(...$types)->and($json['@context'])->toBe('https://schema.org');
    }
});

it('redirects old WordPress URLs with 301', function (string $from, string $to) {
    // Klient testowy obcina końcowy ukośnik, więc wołamy jądro bezpośrednio.
    $response = app(Kernel::class)->handle(Request::create($from));

    expect($response->getStatusCode())->toBe(301)->and($response->headers->get('Location'))->toEndWith($to);
})->with([
    ['/doradca/', '/doradca'],
    ['/moje-akwarium/', '/moje-akwarium'],
    ['/rejestracja/', '/rejestracja'],
    ['/wp-login.php', '/logowanie'],
    ['/dashboard/', '/moje-akwarium'],
    ['/redfield/', '/moje-akwarium/redfield'],
    ['/polityka-prywatnosci/', '/prywatnosc'],
    ['/oferty-w-twojej-okolicy/?aqs_db=FAUNA&rid=FN0013&src=doradca', '/katalog/fauna/FN0013'],
    ['/oferty-w-twojej-okolicy/?aqs_db=FAUNA&rid=NIE', '/doradca'],
    ['/oferty-w-twojej-okolicy/', '/doradca'],
]);
