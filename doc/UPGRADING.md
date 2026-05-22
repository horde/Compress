# Upgrading from Horde_Compress to Horde\Compress

## Overview

- The modern PSR-4 API lives under `Horde\Compress\` (namespace) in `src/`.
- The legacy PSR-0 API (`Horde_Compress_*`) remains in `lib/` and continues to work.
- Both autoloaders are active simultaneously. There are no breaking change until `lib/` is removed.
- The modern API uses constructor-injected PSR-3 loggers instead of `$params` arrays and traditional Horde_Log.
- Return types are strictly typed (`string` for compress, `array` for decompress).

## Quick Start

```php
// Legacy
$zip = Horde_Compress::factory('Zip');
$archive = $zip->compress($files, $params);
$listing = $zip->decompress($data, ['action' => Horde_Compress_Zip::ZIP_LIST]);

// Modern
use Horde\Compress\CompressFactory;
use Horde\Compress\Driver\Zip;

$zip = (new CompressFactory())->create('zip');
$archive = $zip->compress($files);
$listing = $zip->decompress($data, ['action' => Zip::ZIP_LIST]);
```

## Migration Table

| Legacy (PSR-0) | Modern (PSR-4) | Notes |
|----------------|----------------|-------|
| `Horde_Compress::factory('Zip')` | `(new CompressFactory())->create('zip')` | Instance method, not static |
| `Horde_Compress::factory('Tar')` | `(new CompressFactory())->create('tar')` | |
| `Horde_Compress::factory('Gzip')` | `(new CompressFactory())->create('gzip')` | |
| `Horde_Compress::factory('Rar')` | `(new CompressFactory())->create('rar')` | |
| `Horde_Compress::factory('Tnef')` | `new TnefDecoder()` | Direct instantiation or via factory |
| `Horde_Compress_Zip::ZIP_LIST` | `Horde\Compress\Driver\Zip::ZIP_LIST` | Same integer value (1) |
| `Horde_Compress_Zip::ZIP_DATA` | `Horde\Compress\Driver\Zip::ZIP_DATA` | Same integer value (2) |
| `Horde_Compress_Rar::BLOCK_START` | `Horde\Compress\Driver\Rar::BLOCK_START` | RAR magic bytes |
| `Horde_Compress_Exception` | `Horde\Compress\Exception` | Both catchable as `\Exception` |
| `new Horde_Compress_Tar(['logger' => $l])` | `new Horde\Compress\Driver\Tar($logger)` | Logger is first constructor arg |
| `new Horde_Compress_Zip(['logger' => $l])` | `new Horde\Compress\Driver\Zip($logger)` | Logger is first constructor arg |

## Breaking Changes

- **Constructor signature**: Modern drivers accept `?LoggerInterface $logger = null` as the sole constructor argument. The legacy `$params` array pattern is not supported.
- **Factory is an instance**: `CompressFactory::create()` is not static. Instantiate the factory first, optionally passing a logger that all created drivers inherit.
- **Strict return types**: `compress()` always returns `string`. `decompress()` always returns `array`. No mixed returns.
- **No stream option in compressFiles()**: The legacy `'stream' => true` parameter (returning a PHP resource) is not supported. Use `compressFiles()` which always returns a `string`.
- **TNEF is a separate class**: The modern TNEF parser is `Horde\Compress\Tnef\TnefDecoder`, not a generic driver. It provides additional accessors (`getFiles()`, `getAttachments()`, `getMsgInfo()`).
- **Exception hierarchy**: `Horde\Compress\Exception` extends `Horde\Exception\HordeRuntimeException` (which extends `\RuntimeException`). Code catching `Horde_Compress_Exception` must be updated. Both are catchable via `\RuntimeException` or `\Exception`.

## Driver-Specific Notes

### Zip

The `decompress()` action constants remain integers with the same semantics:

```php
use Horde\Compress\Driver\Zip;

// List archive contents
$list = $zip->decompress($data, ['action' => Zip::ZIP_LIST]);

// Extract file by index (returns ['data' => string])
$file = $zip->decompress($data, ['action' => Zip::ZIP_DATA, 'info' => $list, 'key' => 0]);
```

Stream resources are still supported as `$file['data']` values in `compress()` — requires `horde/stream_filter`.

### Tar

SplFileInfo objects can be passed instead of string data:

```php
$files = [
    ['spl' => new \SplFileInfo('/path/to/file'), 'name' => 'file.txt'],
];
$archive = $tar->compress($files);
```

Symlinks are preserved with their link targets in the archive header.

### Gzip

Decompression handles all RFC 1952 header flags (FEXTRA, FNAME, FCOMMENT, FHCRC). Compression is not supported — use PHP's native `gzencode()`.

### Tnef

```php
use Horde\Compress\Tnef\TnefDecoder;

$decoder = new TnefDecoder();
$parts = $decoder->decompress($winmailData);

// Structured accessors (modern API only)
$files = $decoder->getFiles();          // Horde\Compress\Tnef\File[]
$attachments = $decoder->getAttachments(); // Raw attachment data
$msg = $decoder->getMsgInfo();          // Horde\Compress\Tnef\MessageData
```

Requires `horde/mapi` for MAPI property decoding. Without it, only basic TNEF attributes are parsed.

### Rar

Read-only listing. Returns file metadata (name, compressed size, uncompressed size, method, date). Extraction is not supported.

### Dbx

Read-only extraction of Outlook Express (DBX format) mailbox files. Returns an array of `['info' => [...], 'content' => string]` per message.

## Coexistence During Migration

Both APIs are available simultaneously via dual autoloading in `composer.json`:

```json
{
    "autoload": {
        "psr-0": { "Horde_Compress": "lib/" },
        "psr-4": { "Horde\\Compress\\": "src/" }
    }
}
```

You can migrate consumers incrementally. The legacy `lib/` classes will be removed in a future major version.
