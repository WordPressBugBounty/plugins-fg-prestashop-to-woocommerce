<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://wordpress.org/plugins/fg-prestashop-to-woocommerce/
 * @since      2.0.0
 *
 * @package    FG_PrestaShop_to_WooCommerce
 * @subpackage FG_PrestaShop_to_WooCommerce/admin
 */

if ( !class_exists('FG_PrestaShop_to_WooCommerce_Admin', false) ) {

	/**
	 * The admin-specific functionality of the plugin.
	 *
	 * @package    FG_PrestaShop_to_WooCommerce
	 * @subpackage FG_PrestaShop_to_WooCommerce/admin
	 * @author     Frédéric GILLES
	 */
	class FG_PrestaShop_to_WooCommerce_Admin extends WP_Importer {

		/**
		 * The ID of this plugin.
		 *
		 * @since    2.0.0
		 * @access   private
		 * @var      string    $plugin_name    The ID of this plugin.
		 */
		private $plugin_name;

		/**
		 * The version of this plugin.
		 *
		 * @since    2.0.0
		 * @access   private
		 * @var      string    $version    The current version of this plugin.
		 */
		private $version;
		private $importer = 'fgp2wc';				// URL parameter

		public $plugin_options;						// Plug-in options
		public $download_manager;					// Download Manager
		public $progressbar;
		public $default_language = 1;				// Default language ID
		public $current_language = 1;				// Current language ID
		public $prestashop_version = '';			// PrestaShop DB version
		public $default_country = 0;				// Default country
		public $global_tax_rate = 0;
		public $chunks_size = 10;
		public $product_types = array();			// WooCommerce product types
		public $product_visibilities = array();		// WooCommerce product visibilities
		public $media_count = 0;					// Number of imported medias
		public $imported_media = array();
		public $imported_products = array();		// Imported products
		public $imported_manufacturers = array();	// Imported manufacturers
		public $imported_categories = array();		// Imported product categories
		public $imported_cms_categories = array();	// Imported categories
		public $shop_id = 0;						// Shop ID to import
		public $shops_count = 1;					// Number of shops

		protected $faq_url;							// URL of the FAQ page
		
		private $post_type = 'post';				// post or page
		private $image_filename_key = false;		// Optimization to get the right image filename
		private $default_backorders = 'no';			// Allow backorders
		private $imported_tags = array();			// Imported tags
		private $notices = array();					// Error or success messages
		private $log_file;
		private $log_file_url;
		
		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    2.0.0
		 * @param    string    $plugin_name       The name of this plugin.
		 * @param    string    $version           The version of this plugin.
		 */
		public function __construct( $plugin_name, $version ) {

			$this->plugin_name = $plugin_name;
			$this->version = $version;
			$this->faq_url = 'https://wordpress.org/plugins/fg-prestashop-to-woocommerce/faq/';
			
			// Logger
			$upload_dir = wp_upload_dir();
			$log_filename = $this->get_log_filename();
			$this->log_file = $upload_dir['basedir'] . '/' . $log_filename;
			$this->rename_old_log_file($upload_dir);
			$this->log_file_url = $upload_dir['baseurl'] . '/' . $log_filename;
			// Replace the protocol if the WordPress address is wrong in the WordPress General settings
			if ( is_ssl() ) {
				$this->log_file_url = preg_replace('/^https?/', 'https', $this->log_file_url);
			}

			// Progress bar
			$this->progressbar = new FG_PrestaShop_to_WooCommerce_ProgressBar($this);
			
		}

		/**
		 * Get the log filename
		 * 
		 * @since 4.47.0
		 * 
		 * @return string Log filename
		 */
		private function get_log_filename() {
			$option_key = 'fgp2wc_log_filename';
			$log_filename = get_option($option_key, '');
			if ( empty($log_filename) ) {
				$random_string = substr(md5(wp_rand()), 0, 8);
				$log_filename = 'fgp2wc-' . $random_string . '.logs';
				add_option($option_key, $log_filename);
			}
			return $log_filename;
		}

		/**
		 * Rename the old log file
		 * 
		 * @since 4.47.0
		 * 
		 * @param string $upload_dir WP upload directory
		 */
		private function rename_old_log_file($upload_dir) {
			$old_log_filename = $upload_dir['basedir'] . '/' . $this->plugin_name . '.logs';
			if ( file_exists($old_log_filename) ) {
				rename($old_log_filename, $this->log_file);
			}
		}
		
		/**
		 * The name of the plugin used to uniquely identify it within the context of
		 * WordPress and to define internationalization functionality.
		 *
		 * @since     2.0.0
		 * @return    string    The name of the plugin.
		 */
		public function get_plugin_name() {
			return $this->plugin_name;
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    2.0.0
		 */
		public function enqueue_styles() {

			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/fg-prestashop-to-woocommerce-admin.css', array(), $this->version, 'all' );

		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @since    2.0.0
		 */
		public function enqueue_scripts() {

			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/fg-prestashop-to-woocommerce-admin.js', array( 'jquery', 'jquery-ui-progressbar' ), $this->version, false );
			wp_localize_script( $this->plugin_name, 'objectL10n', array(
				'delete_imported_data_confirmation_message' => __( 'All previously imported data will be deleted from WordPress..', 'fg-prestashop-to-woocommerce' ),
				'delete_all_confirmation_message' => __( 'All content will be deleted from WordPress.', 'fg-prestashop-to-woocommerce' ),
				'delete_no_answer_message' => __( 'Please select a remove option.', 'fg-prestashop-to-woocommerce' ),
				'import_completed' => __( 'IMPORT COMPLETED', 'fg-prestashop-to-woocommerce' ),
				'update_completed' => __( 'UPDATE COMPLETED', 'fg-prestashop-to-woocommerce' ),
				'content_removed_from_wordpress' => __( 'Content removed from WordPress', 'fg-prestashop-to-woocommerce' ),
				'settings_saved' => __( 'Settings saved', 'fg-prestashop-to-woocommerce' ),
				'importing' => __( 'Importing…', 'fg-prestashop-to-woocommerce' ),
				'import_stopped_by_user' => __( 'IMPORT STOPPED BY USER', 'fg-prestashop-to-woocommerce' ),
			) );
			wp_localize_script( $this->plugin_name, 'objectPlugin', array(
				'log_file_url' => $this->log_file_url,
				'progress_url' => $this->progressbar->get_url(),
				'enable_ftp' => defined('FGP2WC_ENABLE_FTP'),
			));
			
		}

		/**
		 * Initialize the plugin
		 */
		public function init() {
			register_importer($this->importer, __('PrestaShop', 'fg-prestashop-to-woocommerce'), __('Import PrestaShop e-commerce solution to WooCommerce', 'fg-prestashop-to-woocommerce'), array($this, 'importer'));
		}

		/**
		 * Display the stored notices
		 */
		public function display_notices() {
			foreach ( $this->notices as $notice ) {
				echo '<div class="' . $notice['level'] . '"><p>[' . $this->plugin_name . '] ' . $notice['message'] . "</p></div>\n";
			}
		}
		
		/**
		 * Write a message in the log file
		 * 
		 * @since 3.0.0
		 * 
		 * @param string $message
		 */
		public function log($message) {
			file_put_contents($this->log_file, "$message\n", FILE_APPEND);
		}
		
		/**
		 * Store an admin notice
		 */
		public function display_admin_notice( $message )	{
			$this->notices[] = array('level' => 'updated', 'message' => $message);
			error_log('[INFO] [' . $this->plugin_name . '] ' . $message);
			$this->log($message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::log($message);
			}
		}

		/**
		 * Store an admin error
		 */
		public function display_admin_error( $message )	{
			$this->notices[] = array('level' => 'error', 'message' => $message);
			error_log('[ERROR] [' . $this->plugin_name . '] ' . $message);
			$this->log('[ERROR] ' . $message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::error($message, false);
			}
		}

		/**
		 * Store an admin warning
		 * 
		 * @since 3.0.0
		 */
		public function display_admin_warning( $message )	{
			$this->notices[] = array('level' => 'error', 'message' => $message);
			error_log('[WARNING] [' . $this->plugin_name . '] ' . $message);
			$this->log('[WARNING] ' . $message);
			if ( defined('WP_CLI') && WP_CLI ) {
				WP_CLI::warning($message);
			}
		}
		
		/**
		 * Run the importer
		 */
		public function importer() {
			$feasible_actions = array(
				'empty',
				'save',
				'test_database',
				'test_download',
				'test_ftp',
				'import',
			);
			$action = '';
			foreach ( $feasible_actions as $potential_action ) {
				if ( isset($_POST[$potential_action]) ) {
					$action = $potential_action;
					break;
				}
			}
			$this->set_local_timezone();
			$this->dispatch($action);
			$this->display_admin_page(); // Display the admin page
		}
		
		/**
		 * Import triggered by AJAX
		 *
		 * @since    3.0.0
		 */
		public function ajax_importer() {
			$current_user = wp_get_current_user();
			if ( !empty($current_user) && $current_user->has_cap('import') ) {
				$action = filter_input(INPUT_POST, 'plugin_action', FILTER_SANITIZE_SPECIAL_CHARS);

				if ( $action == 'update_wordpress_info') {
					// Update the WordPress database info
					echo $this->get_database_info();

				} else {
					ini_set('display_errors', true); // Display the errors that may happen (ex: Allowed memory size exhausted)

					// Empty the log file if we empty the WordPress content
					if ( (($action == 'empty') && check_admin_referer('empty', 'fgp2wc_nonce_empty'))
					  || (($action == 'import') && filter_input(INPUT_POST, 'automatic_empty', FILTER_VALIDATE_BOOLEAN) && check_admin_referer( 'parameters_form', 'fgp2wc_nonce')) ) {
						$this->empty_log_file();
					}

					$this->set_local_timezone();
					$time_start = date('Y-m-d H:i:s');
					$this->display_admin_notice("=== START $action $time_start ===");
					$result = $this->dispatch($action);
					if ( !empty($result) ) {
						echo wp_json_encode($result); // Send the result to the AJAX caller
					}
					$time_end = date('Y-m-d H:i:s');
					$this->display_admin_notice("=== END $action $time_end ===\n");
				}
			}
			wp_die();
		}
		
		/**
		 * Empty the log file
		 * 
		 * @since 4.15.0
		 */
		public function empty_log_file() {
			file_put_contents($this->log_file, '');
		}
		
		/**
		 * Set the local timezone
		 * 
		 * @since 4.6.0
		 */
		public function set_local_timezone() {
			// Set the time zone
			$timezone = get_option('timezone_string');
			if ( !empty($timezone) ) {
				date_default_timezone_set($timezone);
			}
		}
		
		/**
		 * Dispatch the actions
		 * 
		 * @param string $action Action
		 * @return object Result to return to the caller
		 */
		public function dispatch($action) {
			$timeout = defined('IMPORT_TIMEOUT')? IMPORT_TIMEOUT : 7200; // 2 hours
			set_time_limit($timeout);
			
			// Suspend the cache during the migration to avoid exhausted memory problem
			wp_suspend_cache_addition(true);
			wp_suspend_cache_invalidation(true);
			
			// Default values
			$this->plugin_options = array(
				'automatic_empty'				=> false,
				'url'							=> null,
				'download_protocol'				=> 'http',
				'base_dir'						=> '',
				'hostname'						=> 'localhost',
				'port'							=> 3306,
				'database'						=> null,
				'username'						=> 'root',
				'password'						=> '',
				'prefix'						=> 'ps_',
				'sku'							=> 'reference',
				'skip_media'					=> false,
				'first_image'					=> 'as_is_and_featured',
				'image_size'					=> 'thumbnail',
				'skip_thumbnails'				=> false,
				'import_external'				=> false,
				'import_duplicates'				=> false,
				'force_media_import'			=> false,
				'stock_management'				=> true,
				'meta_keywords_in_tags'			=> false,
				'import_as_pages'				=> false,
				'timeout'						=> 20,
				'price'							=> 'without_tax',
				'first_image_not_in_gallery'	=> false,
				'logger_autorefresh'			=> true,
			);
			$options = get_option('fgp2wc_options');
			if ( is_array($options) ) {
				$this->plugin_options = array_merge($this->plugin_options, $options);
			}
			do_action('fgp2wc_post_get_plugin_options');
			
			// Check if the upload directory is writable
			$upload_dir = wp_upload_dir();
			if ( !is_writable($upload_dir['basedir']) ) {
				$this->display_admin_error(__('The wp-content directory must be writable.', 'fg-prestashop-to-woocommerce'));
			}
			
			// Requires at least WordPress 4.4
			if ( version_compare(get_bloginfo('version'), '4.4', '<') ) {
				$this->display_admin_error(sprintf(__('WordPress 4.4+ is required. Please <a href="%s">update WordPress</a>.', 'fg-prestashop-to-woocommerce'), admin_url('update-core.php')));
			}
			
			elseif ( !empty($action) ) {
				switch($action) {
					
					// Delete content
					case 'empty':
						if ( defined('WP_CLI') || check_admin_referer( 'empty', 'fgp2wc_nonce_empty' ) ) { // Security check
							if ($this->empty_database($_POST['empty_action'])) { // Empty WP database
								$this->display_admin_notice(__('WordPress content removed', 'fg-prestashop-to-woocommerce'));
							} else {
								$this->display_admin_error(__('Couldn\'t remove content', 'fg-prestashop-to-woocommerce'));
							}
							wp_cache_flush();
						}
						break;
					
					// Save database options
					case 'save':
						if ( check_admin_referer( 'parameters_form', 'fgp2wc_nonce' ) ) { // Security check
							$this->save_plugin_options();
							$this->display_admin_notice(__('Settings saved', 'fg-prestashop-to-woocommerce'));
						}
						break;
					
					// Test the database connection
					case 'test_database':
						if ( defined('WP_CLI') || check_admin_referer( 'parameters_form', 'fgp2wc_nonce' ) ) { // Security check
							if ( !defined('WP_CLI') ) {
								// Save database options
								$this->save_plugin_options();
							}

							if ( $this->test_database_connection() ) {
								$result = array('status' => 'OK', 'message' => __('Connection successful', 'fg-prestashop-to-woocommerce'));
							} else {
								$result = array('status' => 'Error', 'message' => __('Connection failed', 'fg-prestashop-to-woocommerce') . '<br />' . __('See the errors in the log below', 'fg-prestashop-to-woocommerce'));
							}
							$result = apply_filters('fgp2wc_post_test_database_connection_click', $result);
							return $result;
						}
						break;
					
					// Test the media connection
					case 'test_download':
						if ( check_admin_referer( 'parameters_form', 'fgp2wc_nonce' ) ) { // Security check
							// Save database options
							$this->save_plugin_options();

							$protocol = $this->plugin_options['download_protocol'];
							$protocol_upcase = strtoupper(str_replace('_', ' ', $protocol));
							$this->download_manager = new FG_PrestaShop_to_WooCommerce_Download($this, $protocol);
							if ( $this->download_manager->test_connection() ) {
								return array('status' => 'OK', 'message' => sprintf(__('%s connection successful', 'fg-prestashop-to-woocommerce'), $protocol_upcase));
							} else {
								return array('status' => 'Error', 'message' => sprintf(__('%s connection failed', 'fg-prestashop-to-woocommerce'), $protocol_upcase));
							}
						}
						break;
					
					// Run the import
					case 'import':
						if ( defined('WP_CLI') || defined('DOING_CRON') || check_admin_referer( 'parameters_form', 'fgp2wc_nonce') ) { // Security check
							if ( !defined('DOING_CRON') && !defined('WP_CLI') ) {
								// Save database options
								$this->save_plugin_options();
							} else {
								if ( defined('DOING_CRON') ) {
									// CRON triggered
									$this->plugin_options['automatic_empty'] = 0; // Don't delete the existing data when triggered by cron
								}
							}

							if ( $this->test_database_connection() ) {
								// Automatic empty
								if ( $this->plugin_options['automatic_empty'] ) {
									if ($this->empty_database('all')) {
										$this->display_admin_notice(__('WordPress content removed', 'fg-prestashop-to-woocommerce'));
									} else {
										$this->display_admin_error(__('Couldn\'t remove content', 'fg-prestashop-to-woocommerce'));
									}
									wp_cache_flush();
								}

								// Import content
								$this->import();
							}
						}
						break;
					
					// Stop the import
					case 'stop_import':
						if ( check_admin_referer( 'parameters_form', 'fgp2wc_nonce' ) ) { // Security check
							$this->stop_import();
						}
						break;
					
					default:
						// Do other actions
						do_action('fgp2wc_dispatch', $action);
				}
			}
		}
		
		/**
		 * Build the option page
		 * 
		 */
		private function display_admin_page() {
			$data = $this->plugin_options;
			
			$data['title'] = __('Import PrestaShop', 'fg-prestashop-to-woocommerce');
			$data['description'] = __('This plugin will import products, categories, tags, images and CMS from PrestaShop to WooCommerce/WordPress.<br />Compatible with PrestaShop versions 1.1 to 1.7.', 'fg-prestashop-to-woocommerce');
			$data['description'] .= "<br />\n" . sprintf(__('For any issue, please read the <a href="%s" target="_blank">FAQ</a> first.', 'fg-prestashop-to-woocommerce'), $this->faq_url);
			$data['database_info'] = $this->get_database_info();
			$data['importer'] = $this->importer;
			$data['tab'] = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);
			
			// Hook for modifying the admin page
			$data = apply_filters('fgp2wc_pre_display_admin_page', $data);
			
			// Load the CSS and Javascript
			$this->enqueue_styles();
			$this->enqueue_scripts();
			
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/admin-display.php';
			
			// Hook for doing other actions after displaying the admin page
			do_action('fgp2wc_post_display_admin_page');
			
		}
		
		/**
		 * Get the WP options name
		 * 
		 * @since 4.0.0
		 * 
		 * @param array $option_names Option names
		 * @return array Option names
		 */
		public function get_option_names($option_names) {
			$option_names[] = 'fgp2wc_options';
			return $option_names;
		}
		
		/**
		 * Get the WordPress database info
		 * 
		 * @since 3.0.0
		 * 
		 * @return string Database info
		 */
		private function get_database_info() {
			$posts_count = $this->count_posts('post');
			$pages_count = $this->count_posts('page');
			$media_count = $this->count_posts('attachment');
			$products_count = $this->count_posts('product');
			$cat_count = wp_count_terms('category');
			$product_cat_count = wp_count_terms('product_cat');
			if ( is_wp_error($product_cat_count) ) {
				$product_cat_count = 0;
			}
			$tags_count = wp_count_terms('post_tag');

			$database_info =
				sprintf(_n('%d category', '%d categories', $cat_count, 'fg-prestashop-to-woocommerce'), $cat_count) . "<br />" .
				sprintf(_n('%d post', '%d posts', $posts_count, 'fg-prestashop-to-woocommerce'), $posts_count) . "<br />" .
				sprintf(_n('%d page', '%d pages', $pages_count, 'fg-prestashop-to-woocommerce'), $pages_count) . "<br />" .
				sprintf(_n('%d product category', '%d product categories', $product_cat_count, 'fg-prestashop-to-woocommerce'), $product_cat_count) . "<br />" .
				sprintf(_n('%d product', '%d products', $products_count, 'fg-prestashop-to-woocommerce'), $products_count) . "<br />" .
				sprintf(_n('%d media', '%d medias', $media_count, 'fg-prestashop-to-woocommerce'), $media_count) . "<br />" .
				sprintf(_n('%d tag', '%d tags', $tags_count, 'fg-prestashop-to-woocommerce'), $tags_count) . "<br />";
			$database_info = apply_filters('fgp2wc_get_database_info', $database_info);
			return $database_info;
		}
		
		/**
		 * Count the number of posts for a post type
		 * @param string $post_type
		 */
		public function count_posts($post_type) {
			$count = 0;
			$excluded_status = array('trash', 'auto-draft');
			$tab_count = wp_count_posts($post_type);
			foreach ( $tab_count as $key => $value ) {
				if ( !in_array($key, $excluded_status) ) {
					$count += $value;
				}
			}
			return $count;
		}
		
		/**
		 * Add an help tab
		 * 
		 */
		public function add_help_tab() {
			$screen = get_current_screen();
			$screen->add_help_tab(array(
				'id'	=> 'fgp2wc_help_instructions',
				'title'	=> __('Instructions'),
				'content'	=> '',
				'callback' => array($this, 'help_instructions'),
			));
			$screen->add_help_tab(array(
				'id'	=> 'fgp2wc_help_options',
				'title'	=> __('Options'),
				'content'	=> '',
				'callback' => array($this, 'help_options'),
			));
			$screen->set_help_sidebar('<a href="' . $this->faq_url . '" target="_blank">' . __('FAQ', 'fg-prestashop-to-woocommerce') . '</a>');
		}
		
		/**
		 * Instructions help screen
		 * 
		 * @return string Help content
		 */
		public function help_instructions() {
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/help-instructions.tpl.php';
		}
		
		/**
		 * Options help screen
		 * 
		 * @return string Help content
		 */
		public function help_options() {
			include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/help-options.tpl.php';
		}
		
		/**
		 * Open the connection on the PrestaShop database
		 *
		 * return boolean Connection successful or not
		 */
		protected function prestashop_connect() {
			global $prestashop_db;

			if ( !class_exists('PDO') ) {
				$this->display_admin_error(__('PDO is required. Please enable it.', 'fg-prestashop-to-woocommerce'));
				return false;
			}
			try {
				$prestashop_db = new PDO('mysql:host=' . $this->plugin_options['hostname'] . ';port=' . $this->plugin_options['port'] . ';dbname=' . $this->plugin_options['database'], $this->plugin_options['username'], $this->plugin_options['password'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
				if ( function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development')) ) {
					$prestashop_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Display SQL errors
				}
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Couldn\'t connect to the PrestaShop database. Please check your parameters. And be sure the WordPress server can access the PrestaShop database.', 'fg-prestashop-to-woocommerce') . "<br />\n" . $e->getMessage() . "<br />\n" . sprintf(__('Please read the <a href="%s" target="_blank">FAQ for the solution</a>.', 'fg-prestashop-to-woocommerce'), $this->faq_url));
				return false;
			}
			$this->get_sql_mode();
			
			return true;
		}
		
		/**
		 * Get the SQL mode
		 * 
		 * @since 4.54.0
		 */
		private function get_sql_mode() {
			global $sql_mode;
			
			$sql = "SHOW VARIABLES LIKE 'sql_mode'";
			$result = $this->prestashop_query($sql);
			if ( isset($result[0]['Value']) ) {
				$sql_mode = explode(',', $result[0]['Value']);
			} else {
				$sql_mode = array();
			}
		}
		
		/**
		 * Execute a SQL query on the PrestaShop database
		 * 
		 * @param string $sql SQL query
		 * @return array Query result
		 */
		public function prestashop_query($sql) {
			global $prestashop_db;
			$result = array();
			
			try {
				$sql = apply_filters('fgp2wc_sql_pre_query', $sql);
				$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
				if ( is_object($query) ) {
					foreach ( $query as $row ) {
						$result[] = $row;
					}
				}
				
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fg-prestashop-to-woocommerce') . $e->getMessage());
			}
			return $result;
		}
		
		/**
		 * Convert the 0000-00-00 00:00:00 dates to avoid the message "Incorrect DATETIME value: '0000-00-00 00:00:00'"
		 * 
		 * @since 4.54.0
		 * 
		 * @param string $sql SQL
		 * @return string SQL
		 */
		public function convert_zero_date($sql) {
			global $sql_mode;
			
			if ( is_array($sql_mode) && (in_array('NO_ZERO_DATE', $sql_mode) || in_array('NO_ZERO_IN_DATE', $sql_mode)) ) {
				$sql = str_replace("= '0000-00-00 00:00:00'", "< '0000-01-01 00:00:00'", $sql);
			}
			return $sql;
		}
		
		/**
		 * Delete all posts, medias and categories from the database
		 *
		 * @param string $action	imported = removes only new imported data
		 * 							all = removes all
		 * @return boolean
		 */
		private function empty_database($action) {
			global $wpdb;
			$result = true;
			
			$wpdb->show_errors();
			
			// Hook for doing other actions before emptying the database
			do_action('fgp2wc_pre_empty_database', $action);
			
			$sql_queries = array();
			
			if ( $action == 'all' ) {
				// Remove all content
				
				$this->save_wp_data();
				
				$sql_queries[] = "TRUNCATE $wpdb->commentmeta";
				$sql_queries[] = "TRUNCATE $wpdb->comments";
				$sql_queries[] = "TRUNCATE $wpdb->term_relationships";
				$sql_queries[] = "TRUNCATE $wpdb->termmeta";
				$sql_queries[] = "TRUNCATE $wpdb->postmeta";
				$sql_queries[] = "TRUNCATE $wpdb->posts";
				$sql_queries[] = <<<SQL
-- Delete Terms
DELETE FROM $wpdb->terms
WHERE term_id > 1 -- non-classe
SQL;
				$sql_queries[] = <<<SQL
-- Delete Terms taxonomies
DELETE FROM $wpdb->term_taxonomy
WHERE term_id > 1 -- non-classe
SQL;
				$sql_queries[] = "ALTER TABLE $wpdb->terms AUTO_INCREMENT = 2";
				$sql_queries[] = "ALTER TABLE $wpdb->term_taxonomy AUTO_INCREMENT = 2";
				
			} else {
				
				// (Re)create a temporary table with the IDs to delete
				$sql_queries[] = <<<SQL
DROP TEMPORARY TABLE IF EXISTS {$wpdb->prefix}fg_data_to_delete;
SQL;

				$sql_queries[] = <<<SQL
CREATE TEMPORARY TABLE IF NOT EXISTS {$wpdb->prefix}fg_data_to_delete (
`id` bigint(20) unsigned NOT NULL,
PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;
SQL;
				
				// Insert the imported posts IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT post_id FROM $wpdb->postmeta
WHERE meta_key LIKE '_fgp2wc_%'
SQL;
				
				// Delete the imported posts and related data

				$sql_queries[] = <<<SQL
-- Delete Comments and Comment metas
DELETE c, cm
FROM $wpdb->comments c
LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE c.comment_post_ID = del.id;
SQL;

				$sql_queries[] = <<<SQL
-- Delete Term relashionships
DELETE tr
FROM $wpdb->term_relationships tr
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE tr.object_id = del.id;
SQL;

				$sql_queries[] = <<<SQL
-- Delete Posts Children and Post metas
DELETE p, pm
FROM $wpdb->posts p
LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE p.post_parent = del.id
AND p.post_type != 'attachment'; -- Don't remove the old medias attached to posts
SQL;

				$sql_queries[] = <<<SQL
-- Delete Posts and Post metas
DELETE p, pm
FROM $wpdb->posts p
LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE p.ID = del.id;
SQL;

				// Truncate the temporary table
				$sql_queries[] = <<<SQL
TRUNCATE {$wpdb->prefix}fg_data_to_delete;
SQL;
				
				// Insert the imported terms IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT term_id FROM $wpdb->termmeta
WHERE meta_key LIKE '_fgp2wc_%'
SQL;
				
				// Delete the imported terms and related data

				$sql_queries[] = <<<SQL
-- Delete Terms, Term taxonomies and Term metas
DELETE t, tt, tm
FROM $wpdb->termmeta tm
LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_id = tm.term_id
LEFT JOIN $wpdb->terms t ON t.term_id = tm.term_id
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE tm.term_id = del.id;
SQL;

				// Truncate the temporary table
				$sql_queries[] = <<<SQL
TRUNCATE {$wpdb->prefix}fg_data_to_delete;
SQL;
				
				// Insert the imported comments IDs in the temporary table
				$sql_queries[] = <<<SQL
INSERT IGNORE INTO {$wpdb->prefix}fg_data_to_delete (`id`)
SELECT comment_id FROM $wpdb->commentmeta
WHERE meta_key LIKE '_fgp2wc_%'
SQL;
				
				// Delete the imported comments and related data
				$sql_queries[] = <<<SQL
-- Delete Comments and Comment metas
DELETE c, cm
FROM $wpdb->comments c
LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
INNER JOIN {$wpdb->prefix}fg_data_to_delete del
WHERE c.comment_ID = del.id;
SQL;

			}
			
			// Delete WooCommerce transients
			$sql_queries[] = <<<SQL
-- Delete WooCommerce transients
DELETE o FROM $wpdb->options o
WHERE o.option_name LIKE '_transient_wc_%'
OR o.option_name LIKE '_transient_timeout_wc_%';
SQL;

			// Execute SQL queries
			if ( count($sql_queries) > 0 ) {
				foreach ( $sql_queries as $sql ) {
					$result &= $wpdb->query($sql);
				}
			}
			
			// Reset the PrestaShop last imported IDs
			update_option('fgp2wc_last_cms_id', 0);
			update_option('fgp2wc_last_product_id', 0);
			delete_option('fgp2wc_last_update');
			
			if ( $action == 'all' ) {
				$this->restore_wp_data();
			}
				
			// Hook for doing other actions after emptying the database
			do_action('fgp2wc_post_empty_database', $action);
			
			// Re-count categories and tags items
			$this->terms_count();
			
			// Clean the cache
			$this->clean_cache(array(), 'category');
			$this->clean_cache(array(), 'product_cat');
			delete_transient('wc_count_comments');
			
			$this->optimize_database();
			
			$this->progressbar->set_total_count(0);
			
			$wpdb->hide_errors();
			return ($result !== false);
		}

		/**
		 * Save the data used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function save_wp_data() {
			$this->save_wp_posts();
			$this->save_wp_terms();
			$this->save_wp_term_relationships();
		}
		
		/**
		 * Save the posts and post meta used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function save_wp_posts() {
			global $wpdb;
			$sql = "
				SELECT *
				FROM {$wpdb->posts} p
				WHERE p.`post_type` LIKE 'wp\_%'
				ORDER BY p.`ID`
			";
			$posts = $wpdb->get_results($sql, ARRAY_A);
			foreach ( $posts as &$post ) {
				$sql_meta = "SELECT `meta_key`, `meta_value` FROM {$wpdb->postmeta} WHERE `post_id` = %d ORDER BY `meta_id`";
				$postmetas = $wpdb->get_results($wpdb->prepare($sql_meta, $post['ID']), ARRAY_A);
				$post['meta'] = $postmetas;
				unset($post['ID']);
			}
			update_option('fgp2wc_save_posts', $posts);
		}

		/**
		 * Save the terms, term taxonomies and term meta used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function save_wp_terms() {
			global $wpdb;
			$sql = "
				SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.`taxonomy` LIKE 'wp\_%'
				ORDER BY t.term_id
			";
			$terms = $wpdb->get_results($sql, ARRAY_A);
			foreach ( $terms as &$term ) {
				$sql_meta = "SELECT `meta_key`, `meta_value` FROM {$wpdb->termmeta} WHERE `term_id` = %d ORDER BY `meta_id`";
				$termmetas = $wpdb->get_results($wpdb->prepare($sql_meta, $term['term_id']), ARRAY_A);
				$term['meta'] = $termmetas;
				unset($term['term_id']);
			}
			update_option('fgp2wc_save_terms', $terms);
		}

		/**
		 * Save the terms relationships used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function save_wp_term_relationships() {
			global $wpdb;
			$sql = "
				SELECT p.post_name, t.name AS term_name
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE p.`post_type` LIKE 'wp\_%'
			";
			$term_relationships = $wpdb->get_results($sql, ARRAY_A);
			update_option('fgp2wc_save_term_relationships', $term_relationships);
		}

		/**
		 * Restore the saved data used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function restore_wp_data() {
			$this->restore_wp_posts();
			$this->restore_wp_terms();
			$this->restore_wp_term_relationships();
		}
		
		/**
		 * Restore the saved posts and post meta used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function restore_wp_posts() {
			global $wpdb;
			$posts = get_option('fgp2wc_save_posts');
			foreach ( $posts as $post ) {
				$postmetas = $post['meta'];
				unset($post['meta']);
				$wpdb->insert($wpdb->posts, $post);
				$post_id = $wpdb->insert_id;
				if ( $post_id ) {
					foreach ( $postmetas as $meta ) {
						add_post_meta($post_id, $meta['meta_key'], $meta['meta_value']);
					}
				}
			}
		}

		/**
		 * Restore the saved terms, term taxonomies and term meta used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function restore_wp_terms() {
			global $wpdb;
			$terms = get_option('fgp2wc_save_terms');
			foreach ( $terms as $term ) {
				$wpdb->insert($wpdb->terms, array(
					'name' => $term['name'],
					'slug' => $term['slug'],
				));
				$term_id = $wpdb->insert_id;
				if ( $term_id ) {
					$wpdb->insert($wpdb->term_taxonomy, array(
						'term_id' => $term_id,
						'taxonomy' => $term['taxonomy'],
						'description' => $term['description'],
						'count' => $term['count'],
					));
					foreach ( $term['meta'] as $meta ) {
						add_term_meta($term_id, $meta['meta_key'], $meta['meta_value']);
					}
				}
			}
		}
		
		/**
		 * Restore the saved term relationships used by the theme (WP 5.9)
		 * 
		 * @since 4.25.0
		 */
		private function restore_wp_term_relationships() {
			global $wpdb;
			$term_relationships = get_option('fgp2wc_save_term_relationships');
			foreach ( $term_relationships as $term_relationship ) {
				$post_id = $wpdb->get_var($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE post_name = %s", $term_relationship['post_name']));
				$term_taxonomy_id = $wpdb->get_var($wpdb->prepare("SELECT tt.`term_taxonomy_id` FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE t.name = %s", $term_relationship['term_name']));
				if ( $post_id && $term_taxonomy_id ) {
					$wpdb->insert($wpdb->term_relationships, array(
						'object_id' => $post_id,
						'term_taxonomy_id' => $term_taxonomy_id,
					));
				}
			}
		}

		/**
		 * Optimize the database
		 *
		 */
		protected function optimize_database() {
			global $wpdb;
			
			$sql = <<<SQL
OPTIMIZE TABLE 
`$wpdb->commentmeta` ,
`$wpdb->comments` ,
`$wpdb->options` ,
`$wpdb->postmeta` ,
`$wpdb->posts` ,
`$wpdb->terms` ,
`$wpdb->term_relationships` ,
`$wpdb->term_taxonomy`,
`$wpdb->termmeta`
SQL;
			$wpdb->query($sql);
		}
		
		/**
		 * Delete all woocommerce data
		 *
		 */
		public function delete_woocommerce_data() {
			global $wpdb;
			global $wc_product_attributes;
			
			$wpdb->show_errors();
			
			$sql_queries = array();
			
			// Disable foreign key checks
			$sql_queries[] = "SET FOREIGN_KEY_CHECKS=0";
			
			// Delete the WooCommerce tables
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}woocommerce_attribute_taxonomies";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}woocommerce_order_items";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}woocommerce_order_itemmeta";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_download_log";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}woocommerce_downloadable_product_permissions";
			
			// Delete the WooCommerce HPOS tables
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_orders";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_orders_meta";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_addresses";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_operational_data";
			
			// Delete the WooCommerce lookup and stats tables
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_customer_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_coupon_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_product_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_tax_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_product_attributes_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_product_meta_lookup";
			$sql_queries[] = "TRUNCATE {$wpdb->prefix}wc_order_stats";
			
			// re-enable foreign key checks
			$sql_queries[] = "SET FOREIGN_KEY_CHECKS=1";

			// Execute SQL queries
			if ( count($sql_queries) > 0 ) {
				foreach ( $sql_queries as $sql ) {
					$wpdb->query($sql);
				}
			}
			
			// Reset the WC pages flags
			$wc_pages = array('shop', 'cart', 'checkout', 'myaccount');
			foreach ( $wc_pages as $wc_page ) {
				update_option('woocommerce_' . $wc_page . '_page_id', 0);
			}
			
			// Empty attribute taxonomies cache
			delete_transient('wc_attribute_taxonomies');
			delete_transient('woocommerce_reports-transient-version'); // Clear WooCommerce Analytics cache
			$wc_product_attributes = array();
			$this->delete_var_prices_transient();
			
			// Delete the WooCommerce product category cache
			delete_option("product_cat_children");
			
			// Delete the default placeholder image
			delete_option('woocommerce_placeholder_image');
			
			$wpdb->hide_errors();
			
			$this->display_admin_notice(__('WooCommerce data deleted', 'fg-prestashop-to-woocommerce'));
			
			// Recreate WooCommerce default data
			if ( class_exists('WC_Install') ) {
				WC_Install::create_pages();
				$this->display_admin_notice(__('WooCommerce default data created', 'fg-prestashop-to-woocommerce'));
			}
		}
		
		/**
		 * Delete the wc_var_prices transient
		 * 
		 * @since 3.35.0
		 */
		public function delete_var_prices_transient() {
			$this->delete_transient('wc_var_prices_');
		}
		
		/**
		 * Delete the transient
		 * 
		 * @since 3.35.0
		 * 
		 * @param string $transient Transient
		 */
		public function delete_transient($transient) {
			global $wpdb;
			$wpdb->query($wpdb->prepare("DELETE FROM `$wpdb->options` WHERE `option_name` LIKE %s OR `option_name` LIKE %s", $wpdb->esc_like("_transient_$transient") . '%', $wpdb->esc_like("_transient_timeout_$transient") . '%') );
		}
		
		/**
		 * Test the database connection
		 * 
		 * @return boolean
		 */
		private function test_database_connection() {
			global $prestashop_db;
			
			if ( $this->prestashop_connect() ) {
				try {
					$prefix = $this->plugin_options['prefix'];
					
					do_action('fgp2wc_pre_test_database_connection');
					
					// Test that the "product" table exists
					$result = $prestashop_db->query("DESC {$prefix}product");
					if ( !is_a($result, 'PDOStatement') ) {
						$errorInfo = $prestashop_db->errorInfo();
						throw new PDOException($errorInfo[2], $errorInfo[1]);
					}
					
					$this->display_admin_notice(__('Connected with success to the PrestaShop database', 'fg-prestashop-to-woocommerce'));
					
					$this->import_configuration();
					do_action('fgp2wc_post_test_database_connection');
					
					return true;
					
				} catch ( PDOException $e ) {
					$this->display_admin_error(__('Couldn\'t connect to the PrestaShop database. Please check your parameters. And be sure the WordPress server can access the PrestaShop database.', 'fg-prestashop-to-woocommerce') . "<br />\n" . $e->getMessage());
					return false;
				}
				$prestashop_db = null;
			}
			return false;
		}
		
		/**
		 * Test if the WooCommerce plugin is activated
		 *
		 * @return bool True if the WooCommerce plugin is activated
		 */
		public function test_woocommerce_activation() {
			if ( !class_exists('WooCommerce', false) ) {
				$this->display_admin_error(__('Error: the <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce plugin</a> must be installed and activated to import the products.', 'fg-prestashop-to-woocommerce'));
				return false;
			}
			return true;
		}

		/**
		 * Get some PrestaShop information
		 *
		 */
		public function get_prestashop_info() {
			$message = __('PrestaShop data found:', 'fg-prestashop-to-woocommerce') . "\n";
			
			// Products
			$products_count = $this->get_products_count();
			$message .= sprintf(_n('%d product', '%d products', $products_count, 'fg-prestashop-to-woocommerce'), $products_count) . "\n";
			
			// Product categories
			$products_categories_count = $this->get_products_categories_count();
			$message .= sprintf(_n('%d product category', '%d product categories', $products_categories_count, 'fg-prestashop-to-woocommerce'), $products_categories_count) . "\n";
			
			// CMS Articles
			$posts_count = $this->get_cms_articles_count();
			$message .= sprintf(_n('%d CMS article', '%d CMS articles', $posts_count, 'fg-prestashop-to-woocommerce'), $posts_count) . "\n";
			
			// CMS categories
			$cms_categories_count = $this->get_cms_categories_count();
			$message .= sprintf(_n('%d CMS category', '%d CMS categories', $cms_categories_count, 'fg-prestashop-to-woocommerce'), $cms_categories_count) . "\n";
			
			// Employees
			$employees_count = $this->get_employees_count();
			$message .= sprintf(_n('%d employee', '%d employees', $employees_count, 'fg-prestashop-to-woocommerce'), $employees_count) . "\n";
			
			// Customers
			$customers_count = $this->get_customers_count();
			$message .= sprintf(_n('%d customer', '%d customers', $customers_count, 'fg-prestashop-to-woocommerce'), $customers_count) . "\n";
			
			// Orders
			$orders_count = $this->get_orders_count();
			$message .= sprintf(_n('%d order', '%d orders', $orders_count, 'fg-prestashop-to-woocommerce'), $orders_count) . "\n";
			
			$message = apply_filters('fgp2wc_pre_display_prestashop_info', $message);
			
			$this->display_admin_notice($message);
		}
		
		/**
		 * Get the number of PrestaShop products
		 * 
		 * @return int Number of products
		 */
		private function get_products_count() {
			$prefix = $this->plugin_options['prefix'];
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}product p
			";
			$sql = apply_filters('fgp2wc_get_products_count_sql', $sql, $prefix);
			$result = $this->prestashop_query($sql);
			$products_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $products_count;
		}
		
		/**
		 * Get the number of PrestaShop products categories
		 * 
		 * @since 3.0.0
		 * 
		 * @return int Number of products categories
		 */
		private function get_products_categories_count() {
			$prefix = $this->plugin_options['prefix'];
			
			// Exclude the parent categories of the root category
			$root_category = $this->get_root_category();
			$exclude_root_parent_categories_sql = '';
			if ( !empty($root_category) ) {
				$exclude_root_parent_categories_sql = "AND c.level_depth >= " . $root_category['level_depth'];
			}
			$extra_joins = '';
			$extra_criteria = '';
			if ( version_compare($this->prestashop_version, '1.5', '>=') ) {
				if ( $this->shop_id != 0 ) {
					// PrestaShop 1.5+
					$extra_joins .= "INNER JOIN {$prefix}category_shop cs ON cs.id_category = c.id_category";
					$extra_criteria .= "AND cs.id_shop = '{$this->shop_id}'";
				}
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}category c
				$extra_joins
				WHERE c.active = 1
				$exclude_root_parent_categories_sql
				$extra_criteria
			";
			$result = $this->prestashop_query($sql);
			$products_cat_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $products_cat_count;
		}
		
		/**
		 * Get the number of PrestaShop articles
		 * 
		 * @return int Number of articles
		 */
		private function get_cms_articles_count() {
			$prefix = $this->plugin_options['prefix'];
			$extra_joins = '';
			$extra_criteria = '';
			if ( version_compare($this->prestashop_version, '1.5', '>=') && ($this->shop_id != 0)) {
				// PrestaShop 1.5+
				$extra_joins .= " INNER JOIN {$prefix}cms_shop AS cs ON cs.id_cms = a.id_cms AND cs.id_shop = '{$this->shop_id}'";
				$extra_joins .= "INNER JOIN {$prefix}cms_lang AS cl ON cl.id_cms = a.id_cms";
				$extra_criteria .= "WHERE cl.id_lang = '{$this->current_language}'";
				if ( $this->column_exists('cms_lang', 'id_shop') ) {
					$extra_criteria .= " AND cl.id_shop = cs.id_shop";
				}
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}cms a
				$extra_joins
				$extra_criteria
			";
			$result = $this->prestashop_query($sql);
			$cms_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $cms_count;
		}
		
		/**
		 * Get the number of PrestaShop CMS categories
		 * 
		 * @since 3.0.0
		 * 
		 * @return int Number of categories
		 */
		private function get_cms_categories_count() {
			$prefix = $this->plugin_options['prefix'];
			if ( version_compare($this->prestashop_version, '1.4', '<') ) {
				$category_table = 'category'; // PrestaShop 1.4
			} else {
				$category_table = 'cms_category';
			}
			$extra_joins = '';
			if ( version_compare($this->prestashop_version, '1.6', '>=') ) {
				// PrestaShop 1.6+
				if ( ($this->shop_id != 0) && $this->column_exists($category_table . '_lang', 'id_shop') ) {
					$extra_joins .= " INNER JOIN {$prefix}{$category_table}_shop cs ON cs.id_{$category_table} = c.id_{$category_table} AND cs.id_shop = '{$this->shop_id}'";
					$extra_joins .= " INNER JOIN {$prefix}{$category_table}_lang cl ON cl.id_{$category_table} = c.id_{$category_table} AND cl.id_lang = '{$this->current_language}' AND cl.id_shop = cs.id_shop";
				}
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}$category_table c
				$extra_joins
				WHERE c.active = 1
			";
			$result = $this->prestashop_query($sql);
			$cat_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $cat_count;
		}
		
		/**
		 * Get the number of PrestaShop employees
		 * 
		 * @return int Number of employees
		 */
		public function get_employees_count() {
			$prefix = $this->plugin_options['prefix'];
			$extra_joins = '';
			$extra_criteria = '';
			if ( ($this->shop_id != 0) && version_compare($this->prestashop_version, '1.5', '>=') ) {
				$extra_joins = "INNER JOIN {$prefix}employee_shop es ON es.id_employee = e.id_employee";
				$extra_criteria = "AND es.id_shop = '{$this->shop_id}'";
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}employee e
				$extra_joins
				WHERE e.active = 1
				$extra_criteria
			";
			$result = $this->prestashop_query($sql);
			$employees_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $employees_count;
		}
		
		/**
		 * Get the number of PrestaShop customers
		 * 
		 * @return int Number of customers
		 */
		public function get_customers_count() {
			$prefix = $this->plugin_options['prefix'];
			$deleted = '';
			if ( $this->column_exists('customer', 'deleted') ) {
				$deleted = 'AND c.deleted = 0';
			}
			$extra_criteria = '';
			if ( ($this->shop_id != 0) && version_compare($this->prestashop_version, '1.5', '>=') ) {
				$extra_criteria = "AND c.id_shop = '{$this->shop_id}'";
			}
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}customer c
				WHERE c.active = 1
				$deleted
				$extra_criteria
			";
			$result = $this->prestashop_query($sql);
			$customers_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $customers_count;
		}
		
		/**
		 * Get the number of PrestaShop orders
		 * 
		 * @return int Number of orders
		 */
		private function get_orders_count() {
			$prefix = $this->plugin_options['prefix'];
			$sql = "
				SELECT COUNT(*) AS nb
				FROM {$prefix}orders
			";
			$result = $this->prestashop_query($sql);
			$orders_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			return $orders_count;
		}
		
		/**
		 * Get the number of PrestaShop shops
		 * 
		 * @since 4.33.3
		 * 
		 * @return int Number of shops
		 */
		protected function get_shops_count() {
			if ( $this->table_exists('shop') ) {
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT COUNT(*) AS nb
					FROM {$prefix}shop
				";
				$result = $this->prestashop_query($sql);
				$shops_count = isset($result[0]['nb'])? $result[0]['nb'] : 0;
			} else {
				$shops_count = 1;
			}
			return $shops_count;
		}
		
		/**
		 * Save the plugin options
		 *
		 */
		public function save_plugin_options() {
			$this->plugin_options = array_merge($this->plugin_options, $this->validate_form_info());
			update_option('fgp2wc_options', $this->plugin_options);
			
			// Hook for doing other actions after saving the options
			do_action('fgp2wc_post_save_plugin_options');
		}
		
		/**
		 * Validate POST info
		 *
		 * @return array Form parameters
		 */
		private function validate_form_info() {
			// Add http:// before the URL if it is missing
			$url = esc_url(filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL));
			if ( !empty($url) && (preg_match('#^https?://#', $url) == 0) ) {
				$url = 'http://' . $url;
			}
			return array(
				'automatic_empty'				=> filter_input(INPUT_POST, 'automatic_empty', FILTER_VALIDATE_BOOLEAN),
				'url'							=> $url,
				'download_protocol'				=> filter_input(INPUT_POST, 'download_protocol', FILTER_SANITIZE_SPECIAL_CHARS),
				'base_dir'						=> filter_input(INPUT_POST, 'base_dir', FILTER_SANITIZE_SPECIAL_CHARS),
				'hostname'						=> filter_input(INPUT_POST, 'hostname', FILTER_SANITIZE_SPECIAL_CHARS),
				'port'							=> filter_input(INPUT_POST, 'port', FILTER_SANITIZE_NUMBER_INT),
				'database'						=> filter_input(INPUT_POST, 'database', FILTER_SANITIZE_SPECIAL_CHARS),
				'username'						=> filter_input(INPUT_POST, 'username'),
				'password'						=> filter_input(INPUT_POST, 'password'),
				'prefix'						=> filter_input(INPUT_POST, 'prefix', FILTER_SANITIZE_SPECIAL_CHARS),
				'sku'							=> filter_input(INPUT_POST, 'sku', FILTER_SANITIZE_SPECIAL_CHARS),
				'skip_media'					=> filter_input(INPUT_POST, 'skip_media', FILTER_VALIDATE_BOOLEAN),
				'first_image'					=> filter_input(INPUT_POST, 'first_image', FILTER_SANITIZE_SPECIAL_CHARS),
				'image_size'					=> filter_input(INPUT_POST, 'image_size', FILTER_SANITIZE_SPECIAL_CHARS),
				'skip_thumbnails'				=> filter_input(INPUT_POST, 'skip_thumbnails', FILTER_VALIDATE_BOOLEAN),
				'import_external'				=> filter_input(INPUT_POST, 'import_external', FILTER_VALIDATE_BOOLEAN),
				'import_duplicates'				=> filter_input(INPUT_POST, 'import_duplicates', FILTER_VALIDATE_BOOLEAN),
				'force_media_import'			=> filter_input(INPUT_POST, 'force_media_import', FILTER_VALIDATE_BOOLEAN),
				'meta_keywords_in_tags'			=> filter_input(INPUT_POST, 'meta_keywords_in_tags', FILTER_VALIDATE_BOOLEAN),
				'import_as_pages'				=> filter_input(INPUT_POST, 'import_as_pages', FILTER_VALIDATE_BOOLEAN),
				'timeout'						=> filter_input(INPUT_POST, 'timeout', FILTER_SANITIZE_NUMBER_INT),
				'price'							=> filter_input(INPUT_POST, 'price', FILTER_SANITIZE_SPECIAL_CHARS),
				'stock_management'				=> filter_input(INPUT_POST, 'stock_management', FILTER_VALIDATE_BOOLEAN),
				'first_image_not_in_gallery'	=> filter_input(INPUT_POST, 'first_image_not_in_gallery', FILTER_VALIDATE_BOOLEAN),
				'logger_autorefresh'			=> filter_input(INPUT_POST, 'logger_autorefresh', FILTER_VALIDATE_BOOLEAN),
			);
		}
		
		/**
		 * Import
		 *
		 */
		private function import() {
			if ( $this->prestashop_connect() ) {
				
				$time_start = microtime(true);
				
				define('WP_IMPORTING', true);
				update_option('fgp2wc_stop_import', false, false); // Reset the stop import action
				
				// Check prerequesites before the import
				$do_import = apply_filters('fgp2wc_pre_import_check', true);
				if ( !$do_import) {
					return;
				}
				
				$total_elements_count = $this->get_total_elements_count();
				$this->progressbar->set_total_count($total_elements_count);
				
				$this->imported_media = $this->get_imported_ps_posts($meta_key = '_fgp2wc_old_file');
				
				// Hook for doing other actions before the pre import
				do_action('fgp2wc_pre_pre_import');
				
				$this->pre_import();
				
				// Hook for doing other actions before the import
				do_action('fgp2wc_pre_import');
				
				if ( !isset($this->premium_options['skip_cms']) || !$this->premium_options['skip_cms'] ) {
					$this->import_cms();
				}
				if ( !isset($this->premium_options['skip_products_categories']) || !$this->premium_options['skip_products_categories'] ) {
					$this->import_product_categories();
				}
				if ( !isset($this->premium_options['skip_products']) || !$this->premium_options['skip_products'] ) {
					$this->import_products();
					$this->imported_products = $this->get_imported_products();
					
					// Regenerate the WooCommerce product lookup tables
					if ( function_exists('wc_update_product_lookup_tables') ) {
						wc_update_product_lookup_tables();
					}
				}
				
				if ( !$this->import_stopped() ) {
					// Hook for doing other actions after the import
					do_action('fgp2wc_post_import');
				}
				
				if ( !get_option('fgp2wc_last_update') ) {
					// Set the last update date if it was not already set
					update_option('fgp2wc_last_update', date('Y-m-d H:i:s'));
				}
				
				// Hook for other notices
				do_action('fgp2wc_import_notices');
				
				// Debug info
				if ( function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development')) ) {
					$this->display_admin_notice(sprintf("Memory used: %s bytes<br />\n", number_format(memory_get_usage())));
					$time_end = microtime(true);
					$this->display_admin_notice(sprintf("Duration: %d sec<br />\n", $time_end - $time_start));
				}
				
				if ( $this->import_stopped() ) {
					// Import stopped by the user
					$this->display_admin_notice("IMPORT STOPPED BY USER");
				} else {
					// Import completed
					$this->display_admin_notice("IMPORT COMPLETED");
				}
				wp_cache_flush();
			}
		}
		
		/**
		 * Actions to do before the import
		 * 
		 * @param bool $import_doable Can we start the import?
		 * @return bool Can we start the import?
		 */
		public function pre_import_check($import_doable) {
			if ( $import_doable ) {
				if ( !$this->plugin_options['skip_media'] && empty($this->plugin_options['url']) ) {
					$this->display_admin_error(__('The URL field is required to import the media.', 'fg-prestashop-to-woocommerce'));
					$import_doable = false;
				}
			}
			return $import_doable;
		}
		
		/**
		 * Actions to do before the import/update
		 * 
		 * @since 4.44.3
		 */
		protected function pre_import() {
			// Allow the non valid SSL certificates
			stream_context_set_default(array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
				),
			));

			$this->remove_filters();
			$this->import_configuration();
			
			$this->shops_count = $this->get_shops_count();

			$this->post_type = ($this->plugin_options['import_as_pages'] == 1) ? 'page' : 'post';

			$this->product_types = $this->create_woocommerce_product_types(); // (Re)create the WooCommerce product types
			$this->product_visibilities = $this->create_woocommerce_product_visibilities(); // (Re)create the WooCommerce product visibilities
			$this->global_tax_rate = $this->get_default_tax_rate();

			// Set the Download Manager
			$this->download_manager = new FG_PrestaShop_to_WooCommerce_Download($this, $this->plugin_options['download_protocol']);
			$this->download_manager->test_connection();
		}
		
		/**
		 * Remove WordPress and WooCommerce unwanted filters
		 * 
		 * @since 4.44.3
		 */
		protected function remove_filters() {
			kses_remove_filters(); // To solve the issue of links containing ":" in multisite mode

			global $wp_filter;
			unset($wp_filter['wp_insert_post']); // Remove the "wp_insert_post" hook that consumes a lot of CPU and memory
			
			add_filter('woocommerce_mail_callback', function() { return function() {}; }, 99); // Prevent WooCommerce from sending emails
		}

		/**
		 * Get the number of elements to import
		 * 
		 * @since 3.0.0
		 * 
		 * @return int Number of elements to import
		 */
		private function get_total_elements_count() {
			$count = 0;
			
			do_action('fgp2wc_pre_get_total_elements_count');
			
			// CMS
			if ( !isset($this->premium_options['skip_cms']) || !$this->premium_options['skip_cms'] ) {
				$count += $this->get_cms_categories_count();
				$count += $this->get_cms_articles_count();
			}

			// Products categories
			if ( !isset($this->premium_options['skip_products_categories']) || !$this->premium_options['skip_products_categories'] ) {
				$count += $this->get_products_categories_count();
			}
			
			// Products
			if ( !isset($this->premium_options['skip_products']) || !$this->premium_options['skip_products'] ) {
				$count += $this->get_products_count();
			}

			$count = apply_filters('fgp2wc_get_total_elements_count', $count);
			
			return $count;
		}
		
		/**
		 * Stop the import
		 * 
		 * @since 3.0.0
		 */
		public function stop_import() {
			update_option('fgp2wc_stop_import', true);
		}
		
		/**
		 * Test if the import needs to stop
		 * 
		 * @since 3.0.0
		 * 
		 * @return boolean Import needs to stop or not
		 */
		public function import_stopped() {
			return get_option('fgp2wc_stop_import');
		}
		
		/**
		 * Create the WooCommerce product types
		 *
		 * @return array Product types
		 */
		protected function create_woocommerce_product_types() {
			return $this->create_unique_terms(
				array(
					'simple',
					'grouped',
					'variable',
					'external',
				), 'product_type');
		}
		
		/**
		 * Create the WooCommerce visibilities
		 *
		 * @since 3.27.0
		 * 
		 * @return array Product visibilities
		 */
		protected function create_woocommerce_product_visibilities() {
			return $this->create_unique_terms(
				array(
					'exclude-from-search',
					'exclude-from-catalog',
					'featured',
					'outofstock',
					'rated-1',
					'rated-2',
					'rated-3',
					'rated-4',
					'rated-5',
				), 'product_visibility');
		}
		
		/**
		 * Create unique terms and get them
		 *
		 * @since 3.27.0
		 * 
		 * @param array $term_slugs Term slugs
		 * @param string $taxonomy Taxonomy
		 * @return array Terms
		 */
		private function create_unique_terms($term_slugs, $taxonomy) {
			$terms = array();
			foreach ( $term_slugs as $term_slug ) {
				$term = get_term_by('slug', $term_slug, $taxonomy);
				if ( !empty($term) ) {
					$terms[$term_slug] = $term->term_id;
				} else {
					$new_term = wp_insert_term($term_slug, $taxonomy);
					if ( !is_wp_error($new_term) ) {
						$terms[$term_slug] = $new_term['term_id'];
					}
				}
			}
			return $terms;
		}
		
		/**
		 * Import PrestaShop configuration
		 */
		protected function import_configuration() {
			$config = $this->get_configuration();
			$this->default_language = $config['PS_LANG_DEFAULT'];
			update_option('fgp2wc_default_language', $this->default_language);
			$this->current_language = $this->default_language;
			$this->prestashop_version = $this->get_prestashop_version($config);
			$this->default_backorders = ($config['PS_ORDER_OUT_OF_STOCK'] == 1)? 'yes' : 'no';
			$this->default_country = isset($config['PS_COUNTRY_DEFAULT'])? $config['PS_COUNTRY_DEFAULT'] : 0;
			do_action('fgp2wc_import_configuration', $config);
		}
		
		/**
		 * Return the PrestaShop version
		 * 
		 * @since 3.7.4
		 * 
		 * @param array $config PrestaShop constants
		 * @return string PrestaShop version
		 */
		private function get_prestashop_version($config) {
			$version = '0';
			if ( isset($config['PS_VERSION_DB']) ) {
				$version = $config['PS_VERSION_DB'];
			} elseif ( !$this->column_exists('product', 'location') ) {
				$version = '1.0';
			} elseif ( !$this->column_exists('orders', 'total_products_wt') ) {
				$version = '1.2';
			} elseif ( !$this->table_exists('cms_category') ) {
				$version = '1.3';
			} elseif ( !$this->table_exists('stock_available') ) {
				$version = '1.4';
			} else {
				$version = '1.5';
			}
			return $version;
		}

		/**
		 * Import CMS data
		 */
		private function import_cms() {
			$this->import_cms_categories();
			$this->import_cms_articles();
		}
		
		/**
		 * Import CMS categories
		 *
		 * @return int Number of CMS categories imported
		 */
		private function import_cms_categories() {
			$taxonomy = 'category';
			$cat_count = 0;
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			$message = __('Importing CMS categories...', 'fg-prestashop-to-woocommerce');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_cms_categories_count());
			} else {
				$this->log($message);
			}
			
			// Set the list of previously imported categories
			$this->get_imported_cms_categories($this->current_language);
			
			$categories = $this->get_cms_categories();
			if ( is_array($categories) ) {
				$terms = array('1'); // unclassified category
				foreach ( $categories as $category ) {
					$new_category_id = $this->import_cms_category($category, $this->current_language);
					if ( !empty($new_category_id) ) {
						$cat_count++;
						$terms[] = $new_category_id;

						// Hook after importing the category
						do_action('fgp2wc_post_import_category', $new_category_id, $category);
					}
				}
				
				// Set the list of previously imported categories
				$this->get_imported_cms_categories($this->current_language);
				
				// Update the categories with their parent ids
				// We need to do it in a second step because the children categories
				// may have been imported before their parent
				foreach ( $categories as $category ) {
					if ( array_key_exists($category['id_category'], $this->imported_cms_categories[$this->current_language]) && array_key_exists($category['id_parent'], $this->imported_cms_categories[$this->current_language]) ) {
						$cat_id = $this->imported_cms_categories[$this->current_language][$category['id_category']];
						$parent_cat_id = $this->imported_cms_categories[$this->current_language][$category['id_parent']];
						wp_update_term($cat_id, $taxonomy, array('parent' => $parent_cat_id));
						if ( defined('WP_CLI') ) {
							$progress_cli->tick(1);
						}
					}
				}
				
				// Hook after importing all the categories
				do_action('fgp2wc_post_import_categories', $categories);
				
				// Update cache
				if ( !empty($terms) ) {
					wp_update_term_count_now($terms, $taxonomy);
					$this->clean_cache($terms, $taxonomy);
				}
			}
			$this->progressbar->increment_current_count($cat_count);
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			$this->display_admin_notice(sprintf(_n('%d category imported', '%d categories imported', $cat_count, 'fg-prestashop-to-woocommerce'), $cat_count));
			return $cat_count;
		}
		
		/**
		 * Import a CMS category
		 * 
		 * @since 4.49.0
		 * 
		 * @param array $category Prestashop CMS category
		 * @param int $language Language ID
		 * @return int WP category ID
		 */
		public function import_cms_category($category, $language) {
			$new_cat_id = 0;
			// Check if the category is already imported
			if ( isset($this->imported_cms_categories[$language]) && array_key_exists($category['id_category'], $this->imported_cms_categories[$language]) ) {
				return $this->imported_cms_categories[$language][$category['id_category']]; // Do not import already imported category
			}

			// Insert the category
			$new_category = array(
				'cat_name' 				=> $category['name'],
				'category_description'	=> isset($category['description'])? $category['description']: '',
				'category_nicename'		=> sanitize_title($category['slug']),
			);

			// Hook before inserting the category
			$new_category = apply_filters('fgp2wc_pre_insert_category', $new_category, $category);

			if ( ($new_cat_id = wp_insert_category($new_category)) !== false ) {
				add_term_meta($new_cat_id, '_fgp2wc_old_cms_category_id' . '-lang' . $language, $category['id_category'], true); // Store the category ID
				
				// Hook after inserting the category
				do_action('fgp2wc_post_insert_category', $new_cat_id, $category, 'category');
			}

			return $new_cat_id;
		}
		
		/**
		 * Clean the cache
		 * 
		 */
		public function clean_cache($terms=array(), $taxonomy='category') {
			delete_option($taxonomy . '_children');
			clean_term_cache($terms, $taxonomy);
		}

		/**
		 * Store the mapping of the imported CMS categories
		 * 
		 * @param int $language Language ID
		 */
		public function get_imported_cms_categories($language) {
			$this->imported_cms_categories[$language] = $this->get_term_metas_by_metakey('_fgp2wc_old_cms_category_id' . '-lang' . $language);
		}
		
		/**
		 * Import CMS articles
		 *
		 * @return array:
		 * 		int posts_count: Number of posts imported
		 */
		private function import_cms_articles() {
			$imported_posts_count = 0;
			$this->imported_tags = array();
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			$message = __('Importing CMS articles...', 'fg-prestashop-to-woocommerce');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_cms_articles_count());
			} else {
				$this->log($message);
			}
			
			// Set the list of previously imported articles to not import them twice
			$imported_cms_articles = $this->get_imported_ps_posts();
			
			// Set the list of previously imported categories
			$this->get_imported_cms_categories($this->current_language);
			
			// Hook for doing other actions before the import
			do_action('fgp2wc_pre_import_posts');
			
			do {
				if ( $this->import_stopped() ) {
					break;
				}
				$posts = $this->get_cms_articles($this->chunks_size); // Get the CMS articles
				$posts_count = count($posts);
				
				if ( is_array($posts) ) {
					foreach ( $posts as $post ) {
						
						// Increment the CMS last imported post ID
						update_option('fgp2wc_last_cms_id', $post['id_cms']);
						
						if ( !in_array($post['id_cms'], array_keys($imported_cms_articles)) ) { // Avoid duplicates
							$new_post_id = $this->import_cms_article($post, $this->current_language);
							
							if ( !is_wp_error($new_post_id) ) {
								$imported_posts_count++;
								$imported_cms_articles[$post['id_cms']] = $new_post_id;

								// Hook for doing other actions after inserting the post
								do_action('fgp2wc_post_import_post', $new_post_id, $post);
							}
						} else {
							$posts_count--; // Don't count the duplicates
						}
					}
				}
				$this->progressbar->increment_current_count($posts_count);
				
				if ( defined('WP_CLI') ) {
					$progress_cli->tick($this->chunks_size);
				}
			} while ( ($posts != null) && ($posts_count > 0) );
			
			// Hook for doing other actions after the import
			do_action('fgp2wc_post_import_posts');
			
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			$tags_count = count(array_unique($this->imported_tags));
			$this->display_admin_notice(sprintf(_n('%d article imported', '%d articles imported', $imported_posts_count, 'fg-prestashop-to-woocommerce'), $imported_posts_count));
			$this->display_admin_notice(sprintf(_n('%d tag imported', '%d tags imported', $tags_count, 'fg-prestashop-to-woocommerce'), $tags_count));
			return array(
				'posts_count'	=> $imported_posts_count,
				'tags_count'	=> $tags_count,
			);
		}
		
		/**
		 * Import a CMS article
		 *
		 * @param array $post Post data
		 * @param int $language Language ID
		 * @return int $new_post_id New imported post ID
		 */
		public function import_cms_article($post, $language) {
			// Hook for modifying the CMS post before processing
			$post = apply_filters('fgp2wc_pre_process_post', $post);

			// Date
			$post_date = $post['date'];

			// Content
			$content = !is_null($post['content'])? $post['content'] : '';

			// Medias
			if ( !$this->plugin_options['skip_media'] ) {
				// Extra featured image
				$featured_image = '';
				list($featured_image, $post) = apply_filters('fgp2wc_pre_import_media', array($featured_image, $post));
				// Import media
				$post_media = $this->import_media_from_content($featured_image . $content, $post_date);
			} else {
				// Skip media
				$post_media = array();
			}

			// Categories IDs
			$categories = array($post['id_category']);
			// Hook for modifying the post categories
			$categories = apply_filters('fgp2wc_post_categories', $categories, $post);
			$categories_ids = array();
			foreach ( $categories as $ps_category_id ) {
				if ( array_key_exists($ps_category_id, $this->imported_cms_categories[$language]) ) {
					$categories_ids[] = $this->imported_cms_categories[$language][$ps_category_id];
				}
			}
			if ( count($categories_ids) == 0 ) {
				$categories_ids[] = 1; // default category
			}

			// Process content
			$content = $this->process_content($content, $post_media);

			// Status
			$status = ($post['active'] == 1)? 'publish' : 'draft';

			// Tags
			$tags = array();
			if ( $this->plugin_options['meta_keywords_in_tags'] && !empty($post['meta_keywords']) ) {
				$tags = explode(',', $post['meta_keywords']);
				$this->import_tags($tags, 'post_tag');
				$this->imported_tags = array_merge($this->imported_tags, $tags);
			}

			// Insert the post
			$new_post = array(
				'post_category'		=> $categories_ids,
				'post_content'		=> $content,
				'post_date'			=> $post_date,
				'post_status'		=> $status,
				'post_title'		=> $post['meta_title'],
				'post_name'			=> $post['slug'],
				'post_type'			=> $this->post_type,
				'tags_input'		=> $tags,
				'menu_order'        => $post['position'],
			);

			// Hook for modifying the WordPress post just before the insert
			$new_post = apply_filters('fgp2wc_pre_insert_post', $new_post, $post);

			$new_post_id = wp_insert_post($new_post, true);
			
			if ( !is_wp_error($new_post_id) ) {
				add_post_meta($new_post_id, '_fgp2wc_old_cms_article_id', $post['id_cms']);
				
				// Add links between the post and its medias
				$this->add_post_media($new_post_id, $this->get_attachment_ids($post_media), $post_date, $this->plugin_options['first_image'] != 'as_is');
				
				// Hook for doing other actions after inserting the post
				do_action('fgp2wc_post_insert_post', $new_post_id, $post, 'cms-' . $post['id_cms']);
			}
			return $new_post_id;
		}
		
		/**
		 * Import tags
		 * 
		 * @since 2.8.0
		 * 
		 * @param array $tags Tags
		 * @param string $taxonomy Taxonomy (post_tag | product_tag)
		 */
		public function import_tags($tags, $taxonomy) {
			foreach ( $tags as $tag ) {
				$new_term = wp_insert_term($tag, $taxonomy);
				if ( !is_wp_error($new_term) ) {
					add_term_meta($new_term['term_id'], '_fgp2wc_imported', 1, true);
				}
			}
		}
		
		/**
		 * Get the imported products
		 *
		 * @since 3.36.0
		 * 
		 * @return array of products mapped with the PrestaShop products ids
		 */
		public function get_imported_products() {
			return $this->get_imported_ps_posts($meta_key = '_fgp2wc_old_product_id');
		}
		
		/**
		 * Returns the imported posts mapped with their PrestaShop ID
		 *
		 * @since 3.6.0
		 * 
		 * @param string $meta_key Meta key (default = _fgp2wc_old_cms_article_id)
		 * @return array of post IDs [ps_article_id => wordpress_post_id]
		 */
		public function get_imported_ps_posts($meta_key = '_fgp2wc_old_cms_article_id') {
			global $wpdb;
			$posts = array();

			$sql = $wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$posts[$result->meta_value] = $result->post_id;
			}
			ksort($posts);
			return $posts;
		}

		/**
		 * Store the mapping of the imported product categories
		 * 
		 * @param int $language Language ID
		 */
		public function get_imported_categories($language) {
			$this->imported_categories[$language] = $this->get_term_metas_by_metakey('_fgp2wc_old_product_category_id' . '-lang' . $language);
		}
		
		/**
		 * Store the mapping of the imported manufacturers
		 * 
		 * @since 3.2.0
		 * 
		 * @param int $language Language ID
		 */
		public function get_imported_manufacturers($language) {
			$this->imported_manufacturers[$language] = $this->get_term_metas_by_metakey('_fgp2wc_old_manufacturer_id' . '-lang' . $language);
		}
		
		/**
		 * Returns the imported employees mapped with their PrestaShop ID
		 *
		 * @since 3.65.0
		 * 
		 * @return array of user IDs [prestashop_employee_id => wordpress_user_id]
		 */
		public function get_imported_prestashop_employees() {
			return $this->get_users_by_meta_key('prestashop_employee_id');
		}

		/**
		 * Returns the imported customers mapped with their PrestaShop ID
		 *
		 * @since 3.65.0
		 * 
		 * @return array of user IDs [prestashop_customer_id => wordpress_user_id]
		 */
		public function get_imported_prestashop_customers() {
			return $this->get_users_by_meta_key('prestashop_customer_id');
		}

		/**
		 * Returns users by meta key
		 *
		 * @since 3.65.0
		 * 
		 * @param string $meta_key Meta key
		 * @return array of user IDs [prestashop_user_id => wordpress_user_id]
		 */
		private function get_users_by_meta_key($meta_key) {
			global $wpdb;
			$users = array();

			$sql = $wpdb->prepare("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$users[$result->meta_value] = $result->user_id;
			}
			ksort($users);
			return $users;
		}

		/**
		 * Import product categories
		 *
		 * @return int Number of product categories imported
		 */
		private function import_product_categories() {
			$cat_count = 0;
			$imported_cat_count = 0;
			$terms = array();
			$taxonomy = 'product_cat';
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			$message = __('Importing product categories...', 'fg-prestashop-to-woocommerce');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_products_categories_count());
			} else {
				$this->log($message);
			}
			
			// Allow HTML in term descriptions
			foreach ( array('pre_term_description') as $filter ) {
				remove_filter($filter, 'wp_filter_kses');
			}
			
			// Set the list of previously imported categories
			$this->get_imported_categories($this->current_language);
			
			$categories = $this->get_all_product_categories();
			$cat_count = count($categories);
			foreach ( $categories as $category ) {
				if ( $this->import_stopped() ) {
					return 0;
				}
				if ( !array_key_exists($category['id_category'], $this->imported_categories[$this->current_language]) ) { // Category not already imported
					$new_category_id = $this->import_product_category($category, $this->current_language);
					if ( !empty($new_category_id) ) {
						$imported_cat_count++;
						$terms[] = $new_category_id;

						// Hook after importing the category
						do_action('fgp2wc_post_import_product_category', $new_category_id, $category);
					}
					if ( defined('WP_CLI') ) {
						$progress_cli->tick(1);
					}
				} else {
					$cat_count--;
				}
			}
			
			// Set the list of imported categories
			$this->get_imported_categories($this->current_language);
			
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			// Hook after importing all the categories
			do_action('fgp2wc_post_import_product_categories', $categories);
			
			// Update cache
			if ( !empty($terms) ) {
				wp_update_term_count_now($terms, $taxonomy);
				$this->clean_cache($terms, $taxonomy);
			}
			$this->progressbar->increment_current_count($cat_count);
			$this->display_admin_notice(sprintf(_n('%d product category imported', '%d product categories imported', $imported_cat_count, 'fg-prestashop-to-woocommerce'), $imported_cat_count));
		}
		
		/**
		 * Import a product category
		 * 
		 * @since 3.64.0
		 * 
		 * @param array $category Prestashop product category
		 * @param int $language Language ID
		 * @return int WP category ID
		 */
		public function import_product_category($category, $language) {
			$new_category_id = 0;
			$taxonomy = 'product_cat';
			
			// Check if the category is already imported
			if ( isset($this->imported_categories[$language]) && array_key_exists($category['id_category'], $this->imported_categories[$language]) ) {
				return $this->imported_categories[$language][$category['id_category']]; // Do not import already imported category
			}

			// Slug
			$slug = sanitize_title($category['slug']);
			if ( ($language != $this->default_language) && term_exists($slug, $taxonomy) ) {
				$slug .= '-' . $language;
			}
			
			// Insert the category
			$new_category = array(
				'description'	=> isset($category['description'])? $category['description']: '',
				'slug'			=> $slug,
			);

			// Parent category ID
			if ( isset($this->imported_categories[$language]) && array_key_exists($category['id_parent'], $this->imported_categories[$language]) ) {
				$parent_cat_id = $this->imported_categories[$language][$category['id_parent']];
				$new_category['parent'] = $parent_cat_id;
			}

			// Hook before inserting the category
			$new_category = apply_filters('fgp2wc_pre_insert_product_category', $new_category, $category);

			$new_term = wp_insert_term($category['name'], $taxonomy, $new_category);
			if ( !is_wp_error($new_term) ) {
				$new_category_id = $new_term['term_id'];
				$this->imported_categories[$language][$category['id_category']] = $new_category_id;

				// Store the product category ID
				add_term_meta($new_category_id, '_fgp2wc_old_product_category_id' . '-lang' . $language, $category['id_category'], true);

				// Category ordering
				if ( function_exists('wc_set_term_order') ) {
					wc_set_term_order($new_category_id, $category['position'], $taxonomy);
				}

				// Hook after inserting the category
				do_action('fgp2wc_post_insert_product_category', $new_category_id, $category, $taxonomy);
			}
			
			return $new_category_id;
		}
		
		/**
		 * Import the product category thumbnails
		 * 
		 * @since 4.39.0
		 * 
		 * @param int $new_category_id WP Category ID
		 * @param array $category Product category
		 */
		public function import_product_category_thumbnails($new_category_id, $category) {
			if ( !$this->plugin_options['skip_media'] ) {
				if ( ($category['id_parent'] != 0) && ($category['is_root_category'] != 1) ) { // Don't try to import root categories thumbnails
					$category_thumbnails = $this->build_image_filenames('category', $category['id_category']); // Get the potential filenames
					foreach ( $category_thumbnails as $category_thumbnail ) {
						if ( !empty($category_thumbnail) && $this->url_exists($category_thumbnail) ) {
							$thumbnail_id = $this->import_media($category['name'], $category_thumbnail, $category['date']);
							if ( !empty($thumbnail_id) ) {
								update_term_meta($new_category_id, 'thumbnail_id', $thumbnail_id);
								break; // the media has been imported, we don't continue with the other potential filenames
							}
						}
					}
				}
			}
		}
		
		/**
		 * Import products
		 *
		 * @return int Number of products imported
		 */
		private function import_products() {
			$imported_products_count = 0;
			
			if ( !$this->test_woocommerce_activation() ) {
				return 0;
			}
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			
			// Hook for doing other actions before importing the products
			do_action('fgp2wc_pre_import_products');
			
			if ( $this->import_stopped() ) {
				return 0;
			}
			
			$message = __('Importing products...', 'fg-prestashop-to-woocommerce');
			if ( defined('WP_CLI') ) {
				$progress_cli = \WP_CLI\Utils\make_progress_bar($message, $this->get_products_count());
			} else {
				$this->log($message);
			}
			
			// Set the list of previously imported articles to not import them twice
			$imported_products = $this->get_imported_products();
			
			do {
				if ( $this->import_stopped() ) {
					break;
				}
				$products = $this->get_products($this->chunks_size);
				$products_count = count($products);
				foreach ( $products as $product ) {
					
					// Increment the PrestaShop last imported product ID
					update_option('fgp2wc_last_product_id', $product['id_product']);
					
					if ( !in_array($product['id_product'], array_keys($imported_products)) ) { // Avoid duplicates
						$new_post_id = $this->import_product($product, $this->current_language);

						if ( !is_wp_error($new_post_id) ) {
							$imported_products_count++;
							$imported_products[$product['id_product']] = $new_post_id;

							// Hook for doing other actions after inserting the post
							do_action('fgp2wc_post_import_product', $new_post_id, $product);
						}
					} else {
						$products_count--; // Don't count the duplicates
					}
				}
				$this->progressbar->increment_current_count($products_count);
				
				if ( defined('WP_CLI') ) {
					$progress_cli->tick($this->chunks_size);
				}
			} while ( ($products != null) && ($products_count > 0) );
			
			// Recount the terms
			$this->recount_terms();
			
			if ( defined('WP_CLI') ) {
				$progress_cli->finish();
			}
			
			// Hook for doing other actions after all products are imported
			do_action('fgp2wc_post_import_products');
			
			$this->display_admin_notice(sprintf(_n('%d product imported', '%d products imported', $imported_products_count, 'fg-prestashop-to-woocommerce'), $imported_products_count));
		}
		
		/**
		 * Import a product
		 *
		 * @param array $product Product data
		 * @param int $language Language ID
		 * @return int New post ID
		 */
		public function import_product($product, $language) {
			$product_medias = array();
			$post_media = array();

			// Date
			$date = $product['date'];

			// Product images
			if ( !$this->plugin_options['skip_media'] ) {

				$images = $this->get_product_images($product['id_product']);
				foreach ( $images as $image ) {
					$image_name = !empty($image['legend'])? $image['legend'] : $product['name'];
					$image_filenames = $this->build_image_filenames('product', $image['id_image'], $product['id_product']); // Get the potential filenames
					$media_id = $this->guess_import_media($image_name, $image_filenames, $date, $image['id_image']);
					if ( $media_id !== false ) {
						$product_medias[] = $media_id;
					}
				}

				// Import content media
				$post_media = $this->import_media_from_content($product['description'] . $product['description_short'], $date);
			}

			// Product categories
			$categories_ids = array();
			$product_categories = $this->get_product_categories($product['id_product']);
			foreach ( $product_categories as $cat ) {
				if ( isset($this->imported_categories[$language]) && array_key_exists($cat['id_category'], $this->imported_categories[$language]) ) {
					$categories_ids[] = $this->imported_categories[$language][$cat['id_category']];
				}
			}
			$position = isset($product_categories[0])? $product_categories[0]['position'] : 0;

			// Tags
			$tags = $this->get_product_tags($product['id_product']);
			if ( $this->plugin_options['meta_keywords_in_tags'] && !empty($product['meta_keywords']) ) {
				$tags = array_merge($tags, explode(',', $product['meta_keywords']));
			}
			$this->import_tags($tags, 'product_tag');

			// Process content
			$content = isset($product['description'])? $product['description'] : '';
			$content = $this->process_content($content, $post_media);
			$excerpt = isset($product['description_short'])? $product['description_short'] : '';
			$excerpt = $this->process_content($excerpt, $post_media);
			
			// Status
			$status = (($product['active'] == 1) && ($product['available_for_order'] == 1))? 'publish': 'draft';
			
			// Insert the post
			$new_post = array(
				'post_content'		=> $content,
				'post_date'			=> $date,
				'post_excerpt'		=> $excerpt,
				'post_status'		=> $status,
				'post_title'		=> $product['name'],
				'post_name'			=> $product['slug'],
				'post_type'			=> 'product',
				'menu_order'		=> $position,
				'tax_input'			=> array(
					'product_cat'	=> $categories_ids,
					'product_tag'	=> $tags,
				),
			);

			// Hook for modifying the WordPress post just before the insert
			$new_post = apply_filters('fgp2wc_pre_insert_product', $new_post, $product);
			
			$new_post_id = wp_insert_post($new_post, true);

			if ( !is_wp_error($new_post_id) ) {
				// Product type (simple or variable)
				$product_type = $this->product_types['simple'];
				wp_set_object_terms($new_post_id, intval($product_type), 'product_type', true);
				
				// Product visibility
				$this->set_product_visibility($new_post_id, $product['visibility']);
				
				// Product galleries
				$medias_id = array();
				foreach ($product_medias as $media) {
					$medias_id[] = $media;
				}
				if ( $this->plugin_options['first_image_not_in_gallery'] ) {
					// Don't include the first image into the product gallery
					array_shift($medias_id);
				}
				$gallery = implode(',', $medias_id);

				// Prices
				$product['specific_prices'] = $this->get_specific_prices($product['id_product']);
				$prices = $this->calculate_prices($product);
				$reduction_from = isset($product['reduction_from'])? strtotime($product['reduction_from']): '';
				$reduction_to = isset($product['reduction_to'])? strtotime($product['reduction_to']): '';
				
				// SKU = Stock Keeping Unit
				$sku = $this->get_sku($product);
				if ( empty($sku) ) {
					$sku = $this->get_product_supplier_reference($product['id_product']);
				}

				// Stock
				$manage_stock = $this->plugin_options['stock_management']? 'yes': 'no';
				$quantity = 0;
				$out_of_stock_value = 0;
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					$quantity = $product['quantity'];
					$out_of_stock_value = $product['out_of_stock'];
				} else {
					$stock = $this->get_product_stock($product['id_product'], 0, $this->shop_id);
					if ( !empty($stock) ) {
						$quantity = $stock['quantity'];
						$out_of_stock_value = $stock['out_of_stock'];
					}
				}
				$stock_status = (!$this->plugin_options['stock_management'] || ($quantity > 0))? 'instock': 'outofstock';
				if ( $stock_status == 'outofstock' ) {
					wp_set_object_terms($new_post_id, $this->product_visibilities['outofstock'], 'product_visibility', true);
				}

				// Backorders
				$backorders = $this->allow_backorders($out_of_stock_value);
				if ( ($backorders == 'yes') && ($stock_status == 'outofstock') ) {
					$stock_status = 'onbackorder';
				}

				// Add the meta data
				add_post_meta($new_post_id, '_stock_status', $stock_status, true);
				add_post_meta($new_post_id, '_regular_price', $prices['regular_price'], true);
				add_post_meta($new_post_id, '_price', $prices['price'], true);
				if ( $prices['special_price'] != $prices['regular_price'] ) {
					add_post_meta($new_post_id, '_sale_price', $prices['special_price'], true);
					add_post_meta($new_post_id, '_sale_price_dates_from', $reduction_from, true);
					add_post_meta($new_post_id, '_sale_price_dates_to', $reduction_to, true);
				}
				add_post_meta($new_post_id, '_weight', floatval($product['weight']), true);
				add_post_meta($new_post_id, '_length', floatval($product['depth']), true);
				add_post_meta($new_post_id, '_width', floatval($product['width']), true);
				add_post_meta($new_post_id, '_height', floatval($product['height']), true);
				add_post_meta($new_post_id, '_sku', $sku, true);
				add_post_meta($new_post_id, '_stock', $quantity, true);
				add_post_meta($new_post_id, '_manage_stock', $manage_stock, true);
				add_post_meta($new_post_id, '_backorders', $backorders, true);
				add_post_meta($new_post_id, '_product_image_gallery', $gallery, true);
				add_post_meta($new_post_id, '_virtual', 'no', true);
				add_post_meta($new_post_id, '_downloadable', 'no', true);
				add_post_meta($new_post_id, 'total_sales', 0, true);
				add_post_meta($new_post_id, '_wc_review_count', 0, true);
				add_post_meta($new_post_id, '_wc_rating_count', array(), true);
				add_post_meta($new_post_id, '_wc_average_rating', 0, true);
				
				// Add the reference value
				if ( ($this->plugin_options['sku'] != 'reference') && !empty($product['reference']) ) {
					add_post_meta($new_post_id, 'reference', $product['reference'], true);
				}
				
				// Add the EAN-13 value
				if ( !empty($product['ean13']) ) {
					// Default WooCommerce field GTIN, UPC, EAN or ISBN
					add_post_meta($new_post_id, '_global_unique_id', $product['ean13'], true);
					// Barcode
					add_post_meta($new_post_id, 'barcode', $product['ean13'], true);
					// Product GTIN (EAN, UPC, ISBN) for WooCommerce
					add_post_meta($new_post_id, '_wpm_gtin_code', $product['ean13'], true);
					// EAN for WooCommerce
					add_post_meta($new_post_id, '_alg_ean', $product['ean13'], true);
					// WP-Lister Lite for Amazon
					add_post_meta($new_post_id, '_amazon_product_id', $product['ean13'], true);
				}
				
				// Add links between the post and its medias
				$this->add_post_media($new_post_id, $product_medias, $date, true);
				$this->add_post_media($new_post_id, $this->get_attachment_ids($post_media), $date, false);

				// Add the PrestaShop ID as a post meta
				add_post_meta($new_post_id, '_fgp2wc_old_product_id', $product['id_product'], true);
				
				// Hook for doing other actions after inserting the post
				do_action('fgp2wc_post_insert_post', $new_post_id, $product, 'product-' . $product['id_product']);
				do_action('fgp2wc_post_insert_product', $new_post_id, $product, $language);
				do_action('woocommerce_run_product_attribute_lookup_update_callback', $new_post_id, 1); // 1 = ACTION_INSERT
				
				if ( function_exists('wc_delete_product_transients') ) {
					wc_delete_product_transients($new_post_id);
				}
			}
			return $new_post_id;
		}
		
		/**
		 * Import a media by guessing its name
		 * 
		 * @param string $image_name Image name
		 * @param array $image_filenames List of potential filenames
		 * @param date $date Media creation date
		 * @param int $image_id Original image ID (optional)
		 * @return int media ID
		 */
		public function guess_import_media($image_name, $image_filenames, $date='', $image_id=0) {
			// Optimization to get the right image filename
			$media_id = false;
			if ( $this->image_filename_key !== false ) {
				$media_id = $this->import_media($image_name, $image_filenames[$this->image_filename_key], $date, array(), $image_id);
			}
			if ( $media_id === false ) {
				foreach ( $image_filenames as $key => $image_filename ) {
					if ( $key !== $this->image_filename_key ) {
						$media_id = $this->import_media($image_name, $image_filename, $date, array(), $image_id);
						if ( $media_id !== false ) {
							$this->image_filename_key = $key;
							break; // the media has been imported, we don't continue with the other potential filenames
						}
					}
				}
			}
			return $media_id;
		}
		
		/**
		 * Calculate the product prices
		 * 
		 * @since 3.4.0
		 * 
		 * @param array $product Product
		 * @return array Prices
		 */
		public function calculate_prices($product) {
			$regular_price = isset($product['price'])? floatval($product['price']): 0.0;
			
			// Specific regular price if this is an attribute
			if ( isset($product['specific_prices']) && isset($product['id_product_attribute']) ) {
				foreach ( $product['specific_prices'] as $specific_price ) {
					if ( ($product['id_product_attribute'] == $specific_price['id_product_attribute']) ) {
						if ( $specific_price['price'] > 0.0 ) {
							$regular_price = $specific_price['price'];
							break;
						}
					}
				}
			}
			
			// Special price
			$special_price = $this->calculate_special_price($regular_price, $product, 'before_tax');
			
			// Tax included
			if ( $this->plugin_options['price'] == 'with_tax' ) {
				$regular_price *= $this->global_tax_rate;
				$regular_price = round($regular_price, 4);
				if ( !empty($special_price) ) {
					$special_price *= $this->global_tax_rate;
					$special_price = round($special_price, 4);
				}
			}
			
			$special_price = $this->calculate_special_price($special_price, $product, 'after_tax');
			if ( $special_price == 0.0 ) {
				$special_price = '';
			}
			$reduction_from = isset($product['reduction_from'])? strtotime($product['reduction_from']): '';
			$reduction_to = isset($product['reduction_to'])? strtotime($product['reduction_to']): '';
			$now = time();
			if ( !empty($special_price) && ($now > $reduction_from) && (($now < $reduction_to) || empty($reduction_to)) ) {
				$price = $special_price;
			} else {
				$price = $regular_price;
			}
			$prices = array(
				'regular_price'	=> $regular_price,
				'special_price'	=> $special_price,
				'price'			=> $price,
			);
			
			return $prices;
		}
		
		/**
		 * Get the specific prices for a product (PrestaShop 1.4+)
		 * 
		 * @since 3.11.0
		 * 
		 * @param int $product_id Preduct ID
		 * @return array Specific prices
		 */
		protected function get_specific_prices($product_id) {
			$prices = array();
			
			if ( version_compare($this->prestashop_version, '1.4', '>=') ) {
				$prefix = $this->plugin_options['prefix'];
				$extra_criteria = '';
				if ( version_compare($this->prestashop_version, '1.5', '<') ) {
					// PrestaShop 1.4
					$product_attribute_column = '"" AS id_product_attribute';
				} else {
					// PrestaShop 1.5
					$product_attribute_column = 'p.id_product_attribute';
					if ( ($this->shops_count > 1) && ($this->shop_id != 0) ) {
						$extra_criteria .= "AND p.id_shop = '{$this->shop_id}'";
					}
					$extra_criteria .= "AND p.id_specific_price_rule = 0";
				}
				if ( $this->column_exists('specific_price', 'reduction_tax') ) {
					$reduction_tax_field = 'p.reduction_tax';
				} else {
					$reduction_tax_field = '0 AS reduction_tax';
				}
				$sql = "
					SELECT p.id_specific_price, $product_attribute_column, p.price, p.from_quantity, p.reduction, $reduction_tax_field, p.reduction_type, p.`from`, p.`to`
					FROM {$prefix}specific_price p
					WHERE p.id_product = '$product_id'
					AND p.id_group IN(0, 1) -- 0 = All groups, 1 = Visitors
					AND (p.id_country = {$this->default_country} OR p.id_country = 0)
					AND (p.from <= NOW() OR p.from = '0000-00-00 00:00:00')
					AND (p.to >= NOW() OR p.to = '0000-00-00 00:00:00')
					AND p.id_cart = 0
					$extra_criteria
					ORDER BY p.from_quantity
				";
				$sql = apply_filters('fgp2wc_get_specific_prices_sql', $sql);
				$prices = $this->prestashop_query($sql);
			}
			
			return $prices;
		}
		
		/**
		 * Calculate the special price for a product
		 * 
		 * @since 3.26.0
		 * 
		 * @param float $regular_price Regular price
		 * @param array $product Product data
		 * @param string $before_or_after_tax 'before_tax' or 'after_tax'
		 * @return float Special price
		 */
		private function calculate_special_price($regular_price, $product, $before_or_after_tax) {
			$special_price = 0.0;
			if ( version_compare($this->prestashop_version, '1.4', '<') ) {
				// PrestaShop 1.3 and less
				if ( $before_or_after_tax == 'before_tax' ) {
					if ( isset($product['reduction_price']) && !empty($product['reduction_price']) && ($product['reduction_price'] != '0.00') ) {
						$special_price = floatval($product['reduction_price']);
					} elseif ( isset($product['reduction_percent']) && !empty($product['reduction_percent']) ) {
						$special_price = $regular_price * (100 - $product['reduction_percent']) / 100;
					}
				}
			} else {
				// PrestaShop 1.4+
				if ( isset($product['id_product']) ) {
					if ( count($product['specific_prices']) > 0 ) {
						$special_price = $regular_price;
						foreach ( $product['specific_prices'] as $specific_price ) {
							if ( (!isset($specific_price['id_customer']) || ($specific_price['id_customer'] == 0)) // Don't use the customer specific prices
								&& (!isset($specific_price['id_group']) || ($specific_price['id_group'] == 0)) // Don't use the group specific prices
								) {
								if ( $specific_price['from_quantity'] <= 1 ) {
									if ( $specific_price['price'] > 0 ) {
										// Fixed amount
										if ( $before_or_after_tax == 'before_tax' ) {
											$special_price = $regular_price; // Special price will be calculated after the tax addition
										} else {
											$special_price = $specific_price['price'] / $this->global_tax_rate;
										}
									} else {
										if ( (($before_or_after_tax == 'before_tax') && ($specific_price['reduction_tax'] == 0)) || (($before_or_after_tax == 'after_tax') && ($specific_price['reduction_tax'] == 1)) ) {
											if ( !isset($product['id_product_attribute']) || empty($specific_price['id_product_attribute']) || ($product['id_product_attribute'] == $specific_price['id_product_attribute']) ) {
												$reduction = $specific_price['reduction'];
												if ( $specific_price['reduction_type'] == 'percentage' ) {
													$special_price *= (1 - $reduction); // Percentage reduction
												} else {
													if ( ($this->plugin_options['price'] != 'with_tax') && ($specific_price['reduction_tax'] == 1) ) {
														$reduction /= $this->global_tax_rate; // Remove the tax if the product is imported without tax and if the specific price is set with tax
													}
													$special_price -= $reduction; // Amount reduction
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
			return $special_price;
		}
		
		/**
		 * Get the SKU from a product or from a product attribute
		 * 
		 * @param array $product Product or product attribute
		 * @return string SKU
		 */
		public function get_sku($product) {
			$sku = '';
			
			// SKU = Stock Keeping Unit
			switch ( $this->plugin_options['sku'] ) {
				case 'ean13':
					$sku = $product['ean13'];
					break;
				default:
					$sku = $product['reference'];
			}
			if ( empty($sku) ) {
				$sku = $product['supplier_reference'];
			}
			return $sku;
		}
		
		/**
		 * Get the product stock (quantity and backorder)
		 * 
		 * @since 3.8.2
		 * 
		 * @param int $product_id PS product ID
		 * @param int $product_attribute_id PS product attribute ID
		 * @param int $shop_id Shop ID
		 * @return array Stock
		 */
		public function get_product_stock($product_id, $product_attribute_id=0, $shop_id=0) {
			$stock = array();
			$prefix = $this->plugin_options['prefix'];
			$extra_criteria = '';
			if ( $shop_id != 0 ) {
				$extra_criteria = "AND (s.id_shop = '$shop_id' OR s.id_shop = 0)";
			}
			$sql = "
				SELECT s.quantity, s.out_of_stock
				FROM {$prefix}stock_available s
				WHERE s.id_product = '$product_id'
				AND s.id_product_attribute = '$product_attribute_id'
				$extra_criteria
				ORDER BY s.id_shop DESC
				LIMIT 1
			";
			$result = $this->prestashop_query($sql);
			if ( count($result) > 0 ) {
				$stock = $result[0];
			}
			
			return $stock;
		}
		
		/**
		 * Set the product visibility in WooCommerce
		 * 
		 * @since 3.27.0
		 * 
		 * @param int $new_post_id Post ID
		 * @param string $visibility PrestaShop visibility
		 */
		protected function set_product_visibility($new_post_id, $visibility) {
			switch ( $visibility ) {
				case 'catalog':
					wp_set_object_terms($new_post_id, $this->product_visibilities['exclude-from-search'], 'product_visibility', true);
					break;
					
				case 'search':
					wp_set_object_terms($new_post_id, $this->product_visibilities['exclude-from-catalog'], 'product_visibility', true);
					break;
					
				case 'none':
					wp_set_object_terms($new_post_id, $this->product_visibilities['exclude-from-search'], 'product_visibility', true);
					wp_set_object_terms($new_post_id, $this->product_visibilities['exclude-from-catalog'], 'product_visibility', true);
					break;
			}
		}
		
		/**
		 * Recalculate the terms counters
		 * 
		 */
		private function recount_terms() {
			if (method_exists('WC_Cache_Helper', 'invalidate_cache_group') ) {
				WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
			}
			$taxonomy_names = wc_get_attribute_taxonomy_names();
			foreach ( $taxonomy_names as $taxonomy ) {
				$terms = get_terms(array(
					'taxonomy' => $taxonomy,
					'hide_empty' => false,
				));
				$termtax = array();
				foreach ( $terms as $term ) {
					$termtax[] = $term->term_taxonomy_id; 
				}
				wp_update_term_count($termtax, $taxonomy);
			}
		}
		
		/**
		 * Get PrestaShop configuration
		 *
		 * @return array of keys/values
		 */
		private function get_configuration() {
			$config = array();

			$prefix = $this->plugin_options['prefix'];
			$sql = "
				SELECT name, value
				FROM {$prefix}configuration
				ORDER BY id_configuration
			";

			$result = $this->prestashop_query($sql);
			foreach ( $result as $row ) {
				if ( !isset($config[$row['name']]) ) {
					$config[$row['name']] = $row['value'];
				}
			}
			return $config;
		}
		
		/**
		 * Get CMS categories
		 *
		 * @return array of Categories
		 */
		private function get_cms_categories() {
			$categories = array();
			
			if ( version_compare($this->prestashop_version, '1.4', '<') ) {
				$category_table = 'category'; // PrestaShop 1.4
				$order = 'c.id_category';
			} else {
				$category_table = 'cms_category';
				$order = 'c.position';
			}
			if ( $this->table_exists($category_table) ) {
				$prefix = $this->plugin_options['prefix'];
				$extra_joins = '';
				$extra_join_lang = '';
				$extra_criteria = '';
				if ( $this->column_exists($category_table . '_lang', 'id_shop') ) {
					$extra_joins .= "INNER JOIN {$prefix}{$category_table}_shop cs ON cs.id_{$category_table} = c.id_{$category_table}";
					$extra_join_lang = "AND cl.id_shop = cs.id_shop";
					if ( $this->shop_id != 0 ) {
						$extra_criteria .= "AND cs.id_shop = '{$this->shop_id}'";
					}
				}
				$sql = "
					SELECT DISTINCT c.id_{$category_table} AS id_category, cl.id_lang, cl.name, cl.link_rewrite AS slug, cl.description, c.id_parent, $order
					FROM {$prefix}{$category_table} c
					$extra_joins
					INNER JOIN {$prefix}{$category_table}_lang cl ON cl.id_{$category_table} = c.id_{$category_table} AND cl.id_lang = '{$this->current_language}' $extra_join_lang
					WHERE c.active = 1
					$extra_criteria
					ORDER BY $order
				";
				$sql = apply_filters('fgp2wc_get_cms_categories_sql', $sql, $prefix);

				$categories = $this->prestashop_query($sql);
				$categories = apply_filters('fgp2wc_get_cms_categories', $categories);
			}
			return $categories;
		}
		
		/**
		 * Get CMS articles
		 *
		 * @param int $limit Number of articles max
		 * @return array of Posts
		 */
		protected function get_cms_articles($limit=1000) {
			$articles = array();
			
			$last_prestashop_cms_id = (int)get_option('fgp2wc_last_cms_id'); // to restore the import where it left

			$prefix = $this->plugin_options['prefix'];
			$lang = $this->current_language;

			// Hooks for adding extra cols and extra joins
			$extra_cols = apply_filters('fgp2wc_get_posts_add_extra_cols', '');
			$extra_joins = apply_filters('fgp2wc_get_posts_add_extra_joins', '');

			// Index or no index
			if ( $this->column_exists('cms', 'indexation') ) {
				$indexation_field = 'a.indexation';
			} else {
				$indexation_field = ' 1 AS indexation';
			}

			if ( version_compare($this->prestashop_version, '1.4', '<') ) {
				// PrestaShop 1.3
				$sql = "
					SELECT a.id_cms, l.id_lang, l.meta_title, l.meta_description, l.meta_keywords, l.content, l.link_rewrite AS slug, '' AS id_category, 0 AS position, 1 AS active, $indexation_field, '' AS date
					$extra_cols
					FROM {$prefix}cms a
					INNER JOIN {$prefix}cms_lang AS l ON l.id_cms = a.id_cms AND l.id_lang = '$lang'
					WHERE a.id_cms > '$last_prestashop_cms_id'
					$extra_joins
					ORDER BY a.id_cms
					LIMIT $limit
				";
			} else {
				// PrestaShop 1.4+
				$extra_join_lang = '';
				$extra_criteria = '';
				if ( version_compare($this->prestashop_version, '1.5', '>=') ) {
					// PrestaShop 1.5+
					if ( $this->column_exists('cms_lang', 'id_shop') ) {
						$extra_joins .= " INNER JOIN {$prefix}cms_shop AS cs ON cs.id_cms = a.id_cms";
						$extra_join_lang = " AND l.id_shop = cs.id_shop";
						if ( $this->shop_id != 0 ) {
							$extra_criteria .= " AND cs.id_shop = '{$this->shop_id}'";
						}
					}
				}
				$sql = "
					SELECT DISTINCT a.id_cms, l.id_lang, l.meta_title, l.meta_description, l.meta_keywords, l.content, l.link_rewrite AS slug, a.id_cms_category AS id_category, a.position, a.active, $indexation_field, c.date_add AS date
					$extra_cols
					FROM {$prefix}cms a
					$extra_joins
					INNER JOIN {$prefix}cms_lang AS l ON l.id_cms = a.id_cms AND l.id_lang = '$lang' $extra_join_lang
					LEFT JOIN {$prefix}cms_category AS c ON c.id_cms_category = a.id_cms_category
					WHERE a.id_cms > '$last_prestashop_cms_id'
					$extra_criteria
					ORDER BY a.id_cms
					LIMIT $limit
				";
			}
			$sql = apply_filters('fgp2wc_get_posts_sql', $sql, $prefix, $extra_cols, $extra_joins, $last_prestashop_cms_id, $limit);
			$articles = $this->prestashop_query($sql);
			
			return $articles;
		}
		
		/**
		 * Get product categories
		 *
		 * @return array of Categories
		 */
		private function get_all_product_categories() {
			$categories = array();

			$prefix = $this->plugin_options['prefix'];
			$lang = $this->default_language;
			$root_category_field = version_compare($this->prestashop_version, '1.5', '<')? '0 AS is_root_category' : 'c.is_root_category';
			
			// Exclude the parent categories of the root category
			$root_category = $this->get_root_category();
			$exclude_root_parent_categories_sql = '';
			if ( !empty($root_category) ) {
				$exclude_root_parent_categories_sql = "AND c.level_depth >= " . $root_category['level_depth'];
			}
			
			if ( version_compare($this->prestashop_version, '1.4', '<') ) {
				// PrestaShop 1.3
				$position_field = '0 AS position';
				$order = 'c.level_depth, c.id_category';
			} else {
				$position_field = 'c.position';
				$order = 'c.level_depth, c.position';
			}
			$extra_joins = '';
			$extra_join_category_lang = '';
			$extra_criteria = '';
			if ( version_compare($this->prestashop_version, '1.5', '>=') ) {
				// PrestaShop 1.5+
				$position_field = 'cs.position';
				$extra_joins .= "INNER JOIN {$prefix}category_shop cs ON cs.id_category = c.id_category";
				$extra_join_category_lang = "AND cl.id_shop = cs.id_shop";
				if ( $this->shop_id != 0 ) {
					$extra_criteria .= "AND cs.id_shop = '{$this->shop_id}'";
				}
			}
			$sql = "
				SELECT DISTINCT c.id_category, c.date_add AS date, $position_field, c.id_parent, $root_category_field, cl.id_lang, cl.name, cl.description, cl.link_rewrite AS slug, cl.meta_description, cl.meta_keywords, cl.meta_title, $order
				FROM {$prefix}category c
				$extra_joins
				LEFT JOIN {$prefix}category_lang AS cl ON cl.id_category = c.id_category AND cl.id_lang = '$lang' $extra_join_category_lang
				WHERE c.active = 1
				$exclude_root_parent_categories_sql
				$extra_criteria
				ORDER BY $order
			";
			$sql = apply_filters('fgp2wc_get_product_categories_sql', $sql, $prefix);
			$categories = $this->prestashop_query($sql);
			
			$categories = apply_filters('fgp2wc_get_product_categories', $categories);
			
			return $categories;
		}
		
		/**
		 * Get the root category if exists
		 * 
		 * @since 2.4.0
		 * 
		 * @return array Root category
		 */
		private function get_root_category() {
			$category = array();
			$prefix = $this->plugin_options['prefix'];
			if ( $this->column_exists('category', 'is_root_category') ) {
				$sql = "
					SELECT c.id_category, c.level_depth
					FROM {$prefix}category c
					WHERE c.is_root_category = 1
					LIMIT 1
				";
				$result = $this->prestashop_query($sql);
				$category = isset($result[0])? $result[0] : array();
			}
			return $category;
		}
		
		/**
		 * Get the products
		 * 
		 * @param int $limit Number of products max
		 * @return array of products
		 */
		private function get_products($limit=1000) {
			$products = array();

			$last_prestashop_product_id = (int)get_option('fgp2wc_last_product_id'); // to restore the import where it left
			
			$prefix = $this->plugin_options['prefix'];
			$lang = $this->default_language;
			
			// Hooks for adding extra cols and extra joins
			$extra_cols = apply_filters('fgp2wc_get_products_add_extra_cols', '');
			$extra_joins = apply_filters('fgp2wc_get_products_add_extra_joins', '');

			$location_field = $this->column_exists('product', 'location')? 'p.`location`': "'' AS `location`";
			$condition_field = $this->column_exists('product', 'condition')? 'p.`condition`': "'' AS `condition`";
			$show_condition_field = $this->column_exists('product', 'show_condition')? 'p.`show_condition`': "'1' AS `show_condition`";
			if ( version_compare($this->prestashop_version, '1.5', '<') ) {
				if ( version_compare($this->prestashop_version, '1.4', '<') ) {
					// PrestaShop 1.3 and less
					$width_field = '0 AS width';
					$height_field = '0 AS height';
					$depth_field = '0 AS depth';
					$extra_cols .= ', p.reduction_price, p.reduction_percent, p.reduction_from, p.reduction_to';
					$available_for_order_field = '1 AS available_for_order';
					$id_tax_rules_group = '0 AS id_tax_rules_group';
				} else {
					// PrestaShop 1.4+
					$width_field = 'p.width';
					$height_field = 'p.height';
					$depth_field = 'p.depth';
					$available_for_order_field = 'p.available_for_order';
					$id_tax_rules_group = 'p.id_tax_rules_group';
				}
				$sql = "
					SELECT p.id_product, p.id_supplier, p.id_manufacturer, $id_tax_rules_group, p.id_category_default, p.on_sale, p.quantity, p.price, p.wholesale_price, p.reference, p.ean13, p.supplier_reference, $location_field, $condition_field, $show_condition_field, $width_field, $height_field, $depth_field, p.weight, p.out_of_stock, p.active, $available_for_order_field, 'both' AS visibility, 0 AS is_virtual, p.date_add AS date, pl.id_lang, pl.name, pl.link_rewrite AS slug, pl.description, pl.description_short, pl.meta_description, pl.meta_keywords, pl.meta_title
					$extra_cols
					FROM {$prefix}product p
					INNER JOIN {$prefix}product_lang AS pl ON pl.id_product = p.id_product AND pl.id_lang = '$lang'
				";
			} else {
				// PrestaShop 1.5+
				$extra_join_product_shop = '';
				if ( $this->shop_id != 0 ) {
					$extra_join_product_shop .= "AND ps.id_shop = '{$this->shop_id}'";
				}
				$sql = "
					SELECT DISTINCT p.id_product, p.id_supplier, p.id_manufacturer, ps.id_tax_rules_group, ps.id_category_default, ps.on_sale, 0 AS quantity, ps.price, ps.wholesale_price, p.reference, p.ean13, p.supplier_reference, $location_field, $condition_field, $show_condition_field, p.width, p.height, p.depth, p.weight, 0 AS out_of_stock, ps.active, ps.available_for_order, ps.visibility, p.is_virtual, ps.date_add AS date, pl.id_lang, pl.name, pl.link_rewrite AS slug, pl.description, pl.description_short, pl.meta_description, pl.meta_keywords, pl.meta_title
					$extra_cols
					FROM {$prefix}product p
					INNER JOIN {$prefix}product_shop AS ps ON ps.id_product = p.id_product $extra_join_product_shop
					INNER JOIN {$prefix}product_lang AS pl ON pl.id_product = p.id_product AND pl.id_lang = '$lang' AND pl.id_shop = ps.id_shop
				";
			}
			$sql .= "
					$extra_joins
					WHERE p.id_product > '$last_prestashop_product_id'
					ORDER BY p.id_product
					LIMIT $limit
			";
			$sql = apply_filters('fgp2wc_get_products_sql', $sql, $prefix);
			$products = $this->prestashop_query($sql);
			
			return $products;
		}
		
		/**
		 * Get the product images
		 *
		 * @param int $product_id Product ID
		 * @return array of images
		 */
		protected function get_product_images($product_id) {
			$images = array();

			$prefix = $this->plugin_options['prefix'];
			$lang = $this->current_language;
			$extra_joins = '';
			$extra_criteria = '';
			if ( version_compare($this->prestashop_version, '1.5', '<') ) {
				$cover = 'i.cover';
			} else {
				// PrestaShop 1.5+
				$extra_joins = "INNER JOIN {$prefix}image_shop ims ON ims.id_image = i.id_image";
				$extra_criteria = '';
				if ( $this->shop_id != 0 ) {
					$extra_criteria .= "AND ims.id_shop = '{$this->shop_id}'";
				}
				$cover = 'ims.cover';
			}
			$sql = "
				SELECT DISTINCT i.id_image, i.position, $cover, il.legend
				FROM {$prefix}image i
				LEFT JOIN {$prefix}image_lang il ON il.id_image = i.id_image AND il.id_lang = '$lang'
				$extra_joins
				WHERE i.id_product = '$product_id'
				$extra_criteria
				ORDER BY $cover DESC, i.position
			";
			$images = $this->prestashop_query($sql);
			
			return $images;
		}
		
		/**
		 * Get the categories from a product
		 *
		 * @param int $product_id PrestaShop product ID
		 * @return array of categories IDs
		 */
		protected function get_product_categories($product_id) {
			$categories = array();

			$prefix = $this->plugin_options['prefix'];
			$sql = "
				SELECT cp.id_category, cp.position
				FROM {$prefix}category_product cp
				WHERE cp.id_product = $product_id
			";
			$categories = $this->prestashop_query($sql);
			return $categories;
		}
		
		/**
		 * Get the tags from a product
		 *
		 * @param int $product_id PrestaShop product ID
		 * @return array of tags
		 */
		protected function get_product_tags($product_id) {
			$tags = array();

			$prefix = $this->plugin_options['prefix'];
			$lang = $this->current_language;
			$sql = "
				SELECT t.name
				FROM {$prefix}tag t
				INNER JOIN {$prefix}product_tag pt ON pt.id_tag = t.id_tag
				WHERE pt.id_product = $product_id
				AND t.id_lang = '$lang'
			";
			$result = $this->prestashop_query($sql);
			foreach ( $result as $row ) {
				$tags[] = $row['name'];
			}
			
			return $tags;
		}
		
		/**
		 * Get the product supplier reference (PrestaShop 1.5+)
		 *
		 * @param int $product_id PrestaShop product ID
		 * @return string Supplier reference
		 */
		protected function get_product_supplier_reference($product_id) {
			$supplier_reference = '';
			
			if ( version_compare($this->prestashop_version, '1.5', '>=') ) {
				// PrestaShop 1.5+
				$prefix = $this->plugin_options['prefix'];
				$sql = "
					SELECT ps.product_supplier_reference
					FROM {$prefix}product_supplier ps
					WHERE ps.id_product = '$product_id'
					LIMIT 1
				";
				$supplier_references = $this->prestashop_query($sql);
				if ( isset($supplier_references[0]['product_supplier_reference']) ) {
					$supplier_reference = $supplier_references[0]['product_supplier_reference'];
				}
			}
			return $supplier_reference;
		}
		
		/**
		 * Get the WooCommerce default tax rate
		 *
		 * @return float Tax rate
		 */
		protected function get_default_tax_rate() {
			global $wpdb;
			$tax = 1;
			
			try {
				$sql = "
					SELECT tr.tax_rate
					FROM {$wpdb->prefix}woocommerce_tax_rates tr
					WHERE tr.tax_rate_priority = 1
					AND tr.tax_rate_class = ''
					LIMIT 1
				";
				$tax_rate = $wpdb->get_var($sql);
				if ( !empty($tax_rate) ) {
					$tax = 1 + ($tax_rate / 100);
				}
			} catch ( PDOException $e ) {
				$this->display_admin_error(__('Error:', 'fg-prestashop-to-woocommerce') . $e->getMessage());
			}
			return $tax;
		}
		
		/**
		 * Determine potential filenames for the image
		 *
		 * @param string $type Image type (category, product)
		 * @param int $id_image Image ID
		 * @param int $id_product Product ID
		 * @return string Image file name
		 */
		public function build_image_filenames($type, $id_image, $id_product='') {
			$filenames = array();
			switch ( $type ) {
				case 'category':
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/c/' . $id_image . '.jpg';
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/c/' . $id_image . '-category.jpg';
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/tmp/category_' . $id_image . '-thumb.jpg';
					break;
				
				case 'attribute_texture':
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/co/' . $id_image . '.jpg';
					break;
				
				case 'product':
					$subdirs = str_split(strval($id_image));
					$subdir = implode('/', $subdirs);
					if ( $this->plugin_options['image_size'] == 'thumbnail' ) {
						$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/p/' . $subdir . '/' . $id_image . '-thickbox_default.jpg';
					}
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/p/' . $subdir . '/' . $id_image . '.jpg';
					$filenames[] = untrailingslashit($this->plugin_options['url']) . '/img/p/' . $id_product . '-' . $id_image . '.jpg';
					break;
			}
			return $filenames;
		}
		
		/**
		 * Import post medias from content
		 *
		 * @param string $content post content
		 * @param date $post_date Post date (for storing media)
		 * @param array $options Options
		 * @return array Medias imported
		 */
		public function import_media_from_content($content, $post_date, $options=array()) {
			$media = array();
			$matches = array();
			$alt_matches = array();
			$title_matches = array();
			
			if ( preg_match_all('#<(img|a)(.*?)(src|href)="(.*?)"(.*?)>#', $content, $matches, PREG_SET_ORDER) > 0 ) {
				if ( is_array($matches) ) {
					foreach ($matches as $match ) {
						$filename = $match[4];
						$other_attributes = $match[2] . $match[5];
						// Image Alt
						$image_alt = '';
						if (preg_match('#alt="(.*?)"#', $other_attributes, $alt_matches) ) {
							$image_alt = wp_strip_all_tags(stripslashes($alt_matches[1]), true);
						}
						// Image caption
						$image_caption = '';
						if (preg_match('#title="(.*?)"#', $other_attributes, $title_matches) ) {
							$image_caption = $title_matches[1];
						}
						$attachment_id = $this->import_media($image_alt, $filename, $post_date, $options, 0, $image_caption);
						if ( $attachment_id ) {
							$attachment = get_post($attachment_id);
							if ( !is_null($attachment) ) {
								$media[$filename] = array(
									'id'	=> $attachment_id,
									'name'	=> $attachment->post_name,
								);
							}
						}
					}
				}
			}
			return $media;
		}
		
		/**
		 * Import a media
		 *
		 * @param string $name Image name
		 * @param string $filename Image URL
		 * @param date $date Date (optional)
		 * @param array $options Options (optional)
		 * @param int $image_id Original image ID (optional)
		 * @param string $image_caption Image caption
		 * @return int attachment ID or false
		 */
		public function import_media($name, $filename, $date='', $options=array(), $image_id=0, $image_caption='') {
			
			if ( empty($date) || ($date == '0000-00-00 00:00:00') ) {
				$date = date('Y-m-d H:i:s');
			}
			$import_external = ($this->plugin_options['import_external'] == 1) || (isset($options['force_external']) && $options['force_external'] );

			$filename = urldecode($filename); // for filenames with spaces or accents

			$filetype = wp_check_filetype($filename);
			if ( empty($filetype['type']) || ($filetype['type'] == 'text/html') ) { // Unrecognized file type
				return false;
			}

			// Upload the file from the PrestaShop web site to WordPress upload dir
			if ( preg_match('/^http/', $filename) ) {
				if ( $import_external || // External file
					preg_match('#^' . $this->plugin_options['url'] . '#', $filename) // Local file
				) {
					$old_filename = $filename;
				} else {
					return false;
				}
			} else {
				$filename = preg_replace('#^/?img/#', '', $filename); // Remove img/ at the beginning
				$old_filename = trailingslashit($this->plugin_options['url']) . 'img/' . $filename;
			}

			// Don't re-import the already imported media
			if ( array_key_exists($old_filename, $this->imported_media) ) {
				return $this->imported_media[$old_filename];
			}

			// Get the upload path
			$upload_path = $this->upload_dir($filename, $date);

			// Make sure we have an uploads directory.
			if ( !wp_mkdir_p($upload_path) ) {
				$this->display_admin_error(sprintf(__("Unable to create directory %s", 'fg-prestashop-to-woocommerce'), $upload_path));
				return false;
			}

			$new_filename = $filename;
			if ( $this->plugin_options['import_duplicates'] == 1 ) {
				// Images with duplicate names
				$new_filename = preg_replace('#^https?://#', '', $new_filename);
				$crc = hash("crc32b", $new_filename);
				$short_crc = substr($crc, 0, 3); // Keep only the 3 first characters of the CRC (should be enough)
				$new_filename = preg_replace('/(.*)\.(.+?)$/', "$1-" . $short_crc . ".$2", $new_filename); // Insert the CRC before the file extension
			}

			$basename = basename($new_filename);
			$extension = substr(strrchr($basename, '.'), 1);
			$basename_without_extension = preg_replace('/(\.[^.]+)$/', '', $basename);
			$post_title = $name;
			$new_full_filename = $upload_path . '/' . $this->format_filename($basename_without_extension . '-' . $name) . '.' . $extension;

			// GUID
			$upload_dir = wp_upload_dir();
			$guid = substr(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_full_filename), 0, 255);
			$attachment_id = $this->get_post_id_from_guid($guid);

			if ( empty($attachment_id) ) {
				if ( !$this->download_manager->copy($old_filename, $new_full_filename) ) {
					$error = error_get_last();
					$error_message = $error['message'];
					$this->display_admin_error("Can't copy $old_filename to $new_full_filename : $error_message");
					return false;
				}

				// Image Alt
				$image_alt = '';
				if ( !empty($name) ) {
					$image_alt = wp_strip_all_tags(stripslashes($name), true);
				}

				$attachment_id = $this->insert_attachment($post_title, $basename, $new_full_filename, $guid, $date, $filetype['type'], $image_alt, $image_id, $image_caption);
				if ( $attachment_id ) {
					update_post_meta($attachment_id, '_fgp2wc_old_file', $old_filename);
					$this->imported_media[$old_filename] = $attachment_id;
					$this->media_count++;
				}
			}
			
			return $attachment_id;
		}
		
		/**
		 * Format a filename
		 * 
		 * @since 3.7.3
		 * 
		 * @param string $filename Filename
		 * @return string Formated filename
		 */
		public function format_filename($filename) {
			$filename = FG_PrestaShop_to_WooCommerce_Tools::convert_to_latin($filename);
			$filename = preg_replace('/%.{2}/', '', $filename); // Remove the encoded characters
			$filename = sanitize_file_name($filename);
			return $filename;
		}
		
		/**
		 * Returns the imported post ID corresponding to a meta key and value
		 *
		 * @since 3.3.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return int WordPress post ID
		 */
		public function get_wp_post_id_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $meta_value);
			$post_id = $wpdb->get_var($sql);
			return $post_id;
		}

		/**
		 * Returns the imported post IDs corresponding to a meta key and value
		 *
		 * @since 4.49.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return array WordPress post IDs
		 */
		public function get_wp_post_ids_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value);
			$post_ids = $wpdb->get_col($sql);
			return $post_ids;
		}

		/**
		 * Get a Post ID from its GUID
		 * 
		 * @since 4.16.0
		 * 
		 * @global object $wpdb
		 * @param string $guid GUID
		 * @return int Post ID
		 */
		public function get_post_id_from_guid($guid) {
			global $wpdb;
			return $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid=%s", $guid));
		}
		
		/**
		 * Returns the imported term ID corresponding to a meta key and value
		 *
		 * @since 4.49.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return int WordPress category ID
		 */
		public function get_wp_term_id_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $meta_key, $meta_value);
			$term_id = $wpdb->get_var($sql);
			return $term_id;
		}

		/**
		 * Returns the imported term IDs corresponding to a meta key and value
		 *
		 * @since 4.49.0
		 * 
		 * @param string $meta_key Meta key
		 * @param string $meta_value Meta value
		 * @return array WordPress category IDs
		 */
		public function get_wp_term_ids_from_meta($meta_key, $meta_value) {
			global $wpdb;

			$sql = $wpdb->prepare("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value);
			$term_ids = $wpdb->get_col($sql);
			return $term_ids;
		}

		/**
		 * Determine the media upload directory
		 * 
		 * @since 2.3.0
		 * 
		 * @param string $filename Filename
		 * @param date $date Date
		 * @return string Upload directory
		 */
		public function upload_dir($filename, $date) {
			$upload_dir = wp_upload_dir(date('Y/m', strtotime($date)));
			$use_yearmonth_folders = get_option('uploads_use_yearmonth_folders');
			if ( $use_yearmonth_folders ) {
				$upload_path = $upload_dir['path'];
			} else {
				$short_filename = $filename;
				$short_filename = preg_replace('#^' . preg_quote($this->plugin_options['url']) . '#', '', $short_filename);
				$short_filename = preg_replace('#.*img/#', '/', $short_filename);
				if ( strpos($short_filename, '/') != 0 ) {
					$short_filename = '/' . $short_filename; // Add a slash before the filename
				}
				$upload_path = $upload_dir['basedir'] . untrailingslashit(dirname($short_filename));
			}
			return $upload_path;
		}
		
		/**
		 * Save the attachment and generates its metadata
		 * 
		 * @since 2.3.0
		 * 
		 * @param string $attachment_title Attachment name
		 * @param string $basename Original attachment filename
		 * @param string $new_full_filename New attachment filename with path
		 * @param string $guid GUID
		 * @param date $date Date
		 * @param string $filetype File type
		 * @param string $image_alt Image description
		 * @param int $image_id Image ID
		 * @param string $image_caption Image caption
		 * @return int|false Attachment ID or false
		 */
		public function insert_attachment($attachment_title, $basename, $new_full_filename, $guid, $date, $filetype, $image_alt='', $image_id=0, $image_caption='') {
			$post_name = sanitize_title($attachment_title);
			
			// If the attachment does not exist yet, insert it in the database
			$attachment_id = 0;
			$attachment = $this->get_attachment_from_name($post_name);
			if ( $attachment ) {
				$attached_file = basename(get_attached_file($attachment->ID));
				if ( $attached_file == $basename ) { // Check if the filename is the same (in case where the legend is not unique)
					$attachment_id = $attachment->ID;
				}
			}
			if ( $attachment_id == 0 ) {
				$attachment_data = array(
					'guid'				=> $guid, 
					'post_date'			=> $date,
					'post_mime_type'	=> $filetype,
					'post_name'			=> $post_name,
					'post_title'		=> $attachment_title,
					'post_status'		=> 'inherit',
					'post_content'		=> '',
					'post_excerpt'		=> $image_caption,
				);
				$attachment_id = wp_insert_attachment($attachment_data, $new_full_filename);
			}
			
			if ( !empty($attachment_id) ) {
				if ( preg_match('/(image|audio|video)/', $filetype) ) { // Image, audio or video
					if ( !$this->plugin_options['skip_thumbnails'] ) {
						// you must first include the image.php file
						// for the function wp_generate_attachment_metadata() to work
						require_once(ABSPATH . 'wp-admin/includes/image.php');
						$attach_data = wp_generate_attachment_metadata( $attachment_id, $new_full_filename );
						wp_update_attachment_metadata($attachment_id, $attach_data);
					}

					// Image Alt
					if ( !empty($image_alt) ) {
						update_post_meta($attachment_id, '_wp_attachment_image_alt', addslashes($image_alt)); // update_post_meta expects slashed
					}
				}
				return $attachment_id;
			} else {
				return false;
			}
		}
		
		/**
		 * Check if the attachment exists in the database
		 *
		 * @param string $name
		 * @return object Post
		 */
		private function get_attachment_from_name($name) {
			$name = preg_replace('/\.[^.]+$/', '', basename($name));
			$r = array(
				'name'			=> $name,
				'post_type'		=> 'attachment',
				'numberposts'	=> 1,
			);
			$posts_array = get_posts($r);
			if ( is_array($posts_array) && (count($posts_array) > 0) ) {
				return $posts_array[0];
			}
			else {
				return false;
			}
		}
		
		/**
		 * Process the post content
		 *
		 * @param string $content Post content
		 * @param array $post_media Post medias
		 * @return string Processed post content
		 */
		public function process_content($content, $post_media) {
			
			if ( !empty($content) ) {
				$content = str_replace(array("\r", "\n"), array('', ' '), $content);
				
				// Replace page breaks
				$content = preg_replace("#<hr([^>]*?)class=\"system-pagebreak\"(.*?)/>#", "<!--nextpage-->", $content);
				
				// Replace media URLs with the new URLs
				$content = $this->process_content_media_links($content, $post_media);
			}

			return $content;
		}

		/**
		 * Replace media URLs with the new URLs
		 *
		 * @param string $content Post content
		 * @param array $post_media Post medias
		 * @return string Processed post content
		 */
		private function process_content_media_links($content, $post_media) {
			$matches = array();
			$matches_caption = array();
			
			if ( is_array($post_media) ) {
				
				// Get the attachments attributes
				$attachments_found = false;
				foreach ( $post_media as $old_filename => &$media_var ) {
					$post_media_name = $media_var['name'];
					$attachment = $this->get_attachment_from_name($post_media_name);
					if ( $attachment ) {
						$media_var['attachment_id'] = $attachment->ID;
						$media_var['url_old_filename'] = urlencode($old_filename); // for filenames with spaces
						if ( preg_match('/image/', $attachment->post_mime_type) ) {
							// Image
							$image_src = wp_get_attachment_image_src($attachment->ID, 'full');
							$media_var['new_url'] = $image_src[0];
							$media_var['width'] = $image_src[1];
							$media_var['height'] = $image_src[2];
						} else {
							// Other media
							$media_var['new_url'] = wp_get_attachment_url($attachment->ID);
						}
						$attachments_found = true;
					}
				}
				if ( $attachments_found ) {
				
					// Remove the links from the content
					$this->post_link_count = 0;
					$this->post_link = array();
					$content = preg_replace_callback('#<(a) (.*?)(href)=(.*?)</a>#i', array($this, 'remove_links'), $content);
					$content = preg_replace_callback('#<(img) (.*?)(src)=(.*?)>#i', array($this, 'remove_links'), $content);
					
					// Process the stored medias links
					$first_image_removed = false;
					foreach ($this->post_link as &$link) {
						
						// Remove the first image from the content
						if ( ($this->plugin_options['first_image'] == 'as_featured') && !$first_image_removed && preg_match('#^<img#', $link['old_link']) ) {
							$link['new_link'] = '';
							$first_image_removed = true;
							continue;
						}
						$new_link = $link['old_link'];
						$alignment = '';
						if ( preg_match('/(align="|float: )(left|right)/', $new_link, $matches) ) {
							$alignment = 'align' . $matches[2];
						}
						if ( preg_match_all('#(src|href)="(.*?)"#i', $new_link, $matches, PREG_SET_ORDER) ) {
							$caption = '';
							foreach ( $matches as $match ) {
								$old_filename = $match[2];
								$link_type = ($match[1] == 'src')? 'img': 'a';
								if ( array_key_exists($old_filename, $post_media) ) {
									$media = $post_media[$old_filename];
									if ( array_key_exists('new_url', $media) ) {
										if ( (strpos($new_link, $old_filename) > 0) || (strpos($new_link, $media['url_old_filename']) > 0) ) {
											$new_link = preg_replace('#('.$old_filename.'|'.$media['url_old_filename'].')#', $media['new_url'], $new_link, 1);
											
											if ( $link_type == 'img' ) { // images only
												// Define the width and the height of the image if it isn't defined yet
												if ((strpos($new_link, 'width=') === false) && (strpos($new_link, 'height=') === false)) {
													$width_assertion = isset($media['width']) && !empty($media['width'])? ' width="' . $media['width'] . '"' : '';
													$height_assertion = isset($media['height']) && !empty($media['height'])? ' height="' . $media['height'] . '"' : '';
												} else {
													$width_assertion = '';
													$height_assertion = '';
												}
												
												// Caption shortcode
												if ( preg_match('/class=".*caption.*?"/', $link['old_link']) ) {
													if ( preg_match('/title="(.*?)"/', $link['old_link'], $matches_caption) ) {
														$caption_value = str_replace('%', '%%', $matches_caption[1]);
														$align_value = ($alignment != '')? $alignment : 'alignnone';
														$caption = '[caption id="attachment_' . $media['attachment_id'] . '" align="' . $align_value . '"' . $width_assertion . ']%s' . $caption_value . '[/caption]';
													}
												}
												
												$align_class = ($alignment != '')? $alignment . ' ' : '';
												$new_link = preg_replace('#<img(.*?)( class="(.*?)")?(.*) />#', "<img$1 class=\"$3 " . $align_class . 'size-full wp-image-' . $media['attachment_id'] . "\"$4" . $width_assertion . $height_assertion . ' />', $new_link);
											}
										}
									}
								}
							}
							
							// Add the caption
							if ( $caption != '' ) {
								$new_link = sprintf($caption, $new_link);
							}
						}
						$link['new_link'] = $new_link;
					}
					
					// Reinsert the converted medias links
					$content = preg_replace_callback('#__fg_link_(\d+)__#', array($this, 'restore_links'), $content);
				}
			}
			return $content;
		}
		
		/**
		 * Remove all the links from the content and replace them with a specific tag
		 * 
		 * @param array $matches Result of the preg_match
		 * @return string Replacement
		 */
		private function remove_links($matches) {
			$this->post_link[] = array('old_link' => $matches[0]);
			return '__fg_link_' . $this->post_link_count++ . '__';
		}
		
		/**
		 * Restore the links in the content and replace them with the new calculated link
		 * 
		 * @param array $matches Result of the preg_match
		 * @return string Replacement
		 */
		private function restore_links($matches) {
			$link = $this->post_link[$matches[1]];
			$new_link = array_key_exists('new_link', $link)? $link['new_link'] : $link['old_link'];
			return $new_link;
		}
		
		/**
		 * Add a link between a media and a post (parent id + thumbnail)
		 *
		 * @param int $post_id Post ID
		 * @param array $post_media Post medias
		 * @param array $date Date
		 * @param boolean $set_featured_image Set the featured image?
		 */
		public function add_post_media($post_id, $post_media, $date, $set_featured_image=true) {
			$thumbnail_is_set = false;
			if ( is_array($post_media) ) {
				foreach ( $post_media as $media ) {
					$attachment = get_post($media);
					if ( !empty($attachment) && ($attachment->post_type == 'attachment') ) {
						$attachment->post_parent = $post_id; // Attach the post to the media
						$attachment->post_date = $date ;// Define the media's date
						wp_update_post($attachment);

						// Set the featured image. If not defined, it is the first image of the content.
						if ( $set_featured_image && !$thumbnail_is_set ) {
							set_post_thumbnail($post_id, $attachment->ID);
							$thumbnail_is_set = true;
						}
					}
				}
			}
		}

		/**
		 * Get the IDs of the medias
		 *
		 * @param array $post_media Post medias
		 * @return array Array of attachment IDs
		 */
		public function get_attachment_ids($post_media) {
			$attachments_ids = array();
			if ( is_array($post_media) ) {
				foreach ( $post_media as $media ) {
					$attachment = $this->get_attachment_from_name($media['name']);
					if ( !empty($attachment) ) {
						$attachments_ids[] = $attachment->ID;
					}
				}
			}
			return $attachments_ids;
		}
		
		/**
		 * Copy a remote file
		 * in replacement of the copy function
		 * 
		 * @deprecated
		 * @param string $url URL of the source file
		 * @param string $path destination file
		 * @return boolean
		 */
		public function remote_copy($url, $path) {
			return $this->download_manager->copy($url, $path);
		}
		
		/**
		 * Allow the backorders or not
		 * 
		 * @param int $out_of_stock_value Out of stock value 0|1|2
		 * @return string yes|no
		 */
		protected function allow_backorders($out_of_stock_value) {
			switch ( $out_of_stock_value ) {
				case 0: $backorders = 'no'; break;
				case 1: $backorders = 'yes'; break;
				default: $backorders = $this->default_backorders;
			}
			return $backorders;
		}
		
		/**
		 * Recount the items for a taxonomy
		 * 
		 * @return boolean
		 */
		private function terms_tax_count($taxonomy) {
			$terms = get_terms(array(
				'taxonomy' => $taxonomy,
			));
			// Get the term taxonomies
			$terms_taxonomies = array();
			foreach ( $terms as $term ) {
				$terms_taxonomies[] = $term->term_taxonomy_id;
			}
			if ( !empty($terms_taxonomies) ) {
				return wp_update_term_count_now($terms_taxonomies, $taxonomy);
			} else {
				return true;
			}
		}
		
		/**
		 * Recount the items for each category and tag
		 * 
		 * @return boolean
		 */
		private function terms_count() {
			$result = $this->terms_tax_count('category');
			$result |= $this->terms_tax_count('post_tag');
		}
		
		/**
		 * Display the number of imported media
		 * 
		 */
		public function display_media_count() {
			$this->display_admin_notice(sprintf(_n('%d media imported', '%d medias imported', $this->media_count, 'fg-prestashop-to-woocommerce'), $this->media_count));
		}

		/**
		 * Test if a column exists
		 *
		 * @param string $table Table name
		 * @param string $column Column name
		 * @return bool
		 */
		public function column_exists($table, $column) {
			global $prestashop_db;
			
			$cache_key = 'fgp2wc_column_exists:' . $table . '.' . $column;
			$found = false;
			$column_exists = wp_cache_get($cache_key, '', false, $found);
			if ( $found === false ) {
				$column_exists = false;
				try {
					$prefix = $this->plugin_options['prefix'];

					$sql = "SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '$column'";
					$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
					if ( $query !== false ) {
						$result = $query->fetch();
						$column_exists = !empty($result);
					}
				} catch ( PDOException $e ) {}
				
				// Store the result in cache for the current request
				wp_cache_set($cache_key, $column_exists);
			}
			return $column_exists;
		}
		
		/**
		 * Test if a table exists
		 *
		 * @param string $table Table name
		 * @return bool
		 */
		public function table_exists($table) {
			global $prestashop_db;
			
			$cache_key = 'fgp2wc_table_exists:' . $table;
			$found = false;
			$table_exists = wp_cache_get($cache_key, '', false, $found);
			if ( $found === false ) {
				$table_exists = false;
				try {
					$prefix = $this->plugin_options['prefix'];

					$sql = "SHOW TABLES LIKE '{$prefix}{$table}'";
					$query = $prestashop_db->query($sql, PDO::FETCH_ASSOC);
					if ( $query !== false ) {
						$result = $query->fetch();
						$table_exists = !empty($result);
					}
				} catch ( PDOException $e ) {}
				
				// Store the result in cache for the current request
				wp_cache_set($cache_key, $table_exists);
			}
			return $table_exists;
		}
		
		/**
		 * Get all the term metas corresponding to a meta key
		 * 
		 * @param string $meta_key Meta key
		 * @return array List of term metas: term_id => meta_value
		 */
		public function get_term_metas_by_metakey($meta_key) {
			global $wpdb;
			$metas = array();
			
			$sql = $wpdb->prepare("SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s", $meta_key);
			$results = $wpdb->get_results($sql);
			foreach ( $results as $result ) {
				$metas[$result->meta_value] = $result->term_id;
			}
			ksort($metas);
			return $metas;
		}
		
		/**
		 * Returns the imported product ID corresponding to a PrestaShop ID
		 *
		 * @since 3.22.0
		 * 
		 * @param int $ps_product_id PrestaShop product ID
		 * @return int WordPress product ID
		 */
		public function get_wp_product_id_from_prestashop_id($ps_product_id) {
			$product_id = $this->get_wp_post_id_from_meta('_fgp2wc_old_product_id', $ps_product_id);
			return $product_id;
		}
		
		/**
		 * Test if a remote file exists
		 * 
		 * @since 4.16.0
		 * 
		 * @param string $filePath
		 * @return boolean True if the file exists
		 */
		public function url_exists($filePath) {
			$url = str_replace(' ', '%20', $filePath);
			$user_agent = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36';
			
			// Try the get_headers method
			stream_context_set_default(array(
				'http' => array(
					'header' => $user_agent,
				)
			));
			$headers = @get_headers($url);
			if ( !isset($headers[0]) ) {
				$result = false;
			} else {
				$result = preg_match("/200/", $headers[0]);

				if ( !$result && strpos($filePath, 'https:') !== 0 ) {
					// Try the fsock method
					$url = str_replace('http://', '', $url);
					if ( strstr($url, '/') ) {
						$url = explode('/', $url, 2);
						$url[1] = '/' . $url[1];
					} else {
						$url = array($url, '/');
					}

					$fh = fsockopen($url[0], 80);
					if ( $fh ) {
						fputs($fh, 'GET ' . $url[1] . " HTTP/1.1\nHost:" . $url[0] . "\n");
						fputs($fh, $user_agent . "\n\n");
						$response = fread($fh, 22);
						fclose($fh);
						$result = (strpos($response, '200') !== false);
					} else {
						$result = false;
					}
				}
			}
			
			return $result;
		}
		
	}
}
