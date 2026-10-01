<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Lib;

class ProductImportProcessor
{
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;
    
    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory
     */
    protected $setColFactory;
    
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory
    ) {
        $this->productFactory = $productFactory;
        $this->setColFactory = $setColFactory;
    }
}
