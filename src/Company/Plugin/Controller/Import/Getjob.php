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

class Getjob extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    private $database;
    private $helper;
    private $encoder;
    
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
        $this->helper->preExecute();
        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/xml');

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
        
        $jobId = 0;
        if (isset($qryStrArray["id"]) && $qryStrArray['id'] != "") {
            $jobId = $qryStrArray['id'];
        }
        
        if ($jobId > 0) {
            $job_row = $this->database->getJob($jobId);
            
            if ($job_row && count($job_row) > 0):
                $resultResponse->setContents($this->database->printXml($job_row, ""));
                return $resultResponse;
            endif;

            unset($job_row);
        }
        
        $resultResponse->setContents($this->database->printJobXmlError("Error in getjob request"));
        return $resultResponse;
    }
}
