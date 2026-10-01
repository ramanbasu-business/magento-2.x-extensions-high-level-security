<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\App\Filesystem\DirectoryList;

class Csv extends \Magento\Framework\App\Action\Action
{
    private $rowsPerPage = 100000;  // Default 10
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1
    private $storeIdFilter = null;
    protected $helper;
    private $encoder;
    protected $pageFactory;
    protected $rawResultFactory;
    protected $fileFactory;
    protected $productCollection;
    protected $request;
    protected $directory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->encoder = $encoder;
        $this->productCollection = $productCollection;
        $this->fileFactory = $fileFactory;
        $this->rawResultFactory = $context->getResultFactory();

        parent::__construct($context);
        
        $this->directory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
    }
    
    public function execute()
    {
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $this->helper->httpHeader($resultResponse);

        /** Authentication phase starts **/
        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }
        /** Authentication phase ends **/

        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }

        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
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
        
        if ($this->storeIdFilter !== null):
            $collection->addStoreFilter($this->storeIdFilter);
        endif;
        
        $collection->setPageSize($this->rowsPerPage)->setCurPage($this->currentPageIndex)
                ->load();
        
        $content = $this->generateMlnList($collection);
        $this->prepareDownloadResponse("product-export-csv.csv", $content);
    }
    
    /**
     * Generates CSV file with product's list according to the collection in the $this->_list
     * @return array
     */
    public function generateMlnList($items)
    {
        
        if ($items->getSize() > 0) {
            $name = date('m_d_Y_H_i_s');
            $file = $name . '.csv';
            
            $stream = $this->directory->openFile($file, 'w+');
            $stream->writeCsv($this->getCsvHeaders());
            $stream->lock();
        
            foreach ($items as $product) {
                //var_dump("Hello========Mage::getBaseUrl())=============". $product->getData() );
                //break;
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
}
