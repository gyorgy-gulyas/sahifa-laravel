# Sahifa for Laravel

Render Blade views to PDF with **Arabic text that actually works**: joined letters, correct word order, mixed Arabic and English, with Arabic fonts ready. Rendered by Chromium on [Sahifa](https://sahifa.dev/en/)'s servers **in Saudi Arabia**, so there is no browser to install and nothing to configure in your Laravel app.

If you came here because dompdf shows Arabic as `????` or as disconnected letters, this is the fix: dompdf does not shape Arabic, a browser engine does.

```php
use Sahifa\Laravel\Facades\Sahifa;

return Sahifa::pdfFromView('invoices.show', ['invoice' => $invoice])->download('invoice.pdf');
```

## Installation

```
composer require sahifa/laravel
```

Add your key to `.env` (get a free one at [sahifa.dev](https://sahifa.dev/en/account): 100 renders a month, no card needed):

```
SAHIFA_API_KEY=sk_live_...
```

The service provider and the `Sahifa` facade are registered automatically. To change the defaults, publish the config:

```
php artisan vendor:publish --tag=sahifa-config
```

Requirements: PHP 8.2+, Laravel 12 or 13.

## Usage

### PDF from a Blade view

```php
$pdf = Sahifa::pdfFromView('invoices.show', ['invoice' => $invoice], [
    'format' => 'A4',
    'margin' => '15mm',
    'footer' => ['source' => '<div style="width:100%;text-align:center;font-size:9px">صفحة {{page}} من {{total}}</div>'],
]);

return $pdf->download("invoice-{$invoice->number}.pdf"); // or ->inline()
$pdf->store("invoices/{$invoice->number}.pdf", 's3');     // any filesystem disk
$pdf->save(storage_path('app/invoice.pdf'));              // local path
$bytes = $pdf->content();                                 // raw bytes, e.g. for a Mailable attachment
```

Inside the page footer and header, `{{page}}` and `{{total}}` are replaced with page numbers. In a Blade file write them as `@{{page}}` so Blade leaves them alone.

### PDF from HTML or a URL

```php
Sahifa::pdf('<h1>Hello</h1>');
Sahifa::pdf('https://example.com/report/42', ['landscape' => true]);
```

All [PDF options](https://sahifa.dev/en/docs/pdf) are passed through: `format`, `landscape`, `margin`, `header`, `footer`, `css`, `javascript`, `delay`, `wait_for`, `pages`, `zoom`.

### Screenshots

```php
Sahifa::screenshot(['url' => 'https://example.com', 'full_page' => true, 'format' => 'png'])
    ->store('screens/home.png');
```

See the [screenshot options](https://sahifa.dev/en/docs/screenshot).

### Mail attachments

```php
use Illuminate\Mail\Attachment;

public function attachments(): array
{
    $pdf = Sahifa::pdfFromView('invoices.show', ['invoice' => $this->invoice]);

    return [Attachment::fromData(fn () => $pdf->content(), 'invoice.pdf')->withMime('application/pdf')];
}
```

## An Arabic invoice view

```blade
<!doctype html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Noto Naskh Arabic', 'Amiri', serif; }
    td.num { text-align: end; font-variant-numeric: tabular-nums; }
    @page { size: A4; margin: 15mm; }
  </style>
</head>
<body>
  <h1>فاتورة ضريبية مبسطة</h1>
  <p>الرقم الضريبي: <bdi dir="ltr">{{ $seller->vat_number }}</bdi></p>
  <table>
    @foreach ($invoice->items as $item)
      <tr><td>{{ $item->name }}</td><td class="num"><bdi dir="ltr">{{ number_format($item->total, 2) }} SAR</bdi></td></tr>
    @endforeach
  </table>
</body>
</html>
```

- Put `dir="rtl"` on `<html>`, so tables and layout mirror.
- Wrap left-to-right parts (VAT numbers, IBANs, amounts, e-mails) in `<bdi dir="ltr">`.
- `Sahifa\Laravel\Sahifa::arabicDocument($fragment)` wraps a fragment in a full RTL document with Arabic fonts.

## Errors and retries

Failures throw `Sahifa\Laravel\Exceptions\SahifaException` with `->status`, `->errorCode` and the API's message. Busy and rate-limit responses (429, 502, 503, 504) and network errors are retried (`SAHIFA_RETRIES`, default 3), waiting for `Retry-After`.

```php
use Sahifa\Laravel\Exceptions\SahifaException;

try {
    $pdf = Sahifa::pdfFromView('invoices.show', $data);
} catch (SahifaException $e) {
    if ($e->status === 402) { /* monthly allowance used up */ }
    report($e);
}
```

## Testing your app

The package uses Laravel's HTTP client, so `Http::fake()` works:

```php
Http::fake(['api.sahifa.dev/*' => Http::response('%PDF-1.7', 200)]);
```

## Why Sahifa

- **Arabic done right:** Chromium shaping and bidi, Arabic fonts preinstalled.
- **In the Kingdom:** documents are rendered in Jeddah and not stored. [Data residency](https://sahifa.dev/en/data-residency).
- **Nothing to run:** no Chrome, no fonts, no queue workers in your containers.

[Documentation](https://sahifa.dev/en/docs) · [Examples in five languages](https://github.com/gyorgy-gulyas/sahifa-examples) · [Pricing](https://sahifa.dev/en/#pricing) · Support: [support@sahifa.dev](mailto:support@sahifa.dev)

MIT License. Sahifa is a service of Yimello LLC.

## Author

Gulyás György Tamás — [ggyt.hu](https://ggyt.hu/)
