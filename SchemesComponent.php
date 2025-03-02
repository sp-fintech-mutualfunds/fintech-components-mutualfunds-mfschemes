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
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        if (isset($this->getData()['id'])) {
            if ($this->getData()['id'] != 0) {
                $scheme = $this->schemesPackage->getById((int) $this->getData()['id']);

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
            ['isin', 'name', 'category_id', 'scheme_type', 'plan_type', 'expense_ratio_type', 'management_type', 'amc_id', 'amfi_code'],
            true,
            ['isin', 'name', 'category_id', 'scheme_type', 'plan_type', 'expense_ratio_type', 'management_type', 'amc_id', 'amfi_code'],
            $controlActions,
            ['category_id' => 'category type (ID)', 'amc_id' => 'amc (ID)'],
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
}