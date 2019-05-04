Custom Woocommerce plugin developed for infinitishine.com that powers Custom Woocommerce functions. The primary function of this plugin is to handle all needed product customizations and handle checking and updating products against the Quintessence API.

To set up, clone this project to wp-content/plugins/

* run npm install

and Activate the plugin in the WP Admin Panel


FUNCTIONS HANDLED BY THIS PLUGIN

**XLSX Manual Import Function**
* Ability to select a locally stored XLSX file (formatted in Quintessence Jewelry standard format) and import products from the sheet. 
* Must check each product against current Woocommerce product inventory and update if already present. (Probably check against SKU)
* Ability to add product variations (for things like size) for products already in the sheet
* This will be a MIRROR import process; that is, any products currently in the site database that are NOT on the new spreadsheet will need to be deleted.

**Product Availability Confirmation**
* When a product is "added to cart" we need to run a GET request to the Quintessence Jewelry API to make sure that product is still in stock.

**Send orders to Quintessence Jewelry**
* Woocommerce orders, upon completion (paid) must be sent to Quintessence Jewelry via their backend API. 

**Order Tracking**
* Ability to track orders from Quintessence. Pending research on if their API supports this.

