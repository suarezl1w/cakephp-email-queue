<?php
declare(strict_types=1);

namespace EmailQueue\Test\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\FactoryLocator;
use Cake\TestSuite\TestCase;
use EmailQueue\Model\Table\EmailQueueTable;

/**
 * SenderCommand Test Case (CakePHP 5 - replaces SenderShellTest).
 */
class SenderCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Fixtures.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.EmailQueue.EmailQueue',
    ];

    /**
     * @var EmailQueueTable
     */
    protected EmailQueueTable $EmailQueue;

    /**
     * setUp method.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->EmailQueue = FactoryLocator::get('Table')
            ->get('EmailQueue', ['className' => EmailQueueTable::class]);
    }

    /**
     * Test sender command sends queued emails.
     */
    public function testSenderSendsEmails(): void
    {
        $this->exec('email_queue sender -l 10');

        $this->assertExitSuccess();

        $emails = $this->EmailQueue
            ->find()
            ->where(['id IN' => ['email-1', 'email-2', 'email-3']])
            ->all()
            ->toList();

        $this->assertCount(3, $emails);
        $this->assertTrue($emails[0]->sent);
        $this->assertTrue($emails[1]->sent);
        $this->assertTrue($emails[2]->sent);
        $this->assertFalse($emails[0]->locked);
        $this->assertFalse($emails[1]->locked);
        $this->assertFalse($emails[2]->locked);
    }

    /**
     * Test clear_locks command.
     */
    public function testClearLocks(): void
    {
        $this->EmailQueue->getBatch();
        $this->assertNotEmpty($this->EmailQueue->find()->where(['locked' => true])->all()->toList());

        $this->exec('email_queue clear_locks');
        $this->assertExitSuccess();

        $this->assertEmpty($this->EmailQueue->find()->where(['locked' => true])->all()->toList());
    }
}
