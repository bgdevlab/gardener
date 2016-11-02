<?php

namespace Jlapp\SmartSeeder;

use File;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;

class SeedResetCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Migrator.
     *
     * @var object
     */
    private $migrator;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'seed:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets all the seeds in the database';

    /**
     * Constructor.
     *
     * @param SeedMigrator $migrator
     */
    public function __construct(SeedMigrator $migrator)
    {
        parent::__construct();

        $this->migrator = $migrator;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $env = $this->option('env');
        $pretend = $this->input->getOption('pretend');

        $this->prepareDatabase();

        if (File::exists(database_path(config('seeds.dir')))) {
            $this->migrator->setEnv($env);
        }

        $this->migrator->setConnection($this->input->getOption('database'));

        while (true) {
            $rolledBack = $this->migrator->rollback([database_path(config('seeds.dir'))], $this->input->getOptions(), $pretend);

            // Once the migrator has run we will grab the note output and send it out to
            // the console screen, since the migrator itself functions without having
            // any instances of the OutputInterface contract passed into the class.
            foreach ($this->migrator->getNotes() as $note) {
                $this->output->writeln($note);
            }

            if (count($rolledBack) == 0) {
                break;
            }
        }

        $this->line("Seeds reset for $env");
    }

    /**
     * Prepare the migration database for running.
     *
     * @return void
     */
    protected function prepareDatabase()
    {
        $this->migrator->setConnection($this->input->getOption('database'));

        if (! $this->migrator->repositoryExists()) {
            $options = [
                '--database' => $this->input->getOption('database'),
            ];

            $this->call('seed:install', $options);
        }
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
            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'],
            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'],
        ];
    }
}
