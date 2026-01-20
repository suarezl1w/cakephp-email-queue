<?php
declare(strict_types=1);

namespace EmailQueue\Shell;

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Mailer\Mailer;
use Cake\Datasource\FactoryLocator;
use EmailQueue\Model\Table\EmailQueueTable;
use Cake\I18n\I18n;

class PreviewShell extends Shell
{
    /**
     * Main
     *
     * @return bool|int|null|void
     */
    public function main()
    {
        Configure::write('App.baseUrl', '/');

        $conditions = [];
        if ($this->args) {
            $conditions['id IN'] = $this->args;
        }

        $emailQueue = FactoryLocator::get('Table')->get('EmailQueue', ['className' => EmailQueueTable::class]);
        $emails = $emailQueue->find()->where($conditions)->toList();

        if (!$emails) {
            $this->out('No emails found');

            return;
        }

        $this->clear();
        foreach ($emails as $i => $email) {
            if ($i) {
                $this->in('Hit a key to continue');
                $this->clear();
            }

/*             \Cake\Log\Log::write('debug', json_encode($email));
 */            $this->out('Email :' . $email["id"]);
            $this->preview($email);
        }
    }

    /**
     * Preview email
     *
     * @param array $e email data
     * @return void
     */
    public function preview($e)
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

        $this->out('Content:');
        $this->hr();
        $this->out($return['message']);
        $this->hr();
        $this->out('Headers:');
        $this->hr();
        $this->out($return['headers']);
        $this->hr();
        $this->out('Data:');
        $this->hr();
        //debug($e['template_vars']);
        $this->hr();
        $this->out('');
    }
}
