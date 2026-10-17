<?php

namespace Tests;

use App\Support\TestDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Runs before RefreshDatabase/DatabaseMigrations traits can execute.
        TestDatabaseGuard::assertSafe($app->environment(), $app['config']->get('database'));
        $actual = $app['db']->connection()->selectOne('SELECT DATABASE() AS name')->name;
        if ($actual !== TestDatabaseGuard::DATABASE) {
            throw new \RuntimeException('The effective test database failed verification.');
        }
        $root = $app->storagePath('framework/testing/'.getmypid());
        $app['config']->set('filesystems.disks.local.root', $root.'/private');
        $app['config']->set('filesystems.disks.local_published.root', $root.'/published');
        $app['config']->set('session.files', $root.'/sessions');

        return $app;
    }
}
