<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

/**
 * Renders a payroll document, fitting it on one page where it reasonably can.
 *
 * A document that runs a few lines onto a second page looks careless: a page
 * holding a total and a signature and nothing else. Rather than shrinking
 * every document to suit the worst case, each one is rendered at its normal
 * size first, and only one that overflows is rendered again with tighter
 * spacing - up to three steps - until it fits. A document that is genuinely
 * long (a register of hundreds of rows) still runs to as many pages as it
 * needs; this only rescues the near misses.
 *
 * The templates read `$compact` (0-3) and tighten their type, padding and
 * margins accordingly (see payroll/pdf/_base.blade.php).
 */
class PayrollPdf
{
    /** Tightest step tried; beyond this the type is too small to read comfortably. */
    public const MAX_COMPACT = 3;

    public static function make(string $view, array $data, string $orientation = 'portrait'): RenderedPdf
    {
        $binary = '';

        for ($level = 0; $level <= self::MAX_COMPACT; $level++) {
            // A fresh PDF object for every attempt. The Pdf facade caches the
            // first one it makes and hands it back each time, so going through
            // it would re-render into the same document. The container gives a
            // new one per call.
            $pdf = app('dompdf.wrapper')
                ->loadView($view, $data + ['compact' => $level])
                ->setPaper('a4', $orientation);

            // output() is asked for exactly once per object, and the bytes are
            // kept. Asking twice (once to count pages, once to send) compresses
            // the fonts a second time and the text opens as garbage.
            $binary = $pdf->output();

            if (self::pageCount($binary) <= 1) {
                break;
            }
        }

        // Still more than a page at the tightest step means a genuinely long
        // document, and the last attempt is already the compact one.
        return new RenderedPdf($binary);
    }

    /** Page objects in the file. "/Type /Pages" is the tree, so it is excluded. */
    public static function pageCount(string $binary): int
    {
        return (int) preg_match_all('/\/Type\s*\/Page(?![s\w])/', $binary);
    }
}
