<?php

namespace Sahifa\Laravel;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/** A rendered PDF or image. */
class Document
{
    public function __construct(
        private readonly string $bytes,
        private readonly string $mimeType,
        private readonly string $defaultName,
    ) {
    }

    /** The raw bytes. */
    public function content(): string
    {
        return $this->bytes;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }

    /** Writes the file to a local path. */
    public function save(string $path): string
    {
        file_put_contents($path, $this->bytes);

        return $path;
    }

    /** Stores the file on a filesystem disk, e.g. ->store('invoices/1001.pdf', 's3'). */
    public function store(string $path, ?string $disk = null): string
    {
        Storage::disk($disk)->put($path, $this->bytes);

        return $path;
    }

    /** A response that downloads the file. */
    public function download(?string $filename = null): Response
    {
        return $this->respond('attachment', $filename);
    }

    /** A response that shows the file in the browser. */
    public function inline(?string $filename = null): Response
    {
        return $this->respond('inline', $filename);
    }

    private function respond(string $disposition, ?string $filename): Response
    {
        $name = str_replace(['"', "\r", "\n"], '', $filename ?? $this->defaultName);

        return new Response($this->bytes, 200, [
            'Content-Type' => $this->mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$name.'"',
            'Content-Length' => (string) strlen($this->bytes),
        ]);
    }

    public function __toString(): string
    {
        return $this->bytes;
    }
}
