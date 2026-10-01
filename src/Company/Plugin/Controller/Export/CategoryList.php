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

class CategoryList extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $pageFactory;
    protected $request;
    private $helper;
    private $encoder;
    protected $categoryCollection;
    protected $categoryFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();

        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->categoryCollection = $categoryCollection;
        $this->categoryFactory = $categoryFactory;
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();
        
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $this->helper->httpHeader($resultResponse);

        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }

        $parentId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;
        /**
        $parentCategory = $this->_objectManager
                ->create('\Magento\Catalog\Model\Category::c l a s s')
                ->load($parentId);
        **/
        $parentCategory = $this->categoryFactory->create()->load($parentId);

        //$subcategories = $parentCategory->getChildrenCategories();

        $output = '<?xml version="1.0" encoding="UTF-8" ?>';
        $output .= '<categories>';
        $output .= $this->helper->printModuleVersion();

        $this->categoryCollection->addNameToResult()->load();

        foreach ($this->categoryCollection as $data) {
            $output .= '<category>';
            
            $output .= '<entity_id>' . $data->getId() . '</entity_id>';
            $output .= '<name><![CDATA[' . $data->getName() . ']]></name>';
            $output .= '<attribute_set_id>' . $data->getAttribute_set_id() . '</attribute_set_id>';
            $output .= '<parent_id>' . $data->getParentId() . '</parent_id>';
            $output .= '<children_count>' . $data->getChildrenCount() . '</children_count>';

            $output .= '</category>';
        }

        $output .= '</categories>';
        
        $resultResponse->setContents($output);
        return $resultResponse;
    }
}
