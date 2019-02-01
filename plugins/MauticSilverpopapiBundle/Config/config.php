<?php

return [
    'name' => 'Silverpopapi',
    'description' => 'Enables integration with silverpop api for sending email through silverpop API',
    'version' => '1.0',
    'author' => 'Sameer Mehta',
    'services' => [
        'forms' => [
            'plugin.silverpopapi.form' => [
                'class' => 'MauticPlugin\MauticSilverpopapiBundle\Form\Type\SilverpopapiType',
                'alias' => 'silverpopapi',
            ],
        ],
        'events' => [
            'plugin.silverpopapi.email.subscriber' => [
                'class'=>'MauticPlugin\MauticSilverpopapiBundle\EventListener\EmailSubscriber'
            ]
        ],
        'integrations' => [
            'plugin.silverpopapi.integration' => [
                'class'     => 'MauticPlugin\MauticSilverpopapiBundle\Integration\SilverpopapiIntegration',
                'arguments' => [
                ],
            ],
        ]
    ],
    'parameters' => [
        'silverpop_api_enabled' => false
    ],
];
