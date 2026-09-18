<?php

namespace App\Http\Controllers;

use App\Models\OptionItem;
use App\Models\OptionSet;
use App\Models\UserAuditLog;
use App\Support\Masters;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Master Data: the lists every form offers. Only the SuperAdmin edits them;
 * everyone else simply picks from what is here.
 */
class MasterDataController extends Controller
{
    public function index(Request $request)
    {
        $sets = OptionSet::with('items')->orderBy('sort_order')->orderBy('name')->get();
        $current = $sets->firstWhere('key', $request->string('set')->toString()) ?? $sets->first();

        return view('masters.index', compact('sets', 'current'));
    }

    /* ------------------------------------------------------------------ sets */

    public function storeSet(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:160'],
            'icon' => ['nullable', 'string', 'max:40'],
            'input' => ['required', Rule::in(array_keys(OptionSet::INPUTS))],
        ]);

        $data['key'] = $this->uniqueKey($data['name']);
        $data['icon'] = ($data['icon'] ?? null) ?: 'bi-list-ul';
        $data['sort_order'] = (int) OptionSet::max('sort_order') + 1;

        $set = OptionSet::create($data);
        $this->audit('master.set_created', $set->name);

        return redirect()->route('masters.index', ['set' => $set->key])->with('success', "List \"{$set->name}\" created.");
    }

    public function updateSet(Request $request, OptionSet $set)
    {
        $set->update($request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:160'],
            'icon' => ['nullable', 'string', 'max:40'],
            'input' => ['required', Rule::in(array_keys(OptionSet::INPUTS))],
        ]));

        $this->audit('master.set_updated', $set->name);
        Masters::flush();

        return back()->with('success', 'List updated.');
    }

    public function destroySet(OptionSet $set)
    {
        if ($set->is_system) {
            return back()->with('error', 'This list is used by the app itself and cannot be removed. You can still change what is in it.');
        }

        $name = $set->name;
        $set->delete();
        $this->audit('master.set_deleted', $name);
        Masters::flush();

        return redirect()->route('masters.index')->with('success', "List \"{$name}\" removed.");
    }

    /* ----------------------------------------------------------------- items */

    public function storeItem(Request $request, OptionSet $set)
    {
        $data = $this->itemRules($request, $set);

        $data['option_set_id'] = $set->id;
        $data['sort_order'] = (int) $set->items()->max('sort_order') + 1;

        $item = OptionItem::create($data);
        $this->applyDefault($set, $item);

        $this->audit('master.item_created', "{$set->name}: {$item->label}");
        Masters::flush();

        return back()->with('success', "\"{$item->label}\" added to {$set->name}.");
    }

    public function updateItem(Request $request, OptionItem $item)
    {
        $set = $item->set;
        $item->update($this->itemRules($request, $set, $item));
        $this->applyDefault($set, $item);

        $this->audit('master.item_updated', "{$set->name}: {$item->label}");
        Masters::flush();

        return back()->with('success', 'Option updated.');
    }

    public function toggleItem(OptionItem $item)
    {
        $item->update(['is_active' => ! $item->is_active]);
        $this->audit('master.item_updated', $item->set->name.': '.$item->label);
        Masters::flush();

        return back()->with('success', $item->is_active
            ? "\"{$item->label}\" is offered on forms again."
            : "\"{$item->label}\" is hidden from forms. Records that already use it keep it.");
    }

    public function moveItem(Request $request, OptionItem $item)
    {
        $direction = $request->string('direction')->toString() === 'up' ? 'up' : 'down';
        $items = $item->set->items()->get()->values();
        $index = $items->search(fn ($row) => $row->is($item));
        $swapWith = $items[$direction === 'up' ? $index - 1 : $index + 1] ?? null;

        if ($swapWith) {
            [$a, $b] = [$item->sort_order, $swapWith->sort_order];
            $item->update(['sort_order' => $b]);
            $swapWith->update(['sort_order' => $a]);
            Masters::flush();
        }

        return back();
    }

    public function destroyItem(OptionItem $item)
    {
        $set = $item->set;
        $label = $item->label;
        $item->delete();

        $this->audit('master.item_deleted', "{$set->name}: {$label}");
        Masters::flush();

        return back()->with('success', "\"{$label}\" removed. Records that already use it are untouched.");
    }

    /* ---------------------------------------------------------------- helpers */

    private function itemRules(Request $request, OptionSet $set, ?OptionItem $item = null): array
    {
        // A blank value falls back to the label, so the duplicate check has to
        // run against the value that will actually be stored
        if (! $request->filled('value')) {
            $request->merge(['value' => $request->string('label')->toString()]);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'value' => ['nullable', 'string', 'max:60',
                Rule::unique('option_items', 'value')
                    ->where('option_set_id', $set->id)
                    ->ignore($item?->id)],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['value'] = ($data['value'] ?? null) ?: $data['label'];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    /** Only one option in a list can be the one pre-selected on a form. */
    private function applyDefault(OptionSet $set, OptionItem $item): void
    {
        if ($item->is_default) {
            $set->items()->whereKeyNot($item->id)->update(['is_default' => false]);
        }
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_');
        $key = $base;
        $suffix = 2;

        while (OptionSet::where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    private function audit(string $action, string $what): void
    {
        UserAuditLog::record($action, auth()->user(), ['item' => $what]);
    }
}
