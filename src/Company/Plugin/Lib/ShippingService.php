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

class ShippingService extends \Company\Plugin\Lib\CommonService
{
    private $helper;

    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceSender;
    
    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $trackFactory;

    /**
     * @var ShipmentSender
     */
    protected $shipmentSender;

    /**
     * @var \Magento\Shipping\Model\ShipmentNotifier
     */
    protected $shipmentNotifier;

    protected $convertOrderFactory;

    protected $searchCriteriaBuilder;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender,
        \Company\Plugin\Helper\XmlLog $xmlLog,
        \Company\Plugin\Lib\Database $database,
        \Company\Plugin\Helper\Data $helper,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
    ) {
        $this->helper = $helper;
        $this->trackFactory = $trackFactory;
        $this->shipmentSender = $shipmentSender;

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->convertOrderFactory = $convertOrderFactory;
        $this->shipmentNotifier = $shipmentNotifier;

        parent::__construct($objectManager, $xmlLog, $database);
    }

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
    public function setParameters($jobId, $verbose = 0)
    {
        parent::setParameters($jobId, $verbose);
    }

    public function __destruct()
    {
        $this->setDebugMessage('Shipping Service action is complete.');
        /** parent::__destruct(); **/
    }

    public function importShipping($feed_xml)
    {
        $this->setDebugMessage('Updating order tracking');

        foreach ($feed_xml->order as $orderXML):
            $orderIncrementId = (string) $orderXML->orderid;
            $trackingNumber = (string) $orderXML->number;
            $carrierCode = (string) $orderXML->carrier_code;
            $carrierTitle = (string) $orderXML->title;
            $comment = isset($orderXML->comment) ? (string) $orderXML->comment : null;
            $shipmentDate = isset($orderXML->shipment_date) ?
                    (string) $orderXML->shipment_date : null; //date("Y-m-d H:i:s");
            
            $shippingCost = isset($orderXML->shipping_cost) ? doubleval((string)$orderXML->shipping_cost) : null;
            $taxOnShippingCost = isset($orderXML->shipping_tax) ? doubleval((string)$orderXML->shipping_tax) : null;
            
            /**6.8.7.9 Allow multiple trackings**/
            $trackingsArray = [];
            if(isset($orderXML->trackings)):
                foreach ($orderXML->trackings->tracking as $trackingXml):
                    $trackingsArray[] = array(
                        "number" => (string)$trackingXml->number,
                        "carrier_code" => (string)$trackingXml->carrier_code,
                        "title" => (string)$trackingXml->title
                    );
                endforeach;
            endif;

            try {
                $order = $this->getByID($orderIncrementId);

                if ($order == null):
                    $this->xmlLog->log($orderIncrementId, "error", "order does not exist");
                    $this->setDebugMessage('order# ' . $orderIncrementId . ' does not exist');
                    continue;
                endif;

                if ($order->getStatus() == 'complete'):
                    $this->xmlLog->log($orderIncrementId, "success", "order status is already complete");
                    $this->setDebugMessage('order status is already complete');
                    continue;
                endif;

                $this->setDebugMessage('Processing order: ' . $orderIncrementId);
                
                // 6.5.2.5
                if ($shippingCost != null):
                    $this->setDebugMessage(
                        'Updating shipping cost : ' . $shippingCost . ' tax : ' . $taxOnShippingCost
                    );
                    
                    $this->updateShippingCost($order, $shippingCost, $taxOnShippingCost);
                endif;
                
                $this->createInvoice($order);
                $this->completeShipment(
                    $orderXML,
                    $order,
                    $trackingNumber,
                    $carrierCode,
                    $carrierTitle,
                    $comment,
                    $shipmentDate,
                    $trackingsArray
                );

                // Shipment creation is successful.
                $this->setDebugMessage($orderIncrementId . ': imported ');
                $this->xmlLog->log($orderIncrementId, "success", "");
            } catch (\Exception $ex) {
                $this->setDebugMessage($ex->getMessage());
                $this->xmlLog->log($orderIncrementId, "error", $ex->getMessage());
            }
        endforeach;
    }

    private function updateShippingCost(\Magento\Sales\Model\Order $order, $shippingCost, $taxOnShippingCost)
    {
        // Apply Shipping cost
        $order->setShippingAmount($shippingCost)->setBaseShippingAmount($shippingCost);
        
        if ($taxOnShippingCost != null):
            $order->setShippingTaxAmount($taxOnShippingCost)->setBaseShippingTaxAmount($taxOnShippingCost);
        endif;
        
        $order->save();
    }
    
    public function createInvoice(\Magento\Sales\Model\Order $order)
    {
        if (!$order->canInvoice()):
            return false;
        endif;

        // Create invoice for this order
        /**
        *$invoice = $this->objectManager->create('Magento\Sales\Model\Service\InvoiceService::c l a s s')
            ->prepareInvoice($order);
        **/
        $invoice = $this->invoiceService->prepareInvoice($order);

        // Make sure there is a qty on the invoice
        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }

        //$invcol = $order->getInvoiceCollection();

        if ($order->getTotalPaid() > 0):
            return;
        endif;

        $invoice->setOrder($order);

        // Register as invoice item
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
            $this->setDebugMessage("Exception capture: " . $ex->getMessage());
        }

        $invoice->setRequestedCaptureCase($CAPTURE_OFFLINE_ONLINE);
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->register();

        // Save the invoice to the order
        /**
        $transactionSave = $this->objectManager->create('Magento\Framework\DB\Transaction::c l a s s')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
        **/
        $transactionSave = $this->transaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();

        // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
        /**
        * $invoiceSender = $this->objectManager
        * ->create("Magento\Sales\Model\Order\Email\Sender\InvoiceSender::c l a s s");
        **/
        $this->invoiceSender->send($invoice);

        $order
                ->addStatusHistoryComment(
                    __('Automatically INVOICED by Company #%1.', $invoice->getId())
                )->setIsCustomerNotified(true)
                ->setIsInProcess(true)
                ->save();

        return true;
    }

    public function completeShipment(
        $orderXML,
        \Magento\Sales\Model\Order $order,
        $trackingNumber,
        $carrierCode,
        $carrierTitle,
        $comment,
        $shipmentDate,
        $trackingsArray
    ) {
        // D:\xampp\htdocs\m211\vendor\magento\module-shipping\Model\Shipping\LabelGenerator.php
        // Check if order can be shipped or has already shipped

        if (!$order->canShip()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an shipment.')
            );
        }

        if ($order->getState() === 'holded'):
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)->save();
        endif;

        if (!$order->canShip()):
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot ship this order.')
            );
        endif;

        // Start processing the shipment for order.
        // Initialize the order shipment object
        //$this->objectManager->create('Magento\Sales\Model\Convert\Order::c l a s s');
        $convertOrder = $this->convertOrderFactory->create();

        $shipment = $convertOrder->toShipment($order);

        // ****************************************************************** */
        // Loop through order items
        if (!isset($orderXML->orderitems) || count($orderXML->orderitems->orderitem) == 0):
            foreach ($order->getAllItems() as $orderItem):
                // Check if order item has qty to ship or is virtual
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }

                $qtyShipped = $orderItem->getQtyToShip();

                // Create shipment item with qty
                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                // Add shipment item to shipment
                $shipment->addItem($shipmentItem);
            endforeach;
        else:
            foreach ($orderXML->orderitems->orderitem as $orderItemXml):
                $sku = (string) $orderItemXml["sku"];
                $qtyShipped = isset($orderItemXml["qty"]) ? (int) $orderItemXml["qty"] : null;

                /** find the order item ID from $order->getAllItems() **/
                $orderItemObject = null;
                
                foreach ($order->getAllItems() as $_eachItem):
                    if ($_eachItem->getSku() === $sku):
                        $orderItemObject = $_eachItem;
                        break;
                    endif;
                endforeach;

                if ($orderItemObject != null):
                    /** Check if order item has qty to ship or is virtual **/
                    if (!$orderItemObject->getQtyToShip() || $orderItemObject->getIsVirtual()) {
                        continue; // SKIP
                    }

                    $qtyShipped = ($qtyShipped == null) ? $orderItemObject->getQtyToShip() : $qtyShipped;

                    /** Create shipment item with qty **/
                    $shipmentItem = $convertOrder->itemToShipmentItem($orderItemObject)->setQty($qtyShipped);

                    /** Add shipment item to shipment **/
                    $shipment->addItem($shipmentItem);
                endif;
            endforeach;
        endif;
        // ****************************************************************** */

        if ($comment != ""):
            $this->setDebugMessage('Adding comment to shipment object');
            $shipment->addComment($comment, 1, 1);
            
            $shipment->setCustomerNoteNotify(1);
            $this->setDebugMessage('setCustomerNoteNotify=1 to shipment object');
            $shipment->setCustomerNote($comment);
        endif;

        // Register shipment
        $shipment->register();

        $data = $shipment->getOrder()->getData();
        /** if($this->_verbose == 1) {var_dump($ship_data);} **/

        try {
            $this->addTrackingNumbersToShipment(
                $shipment,
                $trackingNumber,
                $carrierCode,
                $carrierTitle,
                $trackingsArray
            );

            // Save created shipment and order
            $shipment->getOrder()->setIsInProcess(true);
            $shipment->getOrder()->setCustomerNoteNotify(1);
            $shipment->getOrder()->setHasDataChanges(true);
            $shipment->setShipmentStatus(\Magento\Sales\Model\Order\Shipment::STATUS_NEW);

            /** 6.6.7.0
            * Due to issue with MSI(Multiple Source Inventory), we need to select which source is affected
            **/
            $this->setDebugMessage('set up inventory source to shipment object');
            $sourceList = $this->helper->getSourceList();

            /* 6.10.0 MSI may or may not be enabled */
            if ($sourceList && count($sourceList) > 1):
                $this->setDebugMessage('MSI-inventory source multiple, selected default');
                $shipment->getExtensionAttributes()->setSourceCode('default');
                /** normally 'default' Or $sourceList[0]["source_code"] **/
            else:
                $this->setDebugMessage('inventory source single or MSI is disabled');
            endif;

            // ================================================================
            // $this->objectManager->create('Magento\Framework\DB\Transaction::c l a s s');
            $transaction = $this->transaction;
            $transaction->addObject($shipment)->addObject($shipment->getOrder())
                    ->save();

            /**Send email
            *$this->objectManager->create('Magento\Shipping\Model\ShipmentNotifier::c l a s s')
            *   ->notify($shipment);
            **/
            $this->shipmentNotifier->notify($shipment);
            $this->setDebugMessage('ShipmentNotifier-> notify ON');
            $shipment->save();

            /**
            *
            *if (!empty($data['send_email'])) {
            *    // x.5.5.0 Commented the two lines below to stop sending mails again(2 times)
            *    //$this->setDebugMessage('send_email not empty, sending mail');
            *    //$this->shipmentSender->send($shipment);
            *}
            **/

            $this->setDebugMessage("Customer email " . $data["customer_email"]);
            $this->setDebugMessage("send_email " . $data["send_email"]);
            $this->setDebugMessage("Shipment Number " . $shipment->getIncrementId());
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $trackingNumbers
     * @param string $carrierCode
     * @param string $carrierTitle
     *
     * @return void
     */
    private function addTrackingNumbersToShipment(
        \Magento\Sales\Model\Order\Shipment $shipment,
        $trackingNumber,
        $carrierCode,
        $carrierTitle,
        $trackingsArray
    ) {
        if($trackingNumber != ""):
            $shipment->addTrack(
                $this->trackFactory->create()
                    ->setNumber($trackingNumber)
                    ->setCarrierCode($carrierCode)
                    ->setTitle($carrierTitle)
            );
        endif;

        /**6.8.7.9 Allow multiple trackings**/
        if(!empty($trackingsArray)):
            foreach ($trackingsArray as $trackingData):
                $shipment->addTrack(
                    $this->trackFactory->create()
                        ->setNumber($trackingData["number"])
                        ->setCarrierCode($trackingData["carrier_code"])
                        ->setTitle($trackingData["title"])
                    );
            endforeach;
        endif;
    }

    public function getByID($incrementId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        /** var_dump($order->getIncrementId()); **/
        if ($order && $order->getIncrementId() != null && $order->getIncrementId() !== ""):
            return $order;
        endif;

        return null;
    }
}
