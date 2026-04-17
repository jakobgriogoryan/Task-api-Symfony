<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Recreate the SQLite test database once per suite so integration tests start
// from a clean schema. Per-test isolation is provided by DAMADoctrineTestBundle
// which wraps every test in a rollback transaction.
$projectDir = dirname(__DIR__);
$console = $projectDir.'/bin/console';

$dbFile = $projectDir.'/var/data_test.db';
if (is_file($dbFile)) {
    @unlink($dbFile);
}

passthru("php {$console} doctrine:database:create --if-not-exists --env=test --quiet 2>/dev/null");
passthru("php {$console} doctrine:schema:create --env=test --quiet 2>/dev/null");
