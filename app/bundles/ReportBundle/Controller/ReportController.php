<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ReportBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Model\ExportResponse;
use Symfony\Component\HttpFoundation;
use Illuminate\Support\Collection;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Class ReportController.
 */
class ReportController extends FormController
{
    public $client;
    public $dbconfig;

    public function __construct() {
        $this->client = new GuzzleClient();
        $this->dbconfig = ['db_host'=>'qtrack-wpeople.cbowlsah6p62.us-east-1.rds.amazonaws.com','db_name'=>'wpeople','db_user'=>'qtrackwpeople','db_password'=>'iWru^QeJD3Kq'];
        $this->aws_api_url = 'https://9maq510jxb.execute-api.us-east-1.amazonaws.com/stage/';
    }
    /**
     * @param int $page
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse|HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model = $this->getModel('report');

        //set some permissions
        $permissions = $this->container->get('mautic.security')->isGranted(
            [
                'report:reports:viewown',
                'report:reports:viewother',
                'report:reports:create',
                'report:reports:editown',
                'report:reports:editother',
                'report:reports:deleteown',
                'report:reports:deleteother',
                'report:reports:publishown',
                'report:reports:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['report:reports:viewown'] && !$permissions['report:reports:viewother']) {
            return $this->accessDenied();
        }

        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        //set limits
        $limit = $this->container->get('session')->get('mautic.report.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $this->container->get('session')->get('mautic.report.filter', ''));
        $this->container->get('session')->set('mautic.report.filter', $search);

        $filter = ['string' => $search, 'force' => []];

        if (!$permissions['report:reports:viewother']) {
            $filter['force'][] = ['column' => 'r.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        $orderBy    = $this->container->get('session')->get('mautic.report.orderby', 'r.name');
        $orderByDir = $this->container->get('session')->get('mautic.report.orderbydir', 'DESC');

        $reports = $model->getEntities(
            [
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderBy'    => $orderBy,
                'orderByDir' => $orderByDir,
            ]
        );

        $count = count($reports);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            $lastPage = ($count === 1) ? 1 : (ceil($count / $limit)) ?: 1;
            $this->container->get('session')->set('mautic.report.page', $lastPage);
            $returnUrl = $this->generateUrl('mautic_report_index', ['page' => $lastPage]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $lastPage],
                    'contentTemplate' => 'MauticReportBundle:Report:index',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_report_index',
                        'mauticContent' => 'report',
                    ],
                ]
            );
        }

        //set what page currently on so that we can return here after form submission/cancellation
        $this->container->get('session')->set('mautic.report.page', $page);

        $tmpl = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';

        return $this->delegateView(
            [
                'viewParameters' => [
                    'searchValue' => $search,
                    'items'       => $reports,
                    'totalItems'  => $count,
                    'page'        => $page,
                    'limit'       => $limit,
                    'permissions' => $permissions,
                    'model'       => $model,
                    'tmpl'        => $tmpl,
                    'security'    => $this->container->get('mautic.security'),
                ],
                'contentTemplate' => 'MauticReportBundle:Report:list.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_report_index',
                    'mauticContent' => 'report',
                    'route'         => $this->generateUrl('mautic_report_index', ['page' => $page]),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @param int $objectId
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse|HttpFoundation\Response
     */
    public function cloneAction($objectId)
    {
        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model  = $this->getModel('report');
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->container->get('mautic.security')->isGranted('report:reports:create')
                || !$this->container->get('mautic.security')->hasEntityAccess(
                    'report:reports:viewown',
                    'report:reports:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
            $entity->setIsPublished(false);
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param $objectId
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->container->get('session')->get('mautic.report.page', 1);
        $returnUrl = $this->generateUrl('mautic_report_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticReportBundle:Report:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_report_index',
                'mauticContent' => 'report',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            /* @type \Mautic\ReportBundle\Model\ReportModel $model */
            $model  = $this->getModel('report');
            $entity = $model->getEntity($objectId);

            $check = $this->checkEntityAccess(
                $postActionVars,
                $entity,
                $objectId,
                ['report:reports:deleteown', 'report:reports:deleteother'],
                $model,
                'report'
            );
            if ($check !== true) {
                return $check;
            }

            $model->deleteEntity($entity);

            $identifier = $this->get('translator')->trans($entity->getName());
            $flashes[]  = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $identifier,
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->container->get('session')->get('mautic.report.page', 1);
        $returnUrl = $this->generateUrl('mautic_report_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticReportBundle:Report:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_report_index',
                'mauticContent' => 'report',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model     = $this->getModel('report');
            $ids       = json_decode($this->request->query->get('ids', '{}'));
            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.report.report.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->container->get('mautic.security')->hasEntityAccess(
                    'report:reports:deleteown',
                    'report:reports:deleteother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'report', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.report.report.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * Generates edit form and processes post data.
     *
     * @param int  $objectId   Item ID
     * @param bool $ignorePost Flag to ignore POST data
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse|HttpFoundation\Response
     */
    public function editAction($objectId, $ignorePost = false)
    {
        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model   = $this->getModel('report');
        $entity  = $model->getEntity($objectId);
        $session = $this->container->get('session');
        $page    = $session->get('mautic.report.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_report_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticReportBundle:Report:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_report_index',
                'mauticContent' => 'report',
            ],
        ];

        //not found
        $check = $this->checkEntityAccess(
            $postActionVars,
            $entity,
            $objectId,
            ['report:reports:viewown', 'report:reports:viewother'],
            $model,
            'report'
        );
        if ($check !== true) {
            return $check;
        }

        //Create the form
        $action = $this->generateUrl('mautic_report_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if (!$ignorePost && $this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                // Columns have to be reset in order for Symfony to honor the new submitted order
                $oldColumns = $entity->getColumns();
                $entity->setColumns([]);

                $oldGraphs = $entity->getGraphs();
                $entity->setGraphs([]);
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_report_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_report_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    $returnUrl = $this->generateUrl(
                        'mautic_report_view',
                        [
                            'objectId' => $entity->getId(),
                        ]
                    );
                    $viewParams = ['objectId' => $entity->getId()];
                    $template   = 'MauticReportBundle:Report:view';
                } else {
                    //reset old columns
                    $entity->setColumns($oldColumns);
                    $entity->setGraphs($oldGraphs);
                }
            } else {
                //unlock the entity
                $model->unlockEntity($entity);

                $returnUrl  = $this->generateUrl('mautic_report_index', ['page' => $page]);
                $viewParams = ['report' => $page];
                $template   = 'MauticReportBundle:Report:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                // Clear session items in case columns changed
                $session->remove('mautic.report.'.$entity->getId().'.orderby');
                $session->remove('mautic.report.'.$entity->getId().'.orderbydir');

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $returnUrl,
                            'viewParameters'  => $viewParams,
                            'contentTemplate' => $template,
                        ]
                    )
                );
            } elseif ($valid) {
                // Rebuild the form for updated columns
                $form = $model->createForm($entity, $this->get('form.factory'), $action);
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'report' => $entity,
                    'form'   => $this->setFormTheme($form, 'MauticReportBundle:Report:form.html.php', 'MauticReportBundle:FormTheme\Report'),
                ],
                'contentTemplate' => 'MauticReportBundle:Report:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_report_index',
                    'mauticContent' => 'report',
                    'route'         => $this->generateUrl(
                        'mautic_report_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Generates new form and processes post data.
     *
     * @param \Mautic\ReportBundle\Entity\Report|null $entity
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse|HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        if (!$this->container->get('mautic.security')->isGranted('report:reports:create')) {
            return $this->accessDenied();
        }

        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model = $this->getModel('report');

        if (!($entity instanceof Report)) {
            /** @var \Mautic\ReportBundle\Entity\Report $entity */
            $entity = $model->getEntity();
        }

        $session = $this->container->get('session');
        $page    = $session->get('mautic.report.page', 1);

        $action = $this->generateUrl('mautic_report_action', ['objectAction' => 'new']);
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_report_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_report_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if (!$form->get('buttons')->get('save')->isClicked()) {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }

                    $viewParameters = [
                        'objectId' => $entity->getId(),
                    ];
                    $returnUrl = $this->generateUrl('mautic_report_view', $viewParameters);
                    $template  = 'MauticReportBundle:Report:view';
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_report_index', $viewParameters);
                $template       = 'MauticReportBundle:Report:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => [
                            'activeLink'    => 'mautic_asset_index',
                            'mauticContent' => 'asset',
                        ],
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'report' => $entity,
                    'form'   => $this->setFormTheme($form, 'MauticReportBundle:Report:form.html.php', 'MauticReportBundle:FormTheme\Report'),
                ],
                'contentTemplate' => 'MauticReportBundle:Report:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_report_index',
                    'mauticContent' => 'report',
                    'route'         => $this->generateUrl(
                        'mautic_report_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Shows a report.
     *
     * @param int $objectId   Report ID
     * @param int $reportPage
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\Response
     */
    public function viewAction($objectId, $reportPage = 1)
    {
        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model    = $this->getModel('report');
        $entity   = $model->getEntity($objectId);
        $security = $this->container->get('mautic.security');

        if ($entity === null) {
            $page = $this->container->get('session')->get('mautic.report.page', 1);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $this->generateUrl('mautic_report_index', ['page' => $page]),
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => 'MauticReportBundle:Report:index',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_report_index',
                        'mauticContent' => 'report',
                    ],
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.report.report.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ]
            );
        } elseif (!$security->hasEntityAccess('report:reports:viewown', 'report:reports:viewother', $entity->getCreatedBy())) {
            return $this->accessDenied();
        }

        // Set filters
        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        $mysqlFormat = 'Y-m-d';
        $session     = $this->container->get('session');

        // Init the forms
        $action = $this->generateUrl('mautic_report_action', ['objectAction' => 'view', 'objectId' => $objectId]);

        // Get the date range filter values from the request of from the session
        $dateRangeValues = $this->request->get('daterange', []);

        if (!empty($dateRangeValues['date_from'])) {
            $from = new \DateTime($dateRangeValues['date_from']);
            $session->set('mautic.report.date.from', $from->format($mysqlFormat));
        } elseif ($fromDate = $session->get('mautic.report.date.from')) {
            $dateRangeValues['date_from'] = $fromDate;
        }
        if (!empty($dateRangeValues['date_to'])) {
            $to = new \DateTime($dateRangeValues['date_to']);
            $session->set('mautic.report.date.to', $to->format($mysqlFormat));
        } elseif ($toDate = $session->get('mautic.report.date.to')) {
            $dateRangeValues['date_to'] = $toDate;
        }

        $dateRangeForm = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $action]);
        if ($this->request->getMethod() == 'POST' && $this->request->request->has('daterange')) {
            if ($this->isFormValid($dateRangeForm)) {
                $to                         = new \DateTime($dateRangeForm['date_to']->getData());
                $dateRangeValues['date_to'] = $to->format($mysqlFormat);
                $session->set('mautic.report.date.to', $dateRangeValues['date_to']);

                $from                         = new \DateTime($dateRangeForm['date_from']->getData());
                $dateRangeValues['date_from'] = $from->format($mysqlFormat);
                $session->set('mautic.report.date.from', $dateRangeValues['date_from']);
            }
        }

        // Setup dynamic filters
        $filterDefinitions = $model->getFilterList($entity->getSource());
        /** @var array $dynamicFilters */
        $dynamicFilters = $session->get('mautic.report.'.$objectId.'.filters', []);
        $filterSettings = [];

        if (count($dynamicFilters) > 0 && count($entity->getFilters()) > 0) {
            foreach ($entity->getFilters() as $fid => $filter) {
                foreach ($dynamicFilters as $dfcol => $dfval) {
                    if (1 === $filter['dynamic'] && $filter['column'] === $dfcol) {
                        $dynamicFilters[$dfcol]['expr'] = $filter['condition'];
                        break;
                    }
                }
            }
        }

        foreach ($dynamicFilters as $filter) {
            $filterSettings[$filterDefinitions->definitions[$filter['column']]['alias']] = $filter['value'];
        }

        $dynamicFilterForm = $this->get('form.factory')->create(
            'report_dynamicfilters',
            $filterSettings,
            [
                'action'            => $action,
                'report'            => $entity,
                'filterDefinitions' => $filterDefinitions,
            ]
        );

        $reportData = $model->getReportData(
            $entity,
            $this->container->get('form.factory'),
            [
                'dynamicFilters' => $dynamicFilters,
                'paginate'       => true,
                'reportPage'     => $reportPage,
                'dateFrom'       => new \DateTime($dateRangeForm->get('date_from')->getData()),
                'dateTo'         => new \DateTime($dateRangeForm->get('date_to')->getData()),
            ]
        );

        return $this->delegateView(
            [
                'viewParameters' => [
                    'data'         => $reportData['data'],
                    'columns'      => $reportData['columns'],
                    'dataColumns'  => $reportData['dataColumns'],
                    'totalResults' => $reportData['totalResults'],
                    'debug'        => $reportData['debug'],
                    'report'       => $entity,
                    'reportPage'   => $reportPage,
                    'graphs'       => $reportData['graphs'],
                    'tmpl'         => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                    'limit'        => $reportData['limit'],
                    'permissions'  => $security->isGranted(
                        [
                            'report:reports:viewown',
                            'report:reports:viewother',
                            'report:reports:create',
                            'report:reports:editown',
                            'report:reports:editother',
                            'report:reports:deleteown',
                            'report:reports:deleteother',
                        ],
                        'RETURN_ARRAY'
                    ),
                    'dateRangeForm'     => $dateRangeForm->createView(),
                    'dynamicFilterForm' => $dynamicFilterForm->createView(),
                ],
                'contentTemplate' => $reportData['contentTemplate'],
                'passthroughVars' => [
                    'activeLink'    => '#mautic_report_index',
                    'mauticContent' => 'report',
                    'route'         => $this->generateUrl(
                        'mautic_report_view',
                        [
                            'objectId'   => $entity->getId(),
                            'reportPage' => $reportPage,
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Checks access to an entity.
     *
     * @param object                               $entity
     * @param int                                  $objectId
     * @param array                                $permissions
     * @param \Mautic\CoreBundle\Model\CommonModel $model
     * @param string                               $modelName
     *
     * @return HttpFoundation\JsonResponse|HttpFoundation\RedirectResponse|void
     */
    private function checkEntityAccess($postActionVars, $entity, $objectId, array $permissions, $model, $modelName)
    {
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.report.report.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->container->get('mautic.security')->hasEntityAccess($permissions[0], $permissions[1], $entity->getCreatedBy())) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, $modelName);
        }

        return true;
    }

    /**
     * @param int    $objectId
     * @param string $format
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @throws \Exception
     */
    public function exportAction($objectId, $format = 'csv')
    {
        /* @type \Mautic\ReportBundle\Model\ReportModel $model */
        $model    = $this->getModel('report');
        $entity   = $model->getEntity($objectId);
        $security = $this->container->get('mautic.security');

        if ($entity === null) {
            $page = $this->container->get('session')->get('mautic.report.page', 1);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $this->generateUrl('mautic_report_index', ['page' => $page]),
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => 'MauticReportBundle:Report:index',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_report_index',
                        'mauticContent' => 'report',
                    ],
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.report.report.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ]
            );
        } elseif (!$security->hasEntityAccess('report:reports:viewown', 'report:reports:viewother', $entity->getCreatedBy())) {
            return $this->accessDenied();
        }

        $session  = $this->get('session');
        $fromDate = $session->get('mautic.report.date.from', (new \DateTime('-30 days'))->format('Y-m-d'));
        $toDate   = $session->get('mautic.report.date.to', (new \DateTime())->format('Y-m-d'));

        $date    = (new DateTimeHelper())->toLocalString();
        $name    = str_replace(' ', '_', $date).'_'.InputHelper::alphanum($entity->getName(), false, '-');
        $options = ['dateFrom' => new \DateTime($fromDate), 'dateTo' => new \DateTime($toDate)];

        $dynamicFilters            = $session->get('mautic.report.'.$objectId.'.filters', []);
        $options['dynamicFilters'] = $dynamicFilters;

        if ($format === 'csv') {
            $response = new HttpFoundation\StreamedResponse(
                function () use ($model, $entity, $format, $options) {
                    $options['paginate'] = true;
                    $options['ignoreGraphData'] = true;
                    $options['limit'] =
                    $reportData['totalResults'] = 10000;
                    $options['page'] = 1;
                    $handle = fopen('php://output', 'r+');
                    while ($reportData['totalResults'] >= ($options['page'] - 1) * $options['limit']) {
                        $reportData = $model->getReportData($entity, null, $options);
                        $model->exportResults($format, $entity, $reportData, $handle, $options['page']);
                        ++$options['page'];
                    }
                    fclose($handle);
                }
            );

            $fileName = $name.'.'.$format;
            ExportResponse::setResponseHeaders($response, $fileName);
        } else {
            if ($format === 'xlsx') {
                $options['ignoreGraphData'] = true;
            }
            $reportData = $model->getReportData($entity, null, $options);
            $response   = $model->exportResults($format, $entity, $reportData);
        }

        return $response;
    }
    
    public function lamdaApiSummaryReport($summary_report_data = array()){

        $return_data = [];
        try {
            $campain_id = $summary_report_data['campain_id'];
            //$user_email = 'harshshah1020120120@qdata.io';
            $user_summary_report_data = ['dbConfig'=>
                $this->dbconfig,
                'campaignId' => $campain_id,
                'Bucket' => 'bancolombia-files'
            ];
            /*echo 'inputuser_receipt_data';
                print_r($user_receipt_data);                                    */
            $res = $this->client->request('POST', $this->aws_api_url.'reports/summary-report', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($user_receipt_data)
            ]);

            $res->getHeader('content-type');
            $ress_body = json_decode($res->getBody(),true);
            //echo '<pre>';print_r($ress_body);exit;
            $data = $ress_body;
            if(!is_array($ress_body)){
                $msg = 'error in response of api';
                throw new \Exception($msg,0);    
            }
            
            /*if(!is_array($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }*/
            if(!isset($ress_body['data'])){
                $msg = 'error in response data of api';
                throw new \Exception($msg,0);   
            }
            
            //$user_email_id = 'harshshah@qdata.io';
            $receipt_id = $ress_body['data']['recipientId'];

            $msg = 'succesfully data';
            throw new \Exception($msg,1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $isuccess=$e->getCode();
        }
        $return_data['message'] = $msg;
        $return_data['success'] = $isuccess;
        $return_data['data'] = $data;
        return $return_data;
    }

    public function insertReportSummaryData($insert_report_data = []){

        $return_data = [];
        $em = $this->container->get('doctrine.orm.entity_manager');
        $q = $em->getConnection()->createQueryBuilder();
        
        $q->insert(MAUTIC_TABLE_PREFIX.'reports')->values(['is_published' => '?', 'date_added' => '?', 'created_by' => '?','created_by_user' => '?','name' => '?','is_scheduled' => '?','status'=>'?','s3_path'=>'?','campaign_id'=>'?'])->setParameter(0, $insert_report_data['is_published'])->setParameter(1, $insert_report_data['date_added'])->setParameter(2, $insert_report_data['created_by'])->setParameter(3, $insert_report_data['created_by_user'])->setParameter(4, $insert_report_data['name'])->setParameter(5, $insert_report_data['is_scheduled'])->setParameter(6, $insert_report_data['status'])->setParameter(7, $insert_report_data['s3_path'])->setParameter(8, $insert_report_data['campaign_id']);

        $q->execute();
        $last_inserted_id = $em->getConnection()->lastInsertId()
        $return_data['last_inserted_id'=>$last_inserted_id];
        return $return_data;
    }

    public function checkCampaignReportStatusAction($campaignId){
        echo $campaignId;exit;
        $return_data = [];
        $s3_path;
        $em = $this->container->get('doctrine.orm.entity_manager');

        $query = $em->createQuery("SELECT * FROM reports re where campaign_id='".$campaignId."' order by id desc limit 1");
        $report_campaign = $query->getResult();

        if(count($report_campaign) > 0){
            $s3_path = $report_campaign['s3_path'];
        }

        $return_data = ['s3_path' => $s3_path];
        return $return_data;
    }
    public function summaryReportAction($campaignId){
        //echo 'dsadasdas';exit;
        $em = $this->container->get('doctrine.orm.entity_manager');
        
        $insert_report_data = ['is_published'=>1,
                               'date_added'=>date("Y-m-d H:i:s"),
                               'created_by'=>,
                               'created_by_user'=>,
                               'name'=>'Summary Report-'.date("Y-m-d H:i:s"),
                               'is_scheduled'=>1,
                               'status'=>'In-Progress',
                               's3_path'=>'',
                               'campaign_id' => $campaignId
                            ];
        $db_insert_report_data = $this->insertReportSummaryData($insert_report_data);

        $summary_report_data = ['compaign_id'=>$campaignId,'report_id'=>$db_insert_report_data['last_inserted_id']];
        $silverpop_summary_report = $this->lamdaApiSummaryReport($summary_report_data);

        return $silverpop_summary_report;
        /*$EMAIL_STATS = "SELECT e.id as email_id, e.name as email_name, e.subject, es.email_address, es.date_sent, c.name as campaign_name, es.open_count, es.lead_id FROM "
                . "email_stats es "
                . "INNER JOIN emails e ON e.id = es.email_id "
                . "INNER JOIN campaign_events ce ON ce.id = es.source_id "
                . "INNER JOIN campaigns c ON c.id = ce.campaign_id "
                . "WHERE es.source = 'campaign.event' AND es.source_id in (select id from campaign_events where campaign_id = ".$campaignId.")";
        
        $statement = $em->getConnection()->prepare($EMAIL_STATS);
        $statement->execute();
        
        $final_array = [["Campaña", "MailingId", "Email", "Asunto", "Sent", "Received", "Open", "Click Through", "Unique Open", "Unique Click", "Opt Out Reply", "Change Address", "Reply Mail Block", "Hard Bounce", "Soft Bounce", "Suppressed", "Reply Change Address", "Received_Perc", "Open_Perc", "ClickThrough_Perc"]];

        $resultopen = $statement->fetchAll();
//        echo "<pre>";
//        print_r($resultopen);exit;
        if(is_array($resultopen) && count($resultopen) > 0){
            $resultopen = new Collection($resultopen);
            $unique_email = $resultopen->keyBy('email_id');
            foreach ($unique_email as $key => $value) {
                $clickquery = "SELECT * FROM page_hits ph INNER JOIN page_redirects pr ON pr.id = ph.redirect_id where ph.source = 'email' AND source_id = ".$key;
                $statement = $em->getConnection()->prepare($clickquery);
                $statement->execute();
                $resultclick = new Collection($statement->fetchAll());
                
                $totalsent = $resultopen->where('email_id',$key)->groupBy('email_address')->count();
                $totalreceived = $totalsent;
                $totalopen = $resultopen->where('email_id',$key)->sum('open_count');
                $totalclick = $resultclick->count();
                
                $unique_click_data = $resultclick->groupBy('lead_id');
                $unique_click = $unique_click_data->count();
                
                $unique_open_data = $resultopen->where('email_id',$key)->where('open_count','>',0)->groupBy('lead_id');
                $unique_open = $unique_open_data->count();
                
                $open_percentage = number_format(100*$unique_open/$totalsent,2);
                $click_percentage = number_format(($unique_click/$totalsent)*100,2);
                $received_percentage = number_format(($totalreceived/$totalsent)*100,2);
                
                $final_array[] = [$value["campaign_name"], $value["email_id"], $value["email_name"], $value["subject"], $totalsent, $totalreceived, $totalopen, $totalclick, $unique_open, $unique_click,0,0,0,0,0,0,0,$received_percentage,$open_percentage, $click_percentage, ];
                
                
            }
        } else {
            
        }
        
        $fileName = 'Program Summary Report '.$campaignId.'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Expires: 0");
        header("Pragma: public");
        $fh = @fopen( 'php://output', 'w' );
        
        foreach ($final_array as $row){
            fputcsv($fh, $row);
            //$csvarr[] = [$row["campaign_name"], $row["recipient_id"], "",$row["email_id"], "", "", $row["campaign_id"], $row["email"], $row["event_type"], $row["event_timestamp"],$row["body_type"], $row["content_id"], $row["click_name"], $row["url"], $row["conversion_action"], $row["conversion_detail"], $row["conversion_amount"], $row["suppression_reason"], $row["recipient_name"], $row["email"]];
        }*/
        exit;
        
    }
    
    public function rawReportAction($campaignId){
        $rawdata = $this->getRawReportData($campaignId);
        $fileName = 'Program Raw Report '.$campaignId.'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Expires: 0");
        header("Pragma: public");
        $fh = @fopen( 'php://output', 'w' );
        $columns = ["Campaña","Recipient Id","Recipient Type","Mailing Id","Qtrack","Report Id","Campaign Id","Email","Event Type","Event Timestamp","Body Type","Content Id","Click Name","URL","Conversion Action","Conversion Detail","Conversion Amount","Suppression Reason","nombre","Email"];
        fputcsv($fh, $columns);
        foreach ($rawdata as $row){
            fputcsv($fh, [$row["campaign_name"], $row["recipient_id"], "",$row["email_id"], "", "", $row["campaign_id"], $row["email"], $row["event_type"], $row["event_timestamp"],$row["body_type"], $row["content_id"], $row["click_name"], $row["url"], $row["conversion_action"], $row["conversion_detail"], $row["conversion_amount"], $row["suppression_reason"], $row["recipient_name"], $row["email"]]);
            //$csvarr[] = [$row["campaign_name"], $row["recipient_id"], "",$row["email_id"], "", "", $row["campaign_id"], $row["email"], $row["event_type"], $row["event_timestamp"],$row["body_type"], $row["content_id"], $row["click_name"], $row["url"], $row["conversion_action"], $row["conversion_detail"], $row["conversion_amount"], $row["suppression_reason"], $row["recipient_name"], $row["email"]];
        }
        exit;
        
    }
    
    
    private function getRawReportData($campaignId){
        
        $em = $this->container->get('doctrine.orm.entity_manager');
        $RAW_QUERY = "SELECT c.name campaign_name, l.id recipient_id, l.name recipient_name, e.id email_id, c.id campaign_id, l.email, "
                    . "'open' event_type, esd.date_opened event_timestamp, 'HTML' body_type, '' content_id, '' click_name, '' url, '' conversion_action, "
                    . "'' conversion_detail, '' conversion_amount, '' suppression_reason, ia.id ip_id, ia.ip_address ip_address, esd.device_id "
                . "FROM email_stats_devices esd "
                . "INNER JOIN email_stats es ON esd.stat_id = es.id "
                . "INNER JOIN leads l ON l.id = es.lead_id "
                . "INNER JOIN emails e ON e.id = es.email_id "
                . "INNER JOIN ip_addresses ia ON ia.id = esd.ip_id "
                . "INNER JOIN lead_devices ld ON ld.id = esd.device_id "
                . "INNER JOIN campaign_events ce ON ce.id = es.source_id "
                . "INNER JOIN campaigns c ON c.id = ce.campaign_id "
                . "where source = 'campaign.event' AND source_id in (select id from campaign_events where campaign_id = ".$campaignId.")";
                
        
        $statement = $em->getConnection()->prepare($RAW_QUERY);
        $statement->execute();

        $resultopen = $statement->fetchAll();

        $email_ids = $this->pluck_array_reduce('email_id', $resultopen);
        
        if(is_array($email_ids) && count($email_ids) > 0){
            $email_ids = array_unique($email_ids);
            $RAW_QUERY = "SELECT * "
                    . "FROM page_hits ph "
                    . "INNER JOIN page_redirects pr ON pr.id = ph.redirect_id "
                    . "where source = 'email' AND source_id in (" . implode(",", $email_ids) . ")";

            $statement = $em->getConnection()->prepare($RAW_QUERY);
            $statement->execute();

            $resultclick = $statement->fetchAll();
//            echo "<pre>";
//            print_r($resultclick);
//            print_r($resultopen);exit;
            foreach ($resultclick as $clickresult){
                foreach($resultopen as $openresult){
                    if($openresult["ip_id"] == $clickresult["ip_id"] && $openresult["email_id"] == $clickresult["email_id"] && $openresult["recipient_id"] == $clickresult["lead_id"]){
                        $newobject = $openresult;
                        $newobject["url"] = $clickresult["url"];
                        $newobject["event_type"] = "Click Through";
                        $newobject["event_timestamp"] = $clickresult["date_hit"];
                        array_push($resultopen, $newobject);
                        break;
                    }
                }
            }            
        }else{
            $resultopen = [];
        }
        if(is_array($resultopen) && count($resultopen)>0){}
        return $resultopen;
        
    }
    
    public function pluck_array_reduce($key, $data) {
        return array_reduce($data, function($result, $array) use($key) {
            isset($array[$key]) &&
                    $result[] = $array[$key];

            return $result;
        }, array());
    }

}
