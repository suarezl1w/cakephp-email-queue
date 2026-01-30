<?php
declare(strict_types=1);

namespace EmailQueue\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\FactoryLocator;
use Cake\I18n\I18n;
use Cake\Mailer\Mailer;
use EmailQueue\Model\Table\EmailQueueTable;

/**
 * Preview queued emails (CakePHP 5 Command - replaces PreviewShell).
 */
class PreviewCommand extends BaseCommand
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'email_queue preview';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Preview queued emails';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Preview queued emails')
            ->addArgument('id', [
                'help' => 'Email ID(s) to preview (optional, previews all if not specified)',
                'required' => false,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        Configure::write('App.baseUrl', '/');

        $conditions = [];
        $idArgs = $args->getArguments();
        if (!empty($idArgs)) {
            $conditions['id IN'] = $idArgs;
        }

        $emailQueue = FactoryLocator::get('Table')->get('EmailQueue', ['className' => EmailQueueTable::class]);
        $emails = $emailQueue->find()->where($conditions)->toList();

        if (!$emails) {
            $io->out('No emails found');

            return static::CODE_SUCCESS;
        }

        $io->nl();
        foreach ($emails as $i => $email) {
            if ($i) {
                $io->ask('Hit a key to continue');
                $io->nl();
            }

            $io->out('Email :' . $email['id']);
            $this->preview($email, $io);
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Preview email
     *
     * @param array $e email data
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return void
     */
    protected function preview(array $e, ConsoleIo $io): void
    {
        $language = $e['language'];
        $configName = $e['config'];
        $template = $e['template'];
        $layout = $e['layout'];
        $headers = empty($e['headers']) ? [] : (array)$e['headers'];
        $theme = empty($e['theme']) ? '' : (string)$e['theme'];

        $email = new Mailer($configName);

        if ($language) {
            I18n::setLocale($language);
        }

        if (!empty($e['attachments'])) {
            $email->setAttachments($e['attachments']);
        }

        $email->setTransport('Debug')
            ->setTo($e['email'])
            ->setSubject($e['prefix'] . $e['subject'])
            ->setEmailFormat($e['format'])
            ->addHeaders($headers)
            ->setMessageId(false)
            ->setReturnPath($email->getFrom())
            ->setViewVars($e['template_vars']);

        $email->viewBuilder()
            ->setTheme($theme)
            ->setTemplate($template)
            ->setLayout($layout);

        $return = $email->deliver();

        $io->out('Content:');
        $io->hr();
        $io->out($return['message']);
        $io->hr();
        $io->out('Headers:');
        $io->hr();
        $io->out($return['headers']);
        $io->hr();
        $io->out('Data:');
        $io->hr();
        $io->hr();
        $io->out('');
    }
}
