<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\ImportGestionali\Support\ImageFetcher;
use Modules\ImportGestionali\Support\ImageFetchException;
use Tests\TestCase;

class ImageFetcherTest extends TestCase
{
    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_downloads_an_image_and_names_the_file(): void
    {
        Http::fake(['https://cdn.example/foto-anello.png' => Http::response($this->pngBytes(), 200)]);

        $image = (new ImageFetcher)->fetch('https://cdn.example/foto-anello.png');

        $this->assertNotSame('', $image->bytes);
        $this->assertSame('foto-anello.png', $image->filename);
    }

    public function test_a_404_is_an_exception(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);

        $this->expectException(ImageFetchException::class);

        (new ImageFetcher)->fetch('https://cdn.example/missing.jpg');
    }

    public function test_a_non_image_body_is_rejected_by_content_sniffing(): void
    {
        Http::fake(['*' => Http::response('<!doctype html><html></html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->expectException(ImageFetchException::class);

        (new ImageFetcher)->fetch('https://cdn.example/not-really.jpg');
    }

    public function test_a_non_url_string_never_hits_the_network(): void
    {
        Http::fake();

        $this->expectException(ImageFetchException::class);

        try {
            (new ImageFetcher)->fetch('anello.jpg');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_private_host_is_blocked(): void
    {
        Http::fake();

        $this->expectException(ImageFetchException::class);

        try {
            (new ImageFetcher)->fetch('http://127.0.0.1/secret.png');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_an_oversized_response_is_rejected_before_it_is_all_read(): void
    {
        Http::fake(['*' => Http::response(str_repeat('x', 4096), 200)]);

        $this->expectException(ImageFetchException::class);

        (new ImageFetcher(15, 512))->fetch('https://cdn.example/huge.jpg');
    }
}
