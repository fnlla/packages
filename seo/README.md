**FNLLA/SEO**

SEO helpers for fnlla (finella) (meta tags, OpenGraph, JSON-LD).

**INSTALLATION**
```bash
composer require fnlla/seo
```
The package registers `SeoServiceProvider` via auto-discovery.

**USAGE**
```php
use Fnlla\\Seo\SeoManager;

$seo = $app->make(SeoManager::class);
$seo->title('TechAyo')
    ->description('AI, web development, IT support, consulting')
    ->canonical('https://techayo.co.uk')
    ->property('og:type', 'website')
    ->property('og:title', 'TechAyo')
    ->jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'TechAyo',
    ]);
```

Render in your layout:
```php
<?= $seo->render(); ?>
```

**CONFIGURATION**
`config/seo/seo.php` (example):
```php
return [
    'defaults' => [
        'title' => env('SEO_DEFAULT_TITLE', ''),
        'description' => env('SEO_DEFAULT_DESCRIPTION', ''),
        'canonical' => env('SEO_CANONICAL', env('APP_URL', '')),
        'properties' => [
            'og:type' => env('SEO_OG_TYPE', 'website'),
        ],
    ],
];
```

**TESTING**
```bash
php tests/smoke.php
```
