<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompany;
use App\Models\PayrollMasterItem;
use App\Models\UserAuditLog;
use App\Support\PayrollContext;
use App\Support\PayrollMasters;
use Illuminate\Http\Request;

/**
 * Payroll Master: the lists payroll forms and actions draw on. SuperAdmin only.
 *
 * It works in whichever scope the payroll menu is in. With no company
 * selected, what is added, changed or removed applies to every company; with
 * a company selected, only to that company. An item can only be changed in
 * the scope it belongs to, so a company page never edits the shared lists by
 * accident.
 */
class PayrollMasterController extends Controller
{
    public function index(Request $request)
    {
        // The scope chooser on the page: "all" or a company id
        if ($request->filled('scope')) {
            $scope = $request->string('scope')->toString();
            $company = $scope === 'all' ? null : PayrollCompany::where('is_active', true)->find($scope);

            $company ? PayrollContext::remember((string) $company->id) : PayrollContext::forget();

            return redirect()->route('payroll.master.index', $request->only('list'));
        }

        $company = PayrollContext::current();
        $key = PayrollMasters::exists($request->string('list')->toString())
            ? $request->string('list')->toString()
            : array_key_first(PayrollMasters::LISTS);

        $items = PayrollMasters::visible($key, $company);

        return view('payroll.pages.master', [
            'company' => $company,
            'companies' => PayrollContext::selectable(),
            'lists' => PayrollMasters::LISTS,
            'key' => $key,
            'definition' => PayrollMasters::LISTS[$key],
            'fixed' => PayrollMasters::isFixed($key),
            // Shown while a free list has nothing of its own, so the page is never blank
            'builtIn' => PayrollMasters::LISTS[$key]['defaults'],
            // Editable here: exactly this scope. Inherited: shared items shown on a company page
            'own' => $items->filter(fn ($i) => $i->payroll_company_id === $company?->id)->values(),
            'inherited' => $company ? $items->filter(fn ($i) => $i->isGlobal())->values() : collect(),
            'counts' => collect(PayrollMasters::LISTS)->map(fn ($d, $k) => PayrollMasterItem::where('list', $k)
                ->where('payroll_company_id', $company?->id)->count()),
        ]);
    }

    public function store(Request $request, string $list)
    {
        abort_unless(PayrollMasters::exists($list), 404);
        abort_if(PayrollMasters::isFixed($list), 403, 'This list is built in.');

        $company = PayrollContext::current();
        $data = $this->validated($request, $list, $company);

        PayrollMasterItem::create($data + [
            'payroll_company_id' => $company?->id,
            'list' => $list,
            'sort_order' => (int) PayrollMasterItem::where('list', $list)->where('payroll_company_id', $company?->id)->max('sort_order') + 1,
        ]);

        $this->audit('payroll_master.added', $list, $data['label']);

        return redirect()->route('payroll.master.index', ['list' => $list])
            ->with('success', $company ? "Added for {$company->name}." : 'Added for every company.');
    }

    public function update(Request $request, PayrollMasterItem $item)
    {
        abort_if(PayrollMasters::isFixed($item->list), 403);

        if ($refusal = $this->outOfScope($item)) {
            return $refusal;
        }

        $item->update($this->validated($request, $item->list, PayrollContext::current(), $item));
        $this->audit('payroll_master.updated', $item->list, $item->label);

        return back()->with('success', 'Saved.');
    }

    public function toggle(PayrollMasterItem $item)
    {
        abort_if(PayrollMasters::isFixed($item->list), 403);

        if ($refusal = $this->outOfScope($item)) {
            return $refusal;
        }

        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', $item->is_active ? 'Switched on.' : 'Switched off.');
    }

    public function destroy(PayrollMasterItem $item)
    {
        abort_if(PayrollMasters::isFixed($item->list), 403);

        if ($refusal = $this->outOfScope($item)) {
            return $refusal;
        }

        $item->delete();
        $this->audit('payroll_master.removed', $item->list, $item->label);

        return back()->with('success', 'Removed.');
    }

    private function validated(Request $request, string $list, ?PayrollCompany $company, ?PayrollMasterItem $ignore = null): array
    {
        $definition = PayrollMasters::LISTS[$list];
        $isEmail = $definition['type'] === 'email';

        $rules = ['label' => ['required', 'string', 'max:120']];

        if ($isEmail) {
            $rules['value'] = ['required', 'email:rfc', 'max:190'];
        }

        $data = $request->validate($rules);

        if ($isEmail) {
            $data['value'] = mb_strtolower(trim($data['value']));
        }

        // The same entry twice in one scope is only ever a slip
        $duplicate = PayrollMasterItem::where('list', $list)
            ->where('payroll_company_id', $company?->id)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->where($isEmail ? 'value' : 'label', $isEmail ? $data['value'] : $data['label'])
            ->exists();

        if ($duplicate) {
            abort(back()->withErrors([$isEmail ? 'value' : 'label' => 'That is already in this list.'])->withInput());
        }

        return $data;
    }

    /** An item is only editable from the scope it lives in. */
    private function outOfScope(PayrollMasterItem $item)
    {
        if ($item->payroll_company_id === PayrollContext::current()?->id) {
            return null;
        }

        return back()->with('error', $item->isGlobal()
            ? 'This one applies to every company. Switch the scope to All companies to change it.'
            : 'This one belongs to another company. Switch to that company to change it.');
    }

    private function audit(string $action, string $list, string $what): void
    {
        UserAuditLog::record($action, auth()->user(), ['list' => $list, 'item' => $what]);
    }
}
