<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Webmail\NullMailboxClient;
use Fnlla\Webmail\WebmailCipher;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$client = new NullMailboxClient();
$folders = $client->listFolders();

ok(is_array($folders), 'folders is array');

if (function_exists('openssl_encrypt')) {
    $key = base64_encode(random_bytes(32));
    $envValue = 'base64:' . $key;
    $_ENV['WEBMAIL_SETTINGS_KEY'] = $envValue;
    $_SERVER['WEBMAIL_SETTINGS_KEY'] = $envValue;
    putenv('WEBMAIL_SETTINGS_KEY=' . $envValue);

    $cipher = new WebmailCipher();
    ok($cipher->canEncrypt() === true, 'cipher can encrypt');
    $encrypted = $cipher->encrypt('secret');
    ok($cipher->isEncrypted($encrypted) === true, 'cipher marks encrypted');
    ok($cipher->decrypt($encrypted) === 'secret', 'cipher decrypts');

    unset($_ENV['WEBMAIL_SETTINGS_KEY'], $_SERVER['WEBMAIL_SETTINGS_KEY']);
    putenv('WEBMAIL_SETTINGS_KEY');
}

echo "Webmail smoke tests OK\n";
