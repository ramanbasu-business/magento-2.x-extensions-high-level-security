<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Lib;

/**
 * Description of OrderCancelService
 *
 * @author raman
 */
class OrderCancelService extends \Company\Plugin\Lib\CommonService
{
    protected $orderFactory;

    private $order_increment_id = "";
    protected $orderManagement;
    
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender
     */
    protected $orderCommentSender;
    protected $helper;
    
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->orderManagement = $orderManagement;
        $this->orderCommentSender = $orderCommentSender;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;

        parent::__construct($objectManager, $xmlLog, $database);
    }
    
    public function cancelOrder($xmlObject)
    {
        $this->order_increment_id = trim((string) $xmlObject->order_increment_id);
        $order = $this->getByID($this->order_increment_id);
        
        if ($order == null):
            $this->addToErrorLog($this->order_increment_id, "Missing order #" . $this->order_increment_id);
            return;
        endif;
        
        if (!$order->canCancel()):
            $this->addToErrorLog($this->order_increment_id, "Cannot cancel the order " . $this->order_increment_id);
            return;
        endif;
        
        try {
            /** if ($this->orderManagement->cancel($this->order_increment_id)) : **/
            if ($this->orderManagement->cancel($order->getId())):
                $this->orderCommentSender->send($order, true, "Order has been cancelled");
            
                $order
                    ->addStatusHistoryComment("Order has been cancelled")
                    ->setIsCustomerNotified(1);

                $this->xmlLog
                        ->log(
                            $this->order_increment_id,
                            "success",
                            "Order " . $this->order_increment_id . " has been cancelled"
                        );
            else:
                $this->addToErrorLog($this->order_increment_id, "Order could not be cancelled.");
            endif;
        } catch (\Exception $ex) {
            $this->addToErrorLog($this->order_increment_id, "[cancelOrder]" . $ex->getMessage());
        }
    }
    
    private function getByID($incrementId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        /** var_dump($order->getIncrementId()); **/
        if ($order && $order->getIncrementId() != null && $order->getIncrementId() !== ""):
            return $order;
        endif;

        return null;
    }
}
