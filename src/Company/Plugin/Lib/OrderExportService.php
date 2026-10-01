<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */

namespace Company\Plugin\Lib;

use Magento\Framework\ObjectManager\ConfigLoaderInterface;

class OrderExportService
{
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;

    private $orderRepository;
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;
    protected $shipmentCollFactory;
    protected $date;
    private $orderId = "";
    private $rowsPerPage = 10;  // Default 10
    private $currentPageIndex = 1;  // Must be given to get paged data, starts with 1
    protected $helper;

    private $verbose = 0;
    private $objectManager;
    private $giftMessageOrderRepository;
    private $amount;
    private $regionFactory;
    private $finalXml;

    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        //\Magento\Framework\Pricing\PriceCurrencyInterface $amount,
        \Company\Plugin\Helper\Data $helper,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\GiftMessage\Model\OrderRepository $giftMessageOrderRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        array $data = []
    ) {
        $this->productFactory = $productFactory;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->shipmentCollFactory = $shipmentCollFactory;
        $this->helper = $helper;
        $this->date = $date;
        $this->regionFactory = $regionFactory;
        //$this->amount = $amount; // 6.6.2.0

        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** //$this->objectManager->create('Magento\GiftMessage\Model\OrderRepository'); **/
        $this->giftMessageOrderRepository = $giftMessageOrderRepository;
    }

    public function printOrders($qryStrArray)
    {
        if (isset($qryStrArray["verbose"]) && $qryStrArray["verbose"] == 1):
            $this->verbose = 1;
        endif;
        
        $collection = $this->getOrders($qryStrArray);
        $recordCountFromCollection = $collection->getSize();
        
        $sql = $collection->getSelectCountSql();

        /** Revised record count 6.8.7.11 **/
        $recordCount = $collection->getConnection()->fetchOne($sql);

        $totalPages = (int) ($recordCount / $this->rowsPerPage)
                + ((int) ($recordCount % $this->rowsPerPage) > 0 ? 1 : 0);

        $this->finalXml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $this->finalXml .= '<orders>';
        $this->finalXml .= $this->helper->printModuleVersion();
        $this->finalXml .= '<recordCountFromCollection>' . $recordCountFromCollection . '</recordCountFromCollection>';
        $this->finalXml .= '<recordcount>' . $recordCount . '</recordcount>';
        $this->finalXml .= '<currentpageindex>' . $this->currentPageIndex . '</currentpageindex>';
        $this->finalXml .= '<pagesize>' . $this->rowsPerPage . '</pagesize>';
        $this->finalXml .= '<totalpages>' . $totalPages . '</totalpages>';
        $this->finalXml .= '<verbose>' . $this->verbose . '</verbose>';

        if ($this->verbose == "1"):
            $this->finalXml .= "\r\n collection->getSelectCountSql = " . $sql;
        endif;
        
        /** $this->helper->sendToScreen($output); **/

        foreach ($collection as $order):
            $od = $order->getData();

            if ($this->verbose == 1):
                var_dump(["ORDER DATA" => $od]);
            endif;

            $this->finalXml .= '<order>';

            $this->finalXml .= '<entity_id>' . $od['entity_id'] . '</entity_id>';
            $this->finalXml .= '<state>' . $od['state'] . '</state>';
            $this->finalXml .= '<status>' . $od['status'] . '</status>';
            $this->finalXml .= '<coupon_code>' . $od['coupon_code'] . '</coupon_code>';
            $this->finalXml .= '<shipping_description>'
                . $this->helper->addWithCData($od['shipping_description']) . '</shipping_description>';
            
            $this->finalXml .= '<is_virtual>' . $od['is_virtual'] . '</is_virtual>';
            $this->finalXml .= '<store_id>' . $od['store_id'] . '</store_id>';
            $this->finalXml .= '<customer_id>' . $od['customer_id'] . '</customer_id>';

            if (isset($od["signature_charge_amount"])):
                $this->finalXml .= '<signature_charge_amount>'
                    . $od['signature_charge_amount'] . '</signature_charge_amount>';
            else:
                $this->finalXml .= '<signature_charge_amount>0.00</signature_charge_amount>';
            endif;

            $this->finalXml .= '<base_discount_amount>'
                . $this->getDecimalValue($od, 'base_discount_amount') . '</base_discount_amount>';

            $this->finalXml .= '<base_discount_canceled>'
                . $this->getDecimalValue($od, 'base_discount_canceled') . '</base_discount_canceled>';

            $this->finalXml .= '<base_discount_invoiced>'
                . $this->getDecimalValue($od, 'base_discount_invoiced') . '</base_discount_invoiced>';

            $this->finalXml .= '<base_discount_refunded>'
                . $this->getDecimalValue($od, 'base_discount_refunded') . '</base_discount_refunded>';

            $this->finalXml .= '<base_grand_total>'
                . $this->getDecimalValue($od, 'base_grand_total') . '</base_grand_total>';

            $this->finalXml .= '<base_shipping_amount>'
                . $this->getDecimalValue($od, 'base_shipping_amount') . '</base_shipping_amount>';

            $this->finalXml .= '<base_shipping_canceled>'
                . $this->getDecimalValue($od, 'base_shipping_canceled') . '</base_shipping_canceled>';

            $this->finalXml .= '<base_shipping_invoiced>'
                . $this->getDecimalValue($od, 'base_shipping_invoiced') . '</base_shipping_invoiced>';

            $this->finalXml .= '<base_shipping_refunded>'
                . $this->getDecimalValue($od, 'base_shipping_refunded') . '</base_shipping_refunded>';

            $this->finalXml .= '<base_shipping_tax_amount>'
                . $this->getDecimalValue($od, 'base_shipping_tax_amount') . '</base_shipping_tax_amount>';

            $this->finalXml .= '<base_shipping_tax_refunded>'
                . $this->getDecimalValue($od, 'base_shipping_tax_refunded') . '</base_shipping_tax_refunded>';

            $this->finalXml .= '<base_subtotal>'
                . $this->getDecimalValue($od, 'base_subtotal') . '</base_subtotal>';

            $this->finalXml .= '<base_subtotal_canceled>'
                . $this->getDecimalValue($od, 'base_subtotal_canceled') . '</base_subtotal_canceled>';

            $this->finalXml .= '<base_subtotal_invoiced>'
                . $this->getDecimalValue($od, 'base_subtotal_invoiced') . '</base_subtotal_invoiced>';

            $this->finalXml .= '<base_subtotal_refunded>'
                . $this->getDecimalValue($od, 'base_subtotal_refunded') . '</base_subtotal_refunded>';

            $this->finalXml .= '<base_tax_amount>'
                . $this->getDecimalValue($od, $od['base_tax_amount']) . '</base_tax_amount>';

            $this->finalXml .= '<base_tax_canceled>'
                . $this->getDecimalValue($od, 'base_tax_canceled') . '</base_tax_canceled>';
            
            $this->finalXml .= '<base_tax_invoiced>'
                . $this->getDecimalValue($od, 'base_tax_invoiced') . '</base_tax_invoiced>';

            $this->finalXml .= '<base_tax_refunded>'
                . $this->getDecimalValue($od, 'base_tax_refunded') . '</base_tax_refunded>';

            $this->finalXml .= '<base_to_global_rate>'
                . $this->getDecimalValue($od, 'base_to_global_rate') . '</base_to_global_rate>';

            $this->finalXml .= '<base_to_order_rate>'
                . $this->getDecimalValue($od, 'base_to_order_rate') . '</base_to_order_rate>';

            $this->finalXml .= '<base_total_canceled>'
                . $this->getDecimalValue($od, 'base_total_canceled') . '</base_total_canceled>';

            $this->finalXml .= '<base_total_invoiced>'
                . $this->getDecimalValue($od, 'base_total_invoiced') . '</base_total_invoiced>';

            $this->finalXml .= '<base_total_invoiced_cost>'
                . $this->getDecimalValue($od, 'base_total_invoiced_cost') . '</base_total_invoiced_cost>';

            $this->finalXml .= '<base_total_offline_refunded>'
                . $this->getDecimalValue($od, 'base_total_offline_refunded')
                . '</base_total_offline_refunded>';

            $this->finalXml .= '<base_total_online_refunded>'
                . $this->getDecimalValue($od, 'base_total_online_refunded') . '</base_total_online_refunded>';

            $this->finalXml .= '<base_total_paid>'
                . $this->getDecimalValue($od, 'base_total_paid') . '</base_total_paid>';

            $this->finalXml .= '<base_total_qty_ordered>'
                . $this->getDecimalValue($od, 'base_total_qty_ordered') . '</base_total_qty_ordered>';

            $this->finalXml .= '<base_total_refunded>'
                . $this->getDecimalValue($od, 'base_total_refunded')
                . '</base_total_refunded>';

            $this->finalXml .= '<discount_amount>'
                . $this->getDecimalValue($od, 'discount_amount') . '</discount_amount>';

            $this->finalXml .= '<discount_canceled>'
                . $this->getDecimalValue($od, 'discount_canceled') . '</discount_canceled>';

            $this->finalXml .= '<discount_invoiced>'
                . $this->getDecimalValue($od, 'discount_invoiced') . '</discount_invoiced>';

            $this->finalXml .= '<discount_refunded>'
                . $this->getDecimalValue($od, 'discount_refunded') . '</discount_refunded>';

            $this->finalXml .= '<grand_total>'
                . $this->getDecimalValue($od, 'grand_total') . '</grand_total>';

            $this->finalXml .= '<shipping_amount>'
                . $this->getDecimalValue($od, 'shipping_amount') . '</shipping_amount>';

            $this->finalXml .= '<shipping_canceled>'
                . $this->getDecimalValue($od, 'shipping_canceled') . '</shipping_canceled>';

            $this->finalXml .= '<shipping_invoiced>'
                . $this->getDecimalValue($od, 'shipping_invoiced') . '</shipping_invoiced>';

            $this->finalXml .= '<shipping_refunded>'
                . $this->getDecimalValue($od, 'shipping_refunded') . '</shipping_refunded>';

            $this->finalXml .= '<shipping_tax_amount>'
                . $this->getDecimalValue($od, 'shipping_tax_amount') . '</shipping_tax_amount>';

            $this->finalXml .= '<shipping_tax_refunded>'
                . $this->getDecimalValue($od, 'shipping_tax_refunded') . '</shipping_tax_refunded>';

            $this->finalXml .= '<store_to_base_rate>'
                . $this->getDecimalValue($od, 'store_to_base_rate') . '</store_to_base_rate>';

            $this->finalXml .= '<store_to_order_rate>'
                . $this->getDecimalValue($od, 'store_to_order_rate') . '</store_to_order_rate>';

            $this->finalXml .= '<subtotal>' . $this->getDecimalValue($od, 'subtotal') . '</subtotal>';
            $this->finalXml .= '<subtotal_canceled>'
                . $this->getDecimalValue($od, 'subtotal_canceled') . '</subtotal_canceled>';

            $this->finalXml .= '<subtotal_invoiced>'
                . $this->getDecimalValue($od, 'subtotal_invoiced') . '</subtotal_invoiced>';

            $this->finalXml .= '<subtotal_refunded>'
                . $this->getDecimalValue($od, 'subtotal_refunded') . '</subtotal_refunded>';

            $this->finalXml .= '<tax_amount>'
                . $this->getDecimalValue($od, 'tax_amount') . '</tax_amount>';

            $this->finalXml .= '<tax_canceled>' . $this->getDecimalValue($od, 'tax_canceled') . '</tax_canceled>';
            $this->finalXml .= '<tax_invoiced>' . $this->getDecimalValue($od, 'tax_invoiced') . '</tax_invoiced>';
            $this->finalXml .= '<tax_refunded>' . $this->getDecimalValue($od, 'tax_refunded') . '</tax_refunded>';
            $this->finalXml .= '<total_canceled>'
                . $this->getDecimalValue($od, 'total_canceled') . '</total_canceled>';

            $this->finalXml .= '<total_invoiced>'
                . $this->getDecimalValue($od, 'total_invoiced') . '</total_invoiced>';

            $this->finalXml .= '<total_offline_refunded>'
                . $this->getDecimalValue($od, 'total_offline_refunded') . '</total_offline_refunded>';

            $this->finalXml .= '<total_online_refunded>'
                . $this->getDecimalValue($od, 'total_online_refunded') . '</total_online_refunded>';

            $this->finalXml .= '<total_paid>' . $this->getDecimalValue($od, 'total_paid') . '</total_paid>';
            $this->finalXml .= '<total_qty_ordered>'
                . $this->getDecimalValue($od, 'total_qty_ordered') . '</total_qty_ordered>';

            $this->finalXml .= '<total_refunded>'
                . $this->getDecimalValue($od, 'total_refunded') . '</total_refunded>';

            $this->finalXml .= '<can_ship_partially>' . $od['can_ship_partially'] . '</can_ship_partially>';
            $this->finalXml .= '<can_ship_partially_item>'
                . $od['can_ship_partially_item'] . '</can_ship_partially_item>';

            $this->finalXml .= '<customer_is_guest>' . $od['customer_is_guest'] . '</customer_is_guest>';
            $this->finalXml .= '<customer_note_notify>' . $od['customer_note_notify'] . '</customer_note_notify>';
            $this->finalXml .= '<billing_address_id>' . $od['billing_address_id'] . '</billing_address_id>';
            $this->finalXml .= '<customer_group_id>' . $od['customer_group_id'] . '</customer_group_id>';
            $this->finalXml .= '<edit_increment>' . $od['edit_increment'] . '</edit_increment>';
            $this->finalXml .= '<email_sent>' . $od['email_sent'] . '</email_sent>';
            $this->finalXml .= '<send_email>' . $od['send_email'] . '</send_email>';
            $this->finalXml .= '<forced_shipment_with_invoice>'
                . $od['forced_shipment_with_invoice'] . '</forced_shipment_with_invoice>';

            $this->finalXml .= '<payment_auth_expiration>'
                . $od['payment_auth_expiration'] . '</payment_auth_expiration>';

            $this->finalXml .= '<quote_address_id>' . $od['quote_address_id'] . '</quote_address_id>';
            $this->finalXml .= '<quote_id>' . $od['quote_id'] . '</quote_id>';
            $this->finalXml .= '<shipping_address_id>' . $od['shipping_address_id'] . '</shipping_address_id>';
            $this->finalXml .= '<adjustment_negative>' . $od['adjustment_negative'] . '</adjustment_negative>';
            $this->finalXml .= '<adjustment_positive>' . $od['adjustment_positive'] . '</adjustment_positive>';
            $this->finalXml .= '<base_adjustment_negative>'
                . $od['base_adjustment_negative'] . '</base_adjustment_negative>';

            $this->finalXml .= '<base_adjustment_positive>'
                . $od['base_adjustment_positive'] . '</base_adjustment_positive>';

            $this->finalXml .= '<base_shipping_discount_amount>'
                . $od['base_shipping_discount_amount'] . '</base_shipping_discount_amount>';

            $this->finalXml .= '<base_subtotal_incl_tax>'
                . $od['base_subtotal_incl_tax'] . '</base_subtotal_incl_tax>';

            $this->finalXml .= '<base_total_due>'
                . $this->getDecimalValue($od, 'base_total_due') . '</base_total_due>';
            
            $this->finalXml .= '<payment_authorization_amount>'
                . $this->getDecimalValue($od, 'payment_authorization_amount')
                . '</payment_authorization_amount>';

            $this->finalXml .= '<shipping_discount_amount>'
                . $this->getDecimalValue($od, 'shipping_discount_amount') . '</shipping_discount_amount>';

            $this->finalXml .= '<subtotal_incl_tax>'
                . $this->getDecimalValue($od, 'subtotal_incl_tax') . '</subtotal_incl_tax>';

            $this->finalXml .= '<total_due>'
                . $this->getDecimalValue($od, 'total_due') . '</total_due>';

            $this->finalXml .= '<weight>' . $od['weight'] . '</weight>';
            $this->finalXml .= '<customer_dob>' . $od['customer_dob'] . '</customer_dob>';
            $this->finalXml .= '<increment_id>' . $od['increment_id'] . '</increment_id>';
            $this->finalXml .= '<applied_rule_ids>' . $od['applied_rule_ids'] . '</applied_rule_ids>';
            
            $this->finalXml .= '<customer_email>'
                . $this->helper->addWithCData($od['customer_email']) . '</customer_email>';

            $this->finalXml .= '<customer_firstname>'
                . $this->helper->addWithCData($od['customer_firstname']) . '</customer_firstname>';

            $this->finalXml .= '<customer_lastname>'
                . $this->helper->addWithCData($od['customer_lastname']) . '</customer_lastname>';

            $this->finalXml .= '<customer_middlename>'
                . $this->helper->addWithCData($od['customer_middlename']) . '</customer_middlename>';

            $this->finalXml .= '<customer_prefix>' . $od['customer_prefix'] . '</customer_prefix>';
            $this->finalXml .= '<customer_suffix>' . $od['customer_suffix'] . '</customer_suffix>';
            $this->finalXml .= '<customer_taxvat>'
                . $this->helper->addWithCData($od['customer_taxvat']) . '</customer_taxvat>';

            $this->finalXml .= '<discount_description>'
                . $this->helper->addWithCData($od['discount_description']) . '</discount_description>';
            
            $this->finalXml .= '<ext_customer_id>' . $od['ext_customer_id'] . '</ext_customer_id>';
            $this->finalXml .= '<ext_order_id>' . $od['ext_order_id'] . '</ext_order_id>';
            
            $this->finalXml .= '<hold_before_state>' . $od['hold_before_state'] . '</hold_before_state>';
            $this->finalXml .= '<hold_before_status>' . $od['hold_before_status'] . '</hold_before_status>';
            $this->finalXml .= '<order_currency_code>' . $od['order_currency_code'] . '</order_currency_code>';

            // raman
            $this->finalXml .= '<base_currency_code>' . $od['base_currency_code'] . '</base_currency_code>';
            $this->finalXml .= '<global_currency_code>' . $od['global_currency_code'] . '</global_currency_code>';
            $this->finalXml .= '<store_currency_code>' . $od['store_currency_code'] . '</store_currency_code>';
            //$this->finalXml .= '<current_currency_rate>'
            //. $this->helper->getCurrentCurrencyRate($od['order_currency_code']) . '</current_currency_rate>';

            $this->finalXml .= '<original_increment_id>'
                . $od['original_increment_id'] . '</original_increment_id>';

            $this->finalXml .= '<relation_child_id>'
                . $od['relation_child_id'] . '</relation_child_id>';

            $this->finalXml .= '<relation_child_real_id>'
                . $od['relation_child_real_id'] . '</relation_child_real_id>';

            $this->finalXml .= '<relation_parent_id>' . $od['relation_parent_id'] . '</relation_parent_id>';
            $this->finalXml .= '<relation_parent_real_id>'
                . $od['relation_parent_real_id'] . '</relation_parent_real_id>';

            $this->finalXml .= '<remote_ip>' . $od['remote_ip'] . '</remote_ip>';
            $this->finalXml .= '<shipping_method>' 
                . $this->helper->addWithCData($od['shipping_method']) . '</shipping_method>';
            
            $this->finalXml .= '<store_name>' 
                . $this->helper->addWithCData($od['store_name']) . '</store_name>';

            //$this->finalXml .= '<x_forwarded_for>' . $od['x_forwarded_for'] . '</x_forwarded_for>';
            $this->finalXml .= '<customer_note>'
                . $this->helper->addWithCData($od['customer_note']) . '</customer_note>';

            $this->finalXml .= '<created_at>'
                . $this->helper->getIso8601DateTime($od['created_at']) . '</created_at>';

            $this->finalXml .= '<created_at_store>'
                . $this->helper->getTimeAccordingToTimeZone($od['created_at']) . '</created_at_store>';

            $this->finalXml .= '<created_at_gmt>' . ($od['created_at']) . '</created_at_gmt>';
            
            $this->finalXml .= '<updated_at>'
                . $this->helper->getIso8601DateTime($od['updated_at']) . '</updated_at>';

            $this->finalXml .= '<updated_at_store>'
                . $this->helper->getTimeAccordingToTimeZone($od['updated_at']) . '</updated_at_store>';

            $this->finalXml .= '<updated_at_gmt>' . ($od['updated_at']) . '</updated_at_gmt>';
            
            $this->finalXml .= '<promise_date>'
                . $this->helper->emptyIfNull($od, 'promise_date') . '</promise_date>';

            $this->finalXml .= '<total_item_count>' . $od['total_item_count'] . '</total_item_count>';
            $this->finalXml .= '<customer_gender>' . $od['customer_gender'] . '</customer_gender>';
            $this->finalXml .= '<discount_tax_compensation_amount>'
                . $od['discount_tax_compensation_amount'] . '</discount_tax_compensation_amount>';

            $this->finalXml .= '<base_discount_tax_compensation_amount>'
                . $od['base_discount_tax_compensation_amount'] . '</base_discount_tax_compensation_amount>';

            $this->finalXml .= '<shipping_discount_tax_compensation_amount>'
                . $od['shipping_discount_tax_compensation_amount']
                . '</shipping_discount_tax_compensation_amount>';
            
            $this->finalXml .= '<base_shipping_discount_tax_compensation_amnt>'
                . $od['base_shipping_discount_tax_compensation_amnt']
                . '</base_shipping_discount_tax_compensation_amnt>';

            $this->finalXml .= '<discount_tax_compensation_invoiced>'
                . $od['discount_tax_compensation_invoiced'] . '</discount_tax_compensation_invoiced>';

            $this->finalXml .= '<base_discount_tax_compensation_invoiced>'
                . $od['base_discount_tax_compensation_invoiced'] . '</base_discount_tax_compensation_invoiced>';
            $this->finalXml .= '<discount_tax_compensation_refunded>'
                . $od['discount_tax_compensation_refunded'] . '</discount_tax_compensation_refunded>';

            $this->finalXml .= '<base_discount_tax_compensation_refunded>'
                . $od['base_discount_tax_compensation_refunded'] . '</base_discount_tax_compensation_refunded>';
            $this->finalXml .= '<shipping_incl_tax>' . $od['shipping_incl_tax'] . '</shipping_incl_tax>';
            $this->finalXml .= '<base_shipping_incl_tax>'
                . $od['base_shipping_incl_tax'] . '</base_shipping_incl_tax>';

            $this->finalXml .= '<coupon_rule_name>'
                . $this->helper->addWithCData($od['coupon_rule_name']) . '</coupon_rule_name>';

            //$this->finalXml .= '<paypal_ipn_customer_notified>'
            //. $od['paypal_ipn_customer_notified'] . '</paypal_ipn_customer_notified>';
            
            $this->finalXml .= '<paypal_ipn_customer_notified>'
                . $this->helper->emptyIfNull($od, 'paypal_ipn_customer_notified')
                . '</paypal_ipn_customer_notified>';

            //$od["gw_price"] = 100;
            if (isset($od["gw_price"])):
                $this->finalXml .= '<gw_price caption="Gift Wrapping for Order">' . $od['gw_price'] . '</gw_price>';
                //$op .= '<gw_card_price>' . $od['gw_card_price'] . '</gw_card_price>';
                //$op .= '<gw_card_tax_amount>' . $od['gw_card_tax_amount'] . '</gw_card_tax_amount>';
                if (!isset($od['gw_items_price'])) {
                    $od['gw_items_price'] = 0;
                }
                if (!isset($od['gw_items_tax_amount'])) {
                    $od['gw_items_tax_amount'] = 0;
                }
                if (!isset($od['gw_printed_card_price'])) {
                    $od['gw_printed_card_price'] = 0;
                }
                if (!isset($od['gw_printed_card_tax_amount'])) {
                    $od['gw_printed_card_tax_amount'] = 0;
                }

                $this->finalXml .= '<gw_items_price>' . $od['gw_items_price'] . '</gw_items_price>';
                $this->finalXml .= '<gw_items_tax_amount>' . $od['gw_items_tax_amount'] . '</gw_items_tax_amount>';
                $this->finalXml .= '<gw_printed_card_price caption="Printed Card">'
                    . $od['gw_printed_card_price'] . '</gw_printed_card_price>';

                $this->finalXml .= '<gw_printed_card_tax_amount>'
                    . $od['gw_printed_card_tax_amount'] . '</gw_printed_card_tax_amount>';
            else:
                $this->finalXml .= '<gw_price caption="Gift Wrapping for Order">0</gw_price>';
                $this->finalXml .= '<gw_items_price>0</gw_items_price>';
                $this->finalXml .= '<gw_items_tax_amount>0</gw_items_tax_amount>';
                $this->finalXml .= '<gw_printed_card_price caption="Printed Card">0</gw_printed_card_price>';
                $this->finalXml .= '<gw_printed_card_tax_amount>0</gw_printed_card_tax_amount>';
            endif;

            $this->finalXml .= '<gift_message>';
            //$this->finalXml .= $this->getGiftMessage($od['gift_message_id']);
            $this->finalXml .= $this->getGiftMessage2($order);
            $this->finalXml .= '</gift_message>';

            //$this->helper->sendToScreen($output);
            $this->getOrderExtensionAttributes($order);
            $this->getOrderItemXml($order);
            $this->getBillingXml($order);
            $this->getShippingXml($order);
            $this->getPaymentXml($order);
            $this->getShipmentXml($order);
            $this->getCommentsXml($order);
            $this->getGiftcertXml($order);

            $this->finalXml .= '</order>';
            /** $this->helper->sendToScreen($output); **/
        endforeach;

        $this->finalXml .= '</orders>';
        /** $this->helper->sendToScreen($output); **/
        unset($collection);

        return $this->finalXml;
    }

    private function getOrders($qryStrArray)
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        
        if (isset($qryStrArray["status"]) && $qryStrArray["status"] != '') {
            // 4.5.0.1 Filter by multiple statuses
            $arrStatuses = explode(',', $qryStrArray["status"]);
            
            $collection->addFieldToFilter('status', ["in" => [$arrStatuses]]);
        }

        if (isset($qryStrArray["from"])
            && $qryStrArray["from"] != ''
            && isset($qryStrArray["to"])
            && $qryStrArray["to"] != ''):
            $f = strtotime($qryStrArray["from"]);
            $from_date = date('Y-m-d' . ' 00:00:00', $f);
            $from_date = $this->helper->timeLocalToUtc($from_date);

            $f = strtotime($qryStrArray["to"]);
            $to_date = date('Y-m-d' . ' 23:59:59', $f);
            $to_date = $this->helper->timeLocalToUtc($to_date);

            $collection->addFieldToFilter(
                'updated_at',
                ['from' => $from_date, 'to' => $to_date, 'datetime' => true]
            );
        //$this->finalXml .= "s";
        endif;

        if (isset($qryStrArray["orderid"]) && $qryStrArray["orderid"] != ''):
            $collection->addFieldToFilter('increment_id', ['in' => [$qryStrArray["orderid"]]]);
        endif;

        if (isset($qryStrArray["rowsperpage"]) && $qryStrArray["rowsperpage"] != "") {
            $this->rowsPerPage = (int) $qryStrArray["rowsperpage"];
        }
        if (isset($qryStrArray["currentpageindex"]) && $qryStrArray["currentpageindex"] != "") {
            $this->currentPageIndex = (int) $qryStrArray["currentpageindex"];
        }

        //6.9.0.0
        if (isset($qryStrArray["storeid"]) && $qryStrArray["storeid"] != ''):
            // Filter by multiple store ids.
            $arrStoreIds = explode(',', $qryStrArray["storeid"]);
            $collection->addFieldToFilter('store_id', ["in" => [$arrStoreIds]]);
        endif;

        $collection
                ->setPageSize($this->rowsPerPage)
                ->setCurPage($this->currentPageIndex)
                ->load();

        return $collection;
    }

    private function getOrderExtensionAttributes($order)
    {
        if(!$order->getExtensionAttributes()):
            return;
        endif;

        if ($this->verbose == 1):
            var_dump($order->getExtensionAttributes());
        endif;
    }

    private function getOrderItemXml($order)
    {
        $orderItems = $order->getAllItems();

        $this->finalXml .= '<orderitems>';
        /** $this->helper->sendToScreen($op); **/

        if ($this->verbose == 1):
            $this->finalXml .= "\r\n ORDERITEM COUNT = " . count($orderItems);
        endif;
        
        foreach ($orderItems as $oi):
            $this->getEachOrderItemXml($orderItems, $oi);
        endforeach;

        $this->finalXml .= '</orderitems>';
        //$this->helper->sendToScreen($op);
    }

    private function getEachOrderItemXml($orderItems, $oi)
    {
        /* foreach ($oi->getData() as $key => $value) {
          $this->finalXml .= "\$op .= '<" . $key . ">' . " . "\$oiArray['" . $key . "']" . ". '</" . $key . ">';";
          }
        */
        $orderitem_data = $oi->getData();
        $product_type = $oi->getProductType();
        $isThisParentItem = $this->isParentInOrder($orderitem_data);

        if ($this->verbose == 1):
            $this->finalXml .= "\r\n ORDERITEM PRODUCT TYPE = " . $product_type;
            //var_dump($orderitem_data);
            //exit;
        endif;

        if ($product_type == 'configurable') {
            return;
        }

        $this->finalXml .= "<orderitem>"; // Start Building XML

        switch ($product_type):
            case "simple":
                $parent_item = $this->getParentItemInOrder($orderItems, $oi);
                //var_dump($parent_item->getProductType());
                if ($parent_item != null):
                    if ($parent_item->getProductType() == 'configurable'):
                        // We got config parent for current order item. Need config parent's price here
                        // to replace the child in xml
                        
                        $orderitem_data_parent = $parent_item->getData();
                        $orderitem_data_parent['item_id'] = $orderitem_data['item_id'];
                        
                        // 4.5.0.2 display child's name in order item.
                        $orderitem_data_parent['name'] = $orderitem_data['name'];
                        
                        /** $orderitem_data_parent['product_type'] = $orderitem_data['product_type']; **/
                        $orderitem_data = $orderitem_data_parent;
                        unset($orderitem_data_parent);
                    elseif ($parent_item->getProductType() == 'bundle'):
                        if ($this->verbose == 1):
                            $this->finalXml .= "\r\n ORDERITEM PRODUCT TYPE = bundle";
                        endif;
                    endif;
                endif;
                unset($parent_item);
                break;
            
            case "virtual":
                $parent_item = $this->getParentItemInOrder($orderItems, $oi);
                //var_dump($parent_item->getProductType());
                //$this->finalXml .= "parent_item == null?" . ($parent_item == null);

                if ($parent_item != null):
                    if ($this->verbose == 1):
                        $this->finalXml .= "\r\n ORDERITEM VIRTUAL, Getting data from parent product type= "
                            . $parent_item->getProductType();
                    endif;

                    if ($parent_item->getProductType() == 'configurable'):
                        // We got config parent for current order item. Need config parent's price here
                        // to replace the child in xml
                        if ($this->verbose == 1):
                            $this->finalXml .= "\r\n ORDERITEM VIRTUAL, Getting data from parent configurable ";
                        endif;

                        $orderitem_data_parent = $parent_item->getData();
                        $orderitem_data_parent['item_id'] = $orderitem_data['item_id'];
                        
                        // 4.5.0.2 display child's name in order item.
                        $orderitem_data_parent['name'] = $orderitem_data['name'];
                        
                        /** $orderitem_data_parent['product_type'] = $orderitem_data['product_type']; **/
                        $orderitem_data = $orderitem_data_parent;
                        unset($orderitem_data_parent);
                    elseif ($parent_item->getProductType() == 'bundle'):
                        if ($this->verbose == 1):
                            $this->finalXml .= "\r\n ORDERITEM PARENT TYPE IS BUNDLE";
                        endif;
                    endif;
                endif;
                unset($parent_item);
                break;

            case "bundle":
                if ($this->verbose == 1):
                    $this->finalXml .= "\r\n ORDERITEM BUNDLE PRODUCT LOADING";
                endif;
                
                /**$product = $this->objectManager->get('Magento\Catalog\Model\Product::c l a s s')
                    ->load($oi->getProductId());
                **/
                $product = $this->productFactory->create()->load($oi->getProductId());

                if ($this->verbose == 1):
                    $this->finalXml .= "\r\n ORDERITEM BUNDLE PRODUCT LOADED";
                endif;
                
                $bundlePriceType = $product->getPriceType();
                
                if ($this->verbose == 1):
                    $this->finalXml .= "\r\n ORDERITEM BUNDLE PRODUCT MOVING TO NEXT PRODUCT - ";
                    var_dump($bundlePriceType);
                    //var_dump (\Magento\Catalog\Model\Product\Type\Price::PRICE_TYPE_FIXED);
                endif;
                
                /** Not working for a client **/
                /** if ((string)$bundlePriceType ==
                * \Magento\Catalog\Model\Product\Type\Price::PRICE_TYPE_FIXED) :
                **/
                if ((string)$bundlePriceType == "1"):
                    if ($this->verbose == 1):
                        $this->finalXml .= "\r\n ORDERITEM BUNDLE PRODUCT MOVING TO NEXT PRODUCT fixed";
                    endif;

                    $isThisParentItem = false;
                    $this->finalXml .= '<bundle_price_type>fixed</bundle_price_type>';
                else:
                    if ($this->verbose == 1):
                        $this->finalXml .= "\r\n ORDERITEM BUNDLE PRODUCT MOVING TO NEXT PRODUCT not fixed";
                    endif;
                    $this->finalXml .= '<bundle_price_type>dynamic</bundle_price_type>';
                endif;
                unset($product);
                
                break;
            default:
                break;
        endswitch;

        $this->finalXml .= '<item_id>' . $orderitem_data['item_id'] . '</item_id>';
        $this->finalXml .= '<parent_item_id>' . $orderitem_data['parent_item_id'] . '</parent_item_id>';
        $this->finalXml .= '<quote_item_id>' . $orderitem_data['quote_item_id'] . '</quote_item_id>';
        $this->finalXml .= '<store_id>' . $orderitem_data['store_id'] . '</store_id>';
        $this->finalXml .= '<product_id>' . $orderitem_data['product_id'] . '</product_id>';
        $this->finalXml .= '<product_type>' . $orderitem_data['product_type'] . '</product_type>';
        
        if ($this->verbose == 1):
            $this->finalXml .= "\r\n ORDERITEM PRODUCT product_options";
            var_dump($orderitem_data['product_options']);
            //exit;
        endif;
        
        $attributes_info = null;
        if (is_array($orderitem_data['product_options'])):
            $attributes_info = isset($orderitem_data['product_options']["attributes_info"]) ?
                $orderitem_data['product_options']["attributes_info"] : null;
        endif;

        // 6.6.1.0 PRint PRODUCT ATTRIBUTE OPTIONS FOR ORDER ITEM
        $this->finalXml .= '<product_options>';
        
        if ($attributes_info != null):
            foreach ($attributes_info as $key => $attrib_data):
                $this->finalXml .= '<option>';
                $this->finalXml .= '<label>' . $this->helper->addWithCData($attrib_data['label']) . '</label>';
                $this->finalXml .= '<value>'
                    . $this->helper->addWithCData($attrib_data['value']) . '</value>';

                $this->finalXml .= '</option>';
            endforeach;
        endif;

        $this->finalXml .= '</product_options>';
        // =======================================================

        // 6.6.2.0 Print PRODUCT (CUSTOMIZABLE) OPTIONS FOR ORDER ITEM
        $product_customizable_options = null;
        if (isset($orderitem_data['product_options']['options'])
            && !empty($orderitem_data['product_options']['options'])):
            $product_customizable_options = $orderitem_data['product_options']["options"];
        endif;

        // 6.10.6 utf8_encode() removed
        $this->finalXml .= '<product_customizable_options>';
        
        if ($product_customizable_options != null):
            foreach ($product_customizable_options as $option):
                $this->finalXml .= '<option>';
                $this->finalXml .= '<label>' . $this->helper->addWithCData($option['label']) . '</label>';
                $this->finalXml .= '<value>'
                    . $this->helper->addWithCData(mb_convert_encoding($option['value'], "UTF-8")) . '</value>';

                //$this->finalXml .= '<caption>' . $this->getCustomOptionPrice() . '</caption>';
                // $oi->getProductOptions()
                //var_dump($option);
                $this->finalXml .= '</option>';
            endforeach;
        endif;

        /** var_dump($oi->getProductOptions()); **/
        foreach ($oi->getProductOptions() as $option):
            $this->finalXml .= '<option>';
            //$this->finalXml .= '<label>' . $option->getLabel() . '</label>';
            //$this->finalXml .= '<value>' . $option->getValue() . '</value>';
            //$this->finalXml .= '<caption>' . $this->getCustomOptionPrice() . '</caption>';
            //var_dump($option->getValues());
            $this->finalXml .= '</option>';
        endforeach;
        $this->finalXml .= '</product_customizable_options>';
        // =======================================================

        // =======================================================
        // 6.8.4    Intrduced additional options if available
        // =======================================================
        $this->finalXml .= '<product_additional_options>';
        if (is_array($orderitem_data['product_options'])):
            $additional_options = isset($orderitem_data['product_options']["additional_options"]) ?
                $orderitem_data['product_options']["additional_options"] : null;

            if ($additional_options != null && is_array($additional_options)):
                foreach ($additional_options as $option):
                    $this->finalXml .= '<option>';
                    
                    $this->finalXml .= '<label>'
                        . $this->helper->addWithCData((string)$option['label'])
                        . '</label>';

                    $this->finalXml .= '<value>'
                        . $this->helper->addWithCData(
                            mb_convert_encoding((string)$option['value'], 'UTF-8')
                        )
                        . '</value>';

                    $this->finalXml .= '</option>';
                endforeach;
            endif;
        endif;
        $this->finalXml .= '</product_additional_options>';

        // =======================================================

        $this->finalXml .= '<weight>' . $orderitem_data['weight'] . '</weight>';
        $this->finalXml .= '<is_virtual>' . $orderitem_data['is_virtual'] . '</is_virtual>';
        $this->finalXml .= '<sku>' . $this->helper->addWithCData($orderitem_data['sku']) . '</sku>';
        $this->finalXml .= '<name>' . $this->helper->addWithCData($orderitem_data['name']) . '</name>';
        //$this->finalXml .= '<description>' . $oiArray['description'] . '</description>';
        $this->finalXml .= '<applied_rule_ids>' . $orderitem_data['applied_rule_ids'] . '</applied_rule_ids>';
        
        $this->finalXml .= '<additional_data>' 
            . $this->helper->addWithCData($orderitem_data['additional_data']) 
            . '</additional_data>';

        $this->finalXml .= '<is_qty_decimal>' . $orderitem_data['is_qty_decimal'] . '</is_qty_decimal>';
        $this->finalXml .= '<no_discount>' . $orderitem_data['no_discount'] . '</no_discount>';
        $this->finalXml .= '<qty_backordered>' . $orderitem_data['qty_backordered'] . '</qty_backordered>';
        $this->finalXml .= '<qty_canceled>' . $orderitem_data['qty_canceled'] . '</qty_canceled>';
        $this->finalXml .= '<qty_invoiced>' . $orderitem_data['qty_invoiced'] . '</qty_invoiced>';
        $this->finalXml .= '<qty_ordered>' . $orderitem_data['qty_ordered'] . '</qty_ordered>';
        $this->finalXml .= '<qty_refunded>' . $orderitem_data['qty_refunded'] . '</qty_refunded>';
        $this->finalXml .= '<qty_shipped>' . $orderitem_data['qty_shipped'] . '</qty_shipped>';
        $this->finalXml .= '<base_cost>' . $orderitem_data['base_cost'] . '</base_cost>';
        $this->finalXml .= '<price>' . $orderitem_data['price'] . '</price>';
        $this->finalXml .= '<base_price>' . $orderitem_data['base_price'] . '</base_price>';
        $this->finalXml .= '<original_price>' . $orderitem_data['original_price'] . '</original_price>';
        $this->finalXml .= '<base_original_price>'
            . $orderitem_data['base_original_price'] . '</base_original_price>';

        $this->finalXml .= '<tax_percent>' . $orderitem_data['tax_percent'] . '</tax_percent>';
        $this->finalXml .= '<tax_amount>' . $orderitem_data['tax_amount'] . '</tax_amount>';
        $this->finalXml .= '<base_tax_amount>' . $orderitem_data['base_tax_amount'] . '</base_tax_amount>';
        $this->finalXml .= '<tax_invoiced>' . $orderitem_data['tax_invoiced'] . '</tax_invoiced>';
        $this->finalXml .= '<base_tax_invoiced>' . $orderitem_data['base_tax_invoiced'] . '</base_tax_invoiced>';
        $this->finalXml .= '<discount_percent>' . $orderitem_data['discount_percent'] . '</discount_percent>';
        $this->finalXml .= '<discount_amount>' . $orderitem_data['discount_amount'] . '</discount_amount>';
        $this->finalXml .= '<base_discount_amount>'
            . $orderitem_data['base_discount_amount'] . '</base_discount_amount>';

        $this->finalXml .= '<discount_invoiced>' . $orderitem_data['discount_invoiced'] . '</discount_invoiced>';
        
        $this->finalXml .= '<base_discount_invoiced>'
            . $orderitem_data['base_discount_invoiced'] . '</base_discount_invoiced>';

        $this->finalXml .= '<amount_refunded>' . $orderitem_data['amount_refunded'] . '</amount_refunded>';
        $this->finalXml .= '<base_amount_refunded>'
            . $orderitem_data['base_amount_refunded'] . '</base_amount_refunded>';

        $this->finalXml .= '<row_total>' . $orderitem_data['row_total'] . '</row_total>';
        $this->finalXml .= '<base_row_total>' . $orderitem_data['base_row_total'] . '</base_row_total>';
        $this->finalXml .= '<row_invoiced>' . $orderitem_data['row_invoiced'] . '</row_invoiced>';
        $this->finalXml .= '<base_row_invoiced>' . $orderitem_data['base_row_invoiced'] . '</base_row_invoiced>';
        $this->finalXml .= '<row_weight>' . $orderitem_data['row_weight'] . '</row_weight>';
        $this->finalXml .= '<base_tax_before_discount>'
            . $orderitem_data['base_tax_before_discount'] . '</base_tax_before_discount>';

        $this->finalXml .= '<tax_before_discount>'
            . $orderitem_data['tax_before_discount'] . '</tax_before_discount>';

        $this->finalXml .= '<ext_order_item_id>' . $orderitem_data['ext_order_item_id'] . '</ext_order_item_id>';
        $this->finalXml .= '<locked_do_invoice>' . $orderitem_data['locked_do_invoice'] . '</locked_do_invoice>';
        $this->finalXml .= '<locked_do_ship>' . $orderitem_data['locked_do_ship'] . '</locked_do_ship>';
        $this->finalXml .= '<price_incl_tax>' . $orderitem_data['price_incl_tax'] . '</price_incl_tax>';
        $this->finalXml .= '<base_price_incl_tax>'
            . $orderitem_data['base_price_incl_tax'] . '</base_price_incl_tax>';

        $this->finalXml .= '<row_total_incl_tax>'
            . $orderitem_data['row_total_incl_tax'] . '</row_total_incl_tax>';

        $this->finalXml .= '<base_row_total_incl_tax>'
            . $orderitem_data['base_row_total_incl_tax'] . '</base_row_total_incl_tax>';

        $this->finalXml .= '<discount_tax_compensation_amount>'
            . $orderitem_data['discount_tax_compensation_amount'] . '</discount_tax_compensation_amount>';

        $this->finalXml .= '<base_discount_tax_compensation_amount>'
            . $orderitem_data['base_discount_tax_compensation_amount']
            . '</base_discount_tax_compensation_amount>';

        $this->finalXml .= '<discount_tax_compensation_invoiced>'
            . $orderitem_data['discount_tax_compensation_invoiced']
            . '</discount_tax_compensation_invoiced>';

        $this->finalXml .= '<base_discount_tax_compensation_invoiced>'
            . $orderitem_data['base_discount_tax_compensation_invoiced']
            . '</base_discount_tax_compensation_invoiced>';
        $this->finalXml .= '<discount_tax_compensation_refunded>'
            . $orderitem_data['discount_tax_compensation_refunded']
            . '</discount_tax_compensation_refunded>';
        $this->finalXml .= '<base_discount_tax_compensation_refunded>'
            . $orderitem_data['base_discount_tax_compensation_refunded']
                . '</base_discount_tax_compensation_refunded>';
        $this->finalXml .= '<tax_canceled>' . $orderitem_data['tax_canceled'] . '</tax_canceled>';
        $this->finalXml .= '<discount_tax_compensation_canceled>'
            . $orderitem_data['discount_tax_compensation_canceled']
                . '</discount_tax_compensation_canceled>';

        $this->finalXml .= '<tax_refunded>' . $orderitem_data['tax_refunded'] . '</tax_refunded>';
        $this->finalXml .= '<base_tax_refunded>' . $orderitem_data['base_tax_refunded'] . '</base_tax_refunded>';
        $this->finalXml .= '<discount_refunded>' . $orderitem_data['discount_refunded'] . '</discount_refunded>';
        $this->finalXml .= '<base_discount_refunded>'
            . $orderitem_data['base_discount_refunded'] . '</base_discount_refunded>';

        $this->finalXml .= '<free_shipping>' . $orderitem_data['free_shipping'] . '</free_shipping>';
        $this->finalXml .= '<gift_message_id>' . $orderitem_data['gift_message_id'] . '</gift_message_id>';
        
        $this->finalXml .= '<gift_message_available>'
            . $orderitem_data['gift_message_available'] . '</gift_message_available>';

        $this->finalXml .=  "</orderitem>";
        //$this->helper->sendToScreen($op);
    }

    private function getCustomOptionPrice($option, $option_id, $option_value)
    {
        $price = '';

        if ($option->getValues() !== null) {
            $values = $option->getValues();
            foreach ($values as $key => $value) {
                if ($key === intval($option_value)) {
                    $optionValues = $values[$option_value]->getData();
                    if ($optionValues['price'] !== null && $optionValues['price'] != "0.0000") {
                        $price =
                        ' (+ '
                        . $this->amount->convertAndFormat(floatval($optionValues['price']), false) . ')';
                    }
                }
            }
        } elseif ($option->getValues() === null) {
            $optionData = $option->getData();
            if ($optionData['option_id'] == $option_id
                && ($optionData['price'] != "0.0000"
                && $optionData['price'] !== null)) {
                $price = ' (+ ' . $this->amount->convertAndFormat(floatval($optionData['price']), false) . ')';
            }
        }
        return $price;
    }

    private function getBillingXml($order)
    {
        $this->finalXml .= "<billing>";
        $billing_data = $order->getBillingAddress()->getData();

        //var_dump($billing_data);
        //foreach ($billing_data as $key => $value)
        //     $this->finalXml .= "\$op .= '<" . $key . ">'"
        //. " . " . "\$billing_data['" . $key . "']" . " . " . "'</" . $key . ">'" . ";";
        
        if ($this->verbose == 1):
            var_dump(["BILLING DATA" => $billing_data]);
        endif;

        $street1 = "";
        $street2 = "";

        $street = $billing_data["street"];
        $street = null;
        // 6.10.2
        //$street_array = explode("\n", $street);
        $street_array = explode("\n", $street ?? "");
            
        if (count($street_array) > 1):
            $street1 = $street_array[0];
            $street2 = $street_array[1];
        else:
            $street1 = $street;
        endif;

        $country_id = "";
        $region_code = "";
        $region_name = $billing_data['region'];
        $region_code = $this->getRegionCodeByName($region_name, $billing_data['country_id']);

        $this->finalXml .= '<entity_id>' . $billing_data['entity_id'] . '</entity_id>';
        $this->finalXml .= '<parent_id>' . $billing_data['parent_id'] . '</parent_id>';
        $this->finalXml .= '<customer_address_id>' . $billing_data['customer_address_id'] . '</customer_address_id>';
        $this->finalXml .= '<quote_address_id>' . $billing_data['quote_address_id'] . '</quote_address_id>';

        $this->finalXml .= '<customer_id>' . $billing_data['customer_id'] . '</customer_id>';
        $this->finalXml .= '<fax>' . $billing_data['fax'] . '</fax>';

        $this->finalXml .= '<region_id>' . $billing_data['region_id'] . '</region_id>';
        $this->finalXml .= '<region><![CDATA[' . $region_code . ']]></region>';
        $this->finalXml .= '<region_name><![CDATA[' . $region_name . ']]></region_name>';

        $this->finalXml .= '<postcode><![CDATA[' . $billing_data['postcode'] . ']]></postcode>';
        $this->finalXml .= '<lastname><![CDATA[' . $billing_data['lastname'] . ']]></lastname>';

        $this->finalXml .= '<street><![CDATA[' . $billing_data['street'] . ']]></street>';
        $this->finalXml .= '<street1><![CDATA[' . $street1 . ']]></street1>';
        $this->finalXml .= '<street2><![CDATA[' . $street2 . ']]></street2>';

        $this->finalXml .= '<city><![CDATA[' . $billing_data['city'] . ']]></city>';
        $this->finalXml .= '<email><![CDATA[' . $billing_data['email'] . ']]></email>';
        $this->finalXml .= '<telephone><![CDATA[' . $billing_data['telephone'] . ']]></telephone>';
        $this->finalXml .= '<country_id>' . $billing_data['country_id'] . '</country_id>';
        $this->finalXml .= '<firstname><![CDATA[' . $billing_data['firstname'] . ']]></firstname>';
        $this->finalXml .= '<address_type>' . $billing_data['address_type'] . '</address_type>';
        
        $this->finalXml .= '<prefix>' 
            . $this->helper->addWithCData($billing_data['prefix']) . '</prefix>';

        $this->finalXml .= '<middlename>' 
                . $this->helper->addWithCData($billing_data['middlename']) . '</middlename>';

        $this->finalXml .= '<suffix>' 
            . $this->helper->addWithCData($billing_data['suffix']) . '</suffix>';

        $this->finalXml .= '<company>' 
            . $this->helper->addWithCData($billing_data['company']) . '</company>';

        $this->finalXml .= '<address_id>'
            . $this->helper->emptyIfNull($billing_data, 'address_id') . '</address_id>';

        $this->finalXml .= '<gift_message_id>'
            . $this->helper->emptyIfNull($billing_data, 'gift_message_id') . '</gift_message_id>';

        $this->finalXml .= '<tax_id>' . $this->helper->emptyIfNull($billing_data, 'tax_id') . '</tax_id>';
        $this->finalXml .= '<vat_id>' . $this->helper->emptyIfNull($billing_data, 'vat_id') . '</vat_id>';
        $this->finalXml .= '<vat_is_valid>'
            . $this->helper->emptyIfNull($billing_data, 'vat_is_valid') . '</vat_is_valid>';

        /**
        $this->finalXml .= '<vat_request_id>'
            . $this->helper->emptyIfNull($billing_data, 'vat_request_id') . '</vat_request_id>';
        $this->finalXml .= '<vat_request_date>'
            . $this->helper->emptyIfNull($billing_data, 'vat_request_date') . '</vat_request_date>';
        $this->finalXml .= '<vat_request_success>'
            . $this->helper->emptyIfNull($billing_data, 'vat_request_success') . '</vat_request_success>';
        **/

        $this->finalXml .= '<citizen_id>'
            . $this->helper->emptyIfNull($billing_data, 'citizen_id') . '</citizen_id>';

        $this->finalXml .= "</billing>";
        /** $this->helper->sendToScreen($op); **/
    }

    private function getShippingXml($order)
    {
        if (!$order->getShippingAddress()) {
            $this->finalXml .= "<shipping></shipping>";
            return;
        }

        $this->finalXml .= "<shipping>";
        $shipping_data = $order->getShippingAddress()->getData();

        if ($this->verbose == 1):
            var_dump(["SHIPPING DATA" => $shipping_data]);
        endif;

        $street1 = "";
        $street2 = "";

        $street = $shipping_data["street"];
        $street = null;

        //$street2 = $order->getBillingAddress()->getStreet(2);
        
        // 6.10.2
        //$street_array = explode("\n", $street);
        $street_array = explode("\n", $street ?? "");

        if (count($street_array) > 1):
            $street1 = $street_array[0];
            $street2 = $street_array[1];
        else:
            $street1 = $street;
        endif;

        $country_id = "";
        $region_code = "";
        $region_name = $shipping_data['region'];
        $region_code = $this->getRegionCodeByName($region_name, $shipping_data['country_id']);

        $this->finalXml .= '<entity_id>' . $shipping_data['entity_id'] . '</entity_id>';
        $this->finalXml .= '<parent_id>' . $shipping_data['parent_id'] . '</parent_id>';
        $this->finalXml .= '<customer_address_id>' . $shipping_data['customer_address_id'] . '</customer_address_id>';
        $this->finalXml .= '<quote_address_id>' . $shipping_data['quote_address_id'] . '</quote_address_id>';
        
        $this->finalXml .= '<customer_id>' . $shipping_data['customer_id'] . '</customer_id>';
        $this->finalXml .= '<fax>' . $shipping_data['fax'] . '</fax>';
        
        $this->finalXml .= '<region_id>' . $shipping_data['region_id'] . '</region_id>';
        $this->finalXml .= '<region><![CDATA[' . $region_code . ']]></region>';
        $this->finalXml .= '<region_name><![CDATA[' . $region_name . ']]></region_name>';

        $this->finalXml .= '<postcode><![CDATA[' . $shipping_data['postcode'] . ']]></postcode>';
        $this->finalXml .= '<lastname><![CDATA[' . $shipping_data['lastname'] . ']]></lastname>';

        $this->finalXml .= '<street><![CDATA[' . $shipping_data['street'] . ']]></street>';
        $this->finalXml .= '<street1><![CDATA[' . $street1 . ']]></street1>';
        $this->finalXml .= '<street2><![CDATA[' . $street2 . ']]></street2>';

        $this->finalXml .= '<city><![CDATA[' . $shipping_data['city'] . ']]></city>';
        $this->finalXml .= '<email><![CDATA[' . $shipping_data['email'] . ']]></email>';
        $this->finalXml .= '<telephone><![CDATA[' . $shipping_data['telephone'] . ']]></telephone>';
        $this->finalXml .= '<country_id>' . $shipping_data['country_id'] . '</country_id>';
        $this->finalXml .= '<firstname><![CDATA[' . $shipping_data['firstname'] . ']]></firstname>';
        $this->finalXml .= '<address_type>' . $shipping_data['address_type'] . '</address_type>';
        
        $this->finalXml .= '<prefix>' 
            . $this->helper->addWithCData($shipping_data['prefix']) . '</prefix>';

        $this->finalXml .= '<middlename>' 
            . $this->helper->addWithCData($shipping_data['middlename']) . '</middlename>';

        $this->finalXml .= '<suffix>' 
            . $this->helper->addWithCData($shipping_data['suffix']) . '</suffix>';

        $this->finalXml .= '<company>' 
            . $this->helper->addWithCData($shipping_data['company']) . '</company>';
        
        $this->finalXml .= '<address_id>' . $this->helper->emptyIfNull($shipping_data, 'address_id') . '</address_id>';
        $this->finalXml .= '<gift_message_id>'
            . $this->helper->emptyIfNull($shipping_data, 'gift_message_id') . '</gift_message_id>';

        // V 18.4
        if ($order->getShippingAddress()->getDeliveryDate()):
            // V18.4.1 : convertGmtDateToDefaultTimeZone() removed from delivery date
            $this->finalXml .= '<delivery_date>'
                . ($order->getShippingAddress()->getDeliveryDate()) . '</delivery_date>';
        else:
            $this->finalXml .= '<delivery_date></delivery_date>';
        endif;

        $this->finalXml .= '<citizen_id>'
            . $this->helper->emptyIfNull($shipping_data, 'citizen_id') . '</citizen_id>';

        $this->finalXml .= "</shipping>";
        /** $this->helper->sendToScreen($op); **/
    }

    private function getPaymentXml($order)
    {
        $this->finalXml .= "<payment>";
        $payment_data = $order->getPayment()->getData();

        /**
        *foreach ($payment_data as $key => $value)
        *    $this->finalXml .= "\$this->finalXml .= '<" . $key . ">'"
        *. " . " . "\$payment_data['" . $key . "']" . " . " . "'</" . $key . ">'" . ";";
        **/
        if ($this->verbose == 1):
            var_dump(["PAYMENT DATA" => $payment_data]);
        endif;

        $paymentTransactionArray = $this->getPaymentTransactionArray($payment_data);
        // If this array contains valid info, we overwrite payment_data
        if ($paymentTransactionArray && $paymentTransactionArray['last_trans_id'] != ''):
            $payment_data['last_trans_id'] = $paymentTransactionArray['last_trans_id'];
            $payment_data['processed_amount'] = $paymentTransactionArray['processed_amount'];
            $payment_data['cc_last4'] = $paymentTransactionArray['cc_last4'];
            $payment_data['cc_type'] = $paymentTransactionArray['cc_type'];
            $payment_data['cc_exp_month'] = $paymentTransactionArray['cc_exp_month'];
            $payment_data['cc_exp_year'] = $paymentTransactionArray['cc_exp_year'];
        //$payment_data['captured_amount'] = $paymentTransactionArray['captured_amount'];
        //$payment_data['refunded_amount'] = $paymentTransactionArray['refunded_amount'];
        else:
            $payment_data['last_trans_id'] = $this->getTransactionId($order);
        endif;

        $this->finalXml .= '<entity_id>' . $payment_data['entity_id'] . '</entity_id>';
        $this->finalXml .= '<parent_id>' . $payment_data['parent_id'] . '</parent_id>';

        $this->finalXml .= '<base_shipping_captured>'
            . $this->getDecimalValue($payment_data, 'base_shipping_captured') . '</base_shipping_captured>';

        $this->finalXml .= '<shipping_captured>'
            . $this->getDecimalValue($payment_data, 'shipping_captured') . '</shipping_captured>';

        $this->finalXml .= '<amount_refunded>'
            . $this->getDecimalValue($payment_data, 'amount_refunded') . '</amount_refunded>';

        $this->finalXml .= '<base_amount_paid>'
            . $this->getDecimalValue($payment_data, 'base_amount_paid') . '</base_amount_paid>';

        $this->finalXml .= '<amount_canceled>'
            . $this->getDecimalValue($payment_data, 'amount_canceled') . '</amount_canceled>';
        
        $this->finalXml .= '<base_amount_authorized>'
            . $this->getDecimalValue($payment_data, 'base_amount_authorized') . '</base_amount_authorized>';

        $this->finalXml .= '<base_amount_paid_online>'
            . $this->getDecimalValue($payment_data, 'base_amount_paid_online') . '</base_amount_paid_online>';
        
        $this->finalXml .= '<base_amount_refunded_online>'
            . $this->getDecimalValue($payment_data, 'base_amount_refunded_online')
            . '</base_amount_refunded_online>';
        
        $this->finalXml .= '<base_shipping_amount>'
            . $this->getDecimalValue($payment_data, 'base_shipping_amount') . '</base_shipping_amount>';

        $this->finalXml .= '<shipping_amount>'
            . $this->getDecimalValue($payment_data, 'shipping_amount') . '</shipping_amount>';
        
        $this->finalXml .= '<amount_paid>'
            . $this->getDecimalValue($payment_data, 'amount_paid') . '</amount_paid>';
        
        $this->finalXml .= '<amount_authorized>'
            . $this->getDecimalValue($payment_data, 'amount_authorized') . '</amount_authorized>';
        
        $this->finalXml .= '<base_amount_ordered>'
            . $this->getDecimalValue($payment_data, 'base_amount_ordered') . '</base_amount_ordered>';
        
        $this->finalXml .= '<base_shipping_refunded>'
            . $this->getDecimalValue($payment_data, 'base_shipping_refunded') . '</base_shipping_refunded>';

        $this->finalXml .= '<shipping_refunded>'
            . $this->getDecimalValue($payment_data, 'shipping_refunded') . '</shipping_refunded>';
        
        $this->finalXml .= '<base_amount_refunded>'
            . $this->getDecimalValue($payment_data, 'base_amount_refunded') . '</base_amount_refunded>';

        $this->finalXml .= '<amount_ordered>'
            . $this->getDecimalValue($payment_data, 'amount_ordered') . '</amount_ordered>';

        $this->finalXml .= '<base_amount_canceled>'
            . $this->getDecimalValue($payment_data, 'base_amount_canceled') . '</base_amount_canceled>';

        $this->finalXml .= '<ideal_transaction_checked>'
            . $this->helper->emptyIfNull($payment_data, 'ideal_transaction_checked')
            . '</ideal_transaction_checked>';

        $this->finalXml .= '<quote_payment_id>' . $payment_data['quote_payment_id'] . '</quote_payment_id>';
        /**
        * $this->finalXml .= '<additional_data><![CDATA['
            . $payment_data['additional_data'] . ']]></additional_data>';
        **/

        $this->finalXml .= '<cc_exp_month>' . $payment_data['cc_exp_month'] . '</cc_exp_month>';
        //$this->finalXml .= '<cc_ss_start_year>' . $payment_data['cc_ss_start_year'] . '</cc_ss_start_year>';
        $this->finalXml .= '<echeck_bank_name>' . $payment_data['echeck_bank_name'] . '</echeck_bank_name>';
        $this->finalXml .= '<method><![CDATA[' . $payment_data['method'] . ']]></method>';
        
        /** //$this->finalXml .= '<cc_debug_request_body>' . $payment_data['cc_debug_request_body']
        * . '</cc_debug_request_body>';
        * $this->finalXml .= '<cc_secure_verify>' . $payment_data['cc_secure_verify'] . '</cc_secure_verify>';
        * $this->finalXml .= '<cybersource_token>'
        * .  $this->_helper->EmptyIfNull($payment_data, 'cybersource_token') . '</cybersource_token>';
        */

        $this->finalXml .= '<cc_approval>' . $payment_data['cc_approval'] . '</cc_approval>';
        //$this->finalXml .= '<cc_last4>' . $payment_data['cc_last4'] . '</cc_last4>';
        $this->finalXml .= '<cc_status_description>'
            . $payment_data['cc_status_description'] . '</cc_status_description>';

        $this->finalXml .= '<echeck_type>' . $payment_data['echeck_type'] . '</echeck_type>';
        $this->finalXml .= '<paybox_question_number>'
            . $this->helper->emptyIfNull($payment_data, 'paybox_question_number') . '</paybox_question_number>';
        
        //$this->finalXml .= '<cc_debug_response_serialized>'
        //. $payment_data['cc_debug_response_serialized'] . '</cc_debug_response_serialized>';

        //$this->finalXml .= '<cc_ss_start_month>' . $payment_data['cc_ss_start_month'] . '</cc_ss_start_month>';
        $this->finalXml .= '<echeck_account_type>'
            . $payment_data['echeck_account_type'] . '</echeck_account_type>';

        //$this->finalXml .= '<last_trans_id>' . $payment_data['last_trans_id'] . '</last_trans_id>';
        //$this->finalXml .= '<cc_cid_status>' . $payment_data['cc_cid_status'] . '</cc_cid_status>';
        $this->finalXml .= '<cc_owner><![CDATA[' . $payment_data['cc_owner'] . ']]></cc_owner>';
        $this->finalXml .= '<cc_type>' . $payment_data['cc_type'] . '</cc_type>';
        
        //$this->finalXml .= '<ideal_issuer_id>'
        //.  $this->_helper->EmptyIfNull($payment_data, 'ideal_issuer_id') . '</ideal_issuer_id>';
        
        $this->finalXml .= '<po_number><![CDATA[' . $payment_data['po_number'] . ']]></po_number>';
        $this->finalXml .= '<cc_exp_year>' . $payment_data['cc_exp_year'] . '</cc_exp_year>';
        $this->finalXml .= '<cc_status>' . $payment_data['cc_status'] . '</cc_status>';
        $this->finalXml .= '<echeck_routing_number>'
            . $payment_data['echeck_routing_number'] . '</echeck_routing_number>';

        $this->finalXml .= '<account_status>' . $payment_data['account_status'] . '</account_status>';
        //$this->finalXml .= '<anet_trans_method>' . $payment_data['anet_trans_method'] . '</anet_trans_method>';
        
        /**
        * //$this->finalXml .= '<cc_debug_response_body>' . $payment_data['cc_debug_response_body']
        * . '</cc_debug_response_body>';
        * $this->finalXml .= '<cc_ss_issue>' . $payment_data['cc_ss_issue'] . '</cc_ss_issue>';
        **/

        $this->finalXml .= '<echeck_account_name>'
            . $payment_data['echeck_account_name'] . '</echeck_account_name>';

        //$this->finalXml .= '<cc_avs_status>' . $payment_data['cc_avs_status'] . '</cc_avs_status>';
        //$this->finalXml .= '<cc_number_enc>' . $payment_data['cc_number_enc'] . '</cc_number_enc>';
        $this->finalXml .= '<cc_trans_id>' . $payment_data['cc_trans_id'] . '</cc_trans_id>';
        
        //$this->finalXml .= '<address_status>' . $payment_data['address_status'] . '</address_status>';
        
        $this->finalXml .= '<amount>' . $this->getDecimalValue($payment_data, 'processed_amount') . '</amount>';
        
        //$this->finalXml .= '<cc_raw_request>'
        //. $this->_helper->EmptyIfNull($payment_data, 'cc_raw_request') . '</cc_raw_request>';
        
        //$this->finalXml .= '<cc_raw_response>'
        //.  $this->_helper->EmptyIfNull($payment_data, 'cc_raw_response') . '</cc_raw_response>';
        //$this->finalXml .= '<customer_payment_id>'
        //.  $this->_helper->EmptyIfNull($payment_data, 'customer_payment_id') . '</customer_payment_id>';

        $this->finalXml .= '<last_trans_id>' . $payment_data['last_trans_id'] . '</last_trans_id>';

        $this->finalXml .= "</payment>";
        /** $this->helper->sendToScreen($op); **/
    }

    private function getShipmentXml($order)
    {
        $shipmentCollection = $this->shipmentCollFactory->create();
        //$shipmentCollection->addFieldToFilter( 'status', array("in" => array($qryStrArray["status"])) );
        $shipmentCollection->setOrderFilter($order)->load();
        $this->finalXml .= '<shipments>';

        foreach ($shipmentCollection as $shipment) {
            $this->finalXml .= '<shipment>';
            // This will give me the shipment IncrementId, but not the actual tracking information.
            $shipment_data = $shipment->getData();

            $this->finalXml .= '<entity_id>' . $shipment_data['entity_id'] . '</entity_id>';
            $this->finalXml .= '<store_id>' . $shipment_data['store_id'] . '</store_id>';
            $this->finalXml .= '<total_weight>' . $shipment_data['total_weight'] . '</total_weight>';
            $this->finalXml .= '<total_qty>' . $shipment_data['total_qty'] . '</total_qty>';
            $this->finalXml .= '<email_sent>' . $shipment_data['email_sent'] . '</email_sent>';
            $this->finalXml .= '<shipping_address_id>'
                . $shipment_data['shipping_address_id'] . '</shipping_address_id>';

            $this->finalXml .= '<billing_address_id>'
                . $shipment_data['billing_address_id'] . '</billing_address_id>';

            $this->finalXml .= '<shipment_status>' . $shipment_data['shipment_status'] . '</shipment_status>';
            $this->finalXml .= '<increment_id>' . $shipment_data['increment_id'] . '</increment_id>';
            $this->finalXml .= '<created_at>'
                . $this->helper->convertGmtDateToDefaultTimeZone($shipment_data['created_at'], true)
                . '</created_at>';
                
            //$this->finalXml .= '<created_at_gmt>' . $shipment_data['created_at'] . '</created_at_gmt>';

            $this->finalXml .= '<updated_at>'
                . $this->helper->convertGmtDateToDefaultTimeZone($shipment_data['updated_at'], true)
                . '</updated_at>';

            //$this->finalXml .= '<updated_at_gmt>' . $shipment_data['updated_at'] . '</updated_at_gmt>';
            //$this->finalXml .= '<packages>' . $shipment_data['packages'] . '</packages>';
            $this->finalXml .= '<shipping_label>'
                . $this->helper->addWithCData($shipment_data['shipping_label']) . '</shipping_label>';

            $this->finalXml .= '<customer_note><![CDATA[' . $shipment_data['customer_note'] . ']]></customer_note>';

            $tracking_data = $shipment->getAllTracks();
            $this->finalXml .= '<trackings>';
            
            if ($tracking_data):
                foreach ($tracking_data as $tracking):
                    $this->finalXml .= '<tracking>';
                    $this->finalXml .= '<track_number>' . $tracking->gettrack_number() . '</track_number>';
                    $this->finalXml .= '<title>' . $this->helper->addWithCData($tracking->gettitle()) . '</title>';
                    $this->finalXml .= '<number>' . $tracking->getnumber() . '</number>';
                    $this->finalXml .= '<carrier_code>'
                        . $this->helper->addWithCData($tracking->getcarrier_code()) . '</carrier_code>';

                    $this->finalXml .= '<weight>' . $tracking->getweight() . '</weight>';
                    $this->finalXml .= '<updated_at>'
                        . $this->helper->convertGmtDateToDefaultTimeZone(
                            $tracking->getupdated_at(),
                            true
                        ) . '</updated_at>';

                    $this->finalXml .= '<updated_at_gmt>' . $tracking->getupdated_at() . '</updated_at_gmt>';

                    $this->finalXml .= '</tracking>';
                endforeach;
            endif;
            $this->finalXml .= '</trackings>';

            $this->finalXml .= '</shipment>';
        }

        $this->finalXml .= '</shipments>';
        /** $this->helper->sendToScreen($op); **/
    }

    private function getCommentsXml($order)
    {
        $this->finalXml .= '<comments>';

        foreach ($order->getAllStatusHistory() as $orderComment) {
            $comment_data = $orderComment;
            $this->finalXml .= '<comment>';
            $this->finalXml .= '<entity_id>' . $comment_data['entity_id'] . '</entity_id>';
            //$this->finalXml .= '<parent_id>' . $comment_data['parent_id'] . '</parent_id>';
            $this->finalXml .= '<is_customer_notified>'
                . $comment_data['is_customer_notified'] . '</is_customer_notified>';

            $this->finalXml .= '<is_visible_on_front>'
                . $comment_data['is_visible_on_front'] . '</is_visible_on_front>';

            $this->finalXml .= '<comment><![CDATA[' . $comment_data['comment'] . ']]></comment>';
            $this->finalXml .= '<status>' . $comment_data['status'] . '</status>';
            $this->finalXml .= '<created_at>' .
                $this->helper->convertGmtDateToDefaultTimeZone($comment_data['created_at'], true)
                . '</created_at>';

            $this->finalXml .= '<created_at_gmt>' . $comment_data['created_at'] . '</created_at_gmt>';
            $this->finalXml .= '<entity_name><![CDATA[' . $comment_data['entity_name'] . ']]></entity_name>';
            $this->finalXml .= '<store_id>' . $comment_data['store_id'] . '</store_id>';
            $this->finalXml .= '</comment>';
        }
        $this->finalXml .= '</comments>';
        /** $this->helper->sendToScreen($op); **/
    }

    private function getGiftcertXml($order)
    {
        $this->finalXml .= "<giftcards>";
        $gift_cards = $order->getGiftCards();

        if ($gift_cards && is_array($gift_cards) && count($gift_cards) > 0):
            foreach ($gift_cards as $gce) {
                $this->finalXml .= "<giftcard>";
                //$gc = $giftCartXML->addChild('giftcard');

                foreach ($gce->getData() as $key => $value) {
                    $value = htmlentities((string) $value);
                    $this->finalXml .= '<' . $key . '>' . $value . '</' . $key . '>';
                }
                $this->finalXml .= "</giftcard>";
            }
        endif;

        /**
         * 6.8.7.10
         * https://stackoverflow.com/questions/11236263/magento-getting-giftcard-code-and-amount
         **/
        if ($this->verbose == 1):
            $this->finalXml .= "GIFTCARD: ";
            var_dump($order->getGiftCards());
        endif;

        $data = $order->getGiftCards();
        /** 6.8.7.12 try-catch block introduced
        * $data = 'a:2:{i:0;s:16:"as:45:"d"';
        **/

        if($data != null):
            try {
                $cards = unserialize($data);

                foreach ($cards as $card):
                    $this->finalXml .= "<giftcard>";
                    $this->finalXml .= "<code>" . $card['c'] . "</code>";
                    $this->finalXml .= "<amount>" . $card['a'] . "</amount>";
                    $this->finalXml .= "</giftcard>";
                endforeach;
            } catch (\Exception $ex) {
                if ($this->verbose == 1):
                    $this->finalXml .= "\r\n GIFTMESSAGE getGiftcertXml unserialize Exception = " . $ex->getMessage();
                endif;
            }
        endif;

        $this->finalXml .= "</giftcards>";
    }

    private function getGiftMessage2($order)
    {
        /** var_dump($order->getGiftMessage()); exit; **/
        if ($order->getGiftMessageId() == null) {
            return "";
        }
        
        try {
            $giftMessage = $this->giftMessageOrderRepository->get($order->getEntityId());
            
            if ($giftMessage == null) {
                return "";
            }
            
            $giftMessageArray = $giftMessage->getData();
            
            $op = '';
            if ($giftMessageArray !== null && isset($giftMessageArray["gift_message_id"])):

                $op .= '<sender><![CDATA[' . $giftMessage->getSender() . ']]></sender>';
                $op .= '<recipient><![CDATA[' . $giftMessage->getRecipient() . ']]></recipient>';
                $op .= '<text><![CDATA[' . $giftMessage->getMessage() . ']]></text>';

                unset($giftMessage);
            endif;

            return $op;
        } catch (\Exception $ex) {
            if ($this->verbose == 1):
                $this->finalXml .= "\r\n GIFTMESSAGE getGiftMessage2 Exception = " . $ex->getMessage();
            endif;

            return "";
        }
    }
    
    private function isParentInOrder($orderitem_data)
    {
        $_isParentInOrder = false;
        //$this->finalXml .= 'xxx-'.$orderitem_data['parent_item_id']. '-xxx';
        //if(isset($orderitem_data['parent_item_id'])):
        if ($orderitem_data['parent_item_id'] == '' || $orderitem_data['parent_item_id'] == '0'):
            switch ($orderitem_data['product_type']) {
                case 'bundle':
                    $_isParentInOrder = true;
                    break;
                case 'configurable':
                    $_isParentInOrder = false;
                    break;
                case 'grouped':
                    $_isParentInOrder = false;
                    break;
                default:
                    break;
            }
        endif;
        //endif;
        return $_isParentInOrder;
    }

    private function getParentItemInOrder($itemCollection, $child_item)
    {
        // if ($child_item->getProductType() !== 'simple')
        if (!($child_item->getProductType() == 'simple' || $child_item->getProductType() == 'virtual')) {
            return null;
        }
        if (is_null($child_item->getParentItemId())) {
            return null;
        }

        foreach ($itemCollection as $oi):
            if ($oi->getItemId() == $child_item->getParentItemId()):
                return $oi;
            endif;
        endforeach;

        return null;
    }

    private function getDecimalValue($od, $fldname)
    {
        if (!isset($od[$fldname])) {
            return '0.00';
        }
        if (empty($od[$fldname])) {
            return '0.00';
        }
        return floatval($od[$fldname]);
    }

    private function getRegionCodeByName($regionName, $countryCode)
    {
        try {
            $regions = $this->regionFactory->create()->getCollection()->addFieldToFilter('country_id', $countryCode);
            
            foreach ($regions as $region):
                if ($region["name"] == $regionName):
                    return $region["code"];
                endif;
            endforeach;
            return "";
        } catch (\Exception $ex) {
            return "";
        }
    }

    private function getPaymentTransactionArray($payment_data)
    {
        $array = [];

        $array['last_trans_id'] = '';
        $array['processed_amount'] = 0.0;
        $array['cc_last4'] = '';
        $array['cc_type'] = '';
        $array['cc_exp_month'] = '';
        $array['cc_exp_year'] = '';
        $array['captured_amount'] = 0.0;
        $array['refunded_amount'] = 0.0;

        if (isset($payment_data['additional_information'])
            && count($payment_data['additional_information']) > 0):
            //$this->finalXml .= 'additional_information exists.';
            if (isset($payment_data['additional_information']['authorize_cards'])
                    && count($payment_data['additional_information']['authorize_cards']) > 0):
                //$this->finalXml .= 'additional_information> card exists.';
                $rawData = $payment_data['additional_information']['authorize_cards'];
                if ($rawData):
                    reset($rawData);
                    $first_key = key($rawData);
                    //$this->finalXml .= 'first key ' . $first_key;
                    if ($first_key):
                        $array['last_trans_id'] = $rawData[$first_key]['last_trans_id'];
                        $array['processed_amount'] = $rawData[$first_key]['processed_amount'];
                        $array['cc_last4'] = $rawData[$first_key]['cc_last4'];
                        $array['cc_type'] = $rawData[$first_key]['cc_type'];
                        $array['cc_exp_month'] = $rawData[$first_key]['cc_exp_month'];
                        $array['cc_exp_year'] = $rawData[$first_key]['cc_exp_year'];
                        $array['captured_amount'] =
                            isset($rawData[$first_key]['captured_amount']) ?
                            $rawData[$first_key]['captured_amount'] : '';

                        $array['refunded_amount'] =
                            isset($rawData[$first_key]['refunded_amount']) ?
                                $rawData[$first_key]['refunded_amount'] : '';
                    endif;
                endif;
            endif;
        endif;
        return $array;
    }

    private function getTransactionId($order)
    {
        $transaction_id = '';
        $transaction_id = $order->getPayment()->getLastTransId();

        /**
        if (empty($transaction_id) || $transaction_id == '') {
            $cardsStorageModel = Mage::getModel('paygate/authorizenet_cards');
            if ($cardsStorageModel) {
                $cardsStorage = $cardsStorageModel->setPayment($order->getPayment());
                if ($cardsStorage) {
                    foreach ($cardsStorage->getCards() as $card) {
                        $transaction_id = $card->getLastTransId();
                        break;
                    }
                }
            }
        }
        **/

        return $transaction_id;
    }
}
