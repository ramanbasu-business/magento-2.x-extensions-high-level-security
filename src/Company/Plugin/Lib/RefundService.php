<?php

/*
 * Company Plugin

  NOTICE OF LICENSE

  This source file is subject to the Open Software License (OSL 3.0)

  DISCLAIMER

  Do not edit or add to this file if you wish to upgrade to newer
  versions in the future. If you wish to customize this extension for your
  needs please refer to http://www.company.com for more information.

  @author Raman Basu <raman@company.com>
  @package     Plugin_Imports
  @copyright   Copyright (C) 2016 Company <support@company.com>
  @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Company\Plugin\Lib;

class RefundService extends \Company\Plugin\Lib\CommonService
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
    protected $convertor;
    private $order = null;
    private $order_increment_id = '';
    private $comment_text = '';
    private $comment_customer_notify = false;
    private $is_visible_on_front = false;
    private $send_email = false;
    private $shipping_amount = 0.0;
    private $adjustment_refund = 0.0;
    private $message = [];
    private $has_order_item = false;

    private $invoiceModel;

    /*
    * vendor\magento\module-sales\Controller\Adminhtml\Order\CreditmemoLoader.php
    * vendor\magento\magento2-base\dev\tests\integration\testsuite\Magento\Sales\_files\order_info.php
    * vendor\magento\magento2-base\dev\tests\integration\testsuite\Magento\Sales\_files\creditmemo_with_list.php
    * vendor\magento\module-sales\Model\Order\Payment.php
    * */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceFactory,
        \Magento\Sales\Model\Order\Invoice $invoiceModel,
        \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory,
        \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditMemoFactory,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Company\Plugin\Helper\Data $helper,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->convertor = $convertOrderFactory->create();
        $this->invoiceFactory = $invoiceFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->creditMemoFactory = $creditMemoFactory;
        
        // 4.4.0.0
        $this->orderManagement = $orderManagement; // 4.4.0.0
        $this->orderCommentSender = $orderCommentSender;
        $this->helper = $helper;
        
        $this->orderFactory = $orderFactory;
        $this->invoiceModel = $invoiceModel;

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
        $this->comment_text = (string) $refundOrderXml->comment_text;
        $this->shipping_amount = isset($refundOrderXml->shipping_amount)
                ? (float) $refundOrderXml->shipping_amount : 0.0;
        $this->adjustment_refund = isset($refundOrderXml->adjustment_refund)
                ? (float) $refundOrderXml->adjustment_refund : 0.0;

        if (isset($refundOrderXml->comment_customer_notify)) {
            if ((int) $refundOrderXml->comment_customer_notify == 1) {
                $this->comment_customer_notify = true;
            } else {
                $this->comment_customer_notify = false;
            }
        }

        if (isset($refundOrderXml->is_visible_on_front)) {
            if ((int) $refundOrderXml->is_visible_on_front == 1) {
                $this->is_visible_on_front = true;
            } else {
                $this->is_visible_on_front = false;
            }
        }

        if (isset($refundOrderXml->send_email)) {
            if ((int) $refundOrderXml->send_email == 1) {
                $this->send_email = true;
            } else {
                $this->send_email = false;
            }
        }

        if (isset($refundOrderXml->orderitems->orderitem)):
            foreach ($refundOrderXml->orderitems->orderitem as $orderItemXml):
                $this->has_order_item = true;
            endforeach;
        endif;

        $this->order = $this->getByID($this->order_increment_id); //getByID

        return true;
    }

    private function validate($refundOrderXml)
    {
        // If there is atleast one item to refund OR adjustment_refund >0, proceed with
        // creating credit memo
        if (!($this->has_order_item == true || $this->adjustment_refund > 0 || $this->shipping_amount > 0)):
            $this->addToErrorLog(
                $this->order_increment_id,
                "Either order items or shipping amount or adjust refund is "
                    . "expected. Order #" . $this->order_increment_id
            );
            return false;
        endif;

        if (!$this->order->canCreditmemo()):
            //$this->message['message'] = 'Cannot create credit memo';
            if (!$this->order->isPaymentReview()) {
                //$this->message['message'] = 'Cannot create credit memo. Payment is in review';
                $this->addToErrorLog($this->order_increment_id, 'Cannot create credit memo. Payment is in review');
                return false;
            }

            if (!$this->order->canUnhold()) {
                $this->addToErrorLog($this->order_increment_id, 'Cannot create credit memo. Order is on hold');
                return false;
            }

            if (abs($this->order->getTotalPaid() - $this->order->getTotalRefunded()) < .0001) {
                $this->addToErrorLog(
                    $this->order_increment_id,
                    'Cannot create credit memo. Amount paid is equal or less than amount refunded'
                );
                return false;
            }

            if ($this->order->getActionFlag('edit') === false) {
                $this->addToErrorLog(
                    $this->order_increment_id,
                    'Cannot create credit memo. Action Flag of Edit not set'
                );
                return false;
            }

            if ($this->order->hasForcedCanCreditmemo()) {
                $this->addToErrorLog(
                    $this->order_increment_id,
                    'Cannot create credit memo. Credit Memo has been forced set'
                );
                return false;
            }
        endif;

        return true;
    }

    public function createRefund($refundOrderXml)
    {
        $this->init($refundOrderXml);
        $this->setDebugMessage("Creating Credit Memo for Order: " . $this->order_increment_id);

        if ($this->order == null):
            $this->addToErrorLog($this->order_increment_id, "Invalid order id: " . $this->order_increment_id);
            return;
        endif;
        
        /** 4.4.0.0
        * If order payment is authorized, payment is in review. we try cancel it
        * as per https://scms.company.com/common/Ticket.aspx?id=137703
        **/
        if (!$this->order->canCreditmemo()):
            if (!$this->order->isPaymentReview()):
                //$this->message['message'] = 'Cannot create credit memo. Payment is in review';
                
                $this->setDebugMessage(
                    $this->order_increment_id . ' Cannot create credit memo. Payment is in review'
                );
                
                $this->setDebugMessage('Trying to cancel the order.');

                if (!$this->order->canCancel()):
                    $this->addToErrorLog($this->order_increment_id, "Order cannot not be cancelled.");
                else:
                    $this->performCancel($refundOrderXml);
                endif;
                return;
            endif;
        endif;
        
        if (!$this->validate($refundOrderXml)):
            return;
        endif;

        // Proceed with creating refund via credit memo
        try {
            $creditmemo = $this->createByOrder($this->order, $refundOrderXml);

            if ($creditmemo):
                $creditmemo->setOrder($this->order);
                $creditmemo->setState(\Magento\Sales\Model\Order\Creditmemo::STATE_OPEN);


                /** 
                * 6.8.8.0
                * Check to see last available invoice and set to credit memo
                * */
                $invoiceIncrementid = false;
                $invoices = $this->order->getInvoiceCollection();
                foreach ($invoices as $invoice) {
                    $invoiceIncrementid = $invoice->getIncrementId();
                    $this->setDebugMessage("Existing Invoice IncremetID: " . $invoiceIncrementid);
                }

                if($invoiceIncrementid && $invoiceIncrementid != ""):
                    $invoiceobj = $this->invoiceModel->loadByIncrementId($invoiceIncrementid);

                    // Don't set invoice if you want to do offline refund
                    $this->setDebugMessage("Setting Invoice to credit memo");
                    $creditmemo->setInvoice($invoiceobj);
                endif;

                /** $creditmemo->save(); **/
                if (!empty($this->comment_text)):
                    $creditmemo->addComment(__($this->comment_text), $this->comment_customer_notify, true);
                    $creditmemo->setCustomerNote($this->comment_text);
                    $creditmemo->setCustomerNoteNotify($this->comment_customer_notify);
                    $creditmemo->setEmailSent($this->send_email);
                endif;

                /** integration\testsuite\Magento\Sales\_files\order_info.php **/
                $this->creditmemoManagement->refund($creditmemo);

                $this->setOrderStateProcessing();

                $this->xmlLog->log(
                    $this->order_increment_id,
                    "success",
                    "The credit memo# " . $creditmemo->getIncrementId() . " created"
                );
            endif;
        } catch (\Exception $ex) {
            $this->addToErrorLog($this->order_increment_id, $ex->getMessage());
        }
    }

    private function performCancel($refundOrderXml)
    {
        try {
            /** 6.5.4.0 **/
            $total_amount_to_refund = $this->getAmountToRefundFromXml($this->order, $refundOrderXml);
            $order_refundable = $this->order->getGrandTotal() - $this->order->getTotalRefunded();
            $this->setDebugMessage('total_amount_to_refund as per input: '
                . (string)$total_amount_to_refund
                . ' order net refundable: ' . (string)$order_refundable);

            // x.6.6.1 floating point comparison between amount to refund and amount available
            /*
            * 6.8.8.0
            * if (round(floatval($total_amount_to_refund), 2) == round(floatval($order_refundable), 2)): 
            * Since there may be tax included, total_amount_to_refund + tax may equal to
            * order_refundable. So, used '<=''
            */
            if (round(floatval($total_amount_to_refund), 2) <= round(floatval($order_refundable), 2)):
                if ($this->orderManagement->cancel($this->order->getId())):
                    if (strpos($this->helper->getWebsite(), "localhost.com") === -1):
                        $this->orderCommentSender->send($this->order, true, "Order has been cancelled");

                        $this->order
                            ->addStatusHistoryComment("Order has been cancelled")
                            ->setIsCustomerNotified(1);
                    endif;
                    
                    $this->xmlLog
                            ->log(
                                $this->order_increment_id,
                                "success",
                                "Order " . $this->order_increment_id . " has been cancelled"
                            );
                else:
                    $this->addToErrorLog($this->order_increment_id, "Order could not be cancelled.");
                endif;
            else:
                $this->addToErrorLog(
                    $this->order_increment_id,
                    "A full refund of " . round($order_refundable, 2) . " is expected to cancel the order#"
                    . $this->order_increment_id
                    . " while requested amount: " . round($total_amount_to_refund, 2)
                );
            endif;
        } catch (Exception $ex) {
            $this->addToErrorLog(
                $this->order_increment_id,
                '[createRefund][cancel order]' . $ex->getMessage()
            );
        }
    }

    private function createByOrder(\Magento\Sales\Model\Order $order, $refundOrderXml)
    {
        $totalQty = 0;
        $creditmemo = $this->convertor->toCreditmemo($order);
        /** $qtys = isset($data['qtys']) ? $data['qtys'] : []; **/

        if ($this->has_order_item == true):
            foreach ($refundOrderXml->orderitems->orderitem as $orderItemXml):
                $sku = (string) $orderItemXml['sku'];
                $qty = isset($orderItemXml['qty']) ? (int) $orderItemXml['qty'] : null;
                $back_to_stock = isset($orderItemXml['back_to_stock']) ? (int) $orderItemXml['back_to_stock'] : 0;
                $back_to_stock = $back_to_stock == 0 ? false : true;

                $orderItemObject = null;
                
                /** find the order item ID from $order->getAllItems() **/
                foreach ($order->getAllItems() as $orderItem):
                    if ($orderItem->getSku() === $sku):
                        $orderItemObject = $orderItem;
                        break;
                    endif;
                endforeach;

                // Create each credit memo ITEM object
                if ($orderItemObject != null):
                    $item = $this->convertor->itemToCreditmemoItem($orderItemObject);
                    $qty = ($qty == null) ? $orderItemObject->getQtyToRefund() : $qty;

                    $totalQty += $qty;
                    $item->setQty($qty);
                    $item->setBackToStock($back_to_stock);
                    $creditmemo->addItem($item);

                    $this->setDebugMessage("Item added: " . $sku . " qty: " . $qty);
                endif;
            endforeach;
        else:
            if ($this->adjustment_refund > 0.0 || $this->shipping_amount > 0.0):
                /** Set dummy item to refund.......... **/
                foreach ($order->getAllItems() as $orderItem):
                    $item = $this->convertor->itemToCreditmemoItem($orderItem);
                    $qty = 0;

                    $totalQty += $qty;
                    $item->setQty($qty);
                    $item->setBackToStock(false);
                    $creditmemo->addItem($item);
                endforeach;
            endif;
        endif;

        $creditmemo->setTotalQty($totalQty);

        $creditmemo->setAdjustmentPositive($this->adjustment_refund);
        $creditmemo->setBaseShippingAmount((double) $this->shipping_amount);

        $this->setDebugMessage("adjustment_refund: " . $this->adjustment_refund);
        $this->setDebugMessage("shipping_amount: " . $this->shipping_amount);

        $creditmemo->collectTotals();
        return $creditmemo;
    }

    private function setOrderStateProcessing()
    {
        $this->order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus(
                    $this->order->getConfig()
                    ->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
                )
                ->addStatusHistoryComment($this->comment_text)
                ->setIsCustomerNotified($this->comment_customer_notify);
    }

    private function getAmountToRefundFromXml(\Magento\Sales\Model\Order $order, $refundOrderXml)
    {
        $total_amount_to_refund = 0.0;

        if ($this->has_order_item == true):
            foreach ($refundOrderXml->orderitems->orderitem as $orderItemXml):
                $sku = (string) $orderItemXml['sku'];
                $qty = isset($orderItemXml['qty']) ? (int) $orderItemXml['qty'] : null;
                
                $orderItemObject = null;
                // find the order item ID from $order->getAllItems()
                foreach ($order->getAllItems() as $orderItem):
                    if ($orderItem->getSku() === $sku):
                        $orderItemObject = $orderItem;
                        break;
                    endif;
                endforeach;

                // Determine total per row for each ITEM object.
                if ($orderItemObject != null):
                    $this->setDebugMessage("orderItemObject->getQtyOrdered(): " . $orderItemObject->getQtyOrdered());
                    $qty = ($qty == null) ? $orderItemObject->getQtyOrdered() : $qty;
                    $total_amount_to_refund +=
                        ($orderItemObject->getRowTotal() * $qty) / $orderItemObject->getQtyOrdered();

                    /** $this->setDebugMessage("Item added: " . $sku . " qty: " . $qty); **/
                endif;
            endforeach;
        endif;

        $total_amount_to_refund += $this->adjustment_refund + $this->shipping_amount;
        $this->setDebugMessage("adjustment_refund: " . $this->adjustment_refund);
        $this->setDebugMessage("shipping_amount: " . $this->shipping_amount);
        
        return $total_amount_to_refund;
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
