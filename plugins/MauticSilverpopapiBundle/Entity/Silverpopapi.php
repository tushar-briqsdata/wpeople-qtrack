<?php

namespace MauticPlugin\MauticSilverpopapiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\CommonEntity;

class Silverpopapi extends CommonEntity {

    /**
     * @var int
     */
    private $id;

}
