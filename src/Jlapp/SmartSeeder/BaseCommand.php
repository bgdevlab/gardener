<?php

namespace Jlapp\SmartSeeder;

use Illuminate\Console\Command;

class BaseCommand extends Command
{
    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath()
    {
        // Single Path seeds
        return database_path(config('seeds.dir'));

        // 5.3 TODO - integrate multi path seeds.
        return databasePath() . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Get all of the migration paths.
     *
     * @return array
     */
    protected function getMigrationPaths()
    {
        // 5.2
        return [$this->getMigrationPath()];

        // 5.3 TODO - integrate multi path seeds.
        // Here, we will check to see if a path option has been defined. If it has
        // we will use the path relative to the root of this installation folder
        // so that migrations may be run for any path within the applications.
        if ($this->input->hasOption('path') && $this->option('path')) {
            return [$this->laravel->basePath() . '/' . $this->option('path')];
        }

        return array_merge(
            [$this->getMigrationPath()], $this->migrator->paths()
        );
    }
}
