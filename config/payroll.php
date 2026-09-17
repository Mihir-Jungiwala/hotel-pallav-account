<?php

return [
    /*
     * Maximum number of companies that may be created. Inactive companies are
     * not counted towards this limit.
     */
    'max_companies' => env('PAYROLL_MAX_COMPANIES', 5),
];
