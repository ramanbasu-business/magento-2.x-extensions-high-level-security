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

namespace Company\Plugin\Controller\Import;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Company\Plugin\Lib\ImportAction;
use Company\Plugin\Helper\Data;

class JobStackTrace extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    protected $database;
    protected $helper;
    protected $encoder;
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Lib\Encoder $encoder
    ) {
        $this->helper = $helper;
        $this->database = $database;
        $this->encoder = $encoder;
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/html');

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
        
        // Print Source XML per xml posting based on Job ID
        $id = $this->getRequest()->getParam('id');
        if ($id == 0):
            $resultResponse->setContents("");
        else:
            $xml_stacktrace = $this->database->getJobStackTrace($id);
            $resultResponse->setContents($xml_stacktrace);
        endif;

        return $resultResponse;
    }
}
