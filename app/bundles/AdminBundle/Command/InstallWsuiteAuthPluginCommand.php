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
use Mautic\UserBundle\Entity\User;
use Mautic\InstallBundle\Helper\SchemaHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Security\Cryptography\Cipher\Symmetric\OpenSSLCipher;
use Mautic\CoreBundle\Security\Cryptography\Cipher\Symmetric\McryptCipher;
use Mautic\CoreBundle\Helper\CoreParametersHelper;

class InstallWsuiteAuthPluginCommand extends ModeratedCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('mautic:admin:installwsuiteauth')
                ->setDescription('Create client admin user from given parameters.')
                ->addOption('--db-host', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-name', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-user', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-password', null, InputOption::VALUE_OPTIONAL, 'User name of admin user')
                ->addOption('--oauth-id', null, InputOption::VALUE_REQUIRED, 'oauth id')
                ->addOption('--oauth-secret', null, InputOption::VALUE_REQUIRED, 'oauth secret');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->container = $this->getContainer();
        if (empty($input->getOption('db-host'))) {
            throw new Exception("Database host is required.");
        }

        if (empty($input->getOption('db-name'))) {
            throw new Exception("Database name is required.");
        }

        if (empty($input->getOption('db-user'))) {
            throw new Exception("Database user is required.");
        }

        $dbParams = [
            "driver" => "pdo_mysql",
            "host" => $input->getOption('db-host'),
            "table_prefix" => "",
            "port" => 3306,
            "name" => $input->getOption('db-name'),
            "user" => $input->getOption('db-user'),
            "password" => $input->getOption('db-password'),
            "backup_tables" => 0,
            "backup_prefix" => "bak_",
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

        $dbObj = DriverManager::getConnection($dbParams);

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
                    'client_id' => $encryptionHelper->encrypt($input->getOption('oauth-id')),
                    'client_secret' => $encryptionHelper->encrypt($input->getOption("oauth-secret"))
                ];
                $dbObj->executeQuery("INSERT into plugin_integration_settings(`plugin_id`,`name`, `is_published`, `supported_features`, `api_keys`, `feature_settings`) values(" . $plugin_id . ", 'WsuiteAuth', 1, '" . serialize($supported_features) . "', '" . serialize($api_keys) . "', '" . serialize($feature_settings) . "')");
                $plugin_id = $dbObj->lastInsertId();
                $dbObj->close();
            }
        } catch (\Exception $exception) {
            echo "Error occured-" . $exception->getMessage();
            $table_exist = null;
            $dbObj->close();
        }
    }

}
