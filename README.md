# Kvadro_PdfReader

Magento 2 module that reads barcodes / QR codes from PDF and image files via an external PDF Reader API, and can request PDF split/download payloads.

## Requirements

- PHP `^8.2`
- Magento Framework `^103.0`
- PHP `curl` extension

## Installation

```bash
composer require kvadro/pdfreader
bin/magento module:enable Kvadro_PdfReader
bin/magento setup:upgrade
bin/magento cache:flush
```

For a local path repository, register the package in the Magento root `composer.json`, then run the commands above.

## Configuration

**Stores → Configuration → PDF Reader** (`kvadro_pdfreader`)

| Setting | Default | Description |
|---|---|---|
| Enable | Yes | When disabled, PDF Reader functions stop |
| PDF Reader API base URL | `https://pdf.kvadro.if.ua` | Base URL of the remote API (trailing slash is normalized) |
| PDF Reader API bearer token | _(empty)_ | Bearer token stored encrypted in `core_config_data` |

ACL resource: `Kvadro_PdfReader::config`

## Usage

Inject `Kvadro\PdfReader\Service\PdfCodesApiService`:

```php
// Read codes from a PDF or image file (type: image|pdf, etc.)
$codes = $pdfCodesApiService->loadCodesFromFile('/absolute/path/to/file.pdf', 'pdf');

// Request PDF split; returns API payload with download_url on success
$result = $pdfCodesApiService->loadZipByPdf('/absolute/path/to/file.pdf');
```

Both methods return `null` when the module credentials are missing, the file is unreadable, or the API call fails.

## Main components

| Component | Role |
|---|---|
| `Model\Config` | Store config: enable flag, API base URL, decrypted bearer token |
| `Service\PdfCodesApiService` | HTTP client for `/api/{type}/codes` and `/api/pdf/split` |

## License

Proprietary
