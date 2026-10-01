<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Controller\Import;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Company\Plugin\Lib\ImportAction;
use Company\Plugin\Helper\Data;
use Magento\Framework\Controller\ResultFactory;

class Cron extends \Magento\Framework\App\Action\Action
{
    private $rawResultFactory;
    private $database;
    private $helper;
    private $encoder;
    private $importAction;
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Lib\Encoder $encoder,
        \Company\Plugin\Lib\ImportAction $importAction
    ) {
        $this->helper = $helper;
        $this->database = $database;
        $this->encoder = $encoder;
        $this->importAction = $importAction;
        $this->rawResultFactory = $context->getResultFactory();

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->helper->preExecute();

        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/xml');
        $output = "";

        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }

        if (isset($qryStrArray['verbose']) && $qryStrArray['verbose'] != ""):
            $qryStrArray["verbose"] = 1;
        else:
            $qryStrArray["verbose"] = 0;
        endif;

        $jobId = 0;
        if (isset($qryStrArray["id"]) && $qryStrArray['id'] != "") {
            $jobId = $qryStrArray['id'];
        }

        if ($jobId > 0):
            $job_row = $this->database->getJob($jobId);
        else:
            $job_row = $this->database->getOneSubmittedJob("");
        endif;

        $output = "";
        if ($job_row && count($job_row) > 0):
            $output = $this->importAction->start($jobId, false, 0, $qryStrArray["verbose"]);
        else:
            $output = $this->database->printJobXmlError("No submitted job to process");
        endif;

        $resultResponse->setContents($output);
        return $resultResponse;
    }
}
