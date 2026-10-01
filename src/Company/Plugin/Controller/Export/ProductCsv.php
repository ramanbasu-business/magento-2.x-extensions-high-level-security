<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Controller\Export;

/**
 * Description of ProductCsv
 *
 * @author raman
 */
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\App\Filesystem\DirectoryList;

class ProductCsv extends \Magento\Framework\App\Action\Action
{
    private $sku = ""; // Incase FILTER is needed
    private $rowsPerPage = 100000;  // Default 10
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1
    protected $helper;
    private $encoder;
    private $storeIdFilter = null;
    protected $rawResultFactory;
    protected $pageFactory;
    protected $fileFactory;
    protected $request;
    protected $directory;
    protected $fileSystem;
    protected $productCollection;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->encoder = $encoder;
        $this->productCollection = $productCollection;
        $this->fileSystem = $fileSystem;
        $this->fileFactory = $fileFactory;
        $this->rawResultFactory = $context->getResultFactory();

        parent::__construct($context);
    }

    public function execute()
    {
        $this->directory = $this->fileSystem
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        //$this->helper->httpHeader($resultResponse);

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
                ->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                ->addAttributeToSelect('weight')
                ->addAttributeToSelect('upc')
                ->addAttributeToSelect('short_description')
                ->addAttributeToSelect('description')
                ->joinField(
                    'qty',
                    'cataloginventory_stock_item',
                    'qty',
                    'product_id=entity_id',
                    '{{table}}.stock_id=1',
                    'left'
                )->setOrder('sku', 'ASC');

        if ($this->sku != ""):
            $collection->addFieldToFilter('sku', ['eq' => [$this->sku]])
                    ->addUrlRewrite();
        endif;

        if ($this->storeIdFilter !== null):
            $collection->addStoreFilter($this->storeIdFilter);
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
    public function getProductCsvGenerateMlnList($items)
    {
        if ($items->getSize() > 0) {
            $name = date('m_d_Y_H_i_s');
            $file = $name . '.csv';
            
            $stream = $this->directory->openFile($file, 'w+');
            $stream->writeCsv($this->getProductcsvGetCsvHeaders());
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

                /*$mediaApi = Mage::getModel("catalog/product_attribute_media_api");
                $i_items = $mediaApi->items($product->getId());
                //var_dump( $i_items );
                foreach ($i_items as $item) {
                    $arr[] = $item['url'];
                }
                unset($mediaApi, $i_items);
                */
                
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
    protected function getProductcsvGetCsvHeaders()
    {
        $headers = ["Productid", "ProductName",
            "ShortDescription", "LongDescription",
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
                
        //var_dump($content);
        
        $isFile = false;
        $file = null;
        
        if (is_array($content)):
            if (!isset($content['type']) || !isset($content['value'])) {
                return null;
            }
            if ($content['type'] == 'filename') {
                $isFile = true;
                $file = $content['value'];
                //$contentLength = filesize(BP . "/var/" . $file);
            }
        endif;
        
        //var_dump(BP. "/var/" . $file);exit;
        
        if ($content !== null && $isFile):
            return $this->fileFactory->create($file, $content, DirectoryList::VAR_DIR);
        endif;
    }
}
