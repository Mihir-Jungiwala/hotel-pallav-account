<?php

namespace App\Providers;

use App\Support\ForceMode;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator as ValidatorInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Links and form actions must stay on HTTPS behind a proxy in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // One hook covers every screen: with force mode armed the SuperAdmin's
        // input is taken as typed, so a correction is never refused.
        Validator::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            if (ForceMode::enabled()) {
                $data = ForceMode::fill($data, $rules);
                $rules = ForceMode::relax($rules);
            }

            return new ValidatorInstance($translator, $data, $rules, $messages, $attributes);
        });
    }
}
