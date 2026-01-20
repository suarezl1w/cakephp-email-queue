<?php
declare(strict_types=1);

namespace EmailQueue;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

/**
 * Plugin class for EmailQueue
 */
class Plugin extends BasePlugin
{
    /**
     * Plugin bootstrap.
     *
     * This method is called when the plugin is loaded.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application instance
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }
}
