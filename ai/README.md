**FNLLA/AI**

AI helpers for fnlla (finella) with a built-in OpenAI Responses API client.

**INSTALLATION**
```bash
composer require fnlla/ai
```
The package registers `AiServiceProvider` via auto-discovery.

**CONFIGURATION**
Create `config/ai/ai.php`:
```php
<?php

declare(strict_types=1);

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', 'gpt-5.4'),
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'realtime_model' => env('OPENAI_REALTIME_MODEL', 'gpt-realtime-1.5'),
    'realtime_voice' => env('OPENAI_REALTIME_VOICE', 'marin'),
    'realtime_instructions' => env('OPENAI_REALTIME_INSTRUCTIONS', ''),
    'organization' => env('OPENAI_ORG', ''),
    'project' => env('OPENAI_PROJECT', ''),
];
```

You can group AI config files under `config/ai/` for readability. fnlla (finella) maps
these to the existing underscore keys (for example `config/ai/rag.php` ->
`ai_rag`). Flat `config/ai_*.php` files are still supported.

**AI POLICY (GOVERNANCE)**
Create `config/ai/policy.php` to enforce safe defaults:
```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_POLICY_ENABLED', true),
    'default_temperature' => (float) env('AI_POLICY_DEFAULT_TEMPERATURE', 0.2),
    'max_temperature' => (float) env('AI_POLICY_MAX_TEMPERATURE', 1.0),
    'default_output_tokens' => (int) env('AI_POLICY_DEFAULT_OUTPUT_TOKENS', 800),
    'max_output_tokens' => (int) env('AI_POLICY_MAX_OUTPUT_TOKENS', 1200),
    'max_input_chars' => (int) env('AI_POLICY_MAX_INPUT_CHARS', 12000),
    'require_rag' => env('AI_POLICY_REQUIRE_RAG', false),
    'rag_min_sources' => (int) env('AI_POLICY_RAG_MIN_SOURCES', 1),
    'rag_min_score' => (float) env('AI_POLICY_RAG_MIN_SCORE', 0.2),
];
```

**REDACTION**
Create `config/ai/redaction.php` to mask sensitive data:
```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_REDACTION_ENABLED', true),
    'mask' => env('AI_REDACTION_MASK', '[REDACTED]'),
    'patterns' => [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i',
        '/\\bsk-[A-Za-z0-9]{16,}\\b/',
        '/\\b(?:api|secret|token|key)[=:\\s]+[A-Za-z0-9_\\-]{8,}\\b/i',
        '/\\b(?:\\d[ -]*?){13,16}\\b/',
    ],
];
```

**MODEL ROUTING**
Route workloads to different models with `config/ai/router.php`:
```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_ROUTER_ENABLED', false),
    'default_route' => env('AI_ROUTER_DEFAULT_ROUTE', 'quality'),
    'fast_model' => env('AI_ROUTER_FAST_MODEL', env('OPENAI_MODEL', 'gpt-5.4')),
    'quality_model' => env('AI_ROUTER_QUALITY_MODEL', env('OPENAI_MODEL', 'gpt-5.4')),
    'fallback_model' => env('AI_ROUTER_FALLBACK_MODEL', ''),
];
```

**TELEMETRY**
Track AI runs via `config/ai/telemetry.php`:
```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_TELEMETRY_ENABLED', false),
    'table' => env('AI_TELEMETRY_TABLE', 'ai_runs'),
    'store_input' => env('AI_TELEMETRY_STORE_INPUT', true),
    'store_output' => env('AI_TELEMETRY_STORE_OUTPUT', true),
    'store_context' => env('AI_TELEMETRY_STORE_CONTEXT', false),
    'store_sources' => env('AI_TELEMETRY_STORE_SOURCES', false),
    'max_chars' => (int) env('AI_TELEMETRY_MAX_CHARS', 8000),
];
```
Telemetry data is stored in the `ai_runs` table.

**USAGE**
```php
use Fnlla\\Ai\AiClientInterface;

$ai = $app->make(AiClientInterface::class);

$response = $ai->responses([
    'model' => 'gpt-5.4',
    'input' => [
        ['role' => 'user', 'content' => 'Write a short project summary.'],
    ],
]);

if ($response['ok']) {
    $data = $response['data'];
}
```

List available models:
```php
$models = $ai->models();
```

Create embeddings:
```php
$embeddings = $ai->embeddings([
    'model' => 'text-embedding-3-small',
    'input' => 'fnlla (finella) makes PHP apps more approachable.',
]);
```

**RAG (EMBEDDINGS + SEARCH)**
`fnlla/ai` ships with a lightweight RAG store backed by your database.
It requires the core Database module (bundled in `fnlla/framework`).

Create `config/ai/rag.php`:
```php
<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_RAG_ENABLED', false),
    'table' => env('AI_RAG_TABLE', 'ai_embeddings'),
    'chunk_size' => (int) env('AI_RAG_CHUNK_SIZE', 1200),
    'chunk_overlap' => (int) env('AI_RAG_CHUNK_OVERLAP', 120),
    'max_candidates' => (int) env('AI_RAG_MAX_CANDIDATES', 200),
    'min_content_length' => (int) env('AI_RAG_MIN_CONTENT', 40),
    'max_content_length' => (int) env('AI_RAG_MAX_CONTENT', 20000),
];
```

Index and search:
```php
use Fnlla\\Ai\Rag\RagService;

$rag = $app->make(RagService::class);
$rag->indexText('docs', $markdown, ['path' => 'documentation/src/index.md'], 'file', 'documentation/src/index.md');

$results = $rag->search('docs', 'How does the warm kernel work?');
```

**REALTIME CLIENT SECRETS**
Generate a client secret for Realtime WebRTC/WebSocket sessions:
```php
$secret = $ai->realtimeClientSecret([
    'session' => [
        'type' => 'realtime',
        'model' => 'gpt-realtime-1.5',
        'audio' => ['output' => ['voice' => 'marin']],
    ],
]);
```

**TESTING**
```bash
php tests/smoke.php
```
