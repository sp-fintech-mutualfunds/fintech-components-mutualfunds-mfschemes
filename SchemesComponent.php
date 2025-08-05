<?php

namespace Apps\Fintech\Components\Mf\Schemes;

use Apps\Fintech\Packages\Adminltetags\Traits\DynamicTable;
use Apps\Fintech\Packages\Mf\Amcs\MfAmcs;
use Apps\Fintech\Packages\Mf\Categories\MfCategories;
use Apps\Fintech\Packages\Mf\Schemes\MfSchemes;
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
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        if (isset($this->getData()['id'])) {
            if ($this->getData()['id'] != 0) {
                $scheme = $this->schemesPackage->getSchemeById((int) $this->getData()['id'], false);

                if (!$scheme) {
                    return $this->throwIdNotFound();
                }

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

            return true;
        }

        $controlActions =
            [
                // 'disableActionsForIds'  => [1],
                'actionsToEnable'       =>
                [
                    'view'      => 'mf/schemes'
                ]
            ];

        $replaceColumns =
            function ($dataArr) {
                if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                    return $this->replaceColumns($dataArr);
                }

                return $dataArr;
            };

        if (count($this->postData()) === 0) {
            // $packagesData = [];
        }

        $this->generateDTContent(
            package : $this->schemesPackage,
            postUrl : 'mf/schemes/view',
            postUrlParams: null,
            columnsForTable : ['name', 'year_cagr', 'two_year_cagr', 'three_year_cagr', 'five_year_cagr', 'seven_year_cagr', 'ten_year_cagr', 'fifteen_year_cagr', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            columnsForFilter : ['name', 'year_cagr', 'two_year_cagr', 'three_year_cagr', 'five_year_cagr', 'seven_year_cagr', 'ten_year_cagr', 'fifteen_year_cagr', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            controlActions : $controlActions,
            dtReplaceColumnsTitle : ['day_cagr' => '1DR', 'day_trajectory' => '1D Trend', 'year_cagr' => '1YR', 'two_year_cagr' => '2YR', 'three_year_cagr' => '3YR', 'five_year_cagr' => '5YR', 'seven_year_cagr' => '7YR', 'ten_year_cagr' => '10YR', 'fifteen_year_cagr' => '15YR', 'year_rr' => '1YRR', 'two_year_rr' => '2YRR', 'three_year_rr' => '3YRR', 'five_year_rr' => '5YRR', 'seven_year_rr' => '7YRR', 'ten_year_rr' => '10YRR', 'fifteen_year_rr' => '15YRR', 'category_id' => 'category type (ID)', 'amc_id' => 'amc (ID)'],
            dtReplaceColumns : $replaceColumns,
            dtNotificationTextFromColumn :'name'
        );

        $this->view->pick('schemes/list');
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            if (!isset($data['day_cagr'])) {
                $data['day_cagr'] = '-';
            }
            if (!isset($data['day_trajectory'])) {
                $data['day_trajectory'] = '-';
            }
            if (!isset($data['year_cagr'])) {
                $data['year_cagr'] = '-';
            }
            if (!isset($data['two_year_cagr'])) {
                $data['two_year_cagr'] = '-';
            }
            if (!isset($data['three_year_cagr'])) {
                $data['three_year_cagr'] = '-';
            }
            if (!isset($data['five_year_cagr'])) {
                $data['five_year_cagr'] = '-';
            }
            if (!isset($data['seven_year_cagr'])) {
                $data['seven_year_cagr'] = '-';
            }
            if (!isset($data['ten_year_cagr'])) {
                $data['ten_year_cagr'] = '-';
            }
            if (!isset($data['fifteen_year_cagr'])) {
                $data['fifteen_year_cagr'] = '-';
            }

            if (!isset($data['year_rr'])) {
                $data['year_rr'] = '-';
            }
            if (!isset($data['two_year_rr'])) {
                $data['two_year_rr'] = '-';
            }
            if (!isset($data['three_year_rr'])) {
                $data['three_year_rr'] = '-';
            }
            if (!isset($data['five_year_rr'])) {
                $data['five_year_rr'] = '-';
            }
            if (!isset($data['seven_year_rr'])) {
                $data['seven_year_rr'] = '-';
            }
            if (!isset($data['ten_year_rr'])) {
                $data['ten_year_rr'] = '-';
            }
            if (!isset($data['fifteen_year_rr'])) {
                $data['fifteen_year_rr'] = '-';
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
    {//used for compare
        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['scheme_id'], false);

        if (!$scheme) {
            return $this->throwIdNotFound();
        }

        $this->getProcessedSchemeNavChunksAction($scheme);

        $rollingPeriods = $this->getProcessedSchemeRollingReturnsAction($scheme);

        $this->addResponse('Ok', 0,
            [
                'navs_chunks'       => $scheme['navs_chunks']['navs_chunks'],
                'trend'             => $scheme['trend'],
                'rolling_returns'   => $scheme['rolling_returns'],
                'rolling_periods'   => $rollingPeriods
            ]
        );
    }

    public function getProcessedSchemeNavChunksAction(&$scheme)
    {
        foreach (['week', 'month', 'threeMonth', 'sixMonth', 'year', 'threeYear', 'fiveYear', 'tenYear', 'all'] as $time) {
            if ($time === 'week') {
                if (!isset($scheme['navs_chunks']['navs_chunks'][$time])) {
                    $scheme['navs_chunks']['navs_chunks'][$time] = false;
                    $scheme['trend'][$time] = false;
                } else {
                    $weekData = $this->schemesPackage->getSchemeNavChunks(['scheme_id' => $scheme['id'], 'chunk_size' => 'week']);
                    $scheme['trend'][$time] = $weekData['trend'];
                }
            } else if ($time !== 'week' && $time !== 'all') {
                if (isset($scheme['navs_chunks']['navs_chunks'][$time]) &&
                    count($scheme['navs_chunks']['navs_chunks'][$time]) > 0
                ) {
                    $scheme['navs_chunks']['navs_chunks'][$time] = true;
                    $scheme['trend'][$time] = true;
                } else {
                    $scheme['navs_chunks']['navs_chunks'][$time] = false;
                    $scheme['trend'][$time] = false;
                }
            } else if ($time === 'all') {
                if (count($scheme['navs_chunks']['navs_chunks'][$time]) > 365) {
                    $scheme['navs_chunks']['navs_chunks'][$time] = true;
                    $scheme['trend'][$time] = true;
                }
            }
        }
    }

    public function getProcessedSchemeRollingReturnsAction(&$scheme)
    {
        $rollingPeriods = [];
        foreach (['year', 'two_year', 'three_year', 'five_year', 'seven_year', 'ten_year', 'fifteen_year'] as $rrTime) {
            if (isset($scheme['rolling_returns'][$rrTime])) {
                $scheme['rolling_returns'][$rrTime] = true;

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
                $scheme['rolling_returns'][$rrTime] = false;
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

        $this->schemesPackage->getSchemeNavChunks($this->postData());

        $this->addResponse(
            $this->schemesPackage->packagesData->responseMessage,
            $this->schemesPackage->packagesData->responseCode,
            $this->schemesPackage->packagesData->responseData ?? []
        );
    }

    public function getSchemeRollingReturnsAction()
    {
        $this->requestIsPost();

        $this->schemesPackage->getSchemeRollingReturns($this->postData());

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

        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['scheme_id'], false, false, false);

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
}