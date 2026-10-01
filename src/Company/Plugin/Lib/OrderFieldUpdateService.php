<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Lib;

/**
 * Description of OrderFieldUpdateService
 *
 * @author Company Plugin
 */
class OrderFieldUpdateService extends \Company\Plugin\Lib\CommonService
{
    protected $orderFactory;
    private $order_increment_id = '';
    protected $invoiceFactory;
    protected $orderManagement;
    protected $orderCommentSender;

    protected $helper;

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

    public function process($xmlObject)
    {
        $this->order_increment_id = trim((string) $xmlObject->order_increment_id);

        $order = $this->findOrderByIncrementId($this->order_increment_id);

        if ($order == null || empty($order['increment_id'])):
            $this->addToErrorLog($this->order_increment_id, "Missing order #" . $this->order_increment_id);
            return;
        endif;

        try {
            foreach ($xmlObject->field as $fieldXML):
                $fieldName = (string) $fieldXML->name;
                $fieldValue = (string) $fieldXML->value;

                $this->setDebugMessage("order_field_update: name:" . $fieldName . " value: " . $fieldValue);

                switch ($fieldName) {
                    case 'order_note':
                        $this->updateOrderNote($order, $fieldValue);
                        break;
                    case "status":
                        $this->updateOrderStatus($order, $fieldValue);
                        break;
                    default:
                        $this->addToErrorLog(
                            $this->order_increment_id,
                            $fieldName . " Provided field is not implemented"
                        );
                        break;
                }
            endforeach;
        } catch (\Exception $ex) {
            $this->addToErrorLog($this->order_increment_id, $ex->getMessage());
        }
    }

    private function updateOrderNote($order, $comment)
    {
        $order->addStatusHistoryComment($comment, false)
                ->setIsCustomerNotified(false)
                ->save();

        $this->xmlLog->log(
            $this->order_increment_id,
            "success",
            "Note has been added to order history",
            $order->getStatus()
        );

        return true;
    }

    private function updateOrderStatus($order, $newStatusId)
    {
        $jobStatus = "error";
        $message = "";

        $this->setDebugMessage("updateOrderStatus[] called. Order Increment Id: " . $order->getIncrementId());

        try {
            switch ($newStatusId):
                case "hold":
                    if (!$order->canHold()):
                        $jobStatus = "error";
                        $message = "Order status cannot be set to hold";
                    else:
                        if ($this->orderManagement->hold($order->getId())): // $order->getEntityId()
                            $jobStatus = "success";
                            $message = "Order status has been set to hold";
                        endif;
                    endif;
                    break;

                case "unhold":
                    if (!$order->canUnhold()):
                        $jobStatus = "error";
                        $message = "Order status cannot be set to unhold";
                    else:
                        if ($this->orderManagement->unhold($order->getId())): // $order->getEntityId()
                            $jobStatus = "success";
                            $message = "Order status has been set to unhold";
                        endif;
                    endif;

                    break;
            endswitch;
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        if ($jobStatus == "success"):
            $order->addStatusHistoryComment($message, false)
                    ->setIsCustomerNotified(false)
                    ->save();
        endif;

        $this->setDebugMessage("Order Increment Id: ");
        $this->setDebugMessage($order->getIncrementId());

        $this->xmlLog->log(
            $order->getIncrementId(),
            $jobStatus,
            $message,
            $order->getStatus()
        );

        return true;
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
