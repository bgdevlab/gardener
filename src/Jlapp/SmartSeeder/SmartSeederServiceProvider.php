<?php

namespace Jlapp\SmartSeeder;

use App;
use Illuminate\Support\ServiceProvider;

class SmartSeederServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Boot.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/seeds.php' => config_path('seeds.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/seeds.php', 'seeds'
        );

        $this->app->singleton('seed.repository', function ($app) {
            return new SmartSeederRepository($app['db'], config('seeds.table'));
        });

        $this->app->singleton('seed.migrator', function ($app) {
            return new SeedMigrator($app['seed.repository'], $app['db'], $app['files']);
        });

        $this->app->bind('command.seed', function ($app) {
            return new SeedOverrideCommand($app['seed.migrator']);
        });

        $this->app->bind('seed.run', function ($app) {
            return new SeedCommand($app['seed.migrator']);
        });

        $this->app->bind('seed.install', function ($app) {
            return new SeedInstallCommand($app['seed.repository']);
        });

        $this->app->bind('seed.make', function () {
            return new SeedMakeCommand();
        });

        $this->app->bind('seed.reset', function ($app) {
            return new SeedResetCommand($app['seed.migrator']);
        });

        $this->app->bind('seed.rollback', function ($app) {
            return new SeedRollbackCommand($app['seed.migrator']);
        });

        $this->app->bind('seed.refresh', function () {
            return new SeedRefreshCommand();
        });

        $this->app->singleton('seed.status', function ($app) {
            return new SeedStatusCommand($app['seed.migrator'], $app['seed.repository']);
        });

        $this->commands([
            'seed.run',
            'seed.install',
            'seed.make',
            'seed.reset',
            'seed.rollback',
            'seed.refresh',
            'seed.status',
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'seed.repository',
            'seed.migrator',
            'command.seed',
            'seed.run',
            'seed.install',
            'seed.make',
            'seed.reset',
            'seed.rollback',
            'seed.refresh',
            'seed.status',
        ];
    }
}
