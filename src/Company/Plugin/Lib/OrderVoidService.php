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
 * @author Company Plugin
 */
class OrderVoidService extends \Company\Plugin\Lib\CommonService
{
    protected $orderFactory;
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
    private $comment_text = '';
    private $comment_customer_notify = 0;
    private $send_email = false;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceFactory,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->invoiceFactory = $invoiceFactory;

        $this->orderManagement = $orderManagement;
        $this->orderCommentSender = $orderCommentSender;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;

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

        if (isset($refundOrderXml->comment_customer_notify)) {
            if ((int) $refundOrderXml->comment_customer_notify == 1) {
                $this->comment_customer_notify = 1;
            } else {
                $this->comment_customer_notify = 0;
            }
        }

        if (isset($refundOrderXml->send_email)) {
            if ((int) $refundOrderXml->send_email == 1) {
                $this->send_email = true;
            } else {
                $this->send_email = false;
            }
        }

        $this->comment_text = (string) $refundOrderXml->comment_text;

        $this->order = $this->findOrderByIncrementId($this->order_increment_id);

        return true;
    }

    public function voidOrder($refundOrderXml)
    {
        $this->init($refundOrderXml);
        $this->setDebugMessage("Voiding Order: " . $this->order_increment_id);

        if ($this->order == null):
            $this->setDebugMessage($this->order_increment_id . ' Invalid order id.');
            $this->addToErrorLog($this->order_increment_id, "Invalid order id: " . $this->order_increment_id);
            return;
        endif;

        if (!$this->order->canVoidPayment()):
            $this->setDebugMessage($this->order_increment_id . ' Cannot void the order.');
            $this->addToErrorLog($this->order_increment_id, ' Cannot void the order.');
            return;
        endif;

        $payment = $this->order->getPayment();

        try {
            $payment->void(new \Magento\Framework\DataObject());

            if (!empty($this->comment_text)):
                $this->order->addStatusHistoryComment($this->comment_text);
            endif;

            $this->order->setIsCustomerNotified($this->comment_customer_notify);

            $this->order->save();
            $this->order = $this->getOrderByID($this->order_increment_id); // reload order

            if ($this->send_email == 1):
                $this->setDebugMessage('send_email=1, orderCommentSender is sending message');
                $this->orderCommentSender->send($this->order, true, $this->comment_text);
            endif;

            if (!$this->order->canVoidPayment()):
                $this->setDebugMessage($this->order_increment_id . ' the order is now voided.');
                $this->xmlLog
                    ->log(
                        $this->order_increment_id,
                        "success",
                        "Order " . $this->order_increment_id . " has been voided"
                    );
            else:
                $this->setDebugMessage($this->order_increment_id . ' could not void the order.');
                $this->addToErrorLog($this->order_increment_id, ' could not void the order.');
            endif;
        } catch (\Exception $ex) {
            $this->addToErrorLog($this->order_increment_id, $ex->getMessage());
        }
    }

    private function findOrderByIncrementId($incrementId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        /** var_dump($order->getIncrementId()); **/
        if ($order && $order->getIncrementId() != null && $order->getIncrementId() !== ""):
            return $order;
        endif;

        return null;
    }
}
