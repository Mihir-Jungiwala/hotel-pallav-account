{{-- Shared premium PDF shell. Fixed header/footer repeat on every page, and
     the content area is sized so short documents never orphan a few lines. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 118px 38px 64px 38px; }

    * { box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #1B1235;
        font-size: 9.5pt;
        line-height: 1.45;
        margin: 0;
    }

    /* ---------- Fixed chrome ---------- */

    .pdf-header {
        position: fixed; top: -96px; left: 0; right: 0; height: 82px;
    }
    .pdf-header .bar {
        background: #5B21B6;
        color: #fff;
        padding: 13px 18px;
        border-radius: 8px;
    }
    .pdf-header .brand {
        font-size: 15pt; font-weight: bold; letter-spacing: -0.3pt;
    }
    .pdf-header .meta {
        font-size: 7.5pt; color: #DCC9FF; margin-top: 2px;
    }
    .pdf-header .doc-type {
        font-size: 9.5pt; font-weight: bold; text-align: right;
        text-transform: uppercase; letter-spacing: 1pt;
    }
    .pdf-header .doc-sub { font-size: 7.5pt; color: #DCC9FF; text-align: right; margin-top: 2px; }

    .pdf-footer {
        position: fixed; bottom: -46px; left: 0; right: 0; height: 34px;
        border-top: 0.6pt solid #E9E2FA;
        padding-top: 6px;
        font-size: 7pt; color: #9A93B0;
    }
    .pdf-footer .page-num:after { content: counter(page); }

    /* ---------- Structure ---------- */

    h2.section {
        font-size: 8pt; font-weight: bold;
        color: #5B21B6; text-transform: uppercase; letter-spacing: 0.9pt;
        margin: 14px 0 6px; padding-bottom: 3px;
        border-bottom: 0.8pt solid #DFD3FD;
        page-break-after: avoid;
    }
    h2.section:first-of-type { margin-top: 4px; }

    table { width: 100%; border-collapse: collapse; }
    .avoid-break { page-break-inside: avoid; }

    /* Compact two-column field grid — halves the vertical space of a KV list */
    table.fields td { padding: 4.5px 8px; vertical-align: top; border-bottom: 0.5pt solid #F1ECFD; }
    table.fields td.k {
        width: 20%; color: #6B6486; font-size: 7.5pt;
        text-transform: uppercase; letter-spacing: 0.4pt; font-weight: bold;
    }
    table.fields td.v { width: 30%; font-weight: bold; font-size: 9pt; }

    /* Data grid */
    table.grid { margin-top: 2px; }
    table.grid th {
        background: #F3EEFE; color: #5B21B6;
        font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt;
        padding: 6px 8px; text-align: left; border-bottom: 0.8pt solid #DFD3FD;
    }
    table.grid td { padding: 6px 8px; border-bottom: 0.5pt solid #F1ECFD; font-size: 9pt; }
    table.grid tr.total td {
        background: #F7F4FF; font-weight: bold; color: #4A1A8F;
        border-top: 0.8pt solid #C6B0FB; border-bottom: none;
    }
    .num { text-align: right; }
    table.grid th.num { text-align: right; }

    /* Highlight panel */
    .panel {
        background: #F7F4FF; border: 0.8pt solid #DFD3FD;
        border-radius: 7px; padding: 11px 14px; margin-top: 10px;
    }
    .panel .label { font-size: 7.5pt; color: #6B6486; text-transform: uppercase; letter-spacing: 0.5pt; font-weight: bold; }
    .panel .value { font-size: 16pt; font-weight: bold; color: #4A1A8F; letter-spacing: -0.4pt; }
    .panel .words { font-size: 8pt; color: #4A4262; font-style: italic; margin-top: 1px; }

    .chip {
        display: inline-block; padding: 2px 8px; border-radius: 9px;
        background: #EFE9FE; color: #5B21B6; font-size: 7.5pt; font-weight: bold;
    }
    .chip.ok { background: #DCFCE7; color: #166534; }
    .chip.warn { background: #FEF3C7; color: #92400E; }

    .muted { color: #6B6486; }
    .sign-area { margin-top: 26px; page-break-inside: avoid; }
    .sign-line { border-top: 0.8pt solid #C6B0FB; padding-top: 5px; font-size: 8pt; }
</style>
</head>
<body>

<div class="pdf-header">
    <div class="bar">
        <table>
            <tr>
                <td style="width:62%;">
                    <div class="brand">{{ $brandName }}</div>
                    <div class="meta">{{ $brandMeta }}</div>
                </td>
                <td style="width:38%;">
                    <div class="doc-type">{{ $docType }}</div>
                    <div class="doc-sub">{{ $docSub ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="pdf-footer">
    <table>
        <tr>
            <td style="width:60%;">{{ $brandName }} &middot; generated {{ now()->format('d M Y, H:i') }}</td>
            <td style="width:40%; text-align:right;">Page <span class="page-num"></span></td>
        </tr>
    </table>
</div>

@yield('content')

</body>
</html>
