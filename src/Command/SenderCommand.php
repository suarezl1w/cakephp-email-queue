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
use Cake\Mailer\Exception\MailerException;
use Cake\Mailer\Mailer;
use EmailQueue\Model\Table\EmailQueueTable;

/**
 * Send queued emails (CakePHP 5 Command - replaces SenderShell).
 */
class SenderCommand extends BaseCommand
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'email_queue sender';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Sends queued emails in a batch';
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Sends queued emails in a batch')
            ->addOption('limit', [
                'short' => 'l',
                'help' => 'How many emails should be sent in this batch?',
                'default' => '50',
            ])
            ->addOption('template', [
                'short' => 't',
                'help' => 'Name of the template to be used to render email',
                'default' => 'default',
            ])
            ->addOption('layout', [
                'short' => 'w',
                'help' => 'Name of the layout to be used to wrap template',
                'default' => 'default',
            ])
            ->addOption('stagger', [
                'short' => 's',
                'help' => 'Seconds to maximum wait randomly before proceeding (useful for parallel executions)',
                'default' => false,
            ])
            ->addOption('config', [
                'short' => 'c',
                'help' => 'Name of email settings to use as defined in email.php',
                'default' => 'default',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $stagger = $args->getOption('stagger');
        if ($stagger) {
            sleep((int)random_int(0, (int)$stagger));
        }

        Configure::write('App.baseUrl', '/');
        $emailQueue = FactoryLocator::get('Table')->get('EmailQueue', ['className' => EmailQueueTable::class]);
        $limit = (int)$args->getOption('limit');
        $emails = $emailQueue->getBatch($limit);

        $count = count($emails);
        foreach ($emails as $e) {
            $language = $e->config;

            $configName = $e->config === 'default' ? $args->getOption('config') : $e->config;
            $template = $e->template === 'default' ? $args->getOption('template') : $e->template;
            $layout = $e->layout === 'default' ? $args->getOption('layout') : $e->layout;
            $headers = empty($e->headers) ? [] : (array)$e->headers;
            $theme = empty($e->theme) ? '' : (string)$e->theme;
            $viewVars = empty($e->template_vars) ? [] : $e->template_vars;
            $errorMessage = null;
            $sent = true;

            if ($language) {
                I18n::setLocale($language);
            }

            try {
                $mailer = $this->newMailer($configName);

                if (!empty($e->from_email) && !empty($e->from_name)) {
                    $mailer->setFrom($e->from_email, $e->from_name);
                }

                $transport = $mailer->getTransport();

                if ($transport && $transport->getConfig('additionalParameters')) {
                    $from = key($mailer->getFrom());
                    $transport->setConfig(['additionalParameters' => "-f $from"]);
                }

                if (!empty($e->attachments)) {
                    $mailer->setAttachments($e->attachments);
                }

                $mailer
                    ->setTo($e->email)
                    ->setSubject($e->prefix . $e->subject)
                    ->setEmailFormat($e->format)
                    ->addHeaders($headers)
                    ->setViewVars($viewVars)
                    ->setMessageId(false)
                    ->setReturnPath($mailer->getFrom());

                $mailer->viewBuilder()
                    ->setLayout($layout)
                    ->setTheme($theme)
                    ->setTemplate($template);

                $mailer->deliver();
            } catch (MailerException $exception) {
                $io->err($exception->getMessage());
                $errorMessage = $exception->getMessage();
                $sent = false;
            }

            if ($sent) {
                $emailQueue->success($e->id);
                $io->out('<success>Email ' . $e->id . ' was sent</success>');
            } else {
                $emailQueue->fail($e->id, $errorMessage);
                $io->out('<error>Email ' . $e->id . ' was not sent</error>');
            }
        }

        if ($count > 0) {
            $locks = collection($emails)->extract('id')->toList();
            $emailQueue->releaseLocks($locks);
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Returns a new Mailer instance.
     *
     * @param string $config Config name
     * @return \Cake\Mailer\Mailer
     */
    protected function newMailer(string $config): Mailer
    {
        return new Mailer($config);
    }
}
