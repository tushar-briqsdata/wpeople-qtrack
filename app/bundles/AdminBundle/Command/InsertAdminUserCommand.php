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

class InsertAdminUserCommand extends ModeratedCommand {
    
    protected $container;

    protected function configure() {
        $this
                ->setName('mautic:admin:insertadmin')
                ->setDescription('Create admin user from given parameters.')
                ->addOption('--first-name', null, InputOption::VALUE_OPTIONAL, 'First name of admin user', 'WPeople')
                ->addOption('--last-name', null, InputOption::VALUE_OPTIONAL, 'Last name of admin user', 'Admin')
                ->addOption('--user-name', null, InputOption::VALUE_REQUIRED, 'User name of admin user')
                ->addOption('--email', null, InputOption::VALUE_REQUIRED, 'Email of admin user')
                ->addOption('--password', null, InputOption::VALUE_REQUIRED, 'password of admin user');


        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->container = $this->getContainer();
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        try {
            $existingUser = $entityManager->getRepository('MauticUserBundle:User')->find(1);
        } catch (\Exception $e) {
            $existingUser = null;
        }

        if ($existingUser != null) {
            $user = $existingUser;
        } else {
            $user = new User();
        }
        if ($existingUser == null) {
            $encoder = $this->container->get('security.encoder_factory')->getEncoder($user);

            $user->setFirstName($input->getOption('first-name'));
            $user->setLastName($input->getOption('last-name'));
            $user->setUsername($input->getOption('user-name'));
            $user->setEmail($input->getOption('email'));
            $user->setPassword($encoder->encodePassword($input->getOption('password'), $user->getSalt()));
            $user->setRole($entityManager->getReference('MauticUserBundle:Role', 1));

            $entityManager->persist($user);
            $entityManager->flush();
            echo "Admin user created\n";
        }else{
            echo "Admin user exist\n";
        }
    }

}
