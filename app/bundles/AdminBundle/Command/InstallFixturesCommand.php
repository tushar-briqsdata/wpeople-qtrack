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

class InstallFixturesCommand extends ModeratedCommand {

    protected $container;

    protected function configure() {
        $this
                ->setName('mautic:admin:addfixture')
                ->setDescription('Install Fixtures');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->container = $this->getContainer();



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

        $purger = new ORMPurger($entityManager);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $executor = new ORMExecutor($entityManager, $purger);
        $executor->execute($fixtures, true);
        echo "Fixtures added\n";
    }

}
