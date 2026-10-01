<?php

/*
 * Company Plugin

  NOTICE OF LICENSE

  This source file is subject to the Open Software License (OSL 3.0)

  DISCLAIMER

  Do not edit or add to this file if you wish to upgrade to newer
  versions in the future. If you wish to customize this extension for your
  needs please refer to http://www.company.com for more information.

  @author      Raman Basu <support@company.com>
  @package     Company/Plugin
  @copyright   Copyright (C) 2016 Company <support@company.com>
  @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Company\Plugin\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;

class OptionList extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $scopeConfig;
    protected $pageFactory;
    protected $request;
    protected $helper;
    private $verbose = 0;
    private $encoder;

    private $storeManager = null;

    /**
     * Customer Group
     *
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    protected $customerGroupCollection;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroupCollection
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->rawResultFactory = $context->getResultFactory();
        
        $this->customerGroupCollection = $customerGroupCollection;

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();
        
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $this->helper->httpHeader($resultResponse);

        /** Authentication phase starts **/
        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }
        /** Authentication phase ends **/

        $this->storeManager = $this->helper->getStoreManager();

        if (isset($qryStrArray["verbose"]) && $qryStrArray["verbose"] == 1):
            $this->verbose = 1;
        endif;

        $option = '';
        if (isset($qryStrArray['option']) && $qryStrArray['option'] != "") {
            $option = (string) $qryStrArray["option"];
        }

        $output = "";
        $output .= '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<list type="' . $option . '">';
        $output .= $this->helper->printModuleVersion();

        $output .= '<default>';
        $output .= '<group_id>' . $this->helper->getDefaultCustomerGroupsId() . '</group_id>';
        //$output .= '<website_id>' . $this->helper->getDefaultWebsiteId() . '</website_id>';
        $output .= '<website_id>' . $this->helper->getWebsiteForPriceScope() . '</website_id>';
        $output .= '<default_store_id>' . $this->helper->getDefaultStoreId() . '</default_store_id>';
        $output .= '</default>';
        
        switch ($option):
            case "backorders":
                $output .= $this->printBackOrderData();
                break;

            case "order_status":
                $output .= $this->printAllOrderStatuses();
                break;

            case "customer_groups":
                $output .= $this->printAllCustomerGroups();
                break;

            case "stores":
                $output .= $this->printAllStoreIds();
                break;

            case "msi_sources":
                $output .= $this->printAllMsiSources();
                break;
        endswitch;

        $output .= '</list>';
        
        $resultResponse->setContents($output);
        return $resultResponse;
    }

    private function printBackOrderData()
    {
        $output = "";
        try {
            $arrayData = $this->helper->getBackOrderDataArray();
            //var_dump($arrayData);

            foreach ($arrayData as $data):
                $output .= '<option>';
                $output .= '<value>' . $data["value"] . '</value>';
                $output .= '<label>' . $data["label"] . '</label>';
                $output .= '</option>';
            endforeach;
        } catch (Exception $ex) {
            $output .= $ex->getMessage();
        }

        return $output;
    }
    
    private function printAllOrderStatuses()
    {
        $output = "";
        $arrayData = $this->helper->getAllOrderStatusArray(); //$source->create()->toOptionArray();
        
        foreach ($arrayData as $data):
            $output .= '<option>';
            $output .= '<value>' . $data["value"] . '</value>';
            $output .= '<label>' . $data["label"] . '</label>';
            $output .= '</option>';
        endforeach;

        return $output;
    }
    
    private function printAllCustomerGroups()
    {
        $output = "";
        
        /**$customerGroupsCollection = $this->_objectManager
            ->get('\Magento\Customer\Model\ResourceModel\Group\Collection');
        $customerGroups = $customerGroupsCollection->toOptionArray();
        **/

        $customerGroups = $this->customerGroupCollection->toOptionArray();
        /** array_unshift($customerGroups, array('value'=>'', 'label'=>'Any')); **/
        
        $output .= '<option>';
        $output .= '<value>' . \Magento\Customer\Model\Group::CUST_GROUP_ALL . '</value>';
        $output .= '<label>ALL GROUPS></label>';
        $output .= '</option>';

        foreach ($customerGroups as $data):
            $output .= '<option>';
            $output .= '<value>' . $data["value"] . '</value>';
            $output .= '<label><![CDATA[' . $data["label"] . ']]></label>';
            $output .= '</option>';
        endforeach;

        return $output;
    }

    private function printAllStoreIds()
    {
        $output = "";
        $storesArray = $this->helper->getAllStores();

        // When setting to admin mode, it returns "0"
        $cont = false;
        if ($cont==false):
            $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
            $this->storeManager->setCurrentStore($store->getCode());
            $storeId = $this->storeManager->getStore()->getId();
            
            //$websiteId = $this->storeManager->getWebsite()->getId();
            //var_dump($storeId);
            //var_dump($$websiteId);
        endif;

        foreach ($storesArray as $data):
            $output .= '<option>';
            $output .= '<value>' . $data["id"] . '</value>';
            $output .= '<label><![CDATA[' . $data["name"] . ']]></label>';
            $output .= '<website_id><![CDATA[' . $data["website_id"] . ']]></website_id>';
            $output .= '</option>';
        endforeach;

        return $output;
    }

    private function printAllMsiSources()
    {
        $output = "";
        $sourceList = $this->helper->getSourceList();

        if ($sourceList):
            foreach ($sourceList as $data):
                $output .= '<option>';
                $output .= '<source_code>' . $data["source_code"] . '</source_code>';
                $output .= '<name><![CDATA[' . $data["name"] . ']]></name>';
                $output .= '<enabled><![CDATA[' . $data["enabled"] . ']]></enabled>';

                $output .= '<country_id><![CDATA[' . $data["country_id"] . ']]></country_id>';
                $output .= '<region_id><![CDATA[' . $data["region_id"] . ']]></region_id>';
                $output .= '<region><![CDATA[' . $data["region"] . ']]></region>';
                $output .= '<city><![CDATA[' . $data["city"] . ']]></city>';
                $output .= '<street><![CDATA[' . $data["street"] . ']]></street>';
                $output .= '<postcode><![CDATA[' . $data["postcode"] . ']]></postcode>';

                $output .= '</option>';
            endforeach;
        endif;

        return $output;
    }
}
