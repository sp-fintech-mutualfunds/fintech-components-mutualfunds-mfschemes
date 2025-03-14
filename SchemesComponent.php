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
        $this->view->apis = $this->schemesPackage->getAvailableApis(true, false);

        if (isset($this->getData()['id'])) {
            if ($this->getData()['id'] != 0) {
                $scheme = $this->schemesPackage->getSchemeById((int) $this->getData()['id']);

                if (!$scheme) {
                    return $this->throwIdNotFound();
                }

                $this->view->scheme = $scheme;
            }

            $this->view->pick('schemes/view');

            return;
        }

        $controlActions =
            [
                // 'disableActionsForIds'  => [1],
                'actionsToEnable'       =>
                [
                    'view'      => 'mf/schemes',
                    'edit'      => 'mf/schemes',
                    'remove'    => 'mf/schemes/remove'
                ]
            ];

        $replaceColumns =
            function ($dataArr) {
                if ($dataArr && is_array($dataArr) && count($dataArr) > 0) {
                    return $this->replaceColumns($dataArr);
                }

                return $dataArr;
            };

        $this->generateDTContent(
            $this->schemesPackage,
            'mf/schemes/view',
            null,
            ['isin', 'name', 'last_updated', 'latest_nav', 'diff', 'diff_percent', 'trajectory', 'category_id', 'scheme_type', 'plan_type', 'expense_ratio_type', 'management_type', 'amc_id', 'amfi_code'],
            true,
            ['isin', 'name', 'last_updated', 'latest_nav', 'diff', 'diff_percent', 'trajectory', 'category_id', 'scheme_type', 'plan_type', 'expense_ratio_type', 'management_type', 'amc_id', 'amfi_code'],
            $controlActions,
            ['category_id' => 'category type (ID)', 'amc_id' => 'amc (ID)', 'diff' => 'Difference'],
            $replaceColumns,
            'name'
        );

        $this->view->pick('schemes/list');
    }

    protected function replaceColumns($dataArr)
    {
        foreach ($dataArr as $dataKey => &$data) {
            $data = $this->formatCategory($dataKey, $data);
            $data = $this->formatAmc($dataKey, $data);
            $data = $this->formatNavInfo($dataKey, $data);
            $data = $this->formatNavDiff($dataKey, $data);
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

    protected function formatNavInfo($rowId, $data)
    {
        if (!isset($data['latest_nav']) ||
            (isset($data['latest_nav']) && is_null($data['latest_nav']))
        ) {
            $data['latest_nav'] = '-';
        }
        if (!isset($data['last_updated']) ||
            (isset($data['last_updated']) && is_null($data['last_updated']))
        ) {
            $data['last_updated'] = '-';
        }

        return $data;
    }

    protected function formatNavDiff($rowId, $data)
    {
        if (!isset($data['diff']) ||
            (isset($data['diff']) && is_null($data['diff']))
        ) {
            $data['diff'] = '-';
        } else {
            $color = 'primary';
            if (isset($data['trajectory'])) {
                if ($data['trajectory'] === 'up') {
                    $color = 'success';
                } else if ($data['trajectory'] === 'down') {
                    $color = 'danger';
                }
            }

            $data['diff'] = '<span class="text-' . $color . '">' . $data['diff'] . '</span>';
        }

        if (!isset($data['diff_percent']) ||
            (isset($data['diff_percent']) && is_null($data['diff_percent']))
        ) {
            $data['diff_percent'] = '-';
        } else {
            $color = 'primary';
            if (isset($data['trajectory'])) {
                if ($data['trajectory'] === 'up') {
                    $color = 'success';
                } else if ($data['trajectory'] === 'down') {
                    $color = 'danger';
                }
            }

            $data['diff_percent'] = '<span class="text-' . $color . '">' . $data['diff_percent'] . '</span>';
        }

        if (!isset($data['trajectory']) ||
            (isset($data['trajectory']) && is_null($data['trajectory']))
        ) {
            $data['trajectory'] = '-';
        } else {
            $color = 'primary';
            if (isset($data['trajectory'])) {
                if ($data['trajectory'] === 'up') {
                    $color = 'success';
                } else if ($data['trajectory'] === 'down') {
                    $color = 'danger';
                }
            }

            $data['trajectory'] = '<span class="text-' . $color . '">' . $data['trajectory'] . '</span>';
        }

        return $data;
    }

    /**
     * @acl(name=add)
     */
    public function addAction()
    {
        $this->requestIsPost();

        //$this->package->add{?}($this->postData());

        $this->addResponse(
            $this->package->packagesData->responseMessage,
            $this->package->packagesData->responseCode
        );
    }

    /**
     * @acl(name=update)
     */
    public function updateAction()
    {
        $this->requestIsPost();

        //$this->package->update{?}($this->postData());

        $this->addResponse(
            $this->package->packagesData->responseMessage,
            $this->package->packagesData->responseCode
        );
    }

    /**
     * @acl(name=remove)
     */
    public function removeAction()
    {
        $this->requestIsPost();

        //$this->package->remove{?}($this->postData());

        $this->addResponse(
            $this->package->packagesData->responseMessage,
            $this->package->packagesData->responseCode
        );
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

    public function getSchemeNavAction()
    {
        $this->requestIsPost();

        if (!isset($this->postData()['id'])) {
            $this->addResponse('Scheme ID not set', 1);

            return;
        }

        $scheme = $this->schemesPackage->getSchemeById((int) $this->postData()['id']);

        $this->addResponse('Ok', 0, ['scheme' => $scheme]);
    }
}