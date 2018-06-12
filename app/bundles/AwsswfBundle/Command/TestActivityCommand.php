<?php

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

class TestActivityCommand extends ModeratedCommand {

    const DS = DIRECTORY_SEPARATOR;

    protected $container;
    protected $config;
    protected $swfclient;
    protected $description = 'Starts a workflow test activity';
    protected static $currentActivities = [];
    protected $outParams;
    protected $inputParams;

    protected function configure() {
        $this
                ->setName('swfworkflows:testactivity')
                ->addOption('--activity', null, InputOption::VALUE_REQUIRED, 'activityname', '')
                ->setDescription('Run swf activity test');


        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $className = "Mautic\\AwsswfBundle\\Task\\EnablePeopleService";
        $classObj = new $className($this->getContainer());
        $activityTask = new ActivityTask("test", [
            'input' => json_encode([
                'oauth_client_id' => 20,
                'oauth_client_secret' => 'ParU9FYnh9AYVDfbdXWamvWvaj6ZCi5o1gT9zP3Y',
                "dbhost" => 'localhost',
                "dbname" => 'mautic',
                "dbuser" => 'root',
                "dbpassword" => '',
            ])
        ]);
        $classObj->{$input->getOption('activity')}($activityTask);
    }

}
