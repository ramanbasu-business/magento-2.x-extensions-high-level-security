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


namespace Company\Plugin\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterfac
     */
    //protected $_scopeConfig;
    protected $productAttributeRepository;
    protected $eavAttributeManagement;
    protected $actionContext;
    protected $storeManager;
    
    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $eavAttributeRepository;
    protected $timezoneInterface;
    protected $objectManager;
    protected $productMetaDataInterface;
    
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $config;
    
    /**
     * @var GroupManagementInterface
     */
    protected $groupManagement;
    
    protected $versionManager;
    protected $moduleManager;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */

    protected $orderStatusCollection;

    protected $searchCriteriaBuilder;
    protected $sourceRepository;

    /** MSI SOURCE: Retrieve items by sku**/
    private $sourceItemsBySku;

    /**
     * @var \Magento\Catalog\Model\Product\AttributeSet\Options
     */
    protected $attributeSetOptions;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Action\Context $actionContext,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface,
        \Magento\Catalog\Model\Product\Attribute\Repository $productAttributeRepository,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepository,
        \Magento\Eav\Api\AttributeManagementInterface $eavAttributeManagement,
        \Magento\Framework\App\ProductMetadataInterface $productMetaDataInterface,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        \Company\Plugin\Helper\Version $versionManager,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollection,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        //\Magento\InventoryApi\Api\SourceRepositoryInterface $sourceRepository,
        //\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $sourceItemsBySku,
        \Magento\Catalog\Model\Product\AttributeSet\Options $attributeSetOptions,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        parent::__construct($context);
        
        $this->actionContext = $actionContext;
        //$this->_scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->eavAttributeRepository = $eavAttributeRepository;
        $this->eavAttributeManagement = $eavAttributeManagement;
        $this->timezoneInterface = $timezoneInterface;
        
        $this->productMetaDataInterface = $productMetaDataInterface;
        
        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        
        $this->groupManagement = $groupManagement;
        $this->versionManager = $versionManager;
        $this->orderStatusCollection = $orderStatusCollection;

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        
        /* 6.10.0 MSI may or may not be enabled */
        //$this->sourceRepository = $sourceRepository;
        //$this->sourceItemsBySku = $sourceItemsBySku;

        $this->attributeSetOptions = $attributeSetOptions;
        $this->moduleManager = $moduleManager;

        if($this->IsMsiEnabled() == 1) {
            $this->sourceRepository = 
                $this->objectManager->create('Magento\InventoryApi\Api\SourceRepositoryInterface');
            $this->sourceItemsBySku = 
                $this->objectManager->create('Magento\InventoryApi\Api\GetSourceItemsBySkuInterface');
        }
    }

    public function getModuleVersion()
    {
        return $this->versionManager->getModuleVersion();
    }
    
    public function getMagentoVersion()
    {
        return $this->productMetaDataInterface->getVersion();
    }

    public function getEdition()
    {
        return $this->productMetaDataInterface->getEdition();
    }
    
    public function printModuleVersion()
    {
        //will return the magento version
        $magentoVersion = $this->productMetaDataInterface->getVersion();

        return "<channel>" .
                "<plugin_version>" . $this->getModuleVersion() . "</plugin_version>" .
                "<magento_version>" . $magentoVersion . "</magento_version>" .
                "<magento_edition>" . $this->getEdition() . "</magento_edition>" .
                "<php_version>" . PHP_VERSION . "</php_version>" .
                "<msi>" . ($this->IsMsiEnabled() ? "1" : "0") . "</msi>" .
                "</channel>";
    }
    
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    public function getAttributeSetDataArray()
    {
        return $this->attributeSetOptions->toOptionArray();
    }

    public function getBackOrderDataArray()
    {
        //return $this->backOrderSource->toOptionArray();
        $backordersVars = [];
        $backordersVars[] = [
            'value' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_NO,
            'label' => 'No Backorders'
        ];
        $backordersVars[] = [
            'value' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NONOTIFY,
            'label' => 'Allow Qty Below 0'
        ];
        $backordersVars[] = [
            'value' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NOTIFY,
            'label' => 'Allow Qty Below 0 and Notify Customer'
        ];

        return $backordersVars;
    }

    public function getAllOrderStatusArray()
    {
        return $this->orderStatusCollection->create()->toOptionArray();
    }

    public function getCurrentCurrencyRate($currencyCode)
    {
        return $this->storeManager->getStore()->getCurrentCurrencyRate($currencyCode);
    }

    public function getDefaultWebsiteId()
    {
        return $this->storeManager->getDefaultStoreView()->getWebsiteId();
    }
    
    /**
     * Returns the website to use for group or tier prices, based on the price scope setting
     *
     * @return int|mixed
     */
    public function getWebsiteForPriceScope()
    {
        $websiteId = 0;
        $value = $this->scopeConfig
            ->getValue('catalog/price/scope', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);

        if ($value != 0) {
            // use the website associated with the current store
            $websiteId = $this->storeManager->getWebsite()->getId(); //6.8.7.5
        }
        return $websiteId;
    }
    
    /**
     * Gets the CUST_GROUP_ALL id
     *
     * @return int
     */
    public function getAllCustomerGroupsId()
    {
        // ex: 32000
        return $this->groupManagement->getAllCustomersGroup()->getId();
    }
    
    public function getDefaultCustomerGroupsId()
    {
        // ex: 32000
        return $this->groupManagement->getDefaultGroup(0)->getId();
    }

    public function getDefaultStoreId()
    {
        // \Magento\Store\Model\Store::DEFAULT_STORE_ID
        return $this->storeManager->getStore()->getStoreId();
    }
    
    public function getAllStores($verbose = 0)
    {

        $storeManagerDataList = $this->storeManager->getStores();
        $options = [];
        
        /**
        if ($verbose == 1):
            echo "isSingleStoreMode = " . $this->storeManager->isSingleStoreMode() . "\r\n";
        endif;
        **/
        
        foreach ($storeManagerDataList as $key => $value) {
            //var_dump($value->getData());
            // 4.5.4.2 Setting store_id to array key. It was blank before.
            $options[$value['store_id']] = [
                'website_id' => $value['website_id'],
                'name' => $value['name'],
                'code' => $value['code'],
                //'id' => $key
                'id' => $value['store_id']
            ];
        }
        
        //$options[] = ['website_id' => 'name' => 'Admin Store Id', 'id' => 0];
        
        /**
        if ($verbose == 1):
            echo "Stores = ";
            var_dump($options);
        endif;
        **/

        return $options;
    }

    public function preExecute()
    {
        //ob_end_clean();
        if (ob_get_length() > 0) {
            //ob_clean();
            ob_end_clean();  // clears buffer and closes buffering
        }
    }
    
    public function getAttribute($attributeCode)
    {
        return $this->eavAttributeRepository->get($attributeCode);
    }

    public function getAttributes($attributeSetId)
    {
        try {
            return $this
                        ->eavAttributeManagement
                        ->getAttributes(
                            \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
                            $attributeSetId
                        );
        } catch (\Magento\Framework\Exception\NoSuchEntityException $ex) {
            //throw $ex;
            return null;
        }
    }

    public function getAttributeOptions($attributeCode)
    {
        $manufacturerOptions = $this->productAttributeRepository->get($attributeCode)->getOptions();
        return $manufacturerOptions;
    }

    public function getWebsite()
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }
    
    public function getScmpLogVirtualPath()
    {
        // e.g. 'http://www.localhost.com/m17/_import/log/'
        return $this->storeManager->getStore()->getBaseUrl() . 'plugin/import/';
    }

    public function httpHeader($resultResponse)
    {
        /**
        $this->actionContext->getResponse()->clearHeader('Location');
        header('HTTP/1.1 200 OK');
        header('Content-type: text/xml');
        header('Expires: 01 Jan 1970 00:00:00 GMT');
        // last modified (good for caching)
        $time = time() - 60; // or filemtime($fn), etc
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $time) . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        **/
        $resultResponse->setHeader('Content-Type', 'text/xml');
        $resultResponse->setHeader('Expires', '01 Jan 1970 00:00:00 GMT');
        
        $time = time() - 60; // or filemtime($fn), etc
        $resultResponse->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $time) . ' GMT');
        $resultResponse->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $resultResponse->setHeader('Cache-Control', 'post-check=0, pre-check=0');
        $resultResponse->setHeader('Pragma', 'no-cache');
    }

    public function filterResponse($html)
    {
        if ($html == '') {
            return "";
        }
        
        return preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~is', '', $html);
    }

    public function addWithCData($var)
    {
        return '<![CDATA[' . $var . ']]>';
    }

    public function emptyIfNull($productArray, $var)
    {
        return (
            !isset($productArray[$var])
            || ($productArray[$var]==null)
        ) ? '' : $productArray[$var];
    }

    public function timeLocalToUtc($date)
    {
        // Magento Timezone Interface
        //$timezone =$this->_objectManager->create('Magento\Framework\Stdlib\DateTime\TimezoneInterface');
        // function converToTz use for conver user timezone date in default timezone(UTC)
        // converToTz is not magneto native method
        $time_final = $this
                ->converToTz(
                    $date,
                    // get default timezone of system (UTC)
                    $this->timezoneInterface->getDefaultTimezone(),
                    // get Config Timezone of current user
                    $this->timezoneInterface->getConfigTimezone()
                );
        return($time_final);
    }

    /**
     * converToTz convert Datetime from one zone to another
     * @param string $dateTime which we want to convert
     * @param string $toTz timezone in which we want to convert
     * @param string $fromTz timezone from which we want to convert
     */
    protected function converToTz($dateTime = "", $toTz = '', $fromTz = '')
    {
        // timezone by php friendly values
        $date = new \DateTime($dateTime, new \DateTimeZone($fromTz));
        $date->setTimezone(new \DateTimeZone($toTz));
        $dateTime = $date->format('Y-m-d H:i:s'); //'m/d/Y H:i:s
        return $dateTime;
    }

    public function convertGmtDateToDefaultTimeZone($date = null)
    {
        $time_final = $this
                ->converToTz(
                    $date,
                    // get default timezone of system (UTC)
                    $this->timezoneInterface->getConfigTimezone(),
                    // get Config Timezone of current user
                    $this->timezoneInterface->getConfigTimezone()
                );
        return($time_final);
    }

    /**
     * @param string $dateTime
     * @return string $dateTime as time zone
     */
    public function getTimeAccordingToTimeZone($dateTime)
    {
        // for get current time according to time zone
        //$today = $this->_timezoneInterface->date()->format('m/d/y H:i:s');
 
        // To convert date time according to magento time zone
        $dateTimeAsTimeZone = $this->timezoneInterface
                                        ->date(new \DateTime($dateTime))
                                        ->format('Y-m-d H:i:s');
        return $dateTimeAsTimeZone;
    }
    
    /**
     * @param string $dateTime
     * @return string $dateTime in ISO-8601 along with time zone
     */
    public function getIso8601DateTime($dateTime)
    {
        // for get current time according to time zone
        //$today = $this->_timezoneInterface->date()->format('m/d/y H:i:s');
 
        // To convert date time according to magento time zone
        $dateTimeAsTimeZone = $this->timezoneInterface
                                        ->date(new \DateTime($dateTime))
                                        ->format('c');
        return $dateTimeAsTimeZone;
    }
    
    public function clean2($contents)
    {

        # Find the bad characters
        preg_match_all('/[^(x20-x7F)]*/', $contents, $badchars);
        //var_dump($badchars);
        # Process bad characters if some were found
        if (isset($badchars[0])) {
            # Narrow down the results to uniques only
            $badchars[0] = array_unique($badchars[0]);

            # Replace the bad characters with their UTF8 equivalents
            foreach ($badchars[0] as $badchar) {
                $contents = preg_replace(
                    "/" . $badchar . "/",
                    mb_convert_encoding($badchar, 'UTF-8'),
                    $contents
                );
            }
        }
        return $contents;
    }

    public function cleanString($text)
    {
        $text = str_replace("�", "-", $text);
        $text = str_replace(" ", " ", $text);
        $text = str_replace("�", "c", $text);
        $text = str_replace("�", "C", $text);
        $text = str_replace("�", "n", $text);
        $text = str_replace("�", "N", $text);

        //2) Translation CP1252. &ndash; => -
        $trans = [];
        $trans['&Acirc;'] = '&#194;';
        $trans['&sbquo;'] = '&#x82;';    // Single Low-9 Quotation Mark
        $trans['&mdash;'] = '&#8212;';    // Latin Small Letter F With Hook
        $trans['&ndash;'] = '&#8211;';    // Double Low-9 Quotation Mark
        $trans['&hellip;'] = '&#8230;';    // Horizontal Ellipsis
        $trans['&circ;'] = '&#8853;';    // Modifier Letter Circumflex Accent
        $trans['&tilde;'] = '&#732;';    // Small Tilde
        $trans['&trade;'] = '&#174;';    // Trade Mark Sign
        $trans['&nbsp;'] = '&#xA0;';
        $trans['&ldquo;'] = '&#x93;'; // Left Double Quote
        $trans['&rdquo;'] = '&#x94;'; // right Double Quote
        $trans['&eacute;'] = '&#201;'; // right Double Quote
        $trans['&euro;'] = '&#8364;';    // euro currency symbol
        $trans['&rsquo;'] = '&#x92;';
        $trans['&lsquo;'] = '&#x91;';
        $trans['&agrave;'] = '&#192;';
        $trans['&reg;'] = '&#xAE;';
        $trans['&Atilde;'] = '&#195;';
        $trans['&frac14;'] = '&#188;';
        $trans['&frac12;'] = '&#189;';
        $trans['&frac34;'] = '&#190;';

        ksort($trans);

        foreach ($trans as $k => $v) {
            $text = str_replace($k, $v, $text);
        }

        $replaceChar = '';
        $text = preg_replace(
            '/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
            '|[\x00-\x7F][\x80-\xBF]+' .
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
            $replaceChar,
            $text
        );

        //reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
        $text = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
                '|\xED[\xA0-\xBF][\x80-\xBF]/S', $replaceChar, $text);

        $text = preg_replace("/\x1d/", '', $text);
        return ($text);
    }

    public function cleanStringByWhiteSpace($text)
    {
        // First preserve HTML structure
        $parts = preg_split('/(<.*?>)/U', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $result = '';
        foreach ($parts as $part) {
            if (preg_match('/^<.*>$/', $part)) {
                // This is an HTML tag - keep it as is
                $result .= $part;
            } else {
                // This is text content
                // Define allowed characters including quotes and measurements
                $allowed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890~!@#$%^&()_-+=~` /\.*[]"\'×?';
                
                // Handle HTML entities separately
                $entities = [];
                preg_match_all('/&[#]?[a-zA-Z0-9]+;/', $part, $matches);
                foreach ($matches[0] as $i => $entity) {
                    $marker = "##ENTITY_{$i}##";
                    $entities[$marker] = $entity;
                    $part = str_replace($entity, $marker, $part);
                }
                
                // Replace non-ASCII characters
                $part = preg_replace('/[^\x00-\x7F]/u', '', $part);
                
                // Keep only allowed characters
                $part = preg_replace('/[^' . preg_quote($allowed, '/') . preg_quote(implode('', array_keys($entities)), '/') . ']/u', '', $part);
                
                // Restore HTML entities
                $part = str_replace(array_keys($entities), array_values($entities), $part);
                
                $result .= $part;
            }
        }
        
        return $result;
    }

    public function getStockItem($productId)
    {
        return $this->_stockItemRepository->get($productId);
    }

    public function IsMsiEnabled()
    {
        /* 6.10.0 MSI may or may not be enabled */
        if (
            $this->moduleManager->isEnabled('Magento_Inventory')
            && $this->moduleManager->isEnabled('Magento_InventorySourceSelection')
        ) {
            return 1;
        }

        return 0;
    }

    public function getSourceList()
    {
        /* 6.10.0 MSI may or may not be enabled */
        if ($this->IsMsiEnabled() == 0) {
            return false;
        }

        /** http://mageprince.com/blog/how-to-get-all-list-of-sources-multi-source-inventory-msi-programmatically-in-magento-2/
        **/
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('enabled', '1')->create();
        $sourceData = $this->sourceRepository->getList($searchCriteria);

        return $sourceData->getItems();
    }

    public function getSourceItemBySku($sku)
    {
        if ($this->sourceItemsBySku == null) {
            return null;
        }
        
        return $this->sourceItemsBySku->execute($sku);
    }
}
