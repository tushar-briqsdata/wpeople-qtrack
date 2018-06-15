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

class InsertDatabseTablesCommand extends ModeratedCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('mautic:admin:dbmigration')
                ->setDescription("Do database migration if tables doesn't exist")
                ->addOption('--db-host', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-name', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-user', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--db-password', null, InputOption::VALUE_OPTIONAL, 'User name of admin user');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->container = $this->getContainer();

        //ensure the username and email are unique
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
            $table_exist = $dbObj->query("SELECT 1 FROM `users` LIMIT 1");
        } catch (\Exception $exception) {
            $table_exist = null;
            $dbObj->close();
        }
        if ($table_exist == null) {
            $schemaHelper = new SchemaHelper($dbParams);
            $schemaHelper->setEntityManager($this->container->get('doctrine.orm.entity_manager'));
            try {
                $schemaHelper->installSchema();
                echo "Database Migrated\n";
            } catch (\Exception $exception) {
                echo $exception->getMessage() . "\n";
                echo "Error occured during creating database schema\n";
            }
        } else {
            echo "Database tables already exists\n";
        }
    }

}
