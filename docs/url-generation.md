# URL Generation

`File::getUrl()` chooses the URL type based on the configured visibility of the resolved filesystem disk.

- Public disks return clean URLs via `getUrl()`.
- Private disks, or disks without an explicit `visibility`, try to return signed temporary URLs via `getTemporaryUrl()`.
- If the active driver does not support temporary URLs, the package falls back to `getUrl()`.

For original files, visibility is resolved from the media record's `disk`. For named conversions such as `thumb` or `preview`, visibility is resolved from `conversions_disk` when it is set, otherwise it falls back to the original `disk`.

This supports both the common single-disk setup and split-disk setups where originals stay private while conversions are served publicly, such as from a CDN-backed disk.

## Example S3 Disk Configuration

Set the disk visibility in `config/filesystems.php`:

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'visibility' => env('AWS_VISIBILITY', 'private'),
    'throw' => false,
    'report' => false,
],
```

Then set the desired visibility in your `.env`:

```bash
AWS_VISIBILITY=public
```

With that configuration:

- `getUrl()` returns a clean public URL for original files on that disk.
- `getUrl('thumb')` returns a clean public URL for conversions on a public conversion disk.
- Private disks continue to use temporary signed URLs when the filesystem driver supports them.
