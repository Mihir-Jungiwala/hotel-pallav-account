<?php

namespace App\Support;

use Composer\Script\Event;

/**
 * Composer steps that run inside PHP instead of starting another process.
 *
 * The stock "@php artisan package:discover" step launches a child process. Some
 * shared hosts switch that PHP function off (proc_open), and "composer install"
 * then dies before it finishes. Building the package manifest directly gives the
 * same result without needing one.
 */
class ComposerHooks
{
    public static function discoverPackages(Event $event): void
    {
        $vendor = $event->getComposer()->getConfig()->get('vendor-dir');

        require_once $vendor.'/autoload.php';

        $app = require dirname($vendor).'/bootstrap/app.php';

        $app->make(\Illuminate\Foundation\PackageManifest::class)->build();

        $event->getIO()->write('<info>Discovered Package: packages rebuilt</info>');
    }
}
