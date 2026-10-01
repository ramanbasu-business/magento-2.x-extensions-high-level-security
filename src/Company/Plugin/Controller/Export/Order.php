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
use Company\Plugin\Helper\Data;

class Order extends \Magento\Framework\App\Action\Action
{
    protected $pageFactory;
    protected $request;
    
    private $helper;
    private $encoder;
    private $orderExportService;
    private $rawResultFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Encoder $encoder,
        \Company\Plugin\Lib\OrderExportService $orderExportService
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $this->getRequest();
        
        $this->helper = $helper;
        $this->encoder = $encoder;
        $this->orderExportService = $orderExportService;
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
        
        $resultResponse->setContents($this->orderExportService->printOrders($qryStrArray));
        
        return $resultResponse;
    }
}
