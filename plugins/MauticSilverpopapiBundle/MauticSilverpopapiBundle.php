<?php

namespace MauticPlugin\MauticSilverpopapiBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\CoreBundle\Factory\MauticFactory;

class MauticSilverpopapiBundle extends PluginBundleBase {

    /**
     * Called by PluginController::reloadAction when adding a new plugin that's not already installed
     *
     * @param Plugin        $plugin
     * @param MauticFactory $factory
     * @param null          $metadata
     */
    static public function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedschema = null) {
        if ($metadata !== null) {
            self::installPluginSchema($metadata, $factory);
        }
        // Do other install stuff
    }

}
