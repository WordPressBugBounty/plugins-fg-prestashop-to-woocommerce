<?php

/**
 * Module to check the modules that are needed
 *
 * @link       https://wordpress.org/plugins/fg-prestashop-to-woocommerce/
 * @since      3.9.0
 *
 * @package    FG_Prestashop_to_WooCommerce
 * @subpackage FG_Prestashop_to_WooCommerce/admin
 */

if ( !class_exists('FG_Prestashop_to_WooCommerce_Modules_Check', false) ) {

	/**
	 * Class to check the modules that are needed
	 *
	 * @package    FG_Prestashop_to_WooCommerce
	 * @subpackage FG_Prestashop_to_WooCommerce/admin
	 * @author     Frédéric GILLES
	 */
	class FG_Prestashop_to_WooCommerce_Modules_Check {

		private $plugin;
		
		/**
		 * Initialize the class and set its properties.
		 *
		 * @param    object    $plugin       Admin plugin
		 */
		public function __construct( $plugin ) {

			$this->plugin = $plugin;

		}

		/**
		 * Check if some modules are needed
		 *
		 */
		public function check_modules() {
			$premium_url = 'https://www.fredericgilles.net/fg-prestashop-to-woocommerce/';
			$message_premium = __('Your Prestashop database contains %s. You need the <a href="%s" target="_blank">Premium version</a> to import them.', 'fg-prestashop-to-woocommerce');
			if ( defined('FGP2WCP_LOADED') ) {
				// Message for the Premium version
				$message_addon = __('Your Prestashop database contains %1$s. You need the <a href="%3$s" target="_blank">%4$s</a> to import them.', 'fg-prestashop-to-woocommerce');
			} else {
				// Message for the free version
				$message_addon = __('Your Prestashop database contains %1$s. You need the <a href="%2$s" target="_blank">Premium version</a> and the <a href="%3$s" target="_blank">%4$s</a> to import them.', 'fg-prestashop-to-woocommerce');
			}
			$modules = array(
				// Check if we need the Premium version: check the number of customers
				array(array($this, 'count'),
					array('customer', 1),
					'fg-prestashop-to-woocommerce-premium/fg-prestashop-to-woocommerce-premium.php',
					sprintf($message_premium, __('several customers', 'fg-prestashop-to-woocommerce'), $premium_url)
				),
				
				// Check if we need the Premium version: check the number of attributes
				array(array($this, 'count'),
					array('attribute', 0),
					'fg-prestashop-to-woocommerce-premium/fg-prestashop-to-woocommerce-premium.php',
					sprintf($message_premium, __('some attributes', 'fg-prestashop-to-woocommerce'), $premium_url)
				),
				
				// Check if we need the Premium version: check the number of accessories
				array(array($this, 'count'),
					array('accessory', 0),
					'fg-prestashop-to-woocommerce-premium/fg-prestashop-to-woocommerce-premium.php',
					sprintf($message_premium, __('some accessories', 'fg-prestashop-to-woocommerce'), $premium_url)
				),
				
				// Check if we need the Premium version: check the number of orders
				array(array($this, 'count'),
					array('orders', 1),
					'fg-prestashop-to-woocommerce-premium/fg-prestashop-to-woocommerce-premium.php',
					sprintf($message_premium, __('some orders', 'fg-prestashop-to-woocommerce'), $premium_url)
				),
				
				// Check if we need the Premium version: check the number of shops
				array(array($this, 'count'),
					array('shop', 1),
					'fg-prestashop-to-woocommerce-premium/fg-prestashop-to-woocommerce-premium.php',
					sprintf(__('Your Prestashop database contains several shops. You need the <a href="%s" target="_blank">Premium version</a> to import a shop different from the default one.', 'fg-prestashop-to-woocommerce'), $premium_url)
				),
				
				// Check if we need the Brands module
				array(array($this, 'count'),
					array('manufacturer', 1),
					'fg-prestashop-to-woocommerce-premium-brands-module/fgp2wc-brands.php',
					sprintf($message_addon, __('several manufacturers', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'brands/', __('Brands add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Internationalization module
				array(array($this, 'count'),
					array('lang', 1),
					'fg-prestashop-to-woocommerce-premium-internationalization-module/fgp2wc-internationalization.php',
					sprintf($message_addon, __('several languages', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'internationalization/', __('Internationalization add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Customer Groups module
				array(array($this, 'count'),
					array('group', 3),
					'fg-prestashop-to-woocommerce-premium-customer-groups-module/fgp2wc-customer-groups.php',
					sprintf($message_addon, __('customer groups', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'customer-groups/', __('Customer Groups add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Attachments module
				array(array($this, 'count'),
					array('attachment', 0),
					'fg-prestashop-to-woocommerce-premium-attachments-module/fgp2wc-attachments.php',
					sprintf($message_addon, __('some attachments', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'attachments/', __('Attachments add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Tiered Prices module
				array(array($this, 'count'),
					array('specific_price', 0, "WHERE from_quantity > 1"),
					'fg-prestashop-to-woocommerce-premium-tiered-prices-module/fgp2wc-tiered-prices.php',
					sprintf($message_addon, __('prices based on quantity', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'tiered-prices/', __('Tiered Prices add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Units module
				array(array($this, 'count'),
					array('product', 0, "WHERE unity IS NOT NULL AND unity != ''", array('unity')),
					'fg-prestashop-to-woocommerce-premium-units-module/fgp2wc-units.php',
					sprintf($message_addon, __('product units', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'units/', __('Units add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the EU VAT module
				array(array($this, 'count'),
					array('address', 0, "WHERE vat_number IS NOT NULL AND vat_number != ''", array('vat_number')),
					'fg-prestashop-to-woocommerce-premium-eu-vat-module/fgp2wc-eu-vat.php',
					sprintf($message_addon, __('VAT numbers', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'eu-vat/', __('EU VAT add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the NIF module
				array(array($this, 'count'),
					array('address', 0, "WHERE dni IS NOT NULL AND dni != ''", array('dni')),
					'fg-prestashop-to-woocommerce-premium-nif-module/fgp2wc-nif.php',
					sprintf($message_addon, __('NIF numbers', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'nif/', __('NIF add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Suppliers module
				array(array($this, 'count'),
					array('supplier', 1),
					'fg-prestashop-to-woocommerce-premium-suppliers-module/fgp2wc-suppliers.php',
					sprintf($message_addon, __('several suppliers', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'suppliers/', __('Suppliers add-on', 'fg-prestashop-to-woocommerce'))
				),
				
				// Check if we need the Variations Swatches module
				array(array($this, 'count'),
					array('attribute_group', 0, "WHERE is_color_group = 1"),
					'fg-prestashop-to-woocommerce-premium-variations-swatches-module/fgp2wc-variations-swatches.php',
					sprintf($message_addon, __('variations swatches', 'fg-prestashop-to-woocommerce'), $premium_url, $premium_url . 'variations-swatches/', __('Variations Swatches add-on', 'fg-prestashop-to-woocommerce'))
				),
				
			);
			foreach ( $modules as $module ) {
				list($callback, $params, $plugin, $message) = $module;
				if ( !is_plugin_active($plugin) ) {
					if ( call_user_func_array($callback, $params) ) {
						$this->plugin->display_admin_warning($message);
					}
				}
			}
		}

		/**
		 * Count the number of rows in the table
		 *
		 * @param string $table Table
		 * @param int $min_value Minimum value to trigger the warning message
		 * @param string $where WHERE clause
		 * @param array $columns Columns to check
		 * @return bool Trigger the warning or not
		 */
		private function count($table, $min_value, $where='', $columns=array()) {
			$count = 0;
			if ( $this->plugin->table_exists($table) ) {
				if ( !empty($columns) ) {
					// Check if the columns exist
					foreach ( $columns as $column ) {
						if ( !$this->plugin->column_exists($table, $column) ) {
							return false;
						}
					}
				}
				$prefix = $this->plugin->plugin_options['prefix'];
				$sql = "SELECT COUNT(*) AS nb FROM `{$prefix}{$table}`";
				if ( !empty($where) ) {
					$sql .= ' ' . $where;
				}
				$count = $this->count_sql($sql);
			}
			return ($count > $min_value);
		}

		/**
		 * Execute the SQL request and return the nb value
		 *
		 * @param string $sql SQL request
		 * @return int Count
		 */
		private function count_sql($sql) {
			$count = 0;
			$result = $this->plugin->prestashop_query($sql, false);
			if ( isset($result[0]['nb']) ) {
				$count = $result[0]['nb'];
			}
			return $count;
		}

	}
}
