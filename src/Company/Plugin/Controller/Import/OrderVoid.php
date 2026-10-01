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

// Magento 2.3
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class OrderVoid extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    private $rawResultFactory;
    protected $driverFile;
    private $database;
    private $helper;
    private $encoder;
    private $importAction;
    
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Lib\Encoder $encoder,
        \Company\Plugin\Lib\ImportAction $importAction,
        \Magento\Framework\Filesystem\Driver\File $driverFile
    ) {
        $this->helper = $helper;
        $this->database = $database;
        $this->encoder = $encoder;
        $this->importAction = $importAction;
        $this->rawResultFactory = $context->getResultFactory();
        $this->driverFile = $driverFile;

        return parent::__construct($context);
    }
    
    public function execute()
    {
        $this->helper->preExecute();
        libxml_use_internal_errors(true);
        
        /** $xml = $this->getRequest()->getPostValue(); **/
        /** $xml = @file_get_contents('php://input'); **/
        $xml = $this->driverFile->fileGetContents('php://input');

        $resultResponse = $this->rawResultFactory->create(ResultFactory::TYPE_RAW);
        $resultResponse->setHeader('Content-Type', 'text/xml');
        
        /** Authentication phase starts **/
        $qryStrArray = $this->encoder->authenticate();

        if ($qryStrArray["is_authenticated"] == 0) {
            $resultResponse->setContents($qryStrArray["message"]);
            return $resultResponse;
        }
        /** Authentication phase ends **/

        if (empty($qryStrArray["queue"])):
            $qryStrArray["queue"] = 0;
        else:
            $qryStrArray["queue"] = 1;
        endif;
        
        if (isset($qryStrArray['verbose']) && $qryStrArray['verbose'] != ""):
            $qryStrArray["verbose"] = 1;
            $resultResponse->setHeader('Content-Type', 'text/html');
        else:
            $qryStrArray["verbose"] = 0;
            $resultResponse->setHeader('Content-Type', 'text/xml');
        endif;
        
        $resultResponse->setContents(
            $this->processPostedData($qryStrArray["queue"], $qryStrArray["verbose"], $xml)
        );
        
        return $resultResponse;
    }
    
    public function processPostedData($queue, $verbose, $local_file_source)
    {
        if (empty($local_file_source)):
            return $this->database->printJobXmlError("The posted XML content is empty");
        endif;

        $feed_xml = simplexml_load_string($local_file_source, "SimpleXMLElement", LIBXML_NOWARNING);
        if (empty($feed_xml)):
            return $this->database->printJobXmlError("XML content is invalid");
        endif;
        
        $module = 'order_void';
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
