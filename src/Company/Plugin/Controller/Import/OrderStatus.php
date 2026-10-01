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
use Magento\Framework\Controller\ResultFactory;

class OrderStatus extends \Magento\Framework\App\Action\Action
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
        
        if (isset($qryStrArray['verbose']) && $qryStrArray['verbose'] != ""):
            $qryStrArray["verbose"] = 1;
        else:
            $qryStrArray["verbose"] = 0;
        endif;

        $orderId = '';
        $statusId = '';

        if (isset($qryStrArray['orderid']) && $qryStrArray['orderid'] != "") {
            $orderId = (string) $qryStrArray['orderid'];
        }
        
        if (isset($qryStrArray['status']) && $qryStrArray['status'] != "") {
            $statusId = (string) $qryStrArray['status'];
        }
        if ($orderId == '' || $statusId == ''):
            $output = $this->database->printJobXmlError("invalid input parameters passed");
            $resultResponse->setContents($output);

            return $resultResponse;
        endif;

        // Build the xml here
        $local_file_source = "<?xml version=\"1.0\"?>\n";
        $local_file_source .= "<order module=\"order_field_update\">";
        $local_file_source .= "<order_increment_id>" . $orderId . "</order_increment_id>";

        $local_file_source .= "<field>";
        $local_file_source .= "<name>status</name>";
        $local_file_source .= "<value>" . $statusId . "</value>";
        $local_file_source .= "</field>";

        $local_file_source .= "</order>";

         $resultResponse->setContents(
             $this->processPostedData(0, $qryStrArray["verbose"], $local_file_source)
         );
        
        return $resultResponse;
    }

    private function processPostedData($queue, $verbose, $local_file_source)
    {
        $feed_xml = simplexml_load_string($local_file_source, "SimpleXMLElement", LIBXML_NOWARNING);
        
        if (empty($feed_xml)):
            return $this->database->printJobXmlError("XML content is invalid");
        endif;

        $module = $feed_xml['module'];
        $datetime = date('dmy-His');

        $log_file_source = "<?xml version=\"1.0\"?>\n";
        $log_file_source .= "<products></products>";

        $newId = $this->database->insertJob(
            date("Y-m-d H:i:s"),
            null,
            0,
            $module,
            $local_file_source,
            $log_file_source
        );
        
        return $this->importAction->start($newId, false, $queue, $verbose);
    }
}
