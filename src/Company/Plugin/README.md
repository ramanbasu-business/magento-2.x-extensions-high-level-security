# Company extension for Magento(Plugin)

Module company\plugin implements integration with the Company system.

## Overview

This extension allows merchants to streamline their processing of inventory, listings and orders seamlessly between their magento stores and their Company account. Additionally this allows merchants to develop and manage accounts on other marketplaces all from one system without disrupting their existing web store workflows.

## Features

There are few magento entities which are required to be stored in Company. This allows merchants to manage their data internally with Company. Once setup, the merchant user is able to sync the updated information from Company via a simple action. Again, the merchant is able to import the orders from their magento website. This allows merchants to control, review and manage their consolidated balances from within their Company account.

### Import into Company from Magento website
The data from the magento site is exported as XML.
In order to help the user manage the magento data in Company, the following entities are imported into Company:
  i.  Product information & Inventory
  ii. Product Categories
  iii.Orders
  iv. Carriers / Order shipping methods
  v.  Customer groups

### Export from Company to Magento website
To push the data from Company, XML is used and posted to the magento site. The Plugin, then stores the request as XML in the ‘job’ table of the magento database. Subsequently, the requested XML is processed to update entities like products, orders etc.
  i. Product Information – Product types such as normal, configurable (along with children skus) are sent for export. Such products contain categories, custom attributes, store info, descriptions and other details.

  ii. Inventory Synchronization – Inventory adjustment in Company or the remote magento site as well as an order creation in magento site have an impact on product stock. Company tries to sync the data at regular intervals.

  iii. Order Shipment – Shipment tracking for the orders can be created in Company and the same can be exported to mark those shipped and complete. For an unpaid order, invoice can also be created subject to the given rules.

  iv. Order Cancellation / Refund – An order, cancelled in Company, can be synchronized to have the same effect on magento site. For refunds, where an order was paid with an ONLINE payment method, can be refunded, subject to the rules prescribed by Magento.

  v. Order Payment Capture – When an order has an authorized payment, Company is able to capture the payment from within it’s interface.

### Inventory sync
In some magento versions(2.3.x), MSI may be enabled. The value in "qty" in the xml for invprice update to magento site indicates that the "default" source is to be updated with the qty. Alternatively, this field can be left empty and <msi_sources> may be used to specify which source needs update with which quantity.
