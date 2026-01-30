<?php
declare(strict_types=1);

namespace EmailQueue\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\FactoryLocator;
use EmailQueue\Model\Table\EmailQueueTable;

/**
 * Clear locked emails in queue (CakePHP 5 Command - replaces SenderShell clearLocks subcommand).
 */
class ClearLocksCommand extends BaseCommand
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'email_queue clear_locks';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Clears all locked emails in the queue, useful for recovering from crashes';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Clears all locked emails in the queue, useful for recovering from crashes');
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        FactoryLocator::get('Table')
            ->get('EmailQueue', ['className' => EmailQueueTable::class])
            ->clearLocks();

        $io->out('<success>Locks cleared</success>');

        return static::CODE_SUCCESS;
    }
}
