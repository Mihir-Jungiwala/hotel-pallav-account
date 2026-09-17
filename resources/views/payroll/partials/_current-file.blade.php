@if(! empty($path))
    @php $url = \Illuminate\Support\Facades\Storage::url($path); @endphp
    <div class="file-current">
        @if(preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $path))
            <img src="{{ $url }}" alt="{{ $label ?? 'Current file' }}">
        @else
            <i class="bi bi-paperclip"></i>
        @endif
        <a href="{{ $url }}" target="_blank">{{ $label ?? 'View current file' }}</a>
        <span class="text-muted">&middot; upload a new one to replace it</span>
    </div>
@endif
