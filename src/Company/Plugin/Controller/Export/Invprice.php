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
use Magento\Catalog\Model\Resource\Category\Collection;
use Company\Plugin\Helper\Data;

class Invprice extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    private $sku = ""; // Incase FILTER is needed
    private $rowsPerPage = 10;  // Default 100
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1
    public $entity_id = "";
    private $storeIdFilter = null;

    protected $helper;
    protected $pageFactory;
    protected $request;
    private $encoder;
    private $stockRegistry;
    protected $productCollection;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->stockRegistry = $stockRegistry;
        $this->productCollection = $productCollection;
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
        
        $collection = $this->productCollection->create()
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                ->addAttributeToSelect('special_price')
                ->addAttributeToSelect('special_from_date')
                ->addAttributeToSelect('special_to_date')
                ->setOrder('sku', 'ASC');

        $collection->setFlag('has_stock_status_filter', false);

        if ($this->sku != ""):
            $collection->addFieldToFilter('sku', ['like' => [$this->sku . '%']])
                    ->addUrlRewrite();
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
            //$blockInstance = $this->pageFactory->getLayout()->getBlock('StockBlock');

            $output .= '<product>';
            $output .= '<sku><![CDATA[' . $product->getSku() . ']]></sku>';

            /* Get stock item  */
            $stockitem = $this->stockRegistry
                ->getStockItem($product->getId(), $product->getStore()->getWebsiteId());

            $output .= '<qty>' . $stockitem->getQty() . '</qty>';
            //$output .= '<qty>' . $stockitem->getQtyCorrection() . '</qty>';
            $output .= '<price>' . $product->getPrice() . '</price>';
            
            // Added to x.5.3.4
            $output .= '<special_price>' . $product->getSpecialPrice() . '</special_price>';
            $output .= '<special_from_date>' . $product->getSpecialFromDate() . '</special_from_date>';
            $output .= '<special_to_date>' . $product->getSpecialToDate() . '</special_to_date>';

            unset($stockitem);

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

            $output .= '</product>';
            /** $this->helper->sendToScreen($output); **/
        endforeach;

        $output .= '</products>';

        /** $this->helper->sendToScreen($output); **/
        $resultResponse->setContents($output);
        return $resultResponse;
    }
}
