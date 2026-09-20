<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Support\CashLedger;
use App\Support\PayrollPdf;
use App\Support\Masters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyProfileController extends Controller
{
    private const FIELDS = [
        'name', 'email', 'mobile_number', 'phone_number', 'country', 'nationality', 'pincode', 'gst_number', 'address',
        'discount_percentage', 'gst_percentage', 'tcs_percentage', 'tds_percentage', 'instruction',
    ];

    /** The roles a contact can have, from Master Data. */
    private function roles(): array
    {
        return Masters::values('company_contact_role');
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
            'contacts' => CompanyProfile::all()->sum(fn ($c) => count($c->contacts ?? [])),
            'month' => CompanyProfile::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('company.index', [
            'companies' => $companies, 'roles' => $this->roles(), 'forms' => $forms, 'blank' => $blank,
            'reopen' => $reopen, 'stats' => $stats, 'q' => $q,
        ]);
    }

    /** Every word must appear somewhere on the company (name, contacts, GST, address...); "quotes" keep a phrase together. */
    private function search($query, string $q)
    {
        if ($q === '') {
            return $query;
        }

        $columns = [...self::FIELDS, 'contacts'];
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

    private function formData(?CompanyProfile $c): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $c?->{$field};
        }
        $values['contacts'] = array_values($c?->contacts ?? []);

        return [
            'id' => $c?->id,
            'title' => $c ? 'Edit '.$c->name : 'Add Company',
            'subtitle' => $c ? 'Company profile · added '.$c->created_at?->format('d M Y').($c->creator ? ' by '.$c->creator->name : '') : null,
            'action' => $c ? route('company.update', $c) : route('company.store'),
            'destroy' => $c ? route('company.destroy', $c) : null,
            'pdf' => $c ? route('company.view', $c) : null,
            'name' => $c?->name,
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
        $data['values']['contacts'] = array_values((array) old('contacts', $data['values']['contacts']));

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

        // At least one person to contact, and each one needs a name
        $rules['contacts'] = ['required', 'array', 'min:1', 'max:30'];
        $rules['contacts.*.role'] = ['nullable', 'string', 'max:60'];
        $rules['contacts.*.name'] = ['required', 'string', 'max:100'];
        $rules['contacts.*.email'] = ['nullable', 'email', 'max:254'];
        $rules['contacts.*.mobile'] = ['nullable', 'string', 'max:15'];

        return $rules;
    }

    private function messages(): array
    {
        return [
            'contacts.required' => 'Add at least one person to contact.',
            'contacts.min' => 'Add at least one person to contact.',
            'contacts.*.name.required' => 'Every contact person needs a name.',
        ];
    }

    /** The contact rows that carry something, tidied; empty rows the form left behind are dropped. */
    private function people(array $rows): array
    {
        return collect($rows)->map(fn ($p) => [
            'role' => trim((string) ($p['role'] ?? '')), 'name' => trim((string) ($p['name'] ?? '')),
            'email' => trim((string) ($p['email'] ?? '')), 'mobile' => trim((string) ($p['mobile'] ?? '')),
        ])->filter(fn ($p) => $p['name'] !== '' || $p['email'] !== '' || $p['mobile'] !== '')->values()->all();
    }

    /** Rows the form left empty are dropped before checking, so "one is required" means one real person. */
    private function tidyContacts(Request $request): void
    {
        $request->merge(['contacts' => $this->people((array) $request->input('contacts', []))]);
    }

    public function store(Request $request)
    {
        $this->tidyContacts($request);
        $data = $request->validate($this->rules(), $this->messages());

        if (! empty($data['gst_number']) && CompanyProfile::where('gst_number', $data['gst_number'])->exists()) {
            return back()->withErrors(['gst_number' => 'A company with this GST number already exists.'])->withInput();
        }

        $data['contacts'] = $this->people($data['contacts'] ?? []);
        $data['created_by'] = Auth::id();
        $company = CompanyProfile::create($data);

        return back()->with('success', $company->name.' added to Company Profiles.');
    }

    public function update(Request $request, CompanyProfile $company)
    {
        $this->tidyContacts($request);
        $data = $request->validate($this->rules($company), $this->messages());

        if (! empty($data['gst_number']) && CompanyProfile::where('gst_number', $data['gst_number'])->where('id', '!=', $company->id)->exists()) {
            return back()->withErrors(['gst_number' => 'A company with this GST number already exists.'])->withInput();
        }

        $data['contacts'] = $this->people($data['contacts'] ?? []);
        $data['modified_by'] = Auth::id();
        $company->update($data);

        return back()->with('success', $company->name.' updated.');
    }

    /** The profile as a sheet to share or print, on the payroll document shell. */
    public function view(CompanyProfile $company)
    {
        $company->loadMissing('creator');

        return PayrollPdf::make('company.pdf', ['record' => $company])
            ->stream('company-'.Str::slug($company->name).'.pdf');
    }

    public function destroy(CompanyProfile $company)
    {
        $company->delete();

        return back()->with('success', $company->name.' deleted.');
    }
}
