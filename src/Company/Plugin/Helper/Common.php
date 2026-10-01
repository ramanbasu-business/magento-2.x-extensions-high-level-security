<?php


/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Helper;

/**
 * Description of Common
 *
 * @author Company Plugin
 */
class Common extends \Magento\Framework\App\Helper\AbstractHelper
{
    
    protected $objectManager = null;
    protected $verbose = 0;
    protected $xmLlog;
    protected $jobId;
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $pobjectManager
    ) {
        parent::__construct($context);
        
        $this->objectManager = $pobjectManager;
        $this->xmLlog = $this->objectManager->get("Company\Plugin\Helper\XmlLog");
    }
    
    public function setParameters($jobId, $verbose = 0)
    {
        $this->verbose = $verbose;
        $this->jobId = $jobId;
        
        if ($jobId != null):
            $this->xmLlog->setParameters($this->jobId, $this->verbose);
        endif;
    }
    
    /**
    public function printToScreen($s)
    {
        if ($this->verbose == 1) {
            echo '<li style="color:blue;">' . $s . '</li>';
        }
    }

    protected function printError($s)
    {
        if ($this->verbose == 1) {
            echo '<li style="color:red;">' . $s . '</li>';
        }
        //$this->LogError($s);
    }

    protected function addToErrorLog($order_increment_id, $msg)
    {
        $this->printToScreen($msg);
        $this->xmLlog->log($order_increment_id, "error", $msg);
    }**/
}
