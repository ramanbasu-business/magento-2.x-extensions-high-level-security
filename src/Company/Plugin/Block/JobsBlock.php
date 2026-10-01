<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Block;

use Magento\Framework\View\Element\Template;

class JobsBlock extends Template
{
    protected $jobFactory;
    protected $collection;
    protected $coreRegistry;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Company\Plugin\Model\JobFactory $jobFactory,
        array $data = []
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->jobFactory = $jobFactory;

        parent::__construct($context, $data);
    }

    public function getCollection($id, $module, $submittedFrom, $submittedTo)
    {
        $this->collection = $this->jobFactory->create()->getCollection();

        //echo "here"; return null;
        if ($id != ""):
            $this->collection->addFieldToFilter('id', ['eq' => [$id]]);
        endif;

        if ($module != "ALL"):
            if ($module == ""):
                //$this->_collection->addFieldToFilter('module', array('eq' => array($module)));
                $this->collection->addFieldToFilter(
                    'module',
                    [['eq' => ''], ['null' => true]]
                );
            else:
                $this->collection->addFieldToFilter('module', ['eq' => [$module]]);
            endif;
        endif;

        if ($submittedFrom != "" && $submittedTo != ""):
            $this->collection->addFieldToFilter(
                'submittedon',
                ['from' => $submittedFrom, 'to' => $submittedTo, 'datetime' => true]
            );
        endif;

        $this->collection
            ->setOrder('id', 'DESC')
            ->setPageSize(100)->setCurPage(0);

        return $this->collection;
    }

    public function getFormAction()
    {
        return '/Plugin/jobs/index';
    }

    public function isAuthenticated()
    {
        return $this->coreRegistry->registry('isAuthenticated');
    }

    public function getQueryString($endPoint, $jobId, $postedData = [])
    {
        return $this->getBaseUrl()
            . 'plugin/import/' . $endPoint
            . '?id=' . (int) $jobId;
    }
}
