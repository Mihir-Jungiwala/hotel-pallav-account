<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Support\CashLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CompanyProfileController extends Controller
{
    /** The people kept on file for each company, in the order the form shows them. */
    public const CONTACTS = [
        'md_one' => 'Managing Director 1', 'md_second' => 'Managing Director 2',
        'hr_head' => 'HR Head', 'assistant_hr' => 'Assistant HR',
        'accountant_head' => 'Accountant Head', 'accountant_assistant_one' => 'Accountant Assistant 1',
        'accountant_assistant_two' => 'Accountant Assistant 2',
    ];

    private const FIELDS = [
        'name', 'email', 'mobile_number', 'phone_number', 'country', 'nationality', 'pincode', 'gst_number', 'address',
        'discount_percentage', 'gst_percentage', 'tcs_percentage', 'tds_percentage', 'instruction',
    ];

    /** Every column a form carries: the company's own and each contact's name, email and mobile. */
    private function allFields(): array
    {
        return [...self::FIELDS, ...collect(array_keys(self::CONTACTS))->flatMap(fn ($k) => ["{$k}_name", "{$k}_email", "{$k}_mobile"])->all()];
    }

    public function index()
    {
        $q = CashLedger::query();

        $companies = $this->search(CompanyProfile::query(), $q)->orderBy('name')->paginate(CashLedger::per())->withQueryString();

        $forms = $companies->mapWithKeys(fn ($c) => [$c->id => $this->formData($c)]);
        $blank = $this->formData(null);
        $reopen = old('_form') === 'company' ? $this->formFromOldInput() : null;

        $stats = [
            'total' => CompanyProfile::count(),
            'withGst' => CompanyProfile::whereNotNull('gst_number')->where('gst_number', '!=', '')->count(),
            'contacts' => CompanyProfile::all()->sum(fn ($c) => $this->contactCount($c)),
            'month' => CompanyProfile::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('company.index', [
            'companies' => $companies, 'contacts' => self::CONTACTS, 'forms' => $forms, 'blank' => $blank,
            'reopen' => $reopen, 'stats' => $stats, 'q' => $q,
        ]);
    }

    /** Every word must appear somewhere on the company (name, contacts, GST, address...); "quotes" keep a phrase together. */
    private function search($query, string $q)
    {
        if ($q === '') {
            return $query;
        }

        $columns = $this->allFields();
        preg_match_all('/"[^"]+"|\S+/u', $q, $found);

        foreach ($found[0] as $token) {
            $like = '%'.addcslashes(trim($token, '"'), '%_\\').'%';
            $query->where(function ($w) use ($columns, $like) {
                foreach ($columns as $column) {
                    $w->orWhere($column, 'like', $like);
                }
            });
        }

        return $query;
    }

    public function contactCount(CompanyProfile $c): int
    {
        return collect(array_keys(self::CONTACTS))->filter(fn ($k) => filled($c->{"{$k}_name"}))->count();
    }

    private function formData(?CompanyProfile $c): array
    {
        $values = [];
        foreach ($this->allFields() as $field) {
            $values[$field] = $c?->{$field};
        }

        return [
            'id' => $c?->id,
            'title' => $c ? 'Edit '.$c->name : 'Add Company',
            'subtitle' => $c ? 'Company profile · added '.$c->created_at?->format('d M Y').($c->creator ? ' by '.$c->creator->name : '') : null,
            'action' => $c ? route('company.update', $c) : route('company.store'),
            'values' => $values,
        ];
    }

    /** What was typed when a save was refused, so the pop-up comes back as it was. */
    private function formFromOldInput(): array
    {
        $record = ($id = old('_company_id')) ? CompanyProfile::find($id) : null;
        $data = $this->formData($record);

        foreach (array_keys($data['values']) as $field) {
            if (old($field) !== null) {
                $data['values'][$field] = old($field);
            }
        }

        return $data;
    }

    private function rules(?CompanyProfile $company = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique('company_profiles', 'name')->ignore($company?->id)],
            'address' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'mobile_number' => ['nullable', 'string', 'max:15'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'gst_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'tcs_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'tds_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'instruction' => ['nullable', 'string'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ];

        foreach (array_keys(self::CONTACTS) as $contact) {
            $rules["{$contact}_name"] = ['nullable', 'string', 'max:100'];
            $rules["{$contact}_email"] = ['nullable', 'email', 'max:254'];
            $rules["{$contact}_mobile"] = ['nullable', 'string', 'max:15'];
        }

        return $rules;
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if (! empty($data['gst_number']) && CompanyProfile::where('gst_number', $data['gst_number'])->exists()) {
            return back()->withErrors(['gst_number' => 'A company with this GST number already exists.'])->withInput();
        }

        $data['created_by'] = Auth::id();
        $company = CompanyProfile::create($data);

        return back()->with('success', $company->name.' added to Company Profiles.');
    }

    public function update(Request $request, CompanyProfile $company)
    {
        $data = $request->validate($this->rules($company));

        if (! empty($data['gst_number']) && CompanyProfile::where('gst_number', $data['gst_number'])->where('id', '!=', $company->id)->exists()) {
            return back()->withErrors(['gst_number' => 'A company with this GST number already exists.'])->withInput();
        }

        $data['modified_by'] = Auth::id();
        $company->update($data);

        return back()->with('success', $company->name.' updated.');
    }

    public function destroy(CompanyProfile $company)
    {
        $company->delete();

        return back()->with('success', $company->name.' deleted.');
    }
}
