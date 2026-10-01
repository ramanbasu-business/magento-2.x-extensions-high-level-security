<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Controller\Go;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $resultRedirectFactory;
    protected $productFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
    ) {
        $this->rawResultFactory = $context->getResultFactory();
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->productFactory = $productFactory;

        return parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/html');

        $postedData = $this->getRequest()->getParams();
        if (!isset($postedData['sku']) || $postedData['sku'] == "") {
            $resultResponse->setContents("");
            return $resultResponse;
        }

        $sku = $postedData['sku'];

        /** Retrieve Product ID**/
        $product = $this->productFactory->create();
        $productId = $product->getIdBySku($sku);
        
        if ($productId <= 0):
            $resultResponse->setContents("invalid product sku");
            return $resultResponse;
        endif;

        $product->load($productId);
        $purl = $product->getUrlModel()->getUrl($product);
        
        if ($purl != "") {
            /** header('Location: ' . $purl, true, 302);
            * die();
            **/
            $resultRedirect->setUrl($purl);
            return $resultRedirect;
        }
        /** End **/

        /**
        $productId = $this->getIdBySku($sku);
        if ($productId <= 0):
            $resultResponse->setContents("invalid product sku");
            return $resultResponse;
        endif;
        $product = $this->productRepository->getByID($productId);
        $purl = $product->getUrlModel()->getUrl($product);
        if ($purl != "") {
            $resultRedirect->setUrl($purl);
            return $resultRedirect;
        }
        **/
    }

    /**
    private function getIdBySku($sku)
    {
        // Works too
        //$product = $this->_productRepositoryInterface->get($sku);
        $resourceFactory = $this->_objectManager->get("\Magento\Catalog\Model\ResourceModel\Product");

        $entityTable = $resourceFactory->getEntityTable();
        $binds = ['sku' => $sku];
        $pid = $resourceFactory
                ->getConnection()
                ->fetchOne(
                    "SELECT entity_id FROM " . $entityTable . " WHERE sku=:sku",
                    $binds
                );
        if (!empty($pid)) {
            return (int) ($pid);
        }
        return 0;
    } **/
}
