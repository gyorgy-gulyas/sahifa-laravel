<?php

namespace Sahifa\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Sahifa\Laravel\Exceptions\SahifaException;

class Sahifa
{
    private const RETRYABLE = [429, 502, 503, 504];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?ViewFactory $views,
        private readonly array $config,
    ) {
    }

    /**
     * HTML or a URL to PDF (POST /v3/convert/pdf, PDFShift v3 compatible).
     *
     * @param  array<string, mixed>  $options  source, format, landscape, margin, header, footer, css, ...
     */
    public function pdf(string $source, array $options = []): Document
    {
        if (trim($source) === '') {
            throw new SahifaException('Sahifa::pdf(): the HTML or URL is empty.');
        }
        $body = array_merge($this->config['pdf_defaults'] ?? [], $options, ['source' => $source]);

        return new Document($this->send('/v3/convert/pdf', $body), 'application/pdf', 'document.pdf');
    }

    /**
     * Renders a Blade view to PDF.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function pdfFromView(string $view, array $data = [], array $options = []): Document
    {
        if (! $this->views) {
            throw new SahifaException('Sahifa::pdfFromView() needs the Laravel view factory.');
        }

        return $this->pdf($this->views->make($view, $data)->render(), $options);
    }

    /**
     * Screenshot of a URL or of HTML (POST /take, ScreenshotOne compatible).
     * Pass exactly one of 'url' or 'html' in $options.
     *
     * @param  array<string, mixed>  $options
     */
    public function screenshot(array $options): Document
    {
        if (empty($options['url']) === empty($options['html'])) {
            throw new SahifaException("Sahifa::screenshot(): pass exactly one of 'url' or 'html'.");
        }
        $format = $options['format'] ?? 'png';
        $mime = $format === 'pdf' ? 'application/pdf' : 'image/'.($format === 'jpg' ? 'jpeg' : $format);
        $ext = $format === 'jpeg' ? 'jpg' : $format;

        return new Document($this->send('/take', $options), $mime, 'screenshot.'.$ext);
    }

    /**
     * Wraps an HTML fragment in a right-to-left Arabic document with Arabic fonts.
     * A full document (starting with <html or <!doctype) is returned unchanged.
     */
    public static function arabicDocument(string $body, string $lang = 'ar', string $css = ''): string
    {
        if (preg_match('/^\s*(<!doctype|<html)/i', $body)) {
            return $body;
        }
        $lang = htmlspecialchars($lang, ENT_QUOTES);

        return '<!doctype html><html dir="rtl" lang="'.$lang.'"><head><meta charset="utf-8">'
            ."<style>body{font-family:'Noto Naskh Arabic','Amiri','Noto Sans Arabic',serif;line-height:1.6}"
            .'.num{font-variant-numeric:tabular-nums}'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    /** @param array<string, mixed> $body */
    private function send(string $path, array $body): string
    {
        $key = $this->config['api_key'] ?? null;
        if (! $key) {
            throw new SahifaException('No Sahifa API key: set SAHIFA_API_KEY in .env. Get a free key at https://sahifa.dev/en/account');
        }
        $retries = (int) ($this->config['retries'] ?? 3);

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->http
                    ->withHeaders(['X-API-Key' => $key, 'X-Access-Key' => $key, 'User-Agent' => 'sahifa-laravel/1.0'])
                    ->timeout((int) ($this->config['timeout'] ?? 120))
                    ->acceptJson()
                    ->asJson()
                    ->post(rtrim($this->config['base_url'] ?? 'https://api.sahifa.dev', '/').$path, $body);
            } catch (ConnectionException $e) {
                if ($attempt < $retries) {
                    usleep((int) (1_000_000 * 2 ** $attempt));

                    continue;
                }
                throw new SahifaException('Network error: '.$e->getMessage(), null, 'network', null, $e);
            }

            if ($response->successful()) {
                return $response->body();
            }
            $retryAfter = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;
            if (in_array($response->status(), self::RETRYABLE, true) && $attempt < $retries) {
                usleep((int) (1_000_000 * min(60, $retryAfter ?? 2 ** $attempt)));

                continue;
            }
            throw $this->error($response, $retryAfter);
        }
    }

    private function error(Response $response, ?int $retryAfter): SahifaException
    {
        $json = $response->json();
        $message = is_array($json) && isset($json['error']) ? (string) $json['error'] : 'HTTP '.$response->status();
        $code = is_array($json) && isset($json['error_code']) ? (string) $json['error_code'] : null;

        return new SahifaException($message, $response->status(), $code, $retryAfter);
    }
}
