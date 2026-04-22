**FNLLA/STORAGE-S3**

S3 storage driver for fnlla (finella) storage. Requires `fnlla/storage` and AWS SDK v3.

**INSTALLATION**
```bash
composer require fnlla/storage fnlla/storage-s3
```

**CONFIGURATION**
Add an S3 disk to `config/storage/storage.php`:
```
'disks' => [
  's3' => [
    'driver' => 's3',
    'bucket' => env('STORAGE_S3_BUCKET', ''),
    'region' => env('STORAGE_S3_REGION', 'eu-west-1'),
    'key' => env('STORAGE_S3_KEY', ''),
    'secret' => env('STORAGE_S3_SECRET', ''),
    'endpoint' => env('STORAGE_S3_ENDPOINT', ''),
    'prefix' => env('STORAGE_S3_PREFIX', ''),
    'public_url' => env('STORAGE_S3_PUBLIC_URL', ''),
    'use_path_style' => env('STORAGE_S3_USE_PATH_STYLE', true),
  ],
]
```

**USAGE**
```php
use Fnlla\\Storage\StorageManager;

$storage = app()->make(StorageManager::class);
$disk = $storage->disk('s3');
$disk->put('avatars/user.png', $binary);
$url = $disk->url('avatars/user.png');
```

**NOTES**
**-** If `endpoint` is set, the driver uses path-style requests by default.
**-** Credentials are optional when using IAM roles or instance profiles.
