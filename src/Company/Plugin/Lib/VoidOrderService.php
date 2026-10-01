<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Lib;

/**
 * Description of RefundService
 *
 * @author raman
 */
class VoidOrderService extends \Company\Plugin\Lib\CommonService
{
    protected $orderManagement;
    
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender
     */
    protected $orderCommentSender;
    protected $helper;
    
    private $creditmemoManagement;
    private $creditMemoFactory;
    private $invoiceFactory;

    /**
     * Quote convert object
     *
     * @var \Magento\Sales\Model\Convert\Order
     */
    
    private $order = null;
    private $order_increment_id = '';
    private $message = [];
    private $has_order_item = false;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceFactory,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database
    ) {
        $this->invoiceFactory = $invoiceFactory;
                        
        // 4.4.0.0
        $this->orderManagement = $orderManagement; // 4.4.0.0
        $this->orderCommentSender = $orderCommentSender;
        $this->helper = $helper;
        
        parent::__construct($objectManager, $xmlLog, $database);
    }

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
    public function setParameters($jobId, $verbose = 0)
    {
        parent::setParameters($jobId, $verbose);
    }

    private function init($refundOrderXml)
    {
        $this->order_increment_id = (string) $refundOrderXml->order_increment_id;
        $this->order = $this->getOrderByID($this->order_increment_id);

        return true;
    }

    public function void($refundOrderXml)
    {
        $this->init($refundOrderXml);
        $this->printToScreen("Voiding Order: " . $this->order_increment_id);

        if ($this->order == null):
            $this->printToScreen($this->order_increment_id . ' Cannot void the order.');
            $this->addToErrorLog($this->order_increment_id, "Invalid order id: " . $this->order_increment_id);
            return;
        endif;
        
        if (!$this->order->canVoidPayment()):
            $this->printToScreen($this->order_increment_id . ' Cannot void the order.');
            $this->addToErrorLog($this->order_increment_id, ' Cannot void the order.');
            return;
        endif;

        $payment = $this->order->getPayment();

        try {
            $payment->void(new \Magento\Framework\DataObject());
            $order->save();

            $this->xmlLog
                ->log(
                    $this->order_increment_id,
                    "success",
                    "Order " . $this->order_increment_id . " has been voided"
                );
        } catch (\Exception $ex) {
            $this->addToErrorLog($this->order_increment_id, $ex->getMessage());
        }
    }
}
