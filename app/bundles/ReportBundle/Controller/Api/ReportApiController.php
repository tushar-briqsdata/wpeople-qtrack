<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ReportBundle\Controller\Api;

use FOS\RestBundle\Util\Codes;
use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class ReportApiController.
 */
class ReportApiController extends CommonApiController
{
    /**
     * {@inheritdoc}
     */
    public function initialize(FilterControllerEvent $event)
    {
        $this->model            = $this->getModel('report');
        $this->entityClass      = 'Mautic\ReportBundle\Entity\Report';
        $this->entityNameOne    = 'report';
        $this->entityNameMulti  = 'reports';
        $this->serializerGroups = ['reportList', 'reportDetails'];

        parent::initialize($event);
    }

    /**
     * Obtains a compiled report.
     *
     * @param int $id Report ID
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getReportAction($id)
    {
        $entity = $this->model->getEntity($id);

        if (!$entity instanceof $this->entityClass) {
            return $this->notFound();
        }

        $reportData = $this->model->getReportData($entity, $this->container->get('form.factory'), ['paginate' => false, 'ignoreGraphData' => true]);

        // Unset keys that we don't need to send back
        foreach (['graphs', 'contentTemplate', 'columns', 'limit'] as $key) {
            unset($reportData[$key]);
        }

        $view = $this->view($reportData, Codes::HTTP_OK);

        return $this->handleView($view);
    }
    
    public function newReportAction(){
        $entity = $this->model->getEntity();
        
        $parameters = $this->request->request->all();
        if(isset($parameters["campaign_id"]) && $parameters["campaign_id"]){
            $parameters["name"] = "Program report ".$parameters["campaign_id"];
            $parameters["source"] = "email.stats";
            $parameters["description"] = "Program report ".$parameters["campaign_id"];
            $parameters["isPublished"] = 1;
            $parameters["system"] = 1;
            $parameters["columns"][] = "clel.campaign_id";
            $parameters["columns"][] = "bounced";
            $parameters["columns"][] = "e.name";
            $parameters["columns"][] = "e.subject";
            $parameters["columns"][] = "is_hit";
            $parameters["columns"][] = "hits";
            $parameters["columns"][] = "es.date_read";
            $parameters["columns"][] = "es.date_sent";
            $parameters["columns"][] = "es.email_address";
            $parameters["columns"][] = "i.ip_address";
            $parameters["columns"][] = "read_delay";
            $parameters["columns"][] = "es.retry_count";
            $parameters["columns"][] = "e.revision";
            $parameters["columns"][] = "e.sent_count";
            $parameters["columns"][] = "unique_hits";
            $parameters["columns"][] = "unsubscribed";
            $parameters["columns"][] = "es.viewed_in_browser";
            $parameters["filters"][2]["glue"] = "and";
            $parameters["filters"][2]["column"] = "clel.campaign_id";
            $parameters["filters"][2]["condition"] = "eq";
            $parameters["filters"][2]["value"] = $parameters["campaign_id"];
            $parameters["filters"][2]["dynamic"] = 0;
            
            return $this->processForm($entity, $parameters, 'POST');
        }else{
            return $this->returnError("campaign_id is missing from request", Codes::HTTP_BAD_REQUEST);
        }
    }
}
