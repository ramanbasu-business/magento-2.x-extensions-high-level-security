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


namespace Company\Plugin\Controller\Index;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $pageFactory;
    protected $attributeHelper;
    protected $attribute2Helper;
    protected $database;
    protected $jobFactory;
    protected $productFactory;
    protected $directory;
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Lib\Database $database
    ) {
        $this->pageFactory = $pageFactory;
        $this->database = $database;
        $this->rawResultFactory = $context->getResultFactory();

        parent::__construct($context);
    }

    public function execute()
    {
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/html');
        $output = "";

        $output .= "Magento Plugin for Company\r\n<br />";
        $output .= "********** Done ***********\r\n<br />";
        $output .= "Php Version " . PHP_VERSION . "\r\n<br />";
        
        $request = $this->getRequest();
        $moduleName = $request->getModuleName();
        $controller = $request->getControllerName();
        $action     = $request->getActionName();
        $route      = $request->getRouteName();

        $resultResponse->setContents($output);
        return $resultResponse;
    }
}
