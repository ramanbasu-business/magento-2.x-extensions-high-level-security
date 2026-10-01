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

class XmlLog
{

    protected $database;
    protected $helper;
    private $log_file_source = null;
    private $verbose = 0;
    private $jobId = 0;

    public function __construct(\Company\Plugin\Lib\Database $database)
    {
        $this->database = $database;
    }

    public function setParameters($p_job_id, $p_verbose = 0)
    {
        $this->verbose = $p_verbose;
        $this->jobId = $p_job_id;
    }

    public function loadXmlIntoVariable()
    {
        $this->log_file_source = $this->database->getJobLogFileSource($this->jobId);
        //var_dump($this->log_file_source);
    }

    private function get($sku)
    {
        $products = simplexml_load_string($this->log_file_source);

        if (!$products) {
            return false; //version 10.7
        }

        $p = $products->xpath('/products/product[sku="' . $sku . '"]');
        if ($p) {
            return true;
        }
        return false;
    }

    public function append($p_sku, $p_status, $p_error)
    {
        if ($this->get($p_sku)) {
            return false;
        }
        //return true;
        //$products = new SimpleXMLElement($this->_filename, null, true);
        $products = simplexml_load_string($this->log_file_source, null, LIBXML_NOCDATA);

        $p = $products->addChild('product');
        $p->addChild('sku', $p_sku);
        $p->addChild('status', $p_status);

        $errors = $p->addChild('errors');
        if ($p_error != "") {
            /* $p_error = cleanString($p_error);
              $p_error = preg_replace("/[<]/u", " ", $p_error);
              $p_error = preg_replace("/[>]/u", " ", $p_error);
              $error = $errors->addChild("error", $p_error);
             */
            $errors->error = null;
            $this->addCData($errors->error, $p_error);
        }
        //echo $products->asXML($this->_filename);
        return true;
    }

    public function appendError($p_sku, $p_error)
    {
        if ($this->get($p_sku) == false) {
            return false;
        }

        $dom = new \DOMDocument();
        $dom->validateOnParse = true;

        $this->loadXmlIntoVariable();
        $dom->loadXML($this->log_file_source);
        //var_dump($dom);

        $elements = $dom->getElementsByTagName('product');
        foreach ($elements as $node) {
            foreach ($node->childNodes as $child) {
                //$data[] = array($child->nodeName => $child->nodeValue);
                if ($child->nodeName == 'sku' && $child->nodeValue == $p_sku) {
                    //echo $child->nodeValue;
                    $p = $node;
                    break;
                }
            }
        }

        /*
          $xpath = new DOMXPath($dom);
          $ps = $xpath->query('//product[sku="'. $p_sku .'"]');

          foreach ($ps as $p) {
          echo ($p->getElementsByTagName('sku')->item(0));
          echo $p->find('sku',0);
          echo $p->childNodes[0];
          }
          return;
         */

        foreach ($p->getElementsByTagName('errors') as $errors) {
            if ($p_error != "") {
                $cdata = $dom->createCDATASection($p_error);
                /** $errors->getElementsByTagName('error')->item(0)->appendChild($cdata); **/
                $error = $dom->createElement("error");
                $errors->appendChild($error)->appendChild($cdata);
            }
        }
        //var_dump( $p[0]->sku );
        //return;
        //$dom->save( $this->_filename );
        $this->database->updateJobLogFileSource($this->jobId, $dom->saveXML());
        return true;
    }

    public function appendError1($p_sku, $p_error)
    {
        if ($this->get($p_sku) == false) {
            return false;
        }
        //return true;
        //$products = simplexml_load_file($this->_filename);
        //$products = new SimpleXMLElement($this->_filename, null, true);
        $products = simplexml_load_file($this->_filename, 'SimpleXMLElement', LIBXML_NOCDATA);
        //$products = (SimpleXMLExtended)$products;
        $p = $products->xpath('/products/product[sku="' . $p_sku . '"]');

        //var_dump( $p[0]->sku );
        if ($p_error != "") {
            /*
              $p_error = cleanString($p_error);
              $p_error = preg_replace("/[<]/u", " ", $p_error);
              $p_error = preg_replace("/[>]/u", " ", $p_error);
              $error = $p[0]->errors->addChild("error", $p_error);
              //$error = $p[0]->errors->addChild("error", "<![CDATA[".$p_error. "]]");
             */
            /*
              $errors = $p[0]->errors;
              var_dump($errors);
              $newError = $errors->addChild("error");
              //$newError = NULL;
              $this->addCData($newError, $p_error );
             */
            $errors = $p[0]->errors;
            $errors->error = null;
            $errors->error->addCData($p_error);
        }
        $products->asXML($this->_filename);
        return true;
    }

    public function log($p_sku, $p_status, $p_error = "", $order_status = "")
    {
        $this->loadXmlIntoVariable();

        if ($this->get($p_sku)) {
            return $this->appendError($p_sku, $p_error);
        }
        
        $dom = new \DOMDocument();
        $dom->validateOnParse = true;

        //var_dump($this->log_file_source);
        $dom->loadXML($this->log_file_source);

        $p = $dom->createElement("product");
        $sku = $dom->createElement('sku', $p_sku);
        $p->appendChild($sku);

        $status = $dom->createElement('status', $p_status);
        $p->appendChild($status);

        if ($order_status != null && $order_status != ""):
            $order_status_node = $dom->createElement('order_status', $order_status);
            $p->appendChild($order_status_node);
        endif;

        $errors = $dom->createElement('errors');

        if ($p_error != "") {
            $cdata = $dom->createCDATASection($p_error);
            $error = $dom->createElement("error");
            $errors->appendChild($error)->appendChild($cdata);
        }
        $p->appendChild($errors);

        // v 18.3
        if ($dom->documentElement != null) {
            $dom->documentElement->appendChild($p);
        } else {
            $dom->appendChild($p);
        }

        //$dom->save( $this->_filename );
        $this->database->updateJobLogFileSource($this->jobId, $dom->saveXML());
        return true;
    }

    public function log2($p_sku, $p_status, $p_error)
    {
        if ($this->get($p_sku)) {
            return $this->appendError($p_sku, $p_error);
        }

        //return true;
        $products = simplexml_load_file($this->_filename, null, LIBXML_NOCDATA);

        $this->loadXmlIntoVariable();

        //$product = new SimpleXMLElement('<product></product>');
        $p = $products->addChild('product');
        $p->addChild('sku', $p_sku);
        $p->addChild('status', $p_status);

        $errors = $p->addChild('errors');
        if ($p_error != "") {
            //$error = $errors->addChild("error", $p_error);
            $errors->error = null;
            $this->addCData($errors->error, $p_error);
        }

        /** $products->asXML( $this->_filename ); **/
        $this->database->updateJobLogFileSource($this->jobId, $products->asXML());
        return true;
    }

    public function log1($p_sku, $p_status, $p_error)
    {
        if ($this->get($p_sku)) {
            return $this->appendError($p_sku, $p_error);
        }
        //return true;
        //$products = new SimpleXMLElement( $this->_filename, null, true );
        $this->loadXmlIntoVariable();

        $p = $products->addChild('product');
        $p->addChild('sku', $p_sku);
        $p->addChild('status', $p_status);

        $errors = $p->addChild('errors');
        if ($p_error != "") {
            $errors->error = null;
            $this->addCData($errors->error, $p_error);
        }
        
        /** $products->asXML( $this->_filename ); **/
        $this->database->updateJobLogFileSource($this->jobId, $products->asXML());
        return true;
    }

    public function addCData($e, $cdata_text)
    {
        $node = dom_import_simplexml($e);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }
}
