<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Sameer
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AwsswfBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mautic\UserBundle\Entity\User;
use Mautic\InstallBundle\Helper\SchemaHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use \Aws\Credentials\CredentialProvider;
use \Aws\Swf\SwfClient;
use Mautic\AwsswfBundle\Task\ActivityTask;

class RunActivityCommand extends ModeratedCommand {

    const DS = DIRECTORY_SEPARATOR;

    protected $container;
    protected $config;
    protected $swfclient;
    protected $description = 'Starts a workflow activity';
    protected static $currentActivities = [];
    protected $outParams;
    protected $inputParams;

    protected function configure() {
        $this
                ->setName('swfworkflows:activity')
                ->setDescription('Run swf activity worker')
                ->addOption('--domain', null, InputOption::VALUE_REQUIRED, 'SWF domain name', 'WTrack')
                ->addOption('--tasklist', null, InputOption::VALUE_REQUIRED, 'Tasklist name', 'default');


        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->outParams = $output;
        $this->inputParams = $input;
        $credentials = CredentialProvider::defaultProvider();
        $this->swfclient = SwfClient::factory([
                    "region" => "us-east-1",
                    "version" => "2012-01-25",
                    "credentials" => $credentials
        ]);
        $this->config = (file_exists(getcwd() . self::DS . "app" . self::DS . "config" . self::DS . "swfworkflows.php")) ? include_once getcwd() . self::DS . "app" . self::DS . "config" . self::DS . "swfworkflows.php" : null;

        $domainArg = $input->getOption('domain');
        foreach ($this->config["workflows"] as $workflow) {
            if ($domainArg === $workflow['domain']) {
                $stack[] = $this->runWorkflowActivity($domainArg);
            }
        }

        if (empty($stack)) {
            echo "No workflows activity running\n";
        }
    }

    private function runWorkflowActivity($domain) {

        echo "Starting activity worker in domain '" . $domain . "'\n";
        do {
            $task = $this->pollForActivityTask($domain);
            $this->processTask($domain, $task);
        } while (1);
    }

    private function pollForActivityTask($domain) {
        $options = [
            'domain' => $domain,
            'taskList' => [
                "name" => $this->inputParams->getOption('tasklist')
            ]
        ];

        return $this->swfclient->pollForActivityTask($options);
    }

    private function processTask($domain, $task) {
        if (isset($task['taskToken']) && $task['taskToken'] !== '') {
            $activityTask = new ActivityTask($domain, $task);
            $activityTaskEventName = $activityTask->getEventName();
            $classmethod = explode(".", $activityTaskEventName)[2];
            $className = "";
            $methodName = "";
            foreach ($this->config["workflows"] as $workflow) {
                if (stristr($classmethod, $workflow["name"])) {
                    $className = "Mautic\\AwsswfBundle\\Task\\" . $workflow["name"];
                    $methodName = explode($workflow["name"], $activityTaskEventName)[1];
                }
            }
            echo "Got new activity task - " . $activityTask->getEventName() . "\n";
            $classObj = new $className($this->getContainer());
            if (method_exists($classObj, $methodName)) {
                $classObj->{$methodName}($activityTask);
            }
        } else {
            echo "No task in the last 60 second... waiting\n";
        }
    }

}
