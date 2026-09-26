<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollCompany extends Model
{
    protected $guarded = ['id'];

    /**
     * Payroll runs for exactly these two companies. They are fixed: nobody adds,
     * renames, deactivates or deletes one. Only their details (address, logo,
     * signatory and so on, which print on letters and slips) can be edited.
     */
    public const FIXED = [
        ['name' => 'Hotel Pallav', 'code' => 'HP01'],
        ['name' => 'Pallav Food', 'code' => 'PF01'],
    ];

    /**
     * Both places that list the companies - the sidebar dropdown and the Company
     * Listing page - order them by this fixed order, so switching between them
     * never shows the two companies in a different order.
     */
    public static function inFixedOrder($query)
    {
        $codes = array_column(self::FIXED, 'code');

        return $query->orderByRaw('CASE code '.implode(' ', array_map(
            fn ($code, $i) => "WHEN '{$code}' THEN {$i}", $codes, array_keys($codes)
        )).' ELSE '.count($codes).' END');
    }

    /**
     * Pallav Food cooks for both companies and alone decides the price. Hotel
     * Pallav's staff eat there and Hotel Pallav (the owner) pays for it; Pallav
     * Food's own staff eat there too, at the same price, as a cost of its own.
     */
    public const FOOD_PAYER_CODE = 'HP01';

    public const FOOD_PROVIDER_CODE = 'PF01';

    public const FOOD_PAYEE = 'Pallav Food';

    /** Hotel Pallav: owes Pallav Food for its staff's meals. */
    public function paysFoodCharges(): bool
    {
        return $this->code === self::FOOD_PAYER_CODE;
    }

    /** Pallav Food: sets the price. The only company that can. */
    public function providesFood(): bool
    {
        return $this->code === self::FOOD_PROVIDER_CODE;
    }

    /** Either company: its staff can be marked as eating at Pallav Food. */
    public function servesMeals(): bool
    {
        return $this->paysFoodCharges() || $this->providesFood();
    }

    /** The company that decides the price. */
    public static function foodProvider(): ?self
    {
        return static::where('code', self::FOOD_PROVIDER_CODE)->first();
    }

    /** Creates either company if it is missing. Safe to call any number of times. */
    public static function ensureFixed(): void
    {
        foreach (self::FIXED as $company) {
            static::firstOrCreate(['code' => $company['code']], ['name' => $company['name'], 'is_active' => true]);
        }
    }

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendanceStatuses(): HasMany
    {
        return $this->hasMany(AttendanceStatus::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    public function joiningLetter(): HasOne
    {
        return $this->hasOne(JoiningLetter::class);
    }

    public function attendanceMonths(): HasMany
    {
        return $this->hasMany(AttendanceMonth::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(PayrollAdvance::class);
    }

    public function bonusIncentives(): HasMany
    {
        return $this->hasMany(BonusIncentive::class);
    }

    public function salaryProcessings(): HasMany
    {
        return $this->hasMany(SalaryProcessing::class);
    }

    /**
     * A company holding any payroll data may only be deactivated, never deleted.
     */
    public function hasPayrollData(): bool
    {
        return $this->employees()->exists()
            || $this->attendanceStatuses()->exists()
            || $this->deductions()->exists()
            || $this->joiningLetter()->exists()
            || $this->attendanceMonths()->exists()
            || $this->advances()->exists()
            || $this->bonusIncentives()->exists()
            || $this->salaryProcessings()->exists();
    }
}
