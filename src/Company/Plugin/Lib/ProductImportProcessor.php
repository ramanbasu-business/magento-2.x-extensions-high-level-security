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
