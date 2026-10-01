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
use Magento\Catalog\Model\Resource\Category\Collection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Company\Plugin\Helper\Data;

class Product extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    private $sku = ""; // Incase FILTER is needed
    private $rowsPerPage = 10;  // Default 10
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1
    protected $helper;
    private $encoder;
    private $productStatus = null;
    private $storeIdFilter = null;

    protected $pageFactory;
    protected $fileFactory;
    protected $request;
    protected $categoryFactory;
    protected $productCollection;
    protected $stockFilter;
    protected $stockStatus;
    protected $stockRegistry;
    protected $directory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\CatalogInventory\Helper\Stock $stockFilter,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Status $stockStatus,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();

        $this->categoryFactory = $categoryFactory;
        $this->productCollection = $productCollection;
        $this->stockFilter = $stockFilter;
        $this->stockStatus = $stockStatus;
        $this->stockRegistry = $stockRegistry;

        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->fileFactory = $fileFactory;
        $this->rawResultFactory = $context->getResultFactory();

        parent::__construct($context);

        $this->directory = $filesystem
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
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

        $originalQryStrArray = $this->getRequest()->getParams();

        if (array_key_exists("status", $qryStrArray)):
            if (isset($qryStrArray['status'])):
                if ($qryStrArray['status'] == "enabled"):
                    $this->productStatus = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
                endif;
                if ($qryStrArray['status'] == "disabled"):
                    $this->productStatus = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
                endif;
            endif;
        endif;
        
        if (isset($qryStrArray['sku']) && $qryStrArray['sku'] != "") {
            $this->sku = (string) $qryStrArray['sku'];
        }
        
        if (array_key_exists("storeid", $qryStrArray) && isset($qryStrArray["storeid"])):
            /**
            * 6.9.0.0 Filter by multiple store ids.
            * Even though multiple store ids can be set using comma, first one will be considered.
            * */
            $arrStoreIds = explode(',', $qryStrArray["storeid"]);
            $this->storeIdFilter = $arrStoreIds[0];
        endif;

        if (array_key_exists("getinvprice", $originalQryStrArray)):
            return $this->renderGetInvprice($qryStrArray, $resultResponse);
        
        elseif (array_key_exists("getall", $originalQryStrArray)):
            return $this->renderGetAll($qryStrArray, $resultResponse);
        
        elseif (array_key_exists("csv", $originalQryStrArray)):
            return $this->renderCsv($qryStrArray, $resultResponse);
        
        elseif (array_key_exists("getproductcsv", $originalQryStrArray)):
            return $this->renderGetProductCsv($qryStrArray, $resultResponse);
        
        elseif (array_key_exists("getproduct", $originalQryStrArray)):
            return $this->renderGetProduct($qryStrArray, $resultResponse);
        
        else:
            return $this->renderGetProduct($qryStrArray, $resultResponse); // Default
        endif;
    }

    private function stripInvalidXml($value)
    {
        $ret = "";
        
        if (empty($value)) {
            return $ret;
        }

        /**
        $current;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $current = ord($value{$i});
            if (($current == 0x9) ||
                    ($current == 0xA) ||
                    ($current == 0xD) ||
                    (($current >= 0x20) && ($current <= 0xD7FF)) ||
                    (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                    (($current >= 0x10000) && ($current <= 0x10FFFF))) {
                $ret .= chr($current);
            } else {
                $ret .= "";
            }
        }
        **/
        
        /*
        * If you wish to also strip line feeds, carriage returns, tabs, non-breaking spaces,
        * and soft-hyphens, you can use:
        * $string = preg_replace('/[\x00-\x1F\x7F-\xA0\xAD]/u', '', $string);
        **/
        $ret = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $value);

        return $ret;
    }

    private function renderGetProduct($qryStrArray, $resultResponse)
    {
        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }
        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }
        

        $collection = $this->productCollection->create()
                ->addAttributeToSelect('*')
                //->joinField('stock_item', 'cataloginventory_stock_item',
                // 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
                ->setOrder('sku', 'ASC');

        // $collection->getSelect()
        //  ->joinLeft('qty', 'cataloginventory_stock_item', 'qty',
        //   'product_id=entity_id', '{{table}}.stock_id=1');
        // $this->_stockFilter->addIsInStockFilterToCollection($collection);
        // $collection = $this->_stockStatus
        //  ->addStockDataToCollection($collection, false);
        // https://magento.stackexchange.com/questions/157193/how-to-get-only-out-of-stock-products-in-magento-2?noredirect=1&lq=1
        // https://magento.stackexchange.com/questions/122972/magento2-product-collection-filter-out-of-stock-products/130070
        $collection->setFlag('has_stock_status_filter', false);

        if ($this->sku != ""):
            $collection->addFieldToFilter('sku', ['like' => [$this->sku . '%']])
                    ->addUrlRewrite();
        endif;

        if ($this->productStatus !== null):
            $collection->addFieldToFilter('status', ['eq' => [$this->productStatus]]);
        endif;
        
        if ($this->storeIdFilter !== null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;

        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();

        $recordCount = $collection->getSize();
        $totalPages = (int) ($recordCount / $this->rowsPerPage)
                + ((int) ($recordCount % $this->rowsPerPage) > 0 ? 1 : 0);

        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<products>';
        $output .= $this->helper->printModuleVersion();
        $output .= '<recordcount>' . $recordCount . '</recordcount>';
        $output .= '<currentpageindex>' . $this->currentPageIndex . '</currentpageindex>';
        $output .= '<pagesize>' . $this->rowsPerPage . '</pagesize>';
        $output .= '<totalpages>' . $totalPages . '</totalpages>';
        /** $this->helper->sendToScreen($output); **/

        foreach ($collection as $product):
            $output .= '<product>';
            $output .= '<entity_id>' . $product->getId() . '</entity_id>';
            $output .= '<sku>' . $this->helper->addWithCData($product->getSku()) . '</sku>';
            $output .= '<status>' . $product->getStatus() . '</status>';
            $output .= '<name>' . $this->helper->addWithCData($product->getName()) . '</name>';
            $output .= '<entity_type_id>' . $product->getTypeId() . '</entity_type_id>';
            $output .= '<attribute_set_id>' . $product->getAttributeSetId() . '</attribute_set_id>';
            
            $output .= '<created_at>' . $product->getCreatedAt() . '</created_at>';
            $output .= '<updated_at>' . $product->getUpdatedAt() . '</updated_at>';

            //$output .= '<now_at>' . date('Y-m-d h:m:s', time()) . '</now_at>';
            //$output .= '<upc>' . $stockitem->getUpc() . '</upc>';
            
            if ($this->storeIdFilter != null):
                $output .= '<store_id>' . $product->getStoreId() . '</store_id>';
            endif;
            

            /* Get Stock Registry  */

            /* Get stock item  */
            $stockitem = $this->stockRegistry
                    ->getStockItem(
                        $product->getId(),
                        $product->getStore()->getWebsiteId()
                    );

            $output .= '<qty>' . $stockitem->getQty() . '</qty>';

            /** MSI SOURCE 6.8.7 **/
            $sourceItemList = $this->helper->getSourceItemBySku($product->getSku());
            
            $output .= '<msi_sources>';
            if ($sourceItemList && $sourceItemList != null):
                foreach ($sourceItemList as $source):
                    $output .= '<source>';
                    $output .= '<source_code>' . $source->getSourceCode() . '</source_code>';
                    $output .= '<qty>' . $source->getQuantity() . '</qty>';
                    $output .= '</source>';
                endforeach;
            endif;
            $output .= '</msi_sources>';

            $output .= '<price>' . $product->getPrice() . '</price>';
            $output .= '<special_price>' . $product->getSpecialPrice() . '</special_price>';
            $output .= '<special_from_date>' . $product->getSpecialFromDate() . '</special_from_date>';
            $output .= '<special_to_date>' . $product->getSpecialToDate() . '</special_to_date>';

            /**
            $test = 'Dont let &#194;';
            $test .= '<h1>!مرحبا بالعالم</h1><h2>你叫什么名字？</h1>
            <h2>This is Chinese Language.</h2>
            at the companies discretion
            <p>This is the code demonstrating encoding Process 12"x12" (10 square feet)';

            //$cleaned = $this->helper->cleanString($test);
            $cleaned = $this->helper->cleanStringByWhiteSpace($test);

            $output .= '<XXX>' 
                . $this->helper->addWithCData($cleaned) 
                . '</XXX>';
            **/
            $desc = $this->helper->cleanStringByWhiteSpace($product->getShortDescription());
            $output .= '<short_description>' 
                . $this->helper->addWithCData($desc) 
                . '</short_description>';

            $desc = $this->helper->cleanStringByWhiteSpace($product->getDescription());
            $output .= '<description>' 
                . $this->helper->addWithCData($desc) 
                . '</description>';

            $output .= '<url>' . $this->helper->addWithCData($product->getUrlKey()) . '</url>';
            $output .= '<meta_title>' . $this->helper->addWithCData($product->getMetaTitle()) . '</meta_title>';
            $output .= '<meta_keyword>' . $this->helper->addWithCData($product->getMetaKeyword()) . '</meta_keyword>';
            $output .= '<meta_description>' . $this->helper->addWithCData($product->getMetaDescription()) . '</meta_description>';

            $output .= '<stock_item>';
            $output .= '<qty>' . $stockitem->getQty() . '</qty>';
            $output .= '<is_in_stock>' . $stockitem->getIsInStock() . '</is_in_stock>';
            $output .= '<use_config_backorders>' . $stockitem->getUseConfigBackorders() . '</use_config_backorders>';
            $output .= '<backorders>' . $stockitem->getBackOrders() . '</backorders>';
            $output .= '<manage_stock>' . $stockitem->getManageStock() . '</manage_stock>';
            $output .= '<use_config_manage_stock>' . $stockitem->getUseConfigManageStock()
                . '</use_config_manage_stock>';

            $output .= '<is_qty_decimal>' . $stockitem->getIsQtyDecimal() . '</is_qty_decimal>';
            $output .= '<min_qty>' . $stockitem->getMinQty() . '</min_qty>';
            $output .= '<use_config_min_qty>' . $stockitem->getUseConfigMinQty() . '</use_config_min_qty>';
            $output .= '<min_sale_qty>' . $stockitem->getMinSaleQty() . '</min_sale_qty>';
            $output .= '<max_sale_qty>' . $stockitem->getMaxSaleQty() . '</max_sale_qty>';
            $output .= '<use_config_min_sale_qty>' . $stockitem->getUseConfigMinSaleQty()
                . '</use_config_min_sale_qty>';
            $output .= '<use_config_max_sale_qty>' . $stockitem->getUseConfigMaxSaleQty()
                . '</use_config_max_sale_qty>';

            $output .= '<notify_stock_qty>' . $stockitem->getNotifyStockQty() . '</notify_stock_qty>';
            $output .= '<use_config_notify_stock_qty>' . $stockitem->getUseConfigNotifyStockQty()
                . '</use_config_notify_stock_qty>';
            $output .= '<enable_qty_increments>' . $stockitem->getEnableQtyIncrements() . '</enable_qty_increments>';
            $output .= '<use_config_enable_qty_inc>' . $stockitem->getUseConfigEnableQtyInc()
                . '</use_config_enable_qty_inc>';
            $output .= '<qty_increments>' . $stockitem->getQtyIncrements() . '</qty_increments>';
            $output .= '<use_config_qty_increments>' . $stockitem->getUseConfigQtyIncrements()
                . '</use_config_qty_increments>';
            $output .= '<low_stock_date>' . $stockitem->getLowStockDate() . '</low_stock_date>';
            $output .= '<stock_status_changed_auto>' . $stockitem->getStockStatusChangedAuto()
                . '</stock_status_changed_auto>';
            $output .= '<is_decimal_divided>' . $stockitem->getIsDecimalDivided() . '</is_decimal_divided>';

            $output .= '</stock_item>';

            unset($stockitem);

            /** $this->helper->sendToScreen($output);**/

            $attributes = $product->getAttributes();
            
            $output .= '<custom_attributes>';
            
            foreach ($attributes as $productCustomAttribute):
                /**
                *$output .= '<is_user_defined>'
                * . $productCustomAttribute->getIsUserDefined() . '</is_user_defined>';
                **/
                if ($productCustomAttribute->getIsUserDefined() === '0'):
                    $output .= '<custom_attribute>';
                    $output .= '<code>' . $productCustomAttribute->getAttributeCode() . '</code>';
                    
                    $valueData = '';
                    try {
                        $valueData = $product->getData($productCustomAttribute->getAttributeCode());

                        if(is_array($valueData)):
                            $valueData = json_encode($valueData);
                        else:
                            $valueData = (string)$valueData;
                        endif;
                    } catch (\Exception $ex) {
                        $valueData .= '';
                    }

                    // utf8_encode removed
                    $output .= '<value><![CDATA['
                        . $this->stripInvalidXml(
                            ($valueData)
                        ) . ']]></value>';
                    $output .= '</custom_attribute>';
                endif;
            endforeach;
            
            $output .= '</custom_attributes>';
            /** $this->helper->sendToScreen($output); **/

            /* Caetgories */
            $cats = $product->getCategoryIds();

            $output .= '<categories>';
            foreach ($cats as $cId):
                $_category = $this->categoryFactory->create()->load($cId);
                $output .= '<category>';
                $output .= '<id>' . $cId . '</id>';
                $output .= '<name>' . $this->helper->addWithCData($_category->getName()) . '</name>';
                $output .= '</category>';
            endforeach;
            $output .= '</categories>';

            $output .= '</product>';
            /** $this->helper->sendToScreen($output);**/
        endforeach;

        $output .= '</products>';

        /** $this->helper->sendToScreen($output);**/
        $resultResponse->setContents($output);
        return $resultResponse;
    }

    private function renderGetInvprice($qryStrArray, $resultResponse)
    {
        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }
        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }
        

        $collection = $this->productCollection->create()
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                ->addAttributeToSelect('special_price')
                ->addAttributeToSelect('special_from_date')
                ->addAttributeToSelect('special_to_date')
                ->setOrder('sku', 'ASC');

        if ($this->sku != ""):
            $collection->addFieldToFilter('sku', ['like' => [$this->sku . '%']])
                    ->addUrlRewrite();
        endif;

        // 4.5.0.1
        if ($this->productStatus !== null):
            $collection->addFieldToFilter('status', ['eq' => [$this->productStatus]]);
        endif;
        
        if ($this->storeIdFilter !== null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;

        // https://magento.stackexchange.com/questions/157193/how-to-get-only-out-of-stock-products-in-magento-2?noredirect=1&lq=1
        // https://magento.stackexchange.com/questions/122972/magento2-product-collection-filter-out-of-stock-products/130070
        $collection->setFlag('has_stock_status_filter', false);

        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();

        $recordCount = $collection->getSize();
        $totalPages = (int) ($recordCount / $this->rowsPerPage)
            + ((int) ($recordCount % $this->rowsPerPage) > 0 ? 1 : 0);

        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<products>';
        $output .= $this->helper->printModuleVersion();
        $output .= '<recordcount>' . $recordCount . '</recordcount>';
        $output .= '<currentpageindex>' . $this->currentPageIndex . '</currentpageindex>';
        $output .= '<pagesize>' . $this->rowsPerPage . '</pagesize>';
        $output .= '<totalpages>' . $totalPages . '</totalpages>';
        
        /** $this->helper->sendToScreen($output); **/

        foreach ($collection as $product):
            //$blockInstance = $this->pageFactory->getLayout()->getBlock('StockBlock');

            $output .= '<product>';
            $output .= '<sku>' . $this->helper->addWithCData($product->getSku()) . '</sku>';

            /* Get stock item  */
            $stockitem = $this->stockRegistry
                ->getStockItem($product->getId(), $product->getStore()->getWebsiteId());

            $output .= '<qty>' . $stockitem->getQty() . '</qty>';
            $output .= '<price>' . $product->getPrice() . '</price>';

            /** MSI SOURCE 6.8.7 **/
            $sourceItemList = $this->helper->getSourceItemBySku($product->getSku());
            
            $output .= '<msi_sources>';
            if ($sourceItemList && $sourceItemList != null):
                foreach ($sourceItemList as $source):
                    $output .= '<source>';
                    $output .= '<source_code>' . $this->helper->addWithCData($source->getSourceCode()) . '</source_code>';
                    $output .= '<qty>' . $source->getQuantity() . '</qty>';
                    $output .= '</source>';
                endforeach;
            endif;
            $output .= '</msi_sources>';

            unset($stockitem);

            $output .= '</product>';
            /** $this->helper->sendToScreen($output); **/
        endforeach;

        $output .= '</products>';

        /** $this->helper->sendToScreen($output); **/
        $resultResponse->setContents($output);
        return $resultResponse;
    }

    /*
     * CSV methods
     */

    private function renderCsv($qryStrArray, $resultResponse)
    {
        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        } else {
            $this->rowsPerPage = 100000;
        }

        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }

        $collection = $this->productCollection->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                ->joinField(
                    'qty',
                    'cataloginventory_stock_item',
                    'qty',
                    'product_id=entity_id',
                    '{{table}}.stock_id=1',
                    'left'
                )
                ->setOrder('sku', 'ASC');

        if ($this->storeIdFilter != null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;

        // https://magento.stackexchange.com/questions/157193/how-to-get-only-out-of-stock-products-in-magento-2?noredirect=1&lq=1
        // https://magento.stackexchange.com/questions/122972/magento2-product-collection-filter-out-of-stock-products/130070
        $collection->setFlag('has_stock_status_filter', false);

        // 4.5.0.1
        if ($this->productStatus !== null):
            $collection->addFieldToFilter('status', ['eq' => [$this->productStatus]]);
        endif;
        
        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();

        $content = $this->generateMlnList($collection);
        return $this->prepareDownloadResponse("product-export-csv.csv", $content);
    }

    /**
     * Generates CSV file with product's list according to the collection in the $this->_list
     * @return array
     */
    public function generateMlnList($items)
    {

        /* foreach ($items as $product) {
          var_dump("Hello ===================== ". count($items) );
          }
          var_dump("Hello ===================== ". ($items->getSize()) );
          exit; */

        if ($items->getSize() > 0) {
            $name = date('m_d_Y_H_i_s');
            $file = $name . '.csv';

            $stream = $this->directory->openFile($file, 'w+');
            $stream->writeCsv($this->getCsvHeaders());
            $stream->lock();

            foreach ($items as $product) {
                $arr = [];

                $arr[] = $product->getEntityId();
                $arr[] = $product->getSku();
                $arr[] = $product->getPrice();
                $arr[] = $product->getQty();

                $stream->writeCsv($arr);
                unset($arr);
            }

            $stream->unlock();
            $stream->close();

            return [
                'type' => 'filename',
                'value' => $file,
                'rm' => true // can delete file after use
            ];
        }
    }

    /**
     * Returns indexes of the fetched array as headers for CSV
     * @param array $products
     * @return array
     */
    protected function getCsvHeaders()
    {
        $headers = [
            "entity_id", "sku", "price", "qty"
        ];

        return $headers;
    }

    protected function prepareDownloadResponse(
        $fileName,
        $content,
        $contentType = 'application/octet-stream',
        $contentLength = null
    ) {

        $isFile = false;
        $file = null;

        if (is_array($content)):
            if (!isset($content['type']) || !isset($content['value'])) {
                return null;
            }
            if ($content['type'] == 'filename') {
                $isFile = true;
                $file = $content['value'];
            }
        endif;

        if ($content !== null && $isFile):
            return $this->fileFactory->create($file, $content, DirectoryList::VAR_DIR);
        endif;
    }

    /* End of CSV methods */

    private function renderGetAll($qryStrArray, $resultResponse)
    {
        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }
        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }
        

        $collection = $this->productCollection->create()
                ->addAttributeToSelect('*')
                ->setOrder('sku', 'ASC');

        if ($this->sku != ""):
            $collection
                ->addFieldToFilter('sku', ['eq' => [$this->sku]])
                ->addUrlRewrite();
        endif;

        if ($this->storeIdFilter != null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;

        // https://magento.stackexchange.com/questions/157193/how-to-get-only-out-of-stock-products-in-magento-2?noredirect=1&lq=1
        // https://magento.stackexchange.com/questions/122972/magento2-product-collection-filter-out-of-stock-products/130070
        $collection->setFlag('has_stock_status_filter', false);

        // 4.5.0.1
        if ($this->productStatus !== null):
            $collection->addFieldToFilter('status', ['eq' => [$this->productStatus]]);
        endif;
        
        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();

        $recordCount = $collection->getSize();
        $totalPages = (int) ($recordCount / $this->rowsPerPage)
                + ((int) ($recordCount % $this->rowsPerPage) > 0 ? 1 : 0);

        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<products>';
        $output .= $this->helper->printModuleVersion();
        $output .= '<recordcount>' . $recordCount . '</recordcount>';
        $output .= '<currentpageindex>' . $this->currentPageIndex . '</currentpageindex>';
        $output .= '<pagesize>' . $this->rowsPerPage . '</pagesize>';
        $output .= '<totalpages>' . $totalPages . '</totalpages>';

        /** $this->helper->sendToScreen($output); **/

        foreach ($collection as $product):
            $productArray = $product->getData();
            $type_id = $product->getTypeId();
            
            $output .= '<product>';
            $output .= '<entity_id>' . $productArray['entity_id'] . '</entity_id>';
            $output .= '<attribute_set_id>' . $productArray['attribute_set_id'] . '</attribute_set_id>';
            $output .= '<type_id>' . $productArray['type_id'] . '</type_id>';
            
            /* 6.11.1.0 */
            $output .= '<sku>' . $this->helper->addWithCData($productArray['sku']) . '</sku>';
            $output .= '<status>' . $productArray['status'] . '</status>';
            $output .= '<has_options>' . $productArray['has_options'] . '</has_options>';
            $output .= '<required_options>' . $productArray['required_options'] . '</required_options>';
            $output .= '<created_at>' . $productArray['created_at'] . '</created_at>';
            $output .= '<updated_at>' . $productArray['updated_at'] . '</updated_at>';
            $output .= '<name>' . $this->helper->addWithCData($productArray['name']) . '</name>';
            $output .= '<image>'
                . $this->helper->addWithCData($this->helper->emptyIfNull($productArray, 'image')) . '</image>';

            $output .= '<small_image>'
                . $this->helper->addWithCData($this->helper->emptyIfNull($productArray, 'small_image'))
                . '</small_image>';

            $output .= '<thumbnail>'
                . $this->helper->addWithCData($this->helper->emptyIfNull($productArray, 'thumbnail'))
                . '</thumbnail>';

            //$output .= '<options_container>' . $productArray['options_container'] . '</options_container>';
            $output .= '<url_key>' 
                . $this->helper->addWithCData($this->helper->emptyIfNull($productArray, 'url_key'))
                . '</url_key>';

            $output .= '<url>' . $this->helper->addWithCData($product->getUrlKey()) . '</url>';
            $output .= '<meta_title>' . $this->helper->addWithCData($product->getMetaTitle()) . '</meta_title>';
            $output .= '<meta_keyword>' . $this->helper->addWithCData($product->getMetaKeyword()) . '</meta_keyword>';
            $output .= '<meta_description>' . $this->helper->addWithCData($product->getMetaDescription()) . '</meta_description>';

            $output .= '<activity>' . $this->helper->emptyIfNull($productArray, 'activity') . '</activity>';
            $output .= '<visibility>' . $productArray['visibility'] . '</visibility>';
            //$output .= '<quantity_and_stock_status>' . $productArray['quantity_and_stock_status']
            //    . '</quantity_and_stock_status>';

            $output .= '<tax_class_id>' . $this->helper->emptyIfNull($productArray, 'tax_class_id') . '</tax_class_id>';
            $output .= '<price>' . $this->helper->emptyIfNull($productArray, 'price') . '</price>';
            $output .= '<special_price>'
                . $this->helper->emptyIfNull($productArray, 'special_price')
                . '</special_price>';

            $output .= '<special_from_date>'
                . $this->helper->emptyIfNull($productArray, 'special_from_date')
                . '</special_from_date>';

            $output .= '<news_from_date>'
                . $this->helper->emptyIfNull($productArray, 'news_from_date')
                . '</news_from_date>';

            $desc = $this->helper->cleanStringByWhiteSpace($product->getShortDescription());
            $output .= '<short_description>' 
                . $this->helper->addWithCData($desc) 
                . '</short_description>';

            $desc = $this->helper->cleanStringByWhiteSpace($product->getDescription());
            $output .= '<description>' 
                . $this->helper->addWithCData($desc) 
                . '</description>';
            
            /** $this->helper->sendToScreen($output); **/

            /* Get Stock Registry  */

            /* Get stock item  */
            $stockitem = $this->stockRegistry
                    ->getStockItem(
                        $product->getId(),
                        $product->getStore()->getWebsiteId()
                    );
            $output .= '<qty>' . $stockitem->getQty() . '</qty>';

            /** MSI SOURCE 6.8.7 **/
            $sourceItemList = $this->helper->getSourceItemBySku($product->getSku());
            
            $output .= '<msi_sources>';
            if ($sourceItemList && $sourceItemList != null):
                foreach ($sourceItemList as $source):
                    $output .= '<source>';
                    $output .= '<source_code>' . $this->helper->addWithCData($source->getSourceCode()) . '</source_code>';
                    $output .= '<qty>' . $source->getQuantity() . '</qty>';
                    $output .= '</source>';
                endforeach;
            endif;
            $output .= '</msi_sources>';

            $output .= '<stock_item>';
            $output .= '<qty>' . $stockitem->getQty() . '</qty>';
            $output .= '<is_in_stock>' . $stockitem->getIsInStock() . '</is_in_stock>';
            $output .= '<use_config_backorders>' . $stockitem->getUseConfigBackorders() . '</use_config_backorders>';
            $output .= '<backorders>' . $stockitem->getBackOrders() . '</backorders>';
            $output .= '<manage_stock>' . $stockitem->getManageStock() . '</manage_stock>';
            $output .= '<use_config_manage_stock>'
                . $stockitem->getUseConfigManageStock()
                . '</use_config_manage_stock>';

            $output .= '<is_qty_decimal>' . $stockitem->getIsQtyDecimal() . '</is_qty_decimal>';
            $output .= '<min_qty>' . $stockitem->getMinQty() . '</min_qty>';
            $output .= '<use_config_min_qty>' . $stockitem->getUseConfigMinQty() . '</use_config_min_qty>';
            $output .= '<min_sale_qty>' . $stockitem->getMinSaleQty() . '</min_sale_qty>';
            $output .= '<max_sale_qty>' . $stockitem->getMaxSaleQty() . '</max_sale_qty>';
            $output .= '<use_config_min_sale_qty>'
                . $stockitem->getUseConfigMinSaleQty()
                . '</use_config_min_sale_qty>';

            $output .= '<use_config_max_sale_qty>'
                . $stockitem->getUseConfigMaxSaleQty()
                . '</use_config_max_sale_qty>';

            $output .= '<notify_stock_qty>' . $stockitem->getNotifyStockQty() . '</notify_stock_qty>';
            $output .= '<use_config_notify_stock_qty>'
                . $stockitem->getUseConfigNotifyStockQty()
                . '</use_config_notify_stock_qty>';

            $output .= '</stock_item>';

            unset($stockitem);
            /** $this->helper->sendToScreen($output); **/

            $attributes = $product->getAttributes();
            
            $output .= '<custom_attributes>';
            
            foreach ($attributes as $productCustomAttribute):
                if ($productCustomAttribute->getIsUserDefined() === '1'):
                    $output .= '<custom_attribute>';
                    $output .= '<code>' . $productCustomAttribute->getAttributeCode() . '</code>';
                    
                    $valueData = '';
                    try {
                        $valueData = $product->getData($productCustomAttribute->getAttributeCode());

                        if(is_array($valueData)):
                            $valueData = json_encode($valueData);
                        else:
                            $valueData = (string)$valueData;
                        endif;
                    } catch (\Exception $ex) {
                        $valueData .= '';
                    }

                    // utf8_encode removed
                    $output .= '<value><![CDATA['
                        . $this->stripInvalidXml(
                            ($valueData)
                        ) . ']]></value>';
                    $output .= '</custom_attribute>';
                endif;
            endforeach;

            $output .= '</custom_attributes>';
            /** $this->helper->sendToScreen($output); **/

            /* Caetgories */
            $cats = $product->getCategoryIds();

            $output .= '<categories>';
            foreach ($cats as $cId):
                $_category = $this->categoryFactory->create()->load($cId);

                $output .= '<category>';
                $output .= '<id>' . $cId . '</id>';
                $output .= '<name>' . $this->helper->addWithCData($_category->getName()) . '</name>';
                $output .= '</category>';
            endforeach;

            $output .= '</categories>';
            /** $this->helper->sendToScreen($output); **/
            
            switch ($type_id):
                case 'simple':
                    $output .= '<children />';
                    break;
                case 'configurable':
                    $output .= $this->renderChildrenConfigurable($product);
                    break;
                case 'grouped':
                    $output .= $this->renderChildrenGrouped($product);
                    break;
                case 'bundle':
                    /** not working. error
                    $output .= $this->renderChildrenBundle($product);
                    */
                    $output .= '<children />';
                    break;
                default:
                    break;
            endswitch;

            $output .= '</product>';
            /** $this->helper->sendToScreen($output); **/

            //$subcategories = $categoryModel->getChildrenCategories();
            //foreach ($subcategories as $value) {
            //}
        endforeach;

        $output .= '</products>';
        /** $this->helper->sendToScreen($output); **/

        unset($this->productCollection);

        $resultResponse->setContents($output);
        return $resultResponse;
    }

    private function renderChildrenConfigurable($product)
    {
        $childProducts = $product->getTypeInstance()->getUsedProducts($product);
        $output = '<children type="configurable">';
        if ($childProducts):
            foreach ($childProducts as $childProduct):
                $output .= '<child>';
                $output .= '<product_id>' . $childProduct->getId() . '</product_id>';
                $output .= '<sku>' . $this->helper->addWithCData($childProduct->getSku()) . '</sku>';
                $output .= '</child>';
            endforeach;
        endif;
        $output .= '</children>';
        /** $this->helper->sendToScreen($output); **/
        return $output;
    }

    private function renderChildrenGrouped($product)
    {
        $childProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
        $output = '<children type="grouped">';
        if ($childProducts):
            foreach ($childProducts as $childProduct):
                $output .= '<child>';
                $output .= '<product_id>' . $childProduct->getId() . '</product_id>';
                $output .= '<sku>' . $this->helper->addWithCData($childProduct->getSku()) . '</sku>';
                $output .= '</child>';
            endforeach;
        endif;
        $output .= '</children>';
        
        /** $this->helper->sendToScreen($output); **/
        return $output;
    }

    public function renderChildrenBundle($product)
    {
        $output = '<children type="bundle">';

        //$bundled_product = new Mage_Catalog_Model_Product();
        //$bundled_product->load($product->getId());
        //echo $product->getId();
        //$selectionCollection = $bundled_product->getTypeInstance(true)->getSelectionsCollection(
        //        $bundled_product->getTypeInstance(true)->getOptionsIds($bundled_product), $bundled_product
        //);
        $selectionCollection = $product->getTypeInstance(true)->getChildrenIds($product->getId(), true);
        if ($selectionCollection && count($selectionCollection) > 0):
            foreach ($selectionCollection as $option) {
                //$bundled_items[] = $option->product_id;
                var_dump($option);
                $output .= '<child>';
                $output .= '<product_id>' . $option['product_id'] . '</product_id>';
                $output .= '<sku>' . $this->helper->addWithCData($option['sku']) . '</sku>';
                $output .= '</child>';
            }
        endif;

        //print_r($bundled_items);
        $output .= '</children>';
        
        /** $this->helper->sendToScreen($output); **/
        return $output;
    }

    private function renderGetProductCsv($qryStrArray, $resultResponse)
    {
        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        } else {
            $this->rowsPerPage = 100000;
        }

        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }
        

        $collection = $this->productCollection->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                ->addAttributeToSelect('weight')
                ->addAttributeToSelect('upc')
                ->addAttributeToSelect('short_description')
                ->addAttributeToSelect('description')
                ->addAttributeToSelect('store_id')
                ->joinField(
                    'qty',
                    'cataloginventory_stock_item',
                    'qty',
                    'product_id=entity_id',
                    '{{table}}.stock_id=1',
                    'left'
                )->setOrder('sku', 'ASC');

        if ($this->sku != ""):
            $collection
                ->addFieldToFilter('sku', ['eq' => [$this->sku]])
                ->addUrlRewrite();
        endif;

        if ($this->storeIdFilter != null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;

        // https://magento.stackexchange.com/questions/157193/how-to-get-only-out-of-stock-products-in-magento-2?noredirect=1&lq=1
        // https://magento.stackexchange.com/questions/122972/magento2-product-collection-filter-out-of-stock-products/130070
        $collection->setFlag('has_stock_status_filter', false);
        
        // 4.5.0.1
        if ($this->productStatus !== null):
            $collection->addFieldToFilter(
                'status',
                [
                    'eq' => [$this->productStatus]
                ]
            );
        endif;
        
        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();

        $content = $this->getProductCsvGenerateMlnList($collection);
        
        return $this->getProductCsvPrepareDownloadResponse("product-export.csv", $content);
    }

    /**
     * Generates CSV file with product's list according to the collection in the $this->_list
     * @return array
     */
    private function getProductCsvGenerateMlnList($items)
    {
        if ($items->getSize() > 0) {
            $name = date('m_d_Y_H_i_s');
            $file = $name . '.csv';

            $stream = $this->directory->openFile($file, 'w+');
            $stream->writeCsv($this->getProductCsvGetCsvHeaders());
            $stream->lock();

            foreach ($items as $product) {
                //var_dump("Hello========Mage::getBaseUrl())=============". $product->getData() );
                //break;
                $arr = [];
                $arr[] = $product->getSku();
                $arr[] = $product->getName();
                $arr[] = $product["short_description"];
                $arr[] = $product["description"];

                $arr[] = $product["price"];
                $arr[] = $product["weight"];
                $arr[] = $product["qty"];
                $arr[] = $product["upc"];

                $stream->writeCsv($arr);
                unset($arr);
            }

            $stream->unlock();
            $stream->close();

            return [
                'type' => 'filename',
                'value' => $file,
                'rm' => true // can delete file after use
            ];
        }
    }

    /**
     * Returns indexes of the fetched array as headers for CSV
     * @param array $products
     * @return array
     */
    protected function getProductCsvGetCsvHeaders()
    {
        $headers = [
            "Productid",
            "ProductName",
            "ShortDescription",
            "LongDescription",
            "SitePrice",
            "Weight",
            "Qty",
            "UPC",
            "Galleryimageurl",
            "SupplimentalImageURL1",
            "SupplimentalImageURL2",
            "SupplimentalImageURL3",
            "SupplimentalImageURL4",
            "SupplimentalImageURL5",
            "SupplimentalImageURL6",
            "SupplimentalImageURL7",
            "SupplimentalImageURL8",
            "SupplimentalImageURL9",
            "SupplimentalImageURL10",
            "SupplimentalImageURL11",
            "SupplimentalImageURL12",
            "SupplimentalImageURL13",
            "SupplimentalImageURL14"
        ];
        return $headers;
    }

    protected function getProductCsvPrepareDownloadResponse(
        $fileName,
        $content,
        $contentType = 'application/octet-stream',
        $contentLength = null
    ) {

        $isFile = false;
        $file = null;

        if (is_array($content)):
            if (!isset($content['type']) || !isset($content['value'])) {
                return null;
            }

            if ($content['type'] == 'filename') {
                $isFile = true;
                $file = $content['value'];
                /** $contentLength = filesize(BP . "/var/" . $file); **/
            }
        endif;

        //var_dump(BP. "/var/" . $file);exit;

        if ($content !== null && $isFile):
            /*
            header('HTTP/1.1 200 OK');
            header("Pragma: public");
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header("Content-type:" . $contentType);
            header("Content-Length: " . $contentLength);
            header("Last-Modified: " . date('r'));
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            **/

            //$csvFile = $this->_directory->readFile(BP. "/var/" . $file);
            //readfile(BP . "/var/" . $file);
            return $this->fileFactory->create($file, $content, DirectoryList::VAR_DIR);
        endif;
    }
}
