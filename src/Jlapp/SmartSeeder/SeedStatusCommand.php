<?php

namespace Jlapp\SmartSeeder;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Input\InputOption;

//\Illuminate\Database\Console\Migrations\BaseCommand
class SeedStatusCommand extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'seed:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each seed migration';

    /**
     * The migrator instance.
     *
     * @var SeedMigrator
     */
    protected $migrator;

    /**
     * @var SmartSeederRepository
     */
    protected $repository;

    /**
     * SeedStatusCommand constructor.
     * @param SeedMigrator $migrator
     * @param SmartSeederRepository $repository
     */
    public function __construct(SeedMigrator $migrator, SmartSeederRepository $repository)
    {
        parent::__construct();

        $this->migrator = $migrator;
        $this->repository = $repository;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $env = $this->option('env');

        $this->migrator->setConnection($this->option('database'));

        $this->migrator->setEnv($env);

        if (!$this->migrator->repositoryExists()) {
            return $this->error('No migrations found.');
        }

        $ran = $this->migrator->getRepository()->getRan();

        $migrations = Collection::make($this->getAllMigrationFiles())
            ->map(function ($migration) use ($ran) {
                return in_array($this->migrator->getMigrationName($migration), $ran)
                    ? ['<info>Y</info>', $this->migrator->getMigrationName($migration)]
                    : ['<fg=red>N</fg=red>', $this->migrator->getMigrationName($migration)];
            });

        if (count($migrations) > 0) {
            $this->table(['Ran?', 'Seed'], $migrations);
        } else {
            $this->error('No migrations found');
        }
    }

    /**
     * Get an array of all of the migration files.
     *
     * @return array
     */
    public function getAllMigrationFiles()
    {
        return $this->migrator->getMigrationFiles($this->getMigrationPaths()[0]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['env', null, InputOption::VALUE_OPTIONAL, 'The environment in which to run the seeds.', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'],

            ['path', null, InputOption::VALUE_OPTIONAL, 'The path of migrations files to use.'],
        ];
    }
}
