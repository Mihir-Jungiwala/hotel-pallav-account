{{-- Table footer: what you are looking at, how much of it fits on a page,
     and the page controls. The rows-per-page menu is drawn in the page, not
     by the operating system, so it carries the theme like everything else. --}}
<div class="pms-pager" id="{{ $id }}">
    <div class="pg-left">
        <span class="pg-info"></span>
        <span class="pg-size">
            <span class="pg-size-label">Rows</span>
            <select aria-label="Rows per page" data-native data-pms-select>
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </span>
    </div>
    <div class="pg-controls"></div>
</div>
