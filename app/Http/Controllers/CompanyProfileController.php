<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyProfileController extends Controller
{
    private array $contacts = [
        'md_one', 'md_second', 'hr_head', 'assistant_hr',
        'accountant_head', 'accountant_assistant_one', 'accountant_assistant_two',
    ];

    public function index()
    {
        $companies = CompanyProfile::orderBy('name')->get();

        return view('company.index', ['companies' => $companies, 'contacts' => $this->contacts]);
    }

    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'mobile_number' => ['nullable', 'string', 'max:15'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'discount_percentage' => ['nullable', 'numeric'],
            'gst_percentage' => ['nullable', 'numeric'],
            'tcs_percentage' => ['nullable', 'numeric'],
            'tds_percentage' => ['nullable', 'numeric'],
            'instruction' => ['nullable', 'string'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ];

        foreach ($this->contacts as $contact) {
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
        CompanyProfile::create($data);

        return redirect()->route('company.index')->with('success', 'Company profile created.');
    }

    public function update(Request $request, CompanyProfile $company)
    {
        $data = $request->validate($this->rules());

        if (! empty($data['gst_number']) && CompanyProfile::where('gst_number', $data['gst_number'])->where('id', '!=', $company->id)->exists()) {
            return back()->withErrors(['gst_number' => 'A company with this GST number already exists.'])->withInput();
        }

        $data['modified_by'] = Auth::id();
        $company->update($data);

        return redirect()->route('company.index')->with('success', 'Company profile updated.');
    }

    public function destroy(CompanyProfile $company)
    {
        $company->delete();

        return back()->with('success', 'Company profile deleted.');
    }
}
