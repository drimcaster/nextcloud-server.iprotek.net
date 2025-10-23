<?php
declare(strict_types=1);

namespace OCA\UserIprotek\Command;

use OC\Core\Command\Base;
use OCA\UserIprotek\Migration\Version0001Date20251023;
use OCP\DB\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Base {

    /** @var IDBConnection */
    private $connection;

    public function __construct(IDBConnection $connection) {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void {
        $this
            ->setName('user_iprotek:migrate')
            ->setDescription('Run user_iprotek custom migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Running user_iprotek migrations...</info>');

        $schema = $this->connection->createSchema();
        $migration = new Version0001Date20251023();
        $migration->changeSchema(new class($output) implements \OCP\Migration\IOutput {
            private $output;
            public function __construct($output) { $this->output = $output; }
            public function info(string $message): void { $this->output->writeln($message); }
        }, fn() => $schema, []);

        $this->connection->migrateToSchema($schema);

        $output->writeln('<info>Migration completed.</info>');
        return 0;
    }
}