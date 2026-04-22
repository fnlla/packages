**FNLLA/STRIPE**

Stripe payments adapter for fnlla (finella).

**INSTALLATION**
```bash
composer require fnlla/stripe
```

**CONFIGURATION**
Create `config/stripe/stripe.php` and set `.env`:
```
STRIPE_ENABLED=1
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

**USAGE**
```php
use Fnlla\\Stripe\StripeManager;

$stripe = app()->make(StripeManager::class);
$client = $stripe->client();

$session = $client->checkout->sessions->create([
    'mode' => 'payment',
    'success_url' => 'https://example.com/success',
    'cancel_url' => 'https://example.com/cancel',
    'line_items' => [
        ['price' => 'price_xxx', 'quantity' => 1],
    ],
]);
```

**WEBHOOK VALIDATION**
```php
use Fnlla\\Stripe\StripeManager;

$stripe = app()->make(StripeManager::class);
$event = $stripe->constructWebhookEvent($payload, $sigHeader);
```
