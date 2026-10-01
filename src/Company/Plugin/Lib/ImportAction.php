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

namespace Company\Plugin\Lib;

class ImportAction
{
    protected $debugMessage;
    protected $jobFactory;
    private $database;
    private $helper;
    
    private $recordCount = 0;
    /**protected $objectManager = null; **/
    
    protected $customerImportService;
    protected $orderFieldUpdateService;
    protected $orderPaymentCaptureService;
    protected $orderVoidService;
    protected $orderCancelService;
    protected $refundService;
    protected $shippingService;
    protected $productService;

    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        \Company\Plugin\Model\JobFactory $jobFactory,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Lib\CustomerImportService $customerImportService,
        \Company\Plugin\Lib\OrderFieldUpdateService $orderFieldUpdateService,
        \Company\Plugin\Lib\OrderPaymentCaptureService $orderPaymentCaptureService,
        \Company\Plugin\Lib\OrderVoidService $orderVoidService,
        \Company\Plugin\Lib\OrderCancelService $orderCancelService,
        \Company\Plugin\Lib\RefundService $refundService,
        \Company\Plugin\Lib\ShippingService $shippingService,
        \Company\Plugin\Lib\ProductService $productService
    ) {
        /** $this->objectManager = $objectManager; **/
        $this->jobFactory = $jobFactory;
        $this->database = $database;
        $this->helper = $helper;

        $this->customerImportService = $customerImportService;
        $this->orderFieldUpdateService = $orderFieldUpdateService;
        $this->orderPaymentCaptureService = $orderPaymentCaptureService;
        $this->orderVoidService = $orderVoidService;
        $this->orderCancelService = $orderCancelService;
        $this->refundService = $refundService;
        $this->shippingService = $shippingService;
        $this->productService = $productService;
        $this->debugMessage = "";
    }

    public function start($jobId = 0, $cron = false, $queue = 0, $verbose = 0)
    {
        $extra_exception = "";

        try {
            if ($jobId > 0) {
                $job_row = $this->database->getJob($jobId);
            } else {
                $job_row = $this->database->getOneSubmittedJob('');
            }

            if (!$job_row || ($job_row && count($job_row) == 0)):
                if ($cron === true):
                    $this->debugMessage .= "No job in queue";
                endif;
            endif;

            if ($job_row && count($job_row) > 0):
                $jobId = $job_row[0]['id'];
                $feed_xml = simplexml_load_string($job_row[0]["local_file_source"]);
                
                if ($verbose == 1):
                    $this->debugMessage .= "job row found. xml retrieval status ";
                    $this->debugMessage .= ($feed_xml == null ) ? "empty<br>" : "Not empty<br>";
                endif;
                
                if (empty($feed_xml)):
                    $this->database->updateJobErrored($jobId);
                    $errorMessage = "XML content could not be loaded into simple xml object. "
                            . "JOB# " . $jobId . " can not load local_file_source. Job status:error, rejected";

                    if ($cron === false && $verbose === 0) {
                        return $this->database->printJobXmlError($errorMessage);
                    } else {
                        $this->debugMessage .= $errorMessage;
                    }
                    
                    return;
                endif;

                $module = $job_row[0]['module'];
                if ($module == null) {
                    $module = "";
                }
                
                // update local recordcount variable
                $this->getRecordCount($module, $feed_xml);
                if ($cron === true || $verbose == 1):
                    $this->debugMessage .= "Job# " . $jobId . " record count: " . $this->recordCount . "\n";
                endif;

                $this->processJobBasedOnModule($module, $jobId, $feed_xml, $queue, $verbose, $cron);
            endif;
        } catch (Exception $ex) {
            $extra_exception .= "Import Action Final Exception: Job# "
                    . $jobId . " " . $ex->getMessage() . " " . $ex->getTraceAsString();
        }

        $job_row = $this->database->getJob($jobId);

        $returnMessageXml = $this->database->printXml($job_row, $extra_exception);
        unset($job_row);

        $returnMessage = "";

        if ($cron === true || $verbose == 1):
            $returnMessage = $this->debugMessage . " " . $extra_exception . " " . $returnMessageXml;
        else:
            $returnMessage = $returnMessageXml;
        endif;

        return $returnMessage;
    }

    private function processJobBasedOnModule($module, $jobId, $feed_xml, $queue, $verbose, $cron)
    {
        try {
            // Actual rocessing starts here........................
            
            switch ($module):
                case "":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);
                        
                        $this->productService->setParameters($jobId, $verbose);
                        
                        $this->productService->importSimple($feed_xml);
                        $this->productService->importConfigurable($feed_xml);
                        $this->productService->importBundle($feed_xml);
                        $this->productService->importRelatedProducts($feed_xml);
                        $this->productService->importImages($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);

                        $this->debugMessage .= $this->productService->getDebugMessage();
                    endif;
                    break;
                
                case "images":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o = $this->objectManager->create("Company\Plugin\Lib\ProductService");
                        $this->productService->setParameters($jobId, $verbose);
                        
                        $this->productService->importImages($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->productService->getDebugMessage();
                    endif;

                    break;
                
                case "description":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);
                        $this->productService->setParameters($jobId, $verbose);
                        
                        $this->productService->importDescriptions($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->productService->getDebugMessage();
                    endif;

                    break;

                case "delete":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o = $this->objectManager->create("Company\Plugin\Lib\ProductService");
                        $this->productService->setParameters($jobId, $verbose);
                        
                        $this->productService->deleteProducts($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->productService->getDebugMessage();
                    endif;

                    break;
                
                case "invprice":
                    if ($cron || ($this->recordCount <= 500 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o = $this->objectManager->create("Company\Plugin\Lib\ProductService");
                        $this->productService->setParameters($jobId, $verbose);
                        
                        $this->productService->importMini($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->productService->getDebugMessage();
                    endif;

                    break;
                    
                case "shipping":
                    if ($cron || ($this->recordCount <= 500 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);
                        
                        //$o = $this->objectManager->create("Company\Plugin\Lib\ShippingService");
                        $this->shippingService->setParameters($jobId, $verbose);
                        $this->shippingService->importShipping($feed_xml);

                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->shippingService->getDebugMessage();
                    endif;
                    break;
                    
                case "refund":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o_refund = $this->objectManager->create("Company\Plugin\Lib\RefundService");
                        $this->refundService->setParameters($jobId, $verbose);
                        $this->refundService->createRefund($feed_xml);
                                                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->refundService->getDebugMessage();
                    endif;
                    break;
                    
                case "order_cancel":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o_opu = $this->objectManager->create("Company\Plugin\Lib\OrderCancelService");
                        $this->orderCancelService->setParameters($jobId, $verbose);
                        $this->orderCancelService->cancelOrder($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->orderCancelService->getDebugMessage();
                    endif;
                    break;
                
                case "order_void":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o_opu = $this->objectManager->create("Company\Plugin\Lib\OrderVoidService");
                        $this->orderVoidService->setParameters($jobId, $verbose);
                        $this->orderVoidService->voidOrder($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->orderVoidService->getDebugMessage();
                    endif;
                    break;
                    
                case "order_payment_capture":
                    if ($cron || ($this->recordCount <= 100 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o_opu = $this->objectManager
                        //->create("Company\Plugin\Lib\OrderPaymentCaptureService");
                        $this->orderPaymentCaptureService->setParameters($jobId, $verbose);
                        $this->orderPaymentCaptureService->capture($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->orderPaymentCaptureService->getDebugMessage();
                    endif;
                    break;
                    
                case "order_field_update":
                    if ($cron || ($this->recordCount <= 300 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);
                        
                        //$o_opu = $this->objectManager->create("Company\Plugin\Lib\OrderFieldUpdateService");
                        
                        $this->orderFieldUpdateService->setParameters($jobId, $verbose);
                        $this->orderFieldUpdateService->process($feed_xml);
                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->orderFieldUpdateService->getDebugMessage();
                    endif;
                    break;

                case "customer":
                    if ($cron || ($this->recordCount <= 500 && $queue == 0)):
                        $this->database->updateJobSubmitted($jobId);

                        //$o_opu = $this->objectManager->create("Company\Plugin\Lib\CustomerImportService");
                        $this->customerImportService->setParameters($jobId, $verbose);
                        $this->customerImportService->importCustomers($feed_xml);
                                                        
                        $this->database->updateJobCompleted($jobId);
                        $this->debugMessage .= $this->customerImportService->getDebugMessage();
                    endif;
                    break;
            endswitch;
        } catch (Exception $ex) {
            $this->database->updateJobErrored($jobId);
            $extra_exception = "Import Action Inner exception: Job# "
                    . $jobId . " Job status:error, " . $ex->getMessage() . " " . $ex->getTraceAsString();
            throw new \Magento\Framework\Exception\LocalizedException($extra_exception);
        }
    }

    private function getRecordCount($module, $feed_xml)
    {
        $this->recordCount = 0;
        //$recordCount = count($feel_xml->children());
        if ($module == '' || $module == 'invprice' || $module == 'dod'):
            foreach ($feed_xml->xpath("/products/product") as $child) {
                $this->recordCount++;
            }
        elseif ($module == 'order_payment_update'):
            $this->recordCount = 1;
        elseif ($module == 'shipping'):
            foreach ($feed_xml->xpath("/orders/order") as $child) {
                $this->recordCount++;
            }
        elseif ($module == 'refund'):
            $this->recordCount = 1;
        elseif ($module == 'order_payment_capture'):
            $this->recordCount = 1;
        elseif ($module == 'order_field_update'):
            $this->recordCount = 1;
        elseif ($module == 'customer'):
            foreach ($feed_xml->xpath("/customers/customer") as $child) {
                $this->recordCount++;
            }
        endif;
    }
}
