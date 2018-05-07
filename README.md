# Standard Order Simulator for WooCommerce

A fork of [Order Simulator for WooCommerce](https://github.com/75nineteen/order-simulator-woocommerce). This plugin has been modified to create fixed 500 orders on installation. Thus, it can be used to check the slq load on various different enviournments. Please install woocommerce before this plugin.

To use this plugin unzip `db.zip` and import the extracted `test.db` as your wordpress db using the wp-cli command: `wp db import test.db`.

Search and replace the db entries with your site-name using: `wp search-replace host.test your_sitename`.

Then install and activate this plugin in your site to generate the fixed orders.