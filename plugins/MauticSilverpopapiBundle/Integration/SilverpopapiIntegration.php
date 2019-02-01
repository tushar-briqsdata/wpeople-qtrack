<?php

namespace MauticPlugin\MauticSilverpopapiBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class SilverpopapiIntegration extends AbstractIntegration {

    public function getName() {
        return 'Silverpopapi';
    }

    /**
     * Return's authentication method such as oauth2, oauth1a, key, etc.
     *
     * @return string
     */
    public function getAuthenticationType() {
        return 'none';
    }

}
