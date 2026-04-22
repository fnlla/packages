**FNLLA/SEARCH**

Search adapter for fnlla (finella) with a lightweight HTTP Meilisearch driver (no external SDK required).

**INSTALLATION**
```bash
composer require fnlla/search
```

**CONFIGURATION**
Create `config/search/search.php` and set `.env`:
```
SEARCH_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
```

**USAGE**
```php
use Fnlla\\Search\SearchManager;

$search = app()->make(SearchManager::class);
$client = $search->client();
$results = $client->search('products', 'phone');
```

Optional index operations:
```php
$index = $client->index('products');
$index->addDocuments([
    ['id' => 1, 'name' => 'Phone X'],
]);
```
