{{-- A row of signature blocks that all share the same geometry.

     Every block has the same blank space to sign in and its rule sits on the
     same baseline, because they are cells of one table row rather than
     separate floating boxes. That is what stops a company signatory, an
     employee's signature and a date line from each ending up at a slightly
     different height.

     $cells: up to two of
         caption - text above the space (e.g. "For Hotel Pallav")
         image   - path to a signature image, sitting on the rule
         name    - bold line under the rule (omit for a bare rule such as Date)
         lines   - muted lines under the name
     $top: space above the block, in px (default 20) --}}
@php
    $cells = array_values($cells ?? []);
    $cells = array_pad($cells, 2, null);
    $top = $top ?? 20;
@endphp

<table class="sig avoid-break" style="margin-top:{{ $top }}px;">
    <tr>
        @foreach([0, 1] as $i)
            @if($i === 1)<td class="sig-gap"></td>@endif
            <td class="sig-space">
                @if($cells[$i])
                    @if(! empty($cells[$i]['caption']))
                        <div class="sig-caption">{!! $cells[$i]['caption'] !!}</div>
                    @endif
                    @if(! empty($cells[$i]['image']) && file_exists(public_path('storage/'.$cells[$i]['image'])))
                        <img src="{{ public_path('storage/'.$cells[$i]['image']) }}" style="max-height:40px;">
                    @endif
                @endif
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach([0, 1] as $i)
            @if($i === 1)<td class="sig-gap"></td>@endif
            <td class="sig-under {{ $cells[$i] ? '' : 'sig-empty' }}">
                @if($cells[$i])
                    @if(! empty($cells[$i]['name']))<div class="sig-name">{{ $cells[$i]['name'] }}</div>@endif
                    @foreach(($cells[$i]['lines'] ?? []) as $line)
                        @if($line)<div class="sig-note">{{ $line }}</div>@endif
                    @endforeach
                @endif
            </td>
        @endforeach
    </tr>
</table>
