<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * A PDF that has already been produced, as bytes.
 *
 * The PDF library cannot be asked for its output twice: the second call
 * compresses the already-compressed font data again, and the document opens
 * with its text turned to garbage. Something that needs to look at the result
 * (to count its pages) and then send it has to take the bytes once and keep
 * them, which is all this holds.
 */
class RenderedPdf
{
    public function __construct(private string $binary) {}

    public function output(): string
    {
        return $this->binary;
    }

    /** Opens in the browser's own viewer, the way the library's stream() does. */
    public function stream(string $filename = 'document.pdf'): Response
    {
        return new Response($this->binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'Content-Length' => (string) strlen($this->binary),
        ]);
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return new Response($this->binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $filename).'"',
            'Content-Length' => (string) strlen($this->binary),
        ]);
    }
}
