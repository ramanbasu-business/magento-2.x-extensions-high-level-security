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

/**
 * Description of CommonService
 *
 * @author Raman Basu <support@company.com>
 */
namespace Company\Plugin\Lib;

class CommonService
{
    protected $objectManager = null;
    protected $verbose = 0;
    protected $xmlLog;
    protected $jobId;
    protected $database;
    protected $debugMessage;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database
    ) {
        $this->objectManager = $objectManager;
        $this->xmlLog = $xmlLog;
        $this->database = $database;
        $this->debugMessage = "";
    }

    public function getDebugMessage()
    {
        return $this->debugMessage;
    }

    protected function setDebugMessage($message)
    {
        $this->debugMessage .= "* " . $message . "\n\r<br />";

        if ($this->jobId != null):
            $this->database->updateJobStackTraceConcatenated($this->jobId, $message);
        endif;
    }
    
    public function setParameters($jobId, $verbose = 0)
    {
        $this->verbose = $verbose;
        $this->jobId = $jobId;
        
        if ($jobId != null):
            $this->xmlLog->setParameters($this->jobId, $this->verbose);
        endif;
    }
    
    /**
    public function printToScreen($s)
    {
        if ($this->verbose == 1) {
            echo '<li>' . $s . '</li>';
        }

        $this->database->updateJobStackTraceConcatenated($this->jobId, $s);
    }

    protected function printError($s)
    {
        if ($this->verbose == 1) {
            echo '<li>' . $s . '</li>';
        }
        $this->database->updateJobStackTraceConcatenated($this->jobId, $s);
    }
     **/

    protected function addToErrorLog($order_increment_id, $msg)
    {
        $this->setDebugMessage($msg);
        $this->xmlLog->log($order_increment_id, "error", $msg);
    }
}
