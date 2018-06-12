<?php

namespace Mautic\AwsswfBundle\Task;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\InstallBundle\Helper\SchemaHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Mautic\UserBundle\Entity\User;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Security\Cryptography\Cipher\Symmetric\OpenSSLCipher;
use Mautic\CoreBundle\Security\Cryptography\Cipher\Symmetric\McryptCipher;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use \Aws\Credentials\CredentialProvider;
use Aws\Ecs\EcsClient;
use GuzzleHttp\Client as GClient;

class EnablePeopleService extends ModeratedCommand {

    protected $container;

    public function __construct($container) {
        $this->container = $container;
    }

    public function DatabaseMigration(ActivityTask $activity) {
        $inputData = json_decode($activity->getActivityInput());

        /** start activity * */
        if (!isset($inputData->dbhost)) {
            $reason["reason"] = "Database host is required.";
            return $activity->failActivityTask($reason);
        }
        if (!isset($inputData->dbname)) {
            $reason["reason"] = "Database name is required.";
            return $activity->failActivityTask($reason);
        }
        if (!isset($inputData->dbuser)) {
            $reason["reason"] = "Database user name is required.";
            return $activity->failActivityTask($reason);
        }

        $dbObj = $this->getDbConnection($inputData);
        try {
            $table_exist = $dbObj->query("SELECT 1 FROM `users` LIMIT 1");
            echo "Database tables already exist......\n";
            $dbObj->close();
        } catch (\Exception $exception) {
            echo "Database tables doesn't exist......\n";
            $table_exist = null;
            $dbObj->close();
        }

        $dbParams = $this->getDbParams($inputData);

        if ($table_exist == null) {
            $schemaHelper = new SchemaHelper($dbParams);
            $dbParams["server_version"] = $schemaHelper->getServerVersion();
            $schemaHelper->setEntityManager($this->container->get('doctrine.orm.entity_manager'));
            try {
                echo "Creating database tables......\n";
                $schemaHelper->installSchema();
            } catch (\Exception $exception) {
                $reason["reason"] = $exception->getMessage();
                return $activity->failActivityTask($reason);
            }
        }

        /** end activity * */
        $nextTaskData = json_encode($inputData);
        return $activity->finishActivityTask(['result' => $nextTaskData]);
    }

    public function InstallFixtures(ActivityTask $activity) {
        $inputData = json_decode($activity->getActivityInput());

        $dbObj = $this->getDbConnection($inputData);

        try {
            $table_exist = $dbObj->fetchAll("SELECT * FROM `roles`");
            if (is_array($table_exist) && count($table_exist) > 0) {
                echo "Fixtures already installed......\n";
                $table_exist = 1;
            } else {
                echo "Fixtures are not installed......\n";
                $table_exist = null;
            }
            $dbObj->close();
        } catch (\Exception $exception) {
            echo "Error occured during checking fixture installed or not: " . $exception->getMessage() . "\n";
            $table_exist = null;
            $dbObj->close();
        }
        if ($table_exist == null) {
            echo "Installing fixtures......\n";
            try {
                $entityManager = $this->container->get('doctrine.orm.entity_manager');
                $paths = [dirname(__DIR__) . '/../InstallBundle/InstallFixtures/ORM'];

                $loader = new ContainerAwareLoader($this->container);

                foreach ($paths as $path) {
                    if (is_dir($path)) {
                        $loader->loadFromDirectory($path);
                    }
                }

                $fixtures = $loader->getFixtures();

                if (!$fixtures) {
                    throw new \InvalidArgumentException(
                    sprintf('Could not find any fixtures to load in: %s', "\n\n- " . implode("\n- ", $paths))
                    );
                }

                $purger = new ORMPurger($this->container->get('doctrine.orm.entity_manager'));
                $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
                $executor = new ORMExecutor($this->container->get('doctrine.orm.entity_manager'), $purger);
                $executor->execute($fixtures, true);

                /** end activity * */
            } catch (\Exception $exception) {
                echo "Error occured during installing fixtures: " . $exception->getTraceAsString() . "\n";
                $reason["reason"] = $exception->getMessage();
                return $activity->failActivityTask($reason);
            }
        }
        $nextTaskData = json_encode($inputData);
        /** start activity * */
        return $activity->finishActivityTask(['result' => $nextTaskData]);
    }

    public function InsertAdminUser(ActivityTask $activity) {
        $inputData = json_decode($activity->getActivityInput());

        /** start activity * */
        $dbObj = $this->getDbConnection($inputData);
        try {
            $table_exist = $dbObj->fetchAll("SELECT * FROM `users` where email = '" . $inputData->adminuseremail . "'");
            if (is_array($table_exist) && count($table_exist) > 0) {
                echo "Admin user exist......\n";
                $existingUser = $table_exist[0];
            } else {
                $existingUser = null;
            }
            $dbObj->close();
        } catch (\Exception $exception) {
            $existingUser = null;
            $dbObj->close();
        }

        if ($existingUser != null) {
            $user = $existingUser;
        } else {
            $user = new User();
        }
        if ($existingUser == null) {
            echo "Creating admin user......\n";
            try {
                $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);

                $user->setFirstName($inputData->adminfirstname);
                $user->setLastName($inputData->adminlastname);
                $user->setUsername($inputData->adminusername);
                $user->setEmail($inputData->adminuseremail);
                $user->setPassword($encoder->encodePassword($inputData->adminuserpassword, $user->getSalt()));
                $user->setRole($this->container->get('doctrine.orm.entity_manager')->getReference('MauticUserBundle:Role', 1));

                $this->container->get('doctrine.orm.entity_manager')->persist($user);
                $this->container->get('doctrine.orm.entity_manager')->flush();
            } catch (\Exception $e) {
                $reason["reason"] = $e->getMessage();
                return $activity->failActivityTask($reason);
            }
        }
        /** end activity * */
        $nextTaskData = json_encode($inputData);
        return $activity->finishActivityTask(['result' => $nextTaskData]);
    }

    public function InsertClientAdminUser(ActivityTask $activity) {
        $inputData = json_decode($activity->getActivityInput());

        /** start activity * */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $dbObj = $this->getDbConnection($inputData);
        try {
            $table_exist = $dbObj->fetchAll("SELECT * FROM `users`");
            if (is_array($table_exist) && count($table_exist) >= 2) {
                echo "Client's admin user already exist......\n";
                $existingUser = $table_exist[0];
            } else {
                $existingUser = null;
            }
            $dbObj->close();
        } catch (\Exception $exception) {
            $existingUser = null;
            $dbObj->close();
        }

        $user = new User();

        if ($existingUser == null) {
            echo "Creating client's admin user......\n";
            try {
                $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);

                $user->setFirstName($inputData->userfirstname);
                $user->setLastName($inputData->userlastname);
                $user->setUsername($inputData->username);
                $user->setEmail($inputData->useremail);
                $user->setPassword($encoder->encodePassword($inputData->userpassword, $user->getSalt()));
                $user->setRole($this->container->get('doctrine.orm.entity_manager')->getReference('MauticUserBundle:Role', 1));

                $this->container->get('doctrine.orm.entity_manager')->persist($user);
                $this->container->get('doctrine.orm.entity_manager')->flush();
            } catch (\Exception $e) {
                $reason["reason"] = $e->getMessage();
                return $activity->failActivityTask($reason);
            }
        }

        /** end activity * */
        $nextTaskData = json_encode($inputData);
        return $activity->finishActivityTask(['result' => $nextTaskData]);
    }

    public function InstallPlugins(ActivityTask $activity) {
        $inputData = json_decode($activity->getActivityInput());

        /** start activity * */
        $dbObj = $this->getDbConnection($inputData);
        try {
            $table_exist = $dbObj->fetchAll("SELECT * FROM `plugins` where bundle = 'MauticSSOBundle'");
            if (is_array($table_exist) && count($table_exist) > 0) {
                $plugin_id = $table_exist[0]["id"];
                echo "Mauticssobundle already installed......\n";
            } else {
                echo "Creating Mauticssobundle......\n";
                $dbObj->executeQuery("INSERT into plugins(`name`,`description`, `is_missing`, `bundle`, `version`, `author`) values('SSO Providers', 'SSO into Mautic using 3rd party services', 0, 'MauticSSOBundle', '1.1', 'Alan Hartless')");
                $plugin_id = $dbObj->lastInsertId();
            }
            $table_exist = $dbObj->fetchAll("SELECT * FROM `plugin_integration_settings` where name = 'WsuiteAuth'");
            if (is_array($table_exist) && count($table_exist) > 0) {
                echo "OAuth settings already installed......\n";
                $plugin_id = $table_exist[0]["id"];
            } else {
                echo "Creating OAuth settings......\n";
                $feature_settings = [
                    "auto_create_user" => 0,
                    "new_user_role" => ""
                ];
                $supported_features = [
                    'sso_service'
                ];
                $opensslcipher = new OpenSSLCipher();
                $mcryptcipher = new OpenSSLCipher();
                $coreparameterhelper = new CoreParametersHelper($this->container->get('kernel'));
                $encryptionHelper = new EncryptionHelper($coreparameterhelper, $opensslcipher, $mcryptcipher);
//            $encryptionHelper = $this->kernel->getHelper('mautic.helper.encryption');
                $api_keys = [
                    'client_id' => $encryptionHelper->encrypt($inputData->oauth_client_id),
                    'client_secret' => $encryptionHelper->encrypt($inputData->oauth_client_secret)
                ];
                $dbObj->executeQuery("INSERT into plugin_integration_settings(`plugin_id`,`name`, `is_published`, `supported_features`, `api_keys`, `feature_settings`) values(" . $plugin_id . ", 'WsuiteAuth', 1, '" . serialize($supported_features) . "', '" . serialize($api_keys) . "', '" . serialize($feature_settings) . "')");
                $plugin_id = $dbObj->lastInsertId();
                $dbObj->close();
            }
        } catch (\Exception $exception) {
            echo "Error occured-".$exception->getMessage();
            $table_exist = null;
            $dbObj->close();
        }

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
                                    'value' => $inputData->domain,
                                ],
                                [
                                    'name' => 'WCLIENT_ID',
                                    'value' => "$inputData->wclient_id",
                                ],
                                [
                                    'name' => "SERVICE_NAME",
                                    'value' => "wpeople_".$inputData->wclient_id
                                ]
                            ]
                        ]
                    ]
                ],
                'startedBy' => "Wpeople_" . $inputData->wclient_id,
                'taskDefinition' => 'WPeople-Worker'
            ];

            $taskrun = $ecsclient->runTask($ecsParams);
            echo "Container Started:" . json_encode($taskrun);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
        }
        /** end activity * */
        $nextTaskData = json_encode($inputData);
        $activity->finishActivityTask(['result' => $nextTaskData]);

        $pid = trim(file_get_contents('/var/www/html/activity.pid'));
        shell_exec("kill -INT " . $pid);
        shell_exec("kill -9 " . $pid);
        return true;
    }

    protected function getDbConnection($inputData) {
        $dbParams = [
            "driver" => "pdo_mysql",
            "host" => $inputData->dbhost,
            "table_prefix" => "",
            "port" => 3306,
            "name" => $inputData->dbname,
            "user" => $inputData->dbuser,
            "password" => $inputData->dbpassword,
            "backup_tables" => 0,
            "backup_prefix" => "bak_",
            "server_version" => "5.5.5-10.1.26-MariaDB"
        ];

        foreach ($dbParams as $k => &$v) {
            if (!empty($v) && is_string($v) && preg_match('/getenv\((.*?)\)/', $v, $match)) {
                $v = (string) getenv($match[1]);
            }
        }

        $dbParams['charset'] = 'UTF8';
        if (isset($dbParams['name'])) {
            $dbParams['dbname'] = $dbParams['name'];
            unset($dbParams['name']);
        }
        return DriverManager::getConnection($dbParams);
    }

    protected function getDbParams($inputData) {
        return [
            "driver" => "pdo_mysql",
            "host" => $inputData->dbhost,
            "table_prefix" => "",
            "port" => 3306,
            "name" => $inputData->dbname,
            "user" => $inputData->dbuser,
            "password" => $inputData->dbpassword,
            "backup_tables" => 0,
            "backup_prefix" => "bak_",
            "server_version" => "5.5.5-10.1.26-MariaDB"
        ];
    }

}
