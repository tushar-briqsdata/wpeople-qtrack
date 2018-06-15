<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Sameer
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AdminBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client as GClient;
use \Aws\Credentials\CredentialProvider;
use Aws\Ecs\EcsClient;

class StartCronCommand extends ModeratedCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('mautic:admin:startcron')
                ->setDescription('Starts cron container')
                ->addOption('--domain', null, InputOption::VALUE_REQUIRED, 'Domain')
                ->addOption('--clientid', null, InputOption::VALUE_REQUIRED, 'Client id');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->container = $this->getContainer();

        try {
            echo "Starting Worker Container";
            $credentials = CredentialProvider::defaultProvider();
            $ecsclient = EcsClient::factory([
                        "region" => "us-east-1",
                        "version" => "2014-11-13",
                        "credentials" => $credentials
            ]);
            $client = new GClient();
            $response = $client->request('GET', 'http://172.17.0.1:51678/v1/metadata')->getBody();
            $cluster = json_decode($response)->Cluster;
            $ecsParams = [
                'cluster' => $cluster,
                'count' => 1,
                'overrides' => [
                    'containerOverrides' => [
                        [
                            'name' => "WPeople-Worker",
                            'environment' => [
                                [
                                    'name' => 'WDOMAIN',
                                    'value' => $input->getOption('domain'),
                                ],
                                [
                                    'name' => 'WCLIENT_ID',
                                    'value' => $input->getOption('clientid'),
                                ],
                                [
                                    'name' => "SERVICE_NAME",
                                    'value' => "wpeople_" . $input->getOption('clientid')
                                ]
                            ]
                        ]
                    ]
                ],
                'startedBy' => "Wpeople_" . $input->getOption('clientid'),
                'taskDefinition' => 'WPeople-Worker'
            ];

            $taskrun = $ecsclient->runTask($ecsParams);
            echo "Container Started:" . json_encode($taskrun);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

}
