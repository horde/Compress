# Horde\Compress

A PHP library for creating and extracting compressed archives and encoded
transport formats. Supports ZIP, TAR, GZIP, RAR (read-only), DBX (read-only)
and for handling Microsoft TNEF (winmail.dat) decoding.

## Installation

```bash
composer require horde/compress
```

### Optional dependencies

| Package | Purpose |
|---------|---------|
| `horde/stream_filter` | CRC32 stream filter for ZIP compression with resource handles |
| `horde/icalendar` | iCalendar generation from TNEF meeting requests |
| `horde/mapi` | MAPI property decoding for TNEF attachments |
| `horde/mail` | RFC 822 address parsing in TNEF messages |
| `ext-zlib` | Required for Gzip driver |

## Usage

### CompressFactory

```php
use Horde\Compress\CompressFactory;

$factory = new CompressFactory();
$zip = $factory->create('zip');
$tar = $factory->create('tar');
$gzip = $factory->create('gzip');
```

Pass a PSR-3 logger to the factory for diagnostic output:

```php
$factory = new CompressFactory($logger);
$zip = $factory->create('zip');
```

### Creating a ZIP archive

```php
use Horde\Compress\CompressFactory;
use Horde\Compress\Driver\Zip;

$zip = (new CompressFactory())->create('zip');

$files = [
    ['data' => 'Hello World', 'name' => 'hello.txt', 'time' => time()],
    ['data' => file_get_contents('image.png'), 'name' => 'image.png', 'time' => filemtime('image.png')],
];

$archive = $zip->compress($files);
file_put_contents('archive.zip', $archive);
```

### Extracting a ZIP archive

```php
use Horde\Compress\Driver\Zip;

$zip = (new CompressFactory())->create('zip');
$data = file_get_contents('archive.zip');

// List contents
$listing = $zip->decompress($data, ['action' => Zip::ZIP_LIST]);
foreach ($listing as $entry) {
    echo $entry['name'] . ' (' . $entry['size'] . " bytes)\n";
}

// Extract a specific file by index
$content = $zip->decompress($data, ['action' => Zip::ZIP_DATA, 'info' => $listing, 'key' => 0]);
```

### Creating a TAR archive

```php
use Horde\Compress\CompressFactory;

$tar = (new CompressFactory())->create('tar');

$files = [
    ['data' => 'content', 'name' => 'file.txt', 'time' => time()],
];

$archive = $tar->compress($files);
file_put_contents('archive.tar', $archive);
```

### Compressing a directory

```php
$tar = (new CompressFactory())->create('tar');
$archive = $tar->compressDirectory('/path/to/directory');
```

### Decompressing GZIP data

```php
$gzip = (new CompressFactory())->create('gzip');
$raw = $gzip->decompress(file_get_contents('file.gz'));
```

### Decoding TNEF (winmail.dat)

```php
use Horde\Compress\Tnef\TnefDecoder;

$decoder = new TnefDecoder();
$parts = $decoder->decompress(file_get_contents('winmail.dat'));

foreach ($parts as $part) {
    echo $part['name'] . ' (' . $part['type'] . '/' . $part['subtype'] . ")\n";
}

// Access message metadata
$msgInfo = $decoder->getMsgInfo();
echo 'Subject: ' . $msgInfo->subject . "\n";
```

### Reading RAR archives (listing only)

```php
$rar = (new CompressFactory())->create('rar');
$listing = $rar->decompress(file_get_contents('archive.rar'));

foreach ($listing as $entry) {
    echo $entry['name'] . ' (' . $entry['size'] . " bytes)\n";
}
```

## Architecture

```
src/
├── Base.php                   # Abstract base class with logger support
├── CompressFactory.php        # Factory — creates driver instances by name
├── CompressorInterface.php    # Contract: compress, decompress, compressFiles, compressDirectory
├── Exception.php              # Library exception
├── Translation.php            # Gettext translation wrapper
├── Driver/
│   ├── Dbx.php                # Outlook Express DBX mailbox extraction
│   ├── Gzip.php               # RFC 1952 gzip decompress
│   ├── Rar.php                # RAR archive listing (read-only)
│   ├── Tar.php                # POSIX tar create/extract, SplFileInfo support
│   └── Zip.php                # ZIP create/extract, stream resource support
└── Tnef/
    ├── BufferReaderTrait.php   # Binary buffer reading (little-endian ints)
    ├── Date.php                # TNEF date attribute → Horde_Date
    ├── File.php                # TNEF file attachment object
    ├── Icalendar.php           # TNEF meeting request → iCalendar
    ├── MessageData.php         # TNEF message envelope (subject, from, date)
    ├── Rtf.php                 # Compressed RTF extraction
    ├── TnefDecoder.php         # Main TNEF parser
    ├── TnefObject.php          # Base TNEF object
    ├── TnefObjectInterface.php # Contract for TNEF attribute handlers
    └── VTodo.php               # TNEF task → vTodo
```

## Upgrading

See [doc/UPGRADING.md](doc/UPGRADING.md) for the migration guide from the
legacy `Horde_Compress` (PSR-0) API to the modern `Horde\Compress` (PSR-4) API.

## Requirements

- PHP 8.1+
- `horde/exception` ^3
- `horde/mime` ^3
- `horde/translation` ^3
- `horde/util` ^3

## License

LGPL-2.1-only. See [LICENSE](LICENSE) for details.
