<?php

namespace Jlapp\SmartSeeder;

use App;
use Config;
use File;
use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

class SeedMigrator extends Migrator
{
    use AppNamespaceDetectorTrait;

    /**
     * Create a new migrator instance.
     *
     * @param  \Illuminate\Database\Migrations\MigrationRepositoryInterface $repository
     * @param  \Illuminate\Database\ConnectionResolverInterface             $resolver
     * @param  \Illuminate\Filesystem\Filesystem                            $files
     * @return void
     */
    public function __construct(SmartSeederRepository $repository, Resolver $resolver, Filesystem $files)
    {
        parent::__construct($repository, $resolver, $files);
    }

    /**
     * Set env.
     *
     * @param string $env
     */
    public function setEnv($env)
    {
        $this->repository->setEnv($env);
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param  array   $files
     * @return void
     */
    public function requireFiles(array $files)
    {
        return; // should they be autoloaded ?
        foreach ($files as $file) {
            $file = database_path(config('seeds.dir')).DIRECTORY_SEPARATOR.$file.'.php';
            $this->files->requireOnce($file);
        }
    }
    /**
     * Get all of the migration files in a given path.
     *
     * @param  string $path
     * @return array
     */
    public function getMigrationFiles($path)
    {
        $files = [];

        if (! empty($this->repository->env)) {
            $files = array_merge($files, $this->files->glob("$path/{$this->repository->env}/*.php"));
        }

        $files = array_merge($files, $this->files->glob($path.'/*.php'));

        // Once we have the array of files in the directory we will just remove the
        // extension and take the basename of the file which is all we need when
        // finding the migrations that haven't been run against the databases.
        if ($files === false) {
            return [];
        }

        $files = array_map(function ($file) {
            return str_replace('.php', '', basename($file));
        }, $files);

        // Once we have all of the formatted file names we will sort them and since
        // they all start with a timestamp this should give us the migrations in
        // the order they were actually created by the application developers.
        sort($files);

        return $files;
    }

    /**
     * Run the outstanding migrations at a given path.
     *
     * @param  string $path
     * @param  bool   $pretend
     * @return void
     */
    public function runSingleFile($path, $pretend = false)
    {
        $this->notes = [];
        $file = str_replace('.php', '', basename($path));
        $files = [$file];

        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $ran = $this->repository->getRan();

        $migrations = array_diff($files, $ran);
        $filename_ext = pathinfo($path, PATHINFO_EXTENSION);

        if (! $filename_ext) {
            $path .= '.php';
        }

        $this->files->requireOnce($path);

        $this->runMigrationList($migrations, $pretend);
    }

    /**
     * Run "up" a migration instance.
     *
     * @param  string $file
     * @param  int    $batch
     * @param  bool   $pretend
     * @return void
     */
    protected function runUp($file, $batch, $pretend)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $fullPath = $this->getAppNamespace().$file;
        $migration = new $fullPath();

        if ($pretend) {
            return $this->pretendToRun($migration, 'run');
        }

        $migration->run();

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($file, $batch);

        $this->note("<info>Seeded:</info> $file");
    }

    /**
     * Run "down" a migration instance.
     *
     * @param  string  $file
     * @param  object  $seed
     * @param  bool    $pretend
     * @return void
     */
    protected function runDown($file, $seed, $pretend)
    {
        $file = is_null($file) ? $seed->seed : $file;

        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve($file);

        if ($pretend) {
            return $this->pretendToRun($instance, 'down');
        }

        if (method_exists($instance, 'down')) {
            $instance->down();
        }

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($seed);

        $this->note("<info>Rolled back:</info> $file");
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param  string $file
     * @return object
     */
    public function resolve($file)
    {
        $filePath = database_path(config('seeds.dir').'/'.$file.'.php');

        if (File::exists($filePath)) {
            require_once $filePath;
        } elseif (! empty($this->repository->env)) {
            require_once database_path(config('seeds.dir').'/'.$this->repository->env.'/'.$file.'.php');
        } else {
            require_once database_path(config('seeds.dir').'/'.App::environment().'/'.$file.'.php');
        }

        $fullPath = $this->getAppNamespace().$file;

        return new $fullPath();
    }

    /**
     * Rollback the last migration operation.
     *
     * @param  array|string $paths
     * @param  array  $options
     * @return array
     */
    public function rollback($paths = [], array $options = [])
    {
        $this->notes = [];

        $rolledBack = [];

        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        if (($steps = Arr::get($options, 'step', 0)) > 0) {
            $migrations = $this->repository->getMigrations($steps);
        } else {
            $migrations = $this->repository->getLast();
        }
//        dd($migrations,$paths);
        $count = count($migrations);
        $files = $this->getMigrationFiles($paths[0]);
        if ($count === 0) {
            $this->note('<info>Nothing to rollback.</info>');
        } else {
            // Next we will run through all of the migrations and call the "down" method
            // which will reverse each migration in order. This getLast method on the
            // repository already returns these migration's names in reverse order.
            $this->requireFiles($files);


            foreach ($migrations as $id => $migration) {
                $migration = (object) $migration;
                $rolledBack[] = $files[$id];

                $this->runDown(
                    $files[$id],
                    $migration, Arr::get($options, 'pretend', false)
                );
            }

        }

        return $rolledBack;
    }
}
