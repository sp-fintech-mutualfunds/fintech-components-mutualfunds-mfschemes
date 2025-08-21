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
        $this->view->today = $this->view->timeline = (\Carbon\Carbon::now())->toDateString();
        $postUrl = 'mf/schemes/view';

        if (isset($this->getData()['timeline'])) {
            try {
                $this->view->timeline = (\Carbon\Carbon::parse($this->getData()['timeline']))->toDateString();

                if ($this->view->timeline !== $this->view->today) {
                    $postUrl = 'mf/schemes/view/q/timeline/' . $this->view->timeline;
                } else {
                    $this->view->timeline = false;
                }
            } catch (\throwable $e) {
                // Do nothing
            }
        } else {
            $this->view->timeline = false;
        }

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

                if (isset($this->getData()['timeline']) && $this->view->timeline) {
                    $scheme = $this->schemesPackage->getSchemeSnapshotById((int) $this->getData()['id'], $this->getData()['timeline'], true, true);
                } else {
                    $scheme = $this->schemesPackage->getSchemeById((int) $this->getData()['id'], false, true, true);
                }

                if (!$scheme) {
                    return $this->throwIdNotFound();
                }

                $this->view->compare = false;

                $timeline = false;
                if ($this->view->timeline) {
                    $timeline = true;
                }

                $this->getProcessedSchemeNavChunksAction($scheme, false, $timeline);

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

        $replaceColumns =
            function ($dataArr) {
                if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                    return $this->replaceColumns($dataArr);
                }

                return $dataArr;
            };

        $conditions = null;

        $viewUrl = 'mf/schemes/q/';
        $urls = ['view' => $viewUrl];
        $urls['customNavs'] =
            [
                'title'             => 'Custom Navs',
                'icon'              => 'money-bill-trend-up',
                'additionalClass'   => 'custom contentAjaxLink',
                'link'              => 'mf/schemes/q/custom/true/'
            ];

        if ($this->request->isPost()) {
            if (count($this->dispatcher->getParams()) > 0 &&
                $this->dispatcher->getParams()[0] === 'timeline' &&
                $this->dispatcher->getParams()[1] !== $this->view->today
            ) {
                $conditions = ['conditions' => '-|start_date|lessthanequals|' . $this->dispatcher->getParams()[1] .  '&'];

                $urls['view'] = 'mf/schemes/q/timeline/' . $this->dispatcher->getParams()[1] . '/';
                unset($urls['customNavs']);
            }
        }

        $controlActions =
            [
                'includeQ'              => true,
                'actionsToEnable'       => $urls
            ];

        $this->generateDTContent(
            package : $this->schemesPackage,
            postUrl : $postUrl,
            postUrlParams: $conditions,
            columnsForTable : ['name', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            columnsForFilter : ['name', 'year_rr', 'two_year_rr', 'three_year_rr', 'five_year_rr', 'seven_year_rr', 'ten_year_rr', 'fifteen_year_rr', 'category_id', 'day_cagr', 'day_trajectory', 'amc_id', 'start_date', 'navs_last_updated'],
            controlActions : $controlActions,
            dtReplaceColumnsTitle : ['day_cagr' => '1DTR', 'day_trajectory' => '1D Trend', 'year_rr' => '1YRR', 'two_year_rr' => '2YRR', 'three_year_rr' => '3YRR', 'five_year_rr' => '5YRR', 'seven_year_rr' => '7YRR', 'ten_year_rr' => '10YRR', 'fifteen_year_rr' => '15YRR', 'category_id' => 'category type (ID)', 'amc_id' => 'amc (ID)'],
            dtReplaceColumns : $replaceColumns,
            dtNotificationTextFromColumn :'name'
        );

        $this->view->pick('schemes/list');
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            if (count($this->dispatcher->getParams()) > 0 &&
                $this->dispatcher->getParams()[0] === 'timeline'
            ) {
                $snapshot = $this->schemesPackage->getSchemeSnapshotById($data['id'], $this->dispatcher->getParams()[1]);

                if ($snapshot) {
                    $data = array_replace($data, $snapshot);
                } else {
                    $data['name'] = '^^ ' . $data['name'];
                }
            }

            if (!isset($data['day_trajectory'])) {
                $data['day_trajectory'] = '-';
            }

            foreach (['year_rr',
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
        $timeline = false;
        if (isset($this->postData()['timeline'])) {
            $timeline = $this->postData()['timeline'];

            $scheme = $this->schemesPackage->getSchemeSnapshotById((int) $this->postData()['scheme_id'], $this->postData()['timeline'], true, true);
        } else {
            $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['scheme_id'], false);
        }

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
            $rollingPeriods = $this->getProcessedSchemeRollingReturnsAction($scheme);

            $responseData['rolling_periods'] = $rollingPeriods;

            $responseData['rolling_returns'] = $scheme['rolling_returns'];

            unset($scheme['rolling_returns']);

        }

        $responseData['scheme'] = $scheme;

        $this->addResponse('Ok', 0, $responseData);
    }

    public function getProcessedSchemeNavChunksAction(&$scheme, $customChunks = false, $timeline = false)
    {
        $chunks = &$scheme['navs_chunks']['navs_chunks'];

        if ($customChunks) {
            $chunks = &$scheme['custom_chunks']['navs_chunks'];
        }

        foreach (['week', 'month', 'threeMonth', 'sixMonth', 'year', 'threeYear', 'fiveYear', 'tenYear', 'fifteenYear', 'twentyYear', 'twentyFiveYear', 'thirtyYear', 'all'] as $time) {
            if ($time === 'week') {
                if (!isset($chunks[$time])) {
                    $chunks[$time] = false;
                    $scheme['trend'][$time] = false;
                } else {
                    $weekData = $this->schemesPackage->getSchemeNavChunks(['scheme_id' => $scheme['id'], 'chunk_size' => 'week'], $customChunks, $timeline);
                    $scheme['trend'][$time] = $weekData['trend'];
                }
            } else if ($time !== 'week' && $time !== 'all') {
                if (isset($chunks[$time]) &&
                    count($chunks[$time]) > 0
                ) {
                    $chunks[$time] = true;
                    $scheme['trend'][$time] = true;
                } else {
                    $chunks[$time] = false;
                    $scheme['trend'][$time] = false;
                }
            } else if ($time === 'all') {
                if (count($chunks[$time]) > 365) {
                    $chunks[$time] = true;
                    $scheme['trend'][$time] = true;
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
        foreach (['year', 'two_year', 'three_year', 'five_year', 'seven_year', 'ten_year', 'fifteen_year', 'twenty_year', 'twenty_five_year', 'thirty_year'] as $rrTime) {
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

        $timeline = false;
        if (isset($this->postData()['timeline'])) {
            $timeline = $this->postData()['timeline'];
        }

        $this->schemesPackage->getSchemeNavChunks($this->postData(), $customChunks, $timeline);

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

        $timeline = false;
        if (isset($this->postData()['timeline'])) {
            $timeline = $this->postData()['timeline'];
        }

        $this->schemesPackage->getSchemeRollingReturns($this->postData(), $customRollingReturns, $timeline);

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