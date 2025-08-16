<?php

namespace Apps\Fintech\Components\Mf\Schemes;

use Apps\Fintech\Packages\Adminltetags\Traits\DynamicTable;
use Apps\Fintech\Packages\Mf\Amcs\MfAmcs;
use Apps\Fintech\Packages\Mf\Categories\MfCategories;
use Apps\Fintech\Packages\Mf\Schemes\MfSchemes;
use Apps\Fintech\Packages\Mf\Tools\Patterns\MfToolsPatterns;
use System\Base\BaseComponent;

class SchemesComponent extends BaseComponent
{
    use DynamicTable;

    protected $schemesPackage;

    protected $categoriesPackage;

    protected $amcsPackage;

    protected $categories = [];

    protected $amcs = [];

    public function initialize()
    {
        $this->schemesPackage = $this->usePackage(MfSchemes::class);

        $this->categoriesPackage = $this->usePackage(MfCategories::class);

        $this->amcsPackage = $this->usePackage(MfAmcs::class);

        $this->setModuleSettings(true);

        $this->setModuleSettingsData([
                'apis' => $this->schemesPackage->getAvailableApis(true, false),
                'apiClients' => $this->schemesPackage->getAvailableApis(false, false)
            ]
        );

        //Increase memory_limit to 1G as the process takes a bit of memory to process the array.
        if ((int) ini_get('memory_limit') < 1024) {
            ini_set('memory_limit', '1024M');
        }
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        if (isset($this->getData()['id'])) {
            if ($this->getData()['id'] != 0) {
                $this->view->custom = false;
                $this->view->compare = false;
                $this->view->customNavStartDate = '';
                $this->view->customNavEndDate = '';

                if (isset($this->getData()['custom']) && $this->getData()['custom'] == true) {
                    $scheme = $this->schemesPackage->getSchemeById((int) $this->getData()['id'], false, false, false, true, true, true);

                    if (!$scheme) {
                        return $this->throwIdNotFound();
                    }

                    $this->view->customNavStartDate = '-';
                    $this->view->customNavEndDate = '-';

                    $customArr[] = 0;
                    if ($scheme['custom'] && isset($scheme['custom']['navs'])) {
                        $this->view->customNavStartDate = $this->helper->first($scheme['custom']['navs'])['date'];
                        $this->view->customNavEndDate = $this->helper->last($scheme['custom']['navs'])['date'];

                        foreach ($scheme['custom']['navs'] as $customNavs) {
                            if (isset($customNavs['diff_percent_since_inception'])) {
                                array_push($customArr, $customNavs['diff_percent_since_inception']);
                            }
                        }
                    }

                    $scheme['custom'] = $customArr;

                    $patternsPackage = $this->usePackage(MfToolsPatterns::class);

                    $this->view->patterns = [];

                    $patterns = $patternsPackage->getByParams(['conditions' => '', 'columns' => ['id', 'name']]);

                    if ($patterns) {
                        $this->view->patterns = $patterns;
                    }

                    $this->view->custom = true;

                    if ($scheme['custom_chunks']) {
                        $this->getProcessedSchemeNavChunksAction($scheme, true);

                        $this->view->schemeNavChunks = $scheme['custom_chunks']['navs_chunks'];

                        unset($scheme['custom_chunks']);//Remove Chunks
                        unset($scheme['navs_chunks']);//Remove Chunks
                    }

                    if ($scheme['custom_rolling_returns']) {
                        $this->view->rollingPeriods = $this->getProcessedSchemeRollingReturnsAction($scheme, true);

                        $this->view->schemeNavRR = $scheme['custom_rolling_returns'];

                        unset($scheme['custom_rolling_returns']);//Remove RR
                        unset($scheme['rolling_returns']);//Remove RR
                    }

                    $this->view->scheme = $scheme;

                    $this->view->pick('schemes/view');

                    return;
                }

                $scheme = $this->schemesPackage->getSchemeById((int) $this->getData()['id'], false, true, true);

                if (!$scheme) {
                    return $this->throwIdNotFound();
                }

                $this->view->compare = false;

                $this->getProcessedSchemeNavChunksAction($scheme);

                $this->view->schemeNavChunks = $scheme['navs_chunks']['navs_chunks'];

                unset($scheme['navs_chunks']);//Remove Chunks

                $this->view->rollingPeriods = $this->getProcessedSchemeRollingReturnsAction($scheme);

                $this->view->schemeNavRR = $scheme['rolling_returns'];

                unset($scheme['rolling_returns']);//Remove RR

                $this->view->scheme = $scheme;
            }

            $this->view->pick('schemes/view');

            return;
        }

        $amcs = msort($this->amcsPackage->getAll()->mfamcs, 'name');
        foreach ($amcs as $amcId => &$amc) {
            $amc['name'] = $amc['name'] . ' (' . $amc['id'] . ')';
        }

        $categories = $this->categoriesPackage->getAll()->mfcategories;

        $parents = [];

        foreach ($categories as $categoryId => &$category) {
            if (isset($category['parent_id'])) {
                if (!isset($parents[$category['parent_id']])) {
                    $parents[$category['parent_id']] = $categories[$category['parent_id']];
                    unset($categories[$categoryId]);
                }

                $category['name'] = $parents[$category['parent_id']]['name'] . ': ' . $category['name'] . ' (' . $category['id'] . ')';
            } else {
                if (!isset($parents[$category['parent_id']])) {
                    $parents[$categoryId] = $categories[$categoryId];
                    unset($categories[$categoryId]);
                }
            }
        }

        if ($this->request->isGet()) {
            $this->view->apis = $this->schemesPackage->getAvailableApis(true, false);
            $this->view->amcs = msort($amcs, 'name');
            $this->view->categories = msort(array: $categories, key: 'name', preserveKey: true);
        }

        if (isset($this->getData()['compare'])) {
            $this->view->pick('schemes/list');

            $this->view->compare = true;
            $this->view->custom = false;
            $this->view->customNavStartDate = '';
            $this->view->customNavEndDate = '';

            return true;
        }

        $this->view->today = $this->view->timeline = (\Carbon\Carbon::now())->toDateString();
        $postUrl = 'mf/schemes/view';

        if (isset($this->getData()['timeline'])) {
            try {
                $this->view->timeline = (\Carbon\Carbon::parse($this->getData()['timeline']))->toDateString();

                $postUrl = 'mf/schemes/view/q/timeline/' . $this->view->timeline;
            } catch (\throwable $e) {
                // Do nothing
            }
        }

        $controlActions =
            [
                'includeQ'              => true,
                'actionsToEnable'       =>
                [
                    'view'          => 'mf/schemes/q/',
                    'customNavs'    =>
                        [
                            'title'             => 'Custom Navs',
                            'icon'              => 'money-bill-trend-up',
                            'additionalClass'   => 'custom contentAjaxLink',
                            'link'              => 'mf/schemes/q/custom/true/'
                        ],
                ]
            ];

        $replaceColumns =
            function ($dataArr) {
                if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                    return $this->replaceColumns($dataArr);
                }

                return $dataArr;
            };

        $conditions = null;

        if ($this->request->isPost()) {
            if (count($this->dispatcher->getParams()) > 0 &&
                $this->dispatcher->getParams()[0] === 'timeline'
            ) {
                $conditions = ['conditions' => '-|start_date|lessthanequals|' . $this->dispatcher->getParams()[1] .  '&'];
            }
        }

        $this->generateDTContent(
            package : $this->schemesPackage,
            postUrl : $postUrl,
            postUrlParams: $conditions,
            columnsForTable : ['name', 'year_cagr', 'two_year_cagr', 'three_year_cagr', 'five_year_cagr', 'seven_year_cagr', 'ten_year_cagr', 'fifteen_year_cagr', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            columnsForFilter : ['name', 'year_cagr', 'two_year_cagr', 'three_year_cagr', 'five_year_cagr', 'seven_year_cagr', 'ten_year_cagr', 'fifteen_year_cagr', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            controlActions : $controlActions,
            dtReplaceColumnsTitle : ['day_cagr' => '1DTR', 'day_trajectory' => '1D Trend', 'year_cagr' => '1YTR', 'two_year_cagr' => '2YTR', 'three_year_cagr' => '3YTR', 'five_year_cagr' => '5YTR', 'seven_year_cagr' => '7YTR', 'ten_year_cagr' => '10YTR', 'fifteen_year_cagr' => '15YTR', 'year_rr' => '1YRR', 'two_year_rr' => '2YRR', 'three_year_rr' => '3YRR', 'five_year_rr' => '5YRR', 'seven_year_rr' => '7YRR', 'ten_year_rr' => '10YRR', 'fifteen_year_rr' => '15YRR', 'category_id' => 'category type (ID)', 'amc_id' => 'amc (ID)'],
            dtReplaceColumns : $replaceColumns,
            dtNotificationTextFromColumn :'name'
        );

        $this->view->pick('schemes/list');
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            if (!isset($data['day_trajectory'])) {
                $data['day_trajectory'] = '-';
            }

            foreach (['day_cagr',
            'year_cagr',
            'two_year_cagr',
            'three_year_cagr',
            'five_year_cagr',
            'seven_year_cagr',
            'ten_year_cagr',
            'fifteen_year_cagr',
            'year_rr',
            'two_year_rr',
            'three_year_rr',
            'five_year_rr',
            'seven_year_rr',
            'ten_year_rr',
            'fifteen_year_rr'] as $number) {
                if (!isset($data[$number])) {
                    $data[$number] = '-';
                }

                $textColor = 'danger';
                if ($data[$number] > 0) {
                    if ($data[$number] < 8) {
                        $textColor = 'secondary';
                    } else if ($data[$number] >= 8 && $data[$number] < 15) {
                        $textColor = 'info';
                    } else if ($data[$number] >= 15 && $data[$number] < 20) {
                        $textColor = 'success';
                    } else if ($data[$number] >= 20 && $data[$number] < 25) {
                        $textColor = 'warning';
                    } else if ($data[$number] >= 20) {
                        $textColor = 'fuchsia';
                    }
                }

                $data[$number] = '<span class="text-' . $textColor . '">' . $data[$number] . '</span>';
            }

            $data = $this->formatCategory($dataKey, $data);
            $data = $this->formatAmc($dataKey, $data);
            // $data = $this->formatNavInfo($dataKey, $data);
            // $data = $this->formatNavDiff($dataKey, $data);
        }

        return $dataArr;
    }

    protected function formatCategory($rowId, $data)
    {
        if (!isset($this->categories[$data['category_id']])) {
            $category = $this->categoriesPackage->getById((int) $data['category_id']);

            if ($category) {
                $this->categories[$data['category_id']] = $category['name'] . ' (' . $data['category_id'] . ')';
            } else {
                $this->categories[$data['category_id']] = $data['category_id'];
            }
        }

        $data['category_id'] = $this->categories[$data['category_id']];

        return $data;
    }

    protected function formatAmc($rowId, $data)
    {
        if (!isset($this->amcs[$data['amc_id']])) {
            $amc = $this->amcsPackage->getById((int) $data['amc_id']);

            if ($amc) {
                $this->amcs[$data['amc_id']] = $amc['name'] . ' (' . $data['amc_id'] . ')';
            } else {
                $this->amcs[$data['amc_id']] = $data['amc_id'];
            }
        }

        $data['amc_id'] = $this->amcs[$data['amc_id']];

        return $data;
    }

    public function getProcessedSchemeDataAction()
    {
        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['scheme_id'], false);

        if (!$scheme) {
            return $this->throwIdNotFound();
        }

        $responseData = [];

        $this->getProcessedSchemeNavChunksAction($scheme);

        if (isset($this->postData()['navs_chunks']) && $this->postData()['navs_chunks'] == 'true') {
            $responseData['navs_chunks'] = $scheme['navs_chunks']['navs_chunks'];
            unset($scheme['navs_chunks']);
        }

        if (isset($this->postData()['trend']) && $this->postData()['trend'] == 'true') {
            $responseData['trend'] = $scheme['trend'];
            unset($scheme['trend']);
        }

        if (isset($this->postData()['rolling_returns']) && $this->postData()['rolling_returns'] == 'true') {
            $responseData['rolling_returns'] = $scheme['rolling_returns'];
            unset($scheme['rolling_returns']);

            $rollingPeriods = $this->getProcessedSchemeRollingReturnsAction($scheme);

            $responseData['rolling_periods'] = $rollingPeriods;
        }

        $responseData['scheme'] = $scheme;

        $this->addResponse('Ok', 0, $responseData);
    }

    public function getProcessedSchemeNavChunksAction(&$scheme, $customChunks = false)
    {
        $chunks = &$scheme['navs_chunks']['navs_chunks'];

        if ($customChunks) {
            $chunks = &$scheme['custom_chunks']['navs_chunks'];
        }

        foreach (['week', 'month', 'threeMonth', 'sixMonth', 'year', 'threeYear', 'fiveYear', 'tenYear', 'fifteenYear', 'twentyYear', 'twentyFiveYear', 'thirtyYear', 'all'] as $time) {
            if ($time === 'week') {
                if (!isset($chunks[$time])) {
                    $chunks[$time] = false;
                    // if (!$customChunks) {
                        $scheme['trend'][$time] = false;
                    // }
                } else {
                    $weekData = $this->schemesPackage->getSchemeNavChunks(['scheme_id' => $scheme['id'], 'chunk_size' => 'week'], $customChunks);
                    // trace([$weekData]);
                    // if (!$customChunks) {
                        $scheme['trend'][$time] = $weekData['trend'];
                    // }
                }
            } else if ($time !== 'week' && $time !== 'all') {
                if (isset($chunks[$time]) &&
                    count($chunks[$time]) > 0
                ) {
                    $chunks[$time] = true;
                    // if (!$customChunks) {
                        $scheme['trend'][$time] = true;
                    // }
                } else {
                    $chunks[$time] = false;
                    // if (!$customChunks) {
                        $scheme['trend'][$time] = false;
                    // }
                }
            } else if ($time === 'all') {
                if (count($chunks[$time]) > 365) {
                    $chunks[$time] = true;
                    // if (!$customChunks) {
                        $scheme['trend'][$time] = true;
                    // }
                }
            }
        }
    }

    public function getProcessedSchemeRollingReturnsAction(&$scheme, $customRollingReturns = false)
    {
        $rr = &$scheme['rolling_returns'];

        if ($customRollingReturns) {
            $rr = &$scheme['custom_rolling_returns'];
        }

        $rollingPeriods = [];
        foreach (['year', 'two_year', 'three_year', 'five_year', 'seven_year', 'ten_year', 'fifteen_year', 'twenty_year', 'twenty_five_year'] as $rrTime) {
            if (isset($rr[$rrTime])) {
                $rr[$rrTime] = true;

                if ($rrTime === 'year') {
                    $name = strtoupper('one year');
                } else {
                    $name = str_replace('_', ' ', strtoupper($rrTime));
                }

                array_push($rollingPeriods,
                    [
                        'id'    => $rrTime,
                        'name'  => $name
                    ]
                );
            } else {
                $rr[$rrTime] = false;
            }
        }

        return $rollingPeriods;
    }
    // protected function formatNavInfo($rowId, $data)
    // {
    //     if (!isset($data['latest_nav']) ||
    //         (isset($data['latest_nav']) && is_null($data['latest_nav']))
    //     ) {
    //         $data['latest_nav'] = '-';
    //     }
    //     if (!isset($data['last_updated']) ||
    //         (isset($data['last_updated']) && is_null($data['last_updated']))
    //     ) {
    //         $data['last_updated'] = '-';
    //     }

    //     return $data;
    // }

    // protected function formatNavDiff($rowId, $data)
    // {
    //     if (!isset($data['diff']) ||
    //         (isset($data['diff']) && is_null($data['diff']))
    //     ) {
    //         $data['diff'] = '-';
    //     } else {
    //         $color = 'primary';
    //         if (isset($data['trajectory'])) {
    //             if ($data['trajectory'] === 'up') {
    //                 $color = 'success';
    //             } else if ($data['trajectory'] === 'down') {
    //                 $color = 'danger';
    //             }
    //         }

    //         $data['diff'] = '<span class="text-' . $color . '">' . $data['diff'] . '</span>';
    //     }

    //     if (!isset($data['diff_percent']) ||
    //         (isset($data['diff_percent']) && is_null($data['diff_percent']))
    //     ) {
    //         $data['diff_percent'] = '-';
    //     } else {
    //         $color = 'primary';
    //         if (isset($data['trajectory'])) {
    //             if ($data['trajectory'] === 'up') {
    //                 $color = 'success';
    //             } else if ($data['trajectory'] === 'down') {
    //                 $color = 'danger';
    //             }
    //         }

    //         $data['diff_percent'] = '<span class="text-' . $color . '">' . $data['diff_percent'] . '</span>';
    //     }

    //     if (!isset($data['trajectory']) ||
    //         (isset($data['trajectory']) && is_null($data['trajectory']))
    //     ) {
    //         $data['trajectory'] = '-';
    //     } else {
    //         $color = 'primary';
    //         if (isset($data['trajectory'])) {
    //             if ($data['trajectory'] === 'up') {
    //                 $color = 'success';
    //             } else if ($data['trajectory'] === 'down') {
    //                 $color = 'danger';
    //             }
    //         }

    //         $data['trajectory'] = '<span class="text-' . $color . '">' . $data['trajectory'] . '</span>';
    //     }

    //     return $data;
    // }

    // /**
    //  * @acl(name=add)
    //  */
    // public function addAction()
    // {
    //     $this->requestIsPost();

    //     //$this->package->add{?}($this->postData());

    //     $this->addResponse(
    //         $this->package->packagesData->responseMessage,
    //         $this->package->packagesData->responseCode
    //     );
    // }

    // /**
    //  * @acl(name=update)
    //  */
    // public function updateAction()
    // {
    //     $this->requestIsPost();

    //     //$this->package->update{?}($this->postData());

    //     $this->addResponse(
    //         $this->package->packagesData->responseMessage,
    //         $this->package->packagesData->responseCode
    //     );
    // }

    // /**
    //  * @acl(name=remove)
    //  */
    // public function removeAction()
    // {
    //     $this->requestIsPost();

    //     //$this->package->remove{?}($this->postData());

    //     $this->addResponse(
    //         $this->package->packagesData->responseMessage,
    //         $this->package->packagesData->responseCode
    //     );
    // }

    public function importschemeAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->getSchemeInfo($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function getSchemeNavChunksAction()
    {
        $this->requestIsPost();

        $customChunks = false;
        if (isset($this->postData()['customChunks']) && $this->postData()['customChunks'] == 'true') {
            $customChunks = true;
        }

        $this->schemesPackage->getSchemeNavChunks($this->postData(), $customChunks);

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function getSchemeRollingReturnsAction()
    {
        $this->requestIsPost();

        $customRollingReturns = false;
        if (isset($this->postData()['customRollingReturns']) && $this->postData()['customRollingReturns'] == 'true') {
            $customRollingReturns = true;
        }

        $this->schemesPackage->getSchemeRollingReturns($this->postData(), $customRollingReturns);

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function searchSchemesForAMCAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->searchSchemesForAMC($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function searchSchemesForCategoryAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->searchSchemesForCategory($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function searchAllSchemesAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->searchAllSchemes($this->postData()['search']);

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function getSchemeNavByDateAction()
    {
        $this->requestIsPost();

        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['scheme_id'], true, false, false);

        $this->schemesPackage->getSchemeNavByDate($scheme, $this->postData()['date'], false, $this->postData()['latest']);

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function getSchemeDetailsAction()
    {
        $this->requestIsPost();

        if (!isset($this->postData()['id'])) {
            $this->addResponse('Scheme ID not set', 1);

            return;
        }

        $navs = false;

        if (isset($this->postData()['navs'])) {
            $navs = true;
        }

        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['id'], $navs, false, false);

        if ($scheme) {
            $this->addResponse('Ok', 0, ['scheme' => $scheme]);

            return;
        }

        $this->addResponse('Error: We do not have NAV data for scheme : ' . $scheme['name'] . '. Try importing NAVs!', 1, ['scheme' => $scheme]);
    }

    public function generateCustomNavAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->generateCustomNav($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData?? []
        );
    }

    public function updateSchemeCustomNavsAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->updateSchemeCustomNavs($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData?? []
        );
    }
}