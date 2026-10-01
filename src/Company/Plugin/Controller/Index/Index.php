<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
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
