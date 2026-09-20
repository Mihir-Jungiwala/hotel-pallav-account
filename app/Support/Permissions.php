<?php

namespace App\Support;

/**
 * Everything an account can be allowed to do, in the words of the business.
 *
 * A permission is "<module>.<action>", e.g. "revenue.create". Modules are the
 * screens of the app; actions are what can be done on that screen. Sensitive
 * actions (deleting money records, touching accounts, changing the system) are
 * kept apart so the risk of a role is obvious at a glance.
 *
 * The list lives in code because it follows the screens, not the data. What
 * each role may do with it is in the database, and can be changed on screen.
 */
class Permissions
{
    /** Actions, in the order they read: look, add, change, remove, print. */
    public const ACTIONS = [
        'view' => ['label' => 'View', 'hint' => 'Open the screen and read it'],
        'create' => ['label' => 'Add', 'hint' => 'Record something new'],
        'edit' => ['label' => 'Edit', 'hint' => 'Change what is already there'],
        'delete' => ['label' => 'Delete', 'hint' => 'Remove a record for good', 'sensitive' => true],
        'export' => ['label' => 'PDF', 'hint' => 'Download or print a document'],
    ];

    /**
     * The modules, grouped the way the sidebar groups them.
     *
     * 'routes' are the route-name prefixes the module owns, which is how a
     * request is matched to a permission.
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'group' => 'Daily work',
                'about' => 'The day at a glance: money in and out, and what still needs doing.',
                'actions' => ['view'], 'routes' => ['dashboard'],
            ],
            'revenue' => [
                'label' => 'Revenue', 'icon' => 'bi-cash-coin', 'group' => 'Daily work',
                'about' => 'Cash paid in, in the Hotel and Food books.',
                'actions' => ['view', 'create', 'edit', 'delete', 'export'], 'routes' => ['revenue'],
            ],
            'expense' => [
                'label' => 'Expenses', 'icon' => 'bi-cash-stack', 'group' => 'Daily work',
                'about' => 'Cash paid out: withdrawals, spending and staff advances.',
                'actions' => ['view', 'create', 'edit', 'delete', 'export'], 'routes' => ['expense'],
            ],
            'handover' => [
                'label' => 'Handover', 'icon' => 'bi-arrow-left-right', 'group' => 'Daily work',
                'about' => 'Cash counted at the end of a shift, with notes for the next one.',
                'actions' => ['view', 'create', 'edit', 'delete', 'export'], 'routes' => ['shift-handover'],
            ],
            'bills' => [
                'label' => 'Bill Master', 'icon' => 'bi-receipt', 'group' => 'Daily work',
                'about' => 'Guest and company bills, advances and what is still owed.',
                'actions' => ['view', 'create', 'edit', 'delete', 'export'], 'routes' => ['bill-master'],
            ],
            'companies' => [
                'label' => 'Company Profiles', 'icon' => 'bi-buildings', 'group' => 'Daily work',
                'about' => 'The companies bills are raised against.',
                'actions' => ['view', 'create', 'edit', 'delete'], 'routes' => ['company'],
            ],

            'payroll' => [
                'label' => 'Payroll', 'icon' => 'bi-people-fill', 'group' => 'Staff and money',
                'about' => 'Staff, attendance, salaries, advances and letters.',
                'actions' => ['view', 'create', 'edit', 'delete', 'export'], 'routes' => ['payroll'],
            ],
            'reports' => [
                'label' => 'Reports', 'icon' => 'bi-file-earmark-bar-graph', 'group' => 'Staff and money',
                'about' => 'Account reports across every book.',
                'actions' => ['view', 'export'], 'routes' => ['reports'],
            ],

            'users' => [
                'label' => 'User Accounts', 'icon' => 'bi-person-badge', 'group' => 'Administration',
                'about' => 'Who can sign in, and what each account may do.',
                'actions' => ['view', 'create', 'edit', 'delete'], 'routes' => ['users'],
                'sensitive' => true,
            ],
            'access' => [
                'label' => 'Roles and Permissions', 'icon' => 'bi-diagram-3', 'group' => 'Administration',
                'about' => 'The ladder of roles and what each one is allowed to do.',
                'actions' => ['view', 'edit'], 'routes' => ['access'],
                'sensitive' => true,
            ],
            'masters' => [
                'label' => 'Master Data', 'icon' => 'bi-list-check', 'group' => 'Administration',
                'about' => 'The lists every form offers: shifts, expense heads, sources.',
                'actions' => ['view', 'edit'], 'routes' => ['masters'],
                'sensitive' => true,
            ],
        ];
    }

    /**
     * Powers that are not a screen: one-off abilities that change how the
     * system treats the person holding them.
     */
    public static function abilities(): array
    {
        return [
            'users.password' => [
                'label' => 'Reset other people\'s passwords', 'icon' => 'bi-key',
                'about' => 'Set a new password on another account, which signs that person out.',
                'sensitive' => true,
            ],
            'users.activity' => [
                'label' => 'See the activity log', 'icon' => 'bi-clock-history',
                'about' => 'Every sign-in and every change made to an account.',
            ],
            'system.backdate' => [
                'label' => 'Choose an entry\'s date and time', 'icon' => 'bi-calendar-event',
                'about' => 'Without this, every entry is stamped with the moment it was saved.',
                'sensitive' => true,
            ],
            'system.force' => [
                'label' => 'Use force mode', 'icon' => 'bi-lightning-charge-fill',
                'about' => 'Turn off field checks and record locks for your own account. Every override is logged.',
                'sensitive' => true,
            ],
        ];
    }

    /** Every permission string that exists. @return list<string> */
    public static function all(): array
    {
        $all = [];
        foreach (self::modules() as $key => $module) {
            foreach ($module['actions'] as $action) {
                $all[] = "{$key}.{$action}";
            }
        }

        return array_merge($all, array_keys(self::abilities()));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /** Is this one of the permissions that deserves a second thought? */
    public static function isSensitive(string $permission): bool
    {
        $abilities = self::abilities();
        if (isset($abilities[$permission])) {
            return (bool) ($abilities[$permission]['sensitive'] ?? false);
        }

        [$module, $action] = array_pad(explode('.', $permission, 2), 2, '');

        return (bool) (self::ACTIONS[$action]['sensitive'] ?? false)
            || (self::modules()[$module]['sensitive'] ?? false) && $action !== 'view';
    }

    /** "Delete a Revenue record", for a log entry or a confirmation. */
    public static function label(string $permission): string
    {
        $abilities = self::abilities();
        if (isset($abilities[$permission])) {
            return $abilities[$permission]['label'];
        }

        [$module, $action] = array_pad(explode('.', $permission, 2), 2, '');
        $modules = self::modules();

        if (! isset($modules[$module])) {
            return $permission;
        }

        return (self::ACTIONS[$action]['label'] ?? ucfirst($action)).' '.$modules[$module]['label'];
    }

    /**
     * The permission a request needs, worked out from its route name and
     * method. Anything this does not recognise is left to the role ceiling
     * (read-only accounts cannot write, and so on).
     */
    public static function forRequest(?string $routeName, string $method): ?string
    {
        if (! $routeName) {
            return null;
        }

        $module = self::moduleForRoute($routeName);
        if (! $module) {
            return null;
        }

        $actions = self::modules()[$module]['actions'];
        $action = self::actionFor($routeName, $method);

        // A module without that action falls back to the nearest one it has:
        // an edit on a view-only module still needs something to check.
        foreach ([$action, 'edit', 'create', 'view'] as $candidate) {
            if (in_array($candidate, $actions, true)) {
                return "{$module}.{$candidate}";
            }
        }

        return null;
    }

    public static function moduleForRoute(string $routeName): ?string
    {
        foreach (self::modules() as $key => $module) {
            foreach ($module['routes'] as $prefix) {
                if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                    return $key;
                }
            }
        }

        return null;
    }

    private static function actionFor(string $routeName, string $method): string
    {
        $tail = last(explode('.', $routeName));

        return match (true) {
            in_array($tail, ['view', 'pdf', 'download', 'slip', 'print'], true) => 'export',
            $method === 'DELETE' => 'delete',
            in_array($method, ['PUT', 'PATCH'], true) => 'edit',
            $method === 'POST' => in_array($tail, ['update', 'toggle', 'move', 'rename'], true) ? 'edit' : 'create',
            default => 'view',
        };
    }
}
