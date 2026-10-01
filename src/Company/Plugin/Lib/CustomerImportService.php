<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Lib;

/**
 * Description of CustomerImportService
 *
 * @author Company Plugin
 */
class CustomerImportService extends \Company\Plugin\Lib\CommonService
{
    protected $helper;
    protected $storeManager;
    protected $customerRepository;
    protected $dateTime;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
        //\Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateTime
    ) {
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;

        //$this->dateTime = $dateTime;

        parent::__construct($objectManager, $xmlLog, $database);
    }

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
    public function setParameters($jobId, $verbose = 0)
    {
        parent::setParameters($jobId, $verbose);
    }

    public function __destruct()
    {
        $this->setDebugMessage('CustomerImport Service action is complete.');
        /** parent::__destruct(); **/
    }

    public function importCustomers($feed_xml)
    {
        $genderNotSpecified = 3; // Male: 1, Female: 2

        $store = $this->storeManager->getStore(\Magento\Store\Model\Store::ADMIN_CODE);
        $this->storeManager->setCurrentStore($store->getCode());

        $this->setDebugMessage('Import customers...');

        foreach ($feed_xml->customer as $customerXml):
            $mode = "M";

            $entity_id = isset($customerXml->entity_id) ? (int)$customerXml->entity_id : null;
            $website_id = isset($customerXml->website_id) ? (int)$customerXml->website_id : null;
            $email = isset($customerXml->email) ? (string)$customerXml->email : null;

            $group_id = isset($customerXml->group_id) ? (int)$customerXml->group_id : null;
            $store_id = isset($customerXml->store_id) ? (int)$customerXml->store_id : null;
            $is_active = isset($customerXml->is_active) ? (int)$customerXml->is_active : 1;
            
            $prefix = isset($customerXml->prefix) ? (string)$customerXml->prefix : null;
            $firstname = isset($customerXml->firstname) ? (string)$customerXml->firstname : null;
            $middlename = isset($customerXml->middlename) ? (string)$customerXml->middlename : null;
            $lastname = isset($customerXml->lastname) ? (string)$customerXml->lastname : null;
            $suffix = isset($customerXml->suffix) ? (string)$customerXml->suffix : null;
            $dob = isset($customerXml->dob) ? (string)$customerXml->dob : null;
            $gender = isset($customerXml->gender) ? (string)$customerXml->gender : $genderNotSpecified;

            if ($store_id == null):
                $storeId = $this->storeManager->getStore()->getId();
            endif;

            if ($website_id == null):
                $defaultWebsiteId = $this->helper->getWebsiteForPriceScope();
                $website_id = $defaultWebsiteId;
            endif;

            if ($group_id == null):
                $allGroupsId = $this->helper->getAllCustomerGroupsId();
                $group_id = $allGroupsId;
            endif;

            // Not sure if multiple website ids can be passed
            if (isset($customerXml->websiteids)
                && (string)$customerXml->websiteids != ""
                && (string)$customerXml->websiteids != "0"):
                $this->setDebugMessage('Setting websiteIDs: ' . (string) $customerXml->websiteids . '');
                $arrWebIds = explode(",", (string) $customerXml->websiteids);
                
                $validWebIdsArray = $this->getValidWebsiteIdsArray($sku, $arrWebIds);
                
                /** $product->setWebsiteIds($validWebIdsArray); **/
            endif;

            try {
                $customer = $this->customerRepository->getById($entity_id);

                if ($customer->getId()):
                    // Customer exists
                     $this->setDebugMessage($email . ': exists ... updating customer data');
                     $mode = "M";
                else:
                     $this->setDebugMessage($email . ': does not exist... creating new customer');
                     $customer = $this->customerRepository->create();
                     $mode = "A";
                endif;

                $customer->setWebsiteId($website_id);
                $customer->setEmail($email);
                $customer->setGroupId($group_id);
                $customer->setStoreId($store_id);
                /** $customer->setIsActive($is_active); **/
                
                $customer->setPrefix($prefix);
                $customer->setFirstName($firstname);
                $customer->setMiddleName($middlename);
                $customer->setLastName($lastname);
                $customer->setSuffix($suffix);
                $customer->setDob($dob);
                $customer->setGender($gender);

                $this->customerRepository->save($customer);

                if ($mode == "A"):
                    $this->setDebugMessage($email . ': added ');
                else:
                    $this->setDebugMessage($email . ': updated ');
                endif;

                // customer create or update is successful.
                $this->setDebugMessage($email . ': imported ');
                $this->xmlLog->log($email, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($email, "error", $ex->getMessage());
            }
        endforeach;

        $this->setDebugMessage('Customer import is complete');
    }
}
