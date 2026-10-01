<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Company\Plugin\Lib;

/**
 * Description of OrderPaymentCaptureService
 *
 * D:\xampp\htdocs\m17\_import\services\orderPaymentCaptureService.php
 * D:\xampp\htdocs\m211\vendor\magento\module-sales\Model\Order\Payment\Operations\RegisterCaptureNotificationOperation.php
 * D:\xampp\htdocs\m211\vendor\magento\module-sales\Model\Order\Payment.php
 * D:\xampp\htdocs\m211\vendor\magento\module-sales\Controller\Adminhtml\Order\Invoice\Save.php
 * @author Company Plugin
 */
class OrderPaymentCaptureService extends \Company\Plugin\Lib\CommonService
{
    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    protected $orderFactory;
    private $invoiceFactory;
    private $orderService;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Api\InvoiceManagementInterface $orderService,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->invoiceFactory = $invoiceFactory;
        $this->orderService = $orderService;
        $this->invoiceSender = $invoiceSender;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;

        parent::__construct($objectManager, $xmlLog, $database);
    }

    public function capture($feed_xml)
    {
        // Decide if single order or multiple.
        if ($feed_xml->getName() === "order"):
            $action = trim((string) $feed_xml->action);
            $order_increment_id = trim((string) $feed_xml->order_increment_id);
            $order = $this->findOrderByIncrementId($order_increment_id);

            if ($order == null):
                $this->addToErrorLog($order_increment_id, "Invalid order id: " . $order_increment_id);
                return;
            endif;

            try {
                if ($action === "capture_preauthorized_payment"):
                    if ($this->capturePayment($order)):
                        $this->xmlLog->log($order_increment_id, "success", "Payment has been captured");
                    else:
                        $this->addToErrorLog($order_increment_id, "Could not capture payment against order");
                    endif;
                else:
                        $this->addToErrorLog($order_increment_id, "Provided action is not implemented");
                endif;
            } catch (\Exception $ex) {
                $this->addToErrorLog($order_increment_id, $ex->getMessage());
            }
        elseif ($feed_xml->getName() === "orders"):
            // process xml with <orders>
            foreach ($feed_xml->order as $orderXml):
                $action = trim((string) $orderXml->action);
                $order_increment_id = trim((string) $orderXml->order_increment_id);
                $order = $this->findOrderByIncrementId($order_increment_id);

                if ($order == null):
                    $this->addToErrorLog($order_increment_id, "Invalid order id: " . $order_increment_id);
                    continue;
                endif;

                try {
                    if ($action === "capture_preauthorized_payment"):
                        if ($this->capturePayment($order)):
                            $this->xmlLog->log($order_increment_id, "success", "Payment has been captured");
                        else:
                            $this->addToErrorLog($order_increment_id, "Could not capture payment against order");
                        endif;
                    else:
                        $this->addToErrorLog($order_increment_id, "Provided action is not implemented");
                    endif;
                } catch (\Exception $ex) {
                    $this->addToErrorLog($order_increment_id, $ex->getMessage());
                }
            endforeach;
        endif;
    }

    private function capturePayment(\Magento\Sales\Model\Order $order)
    {
        if (!$order->canInvoice()):
            throw new \Magento\Framework\Exception\LocalizedException(__("Cannot create an invoice"));
        endif;

        if ($order->getTotalPaid() == 0):
            //$invoice = $this->_orderService->prepareInvoice($order);
            /** $invoice = $this->objectManager->create('Magento\Sales\Model\Service\InvoiceService::c l a s s')
                    ->prepareInvoice($order);
            **/
            $invoice = $this->invoiceService->prepareInvoice($order);

            if (!$invoice->getTotalQty()):
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Cannot create an invoice without products.')
                );
            endif;

            $invoice->setOrder($order);
            $invoice->setRequestedCaptureCase($this->getCaptureCase($order));

            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->register(); //->capture();

            /**
            * $transactionSave = $this->objectManager->create('Magento\Framework\DB\Transaction::c l a s s');
            * $transactionSave->addObject($invoice)->addObject($order)->save();
            **/
            $transactionSave = $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

            //$invoice->sendEmail(true, "");
            $this->invoiceSender->send($invoice);
            $order->save();

            return true;
        else:
            throw new \Magento\Framework\Exception\LocalizedException(__("Invoice already exists"));
        endif;

        return false;
    }

    private function getCaptureCase($order)
    {
        $CAPTURE_OFFLINE_ONLINE = \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE;

        try {
            $paymentModel = $order->getPayment()->getMethodInstance();
            if ($paymentModel) {
                if ($paymentModel->canCapture() == true) {
                    $CAPTURE_OFFLINE_ONLINE = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
                }
            }
            unset($paymentModel);
        } catch (\Exception $ex) {
            throw $ex;
        }

        //var_dump($CAPTURE_OFFLINE_ONLINE);
        if ($CAPTURE_OFFLINE_ONLINE == \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE):
            $this->setDebugMessage("Trying to Capture offline");
        else:
            $this->setDebugMessage("Trying to Capture online");
        endif;

        return $CAPTURE_OFFLINE_ONLINE;
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
