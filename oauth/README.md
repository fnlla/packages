**FNLLA/OAUTH**

OAuth/OIDC adapter for fnlla (finella) using `league/oauth2-client`.

**INSTALLATION**
```bash
composer require fnlla/oauth
```

**CONFIGURATION**
Create `config/oauth/oauth.php` and set `.env`:
```
OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback
OAUTH_GOOGLE_AUTHORIZE_URL=https://accounts.google.com/o/oauth2/v2/auth
OAUTH_GOOGLE_TOKEN_URL=https://oauth2.googleapis.com/token
OAUTH_GOOGLE_RESOURCE_URL=https://openidconnect.googleapis.com/v1/userinfo
```

**USAGE**
```php
use Fnlla\\OAuth\OAuthManager;

$oauth = app()->make(OAuthManager::class);
$authUrl = $oauth->authorizeUrl('google', ['scope' => ['openid', 'email', 'profile']]);
```
