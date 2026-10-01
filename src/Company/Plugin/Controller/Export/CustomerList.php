<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

class CustomerList extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $pageFactory;
    protected $request;
    private $helper;
    private $encoder;
    private $customerFactory;
    private $customersModel;
    protected $addressFactory;

    private $verbose = 0;
    private $rowsPerPage = 10;  // Default 10
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\Customer $customersModel,
        \Magento\Customer\Model\AddressFactory $addressFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->helper = $helper;
        $this->encoder = $encoder;
        
        $this->customerFactory = $customerFactory;
        $this->customersModel = $customersModel;
        $this->addressFactory = $addressFactory;
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();
        
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $this->helper->httpHeader($resultResponse);

        /**
        * -----------------------------------------------
        * Authentication phase starts
        * ===============================================
        **/
        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }
        
        /**
        * -----------------------------------------------
        * Authentication phase ends
        * ===============================================
        **/

        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }
        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }

        return $this->printOrders($qryStrArray, $resultResponse);
    }

    private function printOrders($qryStrArray, $resultResponse)
    {
        $collection = $this->getFilteredCustomerCollection();
        $recordCount = $collection->getSize();
        $totalPages = (int) ($recordCount / $this->rowsPerPage)
                + ((int) ($recordCount % $this->rowsPerPage) > 0 ? 1 : 0);
                
        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<customers>';
        $output .= $this->helper->printModuleVersion();
        $output .= '<recordcount>' . $recordCount . '</recordcount>';
        $output .= '<currentpageindex>' . $this->currentPageIndex . '</currentpageindex>';
        $output .= '<pagesize>' . $this->rowsPerPage . '</pagesize>';
        $output .= '<totalpages>' . $totalPages . '</totalpages>';
        $output .= '<verbose>' . $this->verbose . '</verbose>';

        /** $this->helper->sendToScreen($output); **/

        foreach ($collection as $customer):
            $output .= "<customer>";
            
            $output .= '<entity_id>' . $customer->getEntityId() . '</entity_id>';
            $output .= '<website_id>' . $customer->getWebsiteId() . '</website_id>';
            $output .= '<email>' . $customer->getEmail() . '</email>';
            $output .= '<group_id>' . $customer->getGroupId() . '</group_id>';
            $output .= '<store_id>' . $customer->getStoreId() . '</store_id>';
            $output .= '<created_at>' . $customer->getCreatedAt() . '</created_at>';
            $output .= '<updated_at>' . $customer->getUpdatedAt() . '</updated_at>';
            $output .= '<is_active>' . $customer->getIsActive() . '</is_active>';
            $output .= '<disable_auto_group_change>'
                . $customer->getDisableAutoGroupChange()
                . '</disable_auto_group_change>';
          
            $output .= '<prefix>' . $customer->getPrefix() . '</prefix>';
            $output .= '<firstname>' . $customer->getFirstname() . '</firstname>';
            $output .= '<middlename>' . $customer->getMiddlename() . '</middlename>';
            $output .= '<lastname>' . $customer->getLastname() . '</lastname>';
            $output .= '<suffix>' . $customer->getSuffix() . '</suffix>';
            $output .= '<dob>' . $customer->getDob() . '</dob>';
            $output .= '<gender>' . $customer->getGender() . '</gender>';

            //$billingAddressId = $customer->getDefaultBilling();
            //$addresses = $customer->getAddresses();
            //foreach ($addresses as $address) {
            //     $output .= "<pre>";
            //    print_r($address->getData());
            //    exit;
            //}

            $billingAddressId = $customer->getDefaultBilling();
            
            if ($billingAddressId && $billingAddressId > 0):
                $billingAddress = $this->addressFactory->create()->load($billingAddressId);
                
                //var_dump($billingAddress->getStreet());
                $street = is_array($billingAddress->getStreet()) ? $billingAddress->getStreet()[0] : "";

                $output .= '<billing_address>';

                $output .= '<firstname>'
                    . $this->helper->addWithCData($billingAddress->getFirstname()) . '</firstname>';

                $output .= '<middlename>' . $billingAddress->getMiddlename() . '</middlename>';
                $output .= '<lastname>'
                    . $this->helper->addWithCData($billingAddress->getLastname()) . '</lastname>';

                $output .= '<street>' .  $this->helper->addWithCData($street) . '</street>';

                $output .= '<city>' . $this->helper->addWithCData($billingAddress->getCity()) . '</city>';
                $output .= '<region>' . $billingAddress->getRegion() . '</region>';
                $output .= '<postcode>' . $billingAddress->getPostcode() . '</postcode>';
                $output .= '<country_id>' . $billingAddress->getCountryId() . '</country_id>';

                $output .= '</billing_address>';
            endif;

            $shippingAddressId = $customer->getDefaultShipping();
            
            if ($shippingAddressId && $shippingAddressId > 0):
                $shippingAddress = $this->addressFactory->create()->load($shippingAddressId);
                
                //var_dump($shippingAddress->getStreet());
                $street = is_array($shippingAddress->getStreet()) ? $shippingAddress->getStreet()[0] : "";

                $output .= '<shipping_address>';

                $output .= '<firstname>'
                    . $this->helper->addWithCData($shippingAddress->getFirstname()) . '</firstname>';

                $output .= '<middlename>' . $shippingAddress->getMiddlename() . '</middlename>';
                $output .= '<lastname>'
                    . $this->helper->addWithCData($shippingAddress->getLastname()) . '</lastname>';

                $output .= '<street>' .  $this->helper->addWithCData($street) . '</street>';

                $output .= '<city>' . $this->helper->addWithCData($shippingAddress->getCity()) . '</city>';
                $output .= '<region>' . $shippingAddress->getRegion() . '</region>';
                $output .= '<postcode>' . $shippingAddress->getPostcode() . '</postcode>';
                $output .= '<country_id>' . $shippingAddress->getCountryId() . '</country_id>';

                $output .= '</shipping_address>';
            endif;

            $output .= "</customer>";
        endforeach;

        $output .= '</customers>';

        $resultResponse->setContents($output);
        return $resultResponse;
    }

    private function getFilteredCustomerCollection()
    {
        $collection = $this->customerFactory->create()->getCollection()
                ->addAttributeToSelect("*");

        $collection
                ->setPageSize($this->rowsPerPage)
                ->setCurPage($this->currentPageIndex)
                ->load();

        return $collection;
    }
}
