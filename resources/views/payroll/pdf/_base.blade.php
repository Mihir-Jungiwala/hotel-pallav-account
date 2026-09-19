{{-- Shared shell for every payroll document.

     One palette, taken from the app: the theme purple (#5B21B6) for the
     letterhead, table headings and the key-figure panel, a lighter shade
     (#6D28D9) for marks and accents, and pale tints of it for surfaces. Nothing
     else is used, so every document reads as one family.

     The letterhead sits once at the top of the first page; later pages carry
     only the running footer. It is plain colour with white type - no boxes
     inside it. DomPDF has no gradients or flexbox, so all of this is solid
     colour and tables. --}}
@php
    // 0 = normal; 1-3 = progressively tighter, chosen by PayrollPdf when a
    // document overflows its page (see app/Support/PayrollPdf.php)
    $c = (int) ($compact ?? 0);
    $brandLogo = null;
    if (isset($company) && $company && $company->logo_path) {
        $logoFile = public_path('storage/'.$company->logo_path);
        if (file_exists($logoFile)) $brandLogo = $logoFile;
    }
    $brandInitials = collect(explode(' ', trim((string) $brandName)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    /* A generous top margin: the letterhead is pulled up into it on page 1, so
       a document that runs to a second page starts that page with breathing
       room instead of hard against the top edge. */
    @page { margin: 58px 38px 54px 38px; }

    * { box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #23193F;
        font-size: 9pt;
        line-height: 1.45;
        margin: 0;
    }

    /* ---------- Letterhead (first page only) ---------- */

    .letterhead {
        background: #5B21B6; border-radius: 9px;
        padding: 12px 18px; margin: -24px 0 14px;
    }
    .letterhead td { vertical-align: middle; }

    /* A centred mark. A table cell is the one thing DomPDF centres reliably
       both ways; a line-height trick leaves the letters sitting low. */
    .mark { width: 42px; height: 42px; border-radius: 10px; background: #6D28D9; }
    .mark table { width: 42px; height: 42px; }
    .mark td {
        width: 42px; height: 42px; text-align: center; vertical-align: middle;
        color: #fff; font-size: 14pt; font-weight: bold; letter-spacing: 0.5pt;
    }
    .letterhead .logo { max-height: 42px; max-width: 100px; }

    .letterhead .brand { font-size: 15pt; font-weight: bold; color: #fff; letter-spacing: -0.3pt; line-height: 1.2; }
    .letterhead .meta { font-size: 7.2pt; color: #DDD3FB; margin-top: 3px; line-height: 1.5; }
    .letterhead .doc-type {
        font-size: 10pt; font-weight: bold; color: #fff; text-align: right;
        text-transform: uppercase; letter-spacing: 1.6pt;
    }
    .letterhead .doc-sub { font-size: 8pt; color: #DDD3FB; text-align: right; margin-top: 3px; }

    /* ---------- Footer, on every page ---------- */

    .pdf-footer {
        position: fixed; bottom: -44px; left: 0; right: 0; height: 30px;
        border-top: 0.8pt solid #E4DCF8;
        padding-top: 7px;
        font-size: 6.8pt; color: #8A82A6;
    }
    .pdf-footer .brand-foot { color: #5B21B6; font-weight: bold; }
    .pdf-footer .page-num:after { content: counter(page); }

    /* ---------- Structure ---------- */

    h2.section {
        font-size: 8.4pt; font-weight: bold;
        color: #5B21B6; text-transform: uppercase; letter-spacing: 1pt;
        margin: 13px 0 7px; padding: 0 0 5px;
        border-bottom: 1pt solid #E4DCF8;
        page-break-after: avoid;
    }
    h2.section .dot {
        display: inline-block; width: 7px; height: 7px; border-radius: 2px;
        background: #6D28D9; margin-right: 7px; vertical-align: -2.5px;
    }

    table { width: 100%; border-collapse: collapse; }
    .avoid-break { page-break-inside: avoid; }

    /* Key/value grid on a soft tint */
    /* Every table and container is a rounded shape. A table only rounds with
       separate borders, and its cells must follow the curve at the corners or
       their fills poke out past it. */
    table.fields, table.grid { border-collapse: separate; border-spacing: 0; border-radius: 8px; }
    table.fields { background: #F7F4FF; border: 0.8pt solid #E4DCF8; }
    table.fields td { padding: 5px 11px; vertical-align: top; border-bottom: 0.5pt solid #ECE6FB; }
    table.fields tr:last-child td { border-bottom: none; }
    table.fields td.k {
        width: 19%; color: #6B6486; font-size: 7pt;
        text-transform: uppercase; letter-spacing: 0.5pt; font-weight: bold;
    }
    table.fields td.v { width: 31%; font-weight: bold; font-size: 9pt; color: #23193F; }

    /* Data grid: theme-purple heading row, a soft stripe to follow a line across */
    table.grid { margin-top: 2px; border: 0.8pt solid #E4DCF8; }
    table.grid th:first-child { border-top-left-radius: 7px; }
    table.grid th:last-child { border-top-right-radius: 7px; }
    table.grid tr:last-child td:first-child { border-bottom-left-radius: 7px; }
    table.grid tr:last-child td:last-child { border-bottom-right-radius: 7px; }
    table.fields tr:first-child td:first-child { border-top-left-radius: 7px; }
    table.fields tr:first-child td:last-child { border-top-right-radius: 7px; }
    table.fields tr:last-child td:first-child { border-bottom-left-radius: 7px; }
    table.fields tr:last-child td:last-child { border-bottom-right-radius: 7px; }
    table.grid th {
        background: #5B21B6; color: #fff;
        font-size: 7.2pt; text-transform: uppercase; letter-spacing: 0.6pt;
        padding: 6px 11px; text-align: left; font-weight: bold;
    }
    table.grid td { padding: 6px 11px; border-bottom: 0.5pt solid #ECE6FB; font-size: 9pt; }
    table.grid tbody tr:nth-child(even) td { background: #FAF8FF; }
    table.grid tr:last-child td { border-bottom: none; }
    table.grid tr.total td {
        background: #EFE9FE !important; font-weight: bold; color: #5B21B6;
        border-top: 1pt solid #C9B8F8; border-bottom: none;
        padding-top: 7px; padding-bottom: 7px; font-size: 9.2pt;
    }
    .num { text-align: right; }
    .center { text-align: center; }
    table.grid th.num { text-align: right; }
    table.grid th.center, table.grid td.center { text-align: center; }

    /* Earnings / deductions: one component per row, full width */
    table.ledger td.lbl { font-weight: bold; }
    table.ledger td.num, table.ledger th.num { white-space: nowrap; }

    /* Dense grid for wide registers */
    table.tight th { font-size: 6.4pt; padding: 5px 4px; letter-spacing: 0.2pt; white-space: nowrap; }
    table.tight td { font-size: 7.4pt; padding: 4px 4px; white-space: nowrap; }
    table.tight td.wrap { white-space: normal; }

    /* Summary figures as tiles */
    table.stats { margin-bottom: 12px; table-layout: fixed; border-collapse: separate; border-spacing: 5px 0; }
    table.stats td {
        background: #F7F4FF; padding: 10px 12px; vertical-align: top;
        border: 0.8pt solid #E4DCF8; border-top: 2.4pt solid #6D28D9; border-radius: 7px;
    }
    table.stats .s-label { font-size: 6.4pt; font-weight: bold; color: #6B6486; text-transform: uppercase; letter-spacing: 0.6pt; white-space: nowrap; }
    table.stats .s-value { font-size: 12.5pt; font-weight: bold; color: #23193F; margin-top: 3px; }
    table.stats .s-value.accent { color: #5B21B6; }

    /* The one figure the reader came for, in a panel of its own */
    .panel {
        background: #5B21B6; color: #fff;
        border-radius: 9px; padding: 13px 18px; margin-top: 12px;
    }
    .panel .label { font-size: 7.2pt; color: #DDD3FB; text-transform: uppercase; letter-spacing: 0.9pt; font-weight: bold; }
    .panel .value { font-size: 19pt; font-weight: bold; color: #fff; letter-spacing: -0.5pt; line-height: 1.25; }
    .panel .words { font-size: 7.8pt; color: #EDE6FE; font-style: italic; margin-top: 3px; }
    .panel .side { background: #6D28D9; border-radius: 7px; padding: 10px 14px; }
    /* Anything a document places inside the panel reads light on the purple,
       whether or not it was written with the panel in mind */
    .panel td { color: #fff; }
    .panel .muted { color: #DDD3FB !important; }
    .panel strong { color: #fff; }

    table.net-lines td { font-size: 8pt; padding: 3px 0; color: #EDE6FE; }
    table.net-lines tr.rule td {
        border-top: 0.8pt solid #A78BFA; padding-top: 6px;
        color: #fff; font-size: 9.2pt;
    }

    .chip {
        display: inline-block; padding: 2px 10px; border-radius: 9px; line-height: 1.35; vertical-align: middle;
        background: #EFE9FE; color: #5B21B6; font-size: 7.2pt; font-weight: bold;
    }
    .chip.ok { background: #DCFCE7; color: #166534; }
    .chip.warn { background: #FEF3C7; color: #92400E; }
    .chip.bad { background: #FEE2E2; color: #991B1B; }

    .muted { color: #6B6486; }

    /* Signature row: one table row, so every rule sits on the same baseline
       and every block has the same blank space above it to sign in */
    table.sig { table-layout: fixed; }
    table.sig td.sig-space { width: 46%; height: 54px; vertical-align: bottom; padding: 0 0 4px; }
    table.sig td.sig-gap { width: 8%; }
    table.sig td.sig-under { width: 46%; vertical-align: top; border-top: 1pt solid #6D28D9; padding-top: 5px; }
    table.sig td.sig-under.sig-empty { border-top: none; }
    .sig-caption { font-size: 8.4pt; color: #23193F; margin-bottom: 3px; }
    .sig-name { font-size: 8.8pt; font-weight: bold; color: #23193F; }
    .sig-note { font-size: 7.6pt; color: #6B6486; line-height: 1.4; }
    .sign-area { margin-top: 20px; page-break-inside: avoid; }
    .sign-line {
        display: inline-block; width: 200px; text-align: center;
        border-top: 1pt solid #6D28D9; padding-top: 6px; font-size: 8pt;
    }

    /* Body copy in letters. A class rather than inline sizes, so the compact
       steps below can shrink it. */
    .prose { font-size: 9.2pt; line-height: 1.5; text-align: justify; }

@if($c >= 1)
    /* ---- Compact, step 1: a few lines over. Reclaim padding, keep the type. ---- */
    body { font-size: 8.7pt; line-height: 1.4; }
    .letterhead { padding: 9px 16px; margin: -26px 0 10px; }
    h2.section { margin: 9px 0 5px; padding-bottom: 4px; }
    table.fields td { padding: 3.5px 10px; }
    table.grid th { padding: 4.5px 10px; }
    table.grid td { padding: 4.5px 10px; }
    table.grid tr.total td { padding-top: 5px; padding-bottom: 5px; }
    table.stats td { padding: 7px 10px; }
    table.stats .s-value { font-size: 11.5pt; }
    .panel { padding: 10px 16px; margin-top: 9px; }
    .panel .value { font-size: 17pt; }
    table.tight th { padding: 4px 3px; }
    table.tight td { padding: 3px 3px; }
    table.sig td.sig-space { height: 44px; }
    .prose { font-size: 8.8pt; line-height: 1.42; }
    .sign-area { margin-top: 14px; }
@endif
@if($c >= 2)
    /* ---- Compact, step 2: a little more, type included. ---- */
    @page { margin: 50px 34px 46px 34px; }
    body { font-size: 8.2pt; line-height: 1.34; }
    .letterhead { padding: 8px 14px; margin: -22px 0 8px; }
    .letterhead .brand { font-size: 13.5pt; }
    h2.section { margin: 7px 0 4px; padding-bottom: 3px; }
    table.fields td { padding: 2.6px 9px; }
    table.grid th { padding: 3.6px 9px; }
    table.grid td { padding: 3.4px 9px; font-size: 8.5pt; }
    table.stats td { padding: 5px 8px; }
    .panel { padding: 8px 14px; margin-top: 7px; }
    .panel .value { font-size: 15pt; }
    table.tight td { padding: 2.4px 3px; font-size: 7.1pt; }
    table.sig td.sig-space { height: 38px; }
    .prose { font-size: 8.3pt; line-height: 1.36; }
@endif
@if($c >= 3)
    /* ---- Compact, step 3: the tightest still comfortably legible. ---- */
    @page { margin: 44px 30px 42px 30px; }
    body { font-size: 7.7pt; line-height: 1.3; }
    .letterhead { padding: 7px 12px; margin: -18px 0 6px; }
    h2.section { margin: 6px 0 3px; }
    table.fields td { padding: 2px 8px; }
    table.grid th { padding: 3px 8px; }
    table.grid td { padding: 2.6px 8px; font-size: 8pt; }
    table.tight td { padding: 1.8px 3px; font-size: 6.8pt; }
    table.sig td.sig-space { height: 32px; }
    .prose { font-size: 7.9pt; line-height: 1.32; }
@endif
</style>
</head>
<body>

<div class="pdf-footer">
    <table>
        <tr>
            <td style="width:64%;">
                <span class="brand-foot">{{ $brandName }}</span> &middot; {{ $docType }} &middot; generated {{ now()->format('d M Y, H:i') }}
            </td>
            <td style="width:36%; text-align:right;">Page <span class="page-num"></span></td>
        </tr>
    </table>
</div>

<div class="letterhead">
    <table>
        <tr>
            <td style="width:62px;">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" class="logo">
                @else
                    <div class="mark"><table><tr><td>{{ $brandInitials ?: 'HP' }}</td></tr></table></div>
                @endif
            </td>
            <td style="padding-right:12px;">
                <div class="brand">{{ $brandName }}</div>
                <div class="meta">{{ $brandMeta }}</div>
            </td>
            <td style="width:34%;">
                <div class="doc-type">{{ $docType }}</div>
                <div class="doc-sub">{{ $docSub ?? '' }}</div>
            </td>
        </tr>
    </table>
</div>

@yield('content')

</body>
</html>
