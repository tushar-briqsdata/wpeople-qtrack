<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\InstallBundle\InstallFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Mautic\UserBundle\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RoleData.
 */
class RoleData extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $translator = $this->container->get('translator');
        $role       = new Role();
        $role->setName($translator->trans('mautic.user.role.admin.name', [], 'fixtures'));
        $role->setDescription($translator->trans('mautic.user.role.admin.description', [], 'fixtures'));
        $role->setIsAdmin(1);
        $manager->persist($role);
        $manager->flush();

        $this->addReference('admin-role', $role);
        
        $role1       = new Role();
        $role1->setName("ClientAdmin");
        $role1->setDescription("Client admin");
        $role1->setIsAdmin(0);
        $permission = [
            'api:access' => ['full'],
            'api:clients' => ['full'],
            'asset:categories' => ['full'],            
            'asset:assets' => ['full'],            
            'campaign:categories' => ['full'],            
            'campaign:campaigns' => ['full'],            
            'category:categories' => ['full'],            
            'channel:categories' => ['full'],            
            'channel:messages' => ['full'],            
            'lead:leads' => ['full'],            
            'lead:lists' => ['full'],            
            'lead:fields' => ['full'],            
            'lead:imports' => ['full'],            
            'dynamiccontent:categories' => ['full'],            
            'dynamiccontent:dynamiccontents' => ['full'],            
            'email:categories' => ['full'],            
            'email:emails' => ['full'],            
            'focus:categories' => ['full'],            
            'focus:items' => ['full'],            
            'form:categories' => ['full'],            
            'form:forms' => ['full'],            
            'page:categories' => ['full'],            
            'page:pages' => ['full'],            
            'page:preference_center' => ['full'],            
            'notification:categories' => ['full'],            
            'notification:notifications' => ['full'],            
            'notification:mobile_notifications' => ['full'],            
            'point:categories' => ['full'],            
            'point:points' => ['full'],            
            'point:triggers' => ['full'],            
            'report:reports' => ['full'],            
            'mauticSocial:categories' => ['full'],            
            'mauticSocial:monitoring' => ['full'],            
            'mauticSocial:tweets' => ['full'],            
            'stage:categories' => ['full'],            
            'stage:stages' => ['full'],            
            'sms:categories' => ['full'],            
            'sms:smses' => ['full'],            
            'webhook:categories' => ['full'],            
            'webhook:webhooks' => ['full'],            
        ];
//        $role1->setRawPermissions($permission);
        $manager->persist($role1);
        $manager->flush();

        $this->addReference('client-admin-role', $role1);
        
        $model = $this->container->get('mautic.model.factory')->getModel('user.role');
        $entity = $model->getEntity($role1->getId());
        $model->setRolePermissions($entity, $permission);
        $model->saveEntity($entity, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 1;
    }
}
