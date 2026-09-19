{{-- One pop-up for new and edited handovers, in two steps (shift and cash,
     then notes and instructions) so nothing needs scrolling. shift-handover.js fills it from the JSON on the page:
     blank, a record, or what was typed when a save was refused. --}}
@php
    use App\Models\ShiftHandover;

    // Only a thin strip of each note's own colour, so the eye finds the row
    $noteColours = [
        'd500' => '#7C7F6E', 'd200' => '#E9A23B', 'd100' => '#8E7CC3', 'd50' => '#2FA8CF',
        'd20' => '#9DB83C', 'd10' => '#9A5B3A', 'd5' => '#3F9A5C', 'coins' => '#C99A1C',
    ];
@endphp

<div class="modal fade pay-form-modal sh-modal" id="handoverModal" tabindex="-1" aria-labelledby="handoverModalTitle" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('shift-handover.store') }}" id="handoverForm">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled data-method>
            <input type="hidden" name="_form" value="handover">
            <input type="hidden" name="_handover_id" value="" data-record-id>

            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow" data-subtitle>Handover &middot; New</div>
                    <h5 class="modal-title" id="handoverModalTitle" data-title>New Handover</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div data-wizard>
                    {{-- 1. Who and when --}}
                    <div class="form-step" data-step="Shift and cash">
                        <div class="form-section">
                            <div class="fs-head">
                                <div class="fs-title"><i class="bi bi-clock"></i> Which shift, and when</div>
                                <div class="fs-hint">The shift that is ending and handing over the till.</div>
                            </div>
                            <div class="row g-3">
                                @include('partials._entry-stamp', ['col' => 'col-sm-7'])
                                <div class="col-sm-5">
                                    @include('partials._option-field', ['key' => 'shift', 'name' => 'shift', 'value' => $blank['shift'], 'label' => 'Shift', 'required' => true])
                                </div>
                            </div>
                        </div>

                        {{-- The till --}}
                        <div class="form-section">
                            <div class="fs-head sh-fs-row">
                                <div>
                                    <div class="fs-title"><i class="bi bi-cash-coin"></i> Count the cash</div>
                                    <div class="fs-hint">Tap a note to add one, or type how many. <kbd>Enter</kbd> moves to the next.</div>
                                </div>
                                <button type="button" class="sh-link-btn" data-clear-count><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                            </div>
                            <div class="cash-grid">
                                @foreach(ShiftHandover::DENOMINATIONS as $denom => $value)
                                    @php $isCoin = $denom === 'coins'; @endphp
                                    <div class="cash-tile {{ $isCoin ? 'coin' : '' }}" data-denom="{{ $value }}" style="--note:{{ $noteColours[$denom] }};">
                                        <button type="button" class="cash-face" data-add title="{{ $isCoin ? 'Add ₹1 in coins' : 'Add one ₹'.$value.' note' }}" tabindex="-1">
                                            @if($isCoin)
                                                <span class="cash-coin"><i class="bi bi-coin"></i></span>
                                                <span class="cash-face-text"><strong>Coins</strong><small>total value</small></span>
                                            @else
                                                <span class="cash-face-val">₹{{ $value }}</span>
                                            @endif
                                            <span class="cash-face-plus"><i class="bi bi-plus-lg"></i></span>
                                        </button>
                                        <div class="cash-controls">
                                            <div class="sh-stepper">
                                                <button type="button" data-step="-1" aria-label="One less" tabindex="-1"><i class="bi bi-dash"></i></button>
                                                <input type="number" name="{{ $denom }}_count" min="0" max="1000000" step="1" inputmode="numeric"
                                                       value="0" class="form-control" aria-label="{{ ShiftHandover::denominationLabel($denom) }} {{ $isCoin ? 'value' : 'count' }}">
                                                <button type="button" data-step="1" aria-label="One more" tabindex="-1"><i class="bi bi-plus"></i></button>
                                            </div>
                                            <span class="cash-amount" data-amount>₹0</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- 2. Notes (formatted) and special instructions, side by side --}}
                    <div class="form-step" data-step="Notes and instructions">
                        <div class="sh-two">
                        <div class="form-section">
                            <div class="fs-head">
                                <div class="fs-title"><i class="bi bi-sticky"></i> Notes for the next shift <span class="sh-count" data-note-count>0</span></div>
                                <div class="fs-hint">Each line is one point. <kbd>Enter</kbd> starts the next point, <kbd>Shift</kbd> + <kbd>Enter</kbd> breaks a line inside one.</div>
                            </div>
                            <div class="rt" data-points-editor>
                                <div class="rt-toolbar" role="toolbar" aria-label="Formatting">
                                    <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
                                    <button type="button" data-cmd="strikeThrough" title="Strike through, e.g. a done point"><i class="bi bi-type-strikethrough"></i></button>
                                    <span class="rt-sep"></span>
                                    <button type="button" data-cmd="link" title="Link"><i class="bi bi-link-45deg"></i></button>
                                    <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="bi bi-eraser"></i></button>
                                    <span class="rt-sep"></span>
                                    <button type="button" data-cmd="undo" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <button type="button" data-cmd="redo" title="Redo (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
                                </div>
                                <div class="rt-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Notes for the next shift"><ul><li><br></li></ul></div>
                                <div data-note-inputs hidden></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="fs-head">
                                <div class="fs-title sh-warn-title"><i class="bi bi-exclamation-triangle"></i> Special instructions <span class="sh-count warn" data-instruction-count>0</span></div>
                                <div class="fs-hint">Things the next shift must not miss. They are highlighted on the Handover and its PDF. <kbd>Enter</kbd> adds the next one.</div>
                            </div>
                            <ol class="sh-point-list" data-point-list></ol>
                            <template data-point-template>
                                <li class="sh-point">
                                    <span class="sh-point-no"></span>
                                    <input type="text" name="instructions[]" class="form-control" maxlength="2000" placeholder="VIP guest in 301, keep the suite ready by 11 am">
                                    <button type="button" class="sh-point-remove" data-point-remove title="Remove" aria-label="Remove instruction"><i class="bi bi-x-lg"></i></button>
                                </li>
                            </template>
                            <button type="button" class="btn btn-outline-p btn-sm sh-add-point" data-point-add><i class="bi bi-plus-lg"></i> Add instruction</button>
                        </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer sh-modal-foot">
                <div class="sh-foot-total">
                    <span class="sh-foot-label">Total cash <span data-pieces></span></span>
                    <strong data-total>₹0.00</strong>
                    <span class="sh-foot-words" data-words aria-live="polite">Zero Rupees Only</span>
                </div>
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-p" data-busy-label="Saving…" data-save-label><i class="bi bi-check2"></i> Save Handover</button>
            </div>
        </form>
    </div></div>
</div>
