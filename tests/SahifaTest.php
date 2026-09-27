<?php

namespace Sahifa\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Sahifa\Laravel\Exceptions\SahifaException;
use Sahifa\Laravel\Facades\Sahifa;
use Sahifa\Laravel\Sahifa as Client;
use Sahifa\Laravel\SahifaServiceProvider;

class SahifaTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SahifaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sahifa.api_key', 'sk_test');
        $app['config']->set('sahifa.retries', 2);
        $app['view']->addLocation(__DIR__.'/views');
    }

    public function test_pdf_from_view_posts_the_rendered_html_with_defaults_and_the_key(): void
    {
        Http::fake(['api.sahifa.dev/v3/convert/pdf' => Http::response('%PDF-1.7', 200)]);

        $doc = Sahifa::pdfFromView('invoice', ['number' => 1001], ['margin' => '15mm']);

        $this->assertSame('%PDF-1.7', $doc->content());
        Http::assertSent(fn (Request $r) => $r->hasHeader('X-API-Key', 'sk_test')
            && str_contains($r['source'], 'فاتورة 1001')
            && $r['format'] === 'A4'
            && $r['margin'] === '15mm');
    }

    public function test_download_and_inline_responses(): void
    {
        Http::fake(['*' => Http::response('%PDF', 200)]);
        $doc = Sahifa::pdf('<p>x</p>');

        $this->assertSame('attachment; filename="invoice.pdf"', $doc->download('invoice.pdf')->headers->get('Content-Disposition'));
        $this->assertSame('inline; filename="document.pdf"', $doc->inline()->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $doc->inline()->headers->get('Content-Type'));
    }

    public function test_screenshot_needs_exactly_one_of_url_or_html_and_sets_the_mime_type(): void
    {
        Http::fake(['api.sahifa.dev/take' => Http::response('png', 200)]);

        $doc = Sahifa::screenshot(['url' => 'https://example.com', 'format' => 'jpeg']);
        $this->assertSame('image/jpeg', $doc->mimeType());
        Http::assertSent(fn (Request $r) => $r['url'] === 'https://example.com' && $r->hasHeader('X-Access-Key', 'sk_test'));

        $this->expectException(SahifaException::class);
        Sahifa::screenshot([]);
    }

    public function test_retries_busy_responses_then_succeeds(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'busy'], 429, ['Retry-After' => '0'])
            ->push('%PDF', 200);

        $this->assertSame('%PDF', Sahifa::pdf('<p>x</p>')->content());
        Http::assertSentCount(2);
    }

    public function test_api_errors_carry_status_message_and_code_and_are_not_retried(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'error' => 'Monthly render allowance used up', 'error_code' => 'quota'], 402)]);

        try {
            Sahifa::pdf('<p>x</p>');
            $this->fail('expected an exception');
        } catch (SahifaException $e) {
            $this->assertSame(402, $e->status);
            $this->assertSame('quota', $e->errorCode);
            $this->assertSame('Monthly render allowance used up', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_missing_key_explains_where_to_get_one(): void
    {
        config(['sahifa.api_key' => null]);
        $this->app->forgetInstance(Client::class);

        $this->expectExceptionMessage('SAHIFA_API_KEY');
        Sahifa::pdf('<p>x</p>');
    }

    public function test_arabic_document_wraps_fragments(): void
    {
        $html = Client::arabicDocument('<h1>مرحبا</h1>');
        $this->assertStringContainsString('<html dir="rtl" lang="ar">', $html);
        $this->assertStringContainsString('Noto Naskh Arabic', $html);
        $this->assertSame('<html>x</html>', Client::arabicDocument('<html>x</html>'));
    }
}
