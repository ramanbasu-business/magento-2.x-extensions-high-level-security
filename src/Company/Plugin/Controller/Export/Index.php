<?php

namespace Company\Plugin\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;
    protected $request;
    private $rawResultFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $moduleName = $this->getRequest()->getModuleName();
        $controller = $this->getRequest()->getControllerName();
        $action     = $this->getRequest()->getActionName();
        $route      = $this->getRequest()->getRouteName();

        $result = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/xml');
        
        $result->setContents('<root>Welcome To Company plugin for Magento 2.x</root>');

        return $result;
    }
}
