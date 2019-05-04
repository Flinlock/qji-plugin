<?php

/*
Plugin Name: Quintessence Jewelry Interface
Plugin URI: https://infinitishine.com
Description:  Custom WordPress Plugin  powering Woocommerce and Quintessence Jewelry integration on infinitishine.com.
Version: 1.0.0
Author: Tyson Roehrkasse
Author URI: https://twirltech.io
*/
if ( ! defined( 'ABSPATH' ) ) exit;

if(!defined('QJI_PLUGIN_DIR')) define('QJI_PLUGIN_DIR',  trailingslashit( plugin_dir_path( __FILE__ ) ) );
if(!defined('QJI_PLUGIN_URI')) define('QJI_PLUGIN_URI',  trailingslashit( plugin_dir_url( __FILE__ ) ) );
if(!defined('QJI_PLUGIN_MODULES_URL'))	define('QJI_PLUGIN_MODULES_URL',trailingslashit( QJI_PLUGIN_URI.'framework/modules') );
if(!defined('QJI_PLUGIN_MODULES_BASE'))	define('QJI_PLUGIN_MODULES_BASE', trailingslashit( QJI_PLUGIN_DIR.'framework/modules') );
if(!defined('QJI_PLUGIN_VER')) define('QJI_PLUGIN_VER',  '0.0.1' );
if(!defined('QJI_PLUGIN_SLUG')) define('QJI_PLUGIN_SLUG',"qji_plugin");

include_once(QJI_PLUGIN_DIR."settings.php");

/**
 * QJI_Plugin
 * Wordpress plugin to integrate Woocommerce with Quintessence Jewelry backend
 *
 */
class QJI_Plugin{
	/**
	 * @since 1.0.0
	 */
	const TRANSIENT_PREFIX = "qji_plugin_transient_";

	/**
	 * @since 1.0.0
	 */
	const DATA_CACHE_TIME_SHORT = 30 * MINUTE_IN_SECONDS;

	/**
	 * @since 1.0.0
	 */
	const DATA_CACHE_TIME_MEDIUM = 8 * HOUR_IN_SECONDS;

	/**
	 * @since 1.0.0
	 */
	const DATA_CACHE_TIME_LONG = DAY_IN_SECONDS;

	/**
	 * @since 1.0.0
	 */
	const SLUG = "QJI-plugin";

	/**
	 * Instance of class
	 *
	 * Limit instance of class to one
	 * @since 1.0.0
	 */
	protected static $instance;

	private static $OPTIONS;

	/**
	 * Data about site's current visitor
	 *
	 * @since 1.0.0
	 */
	public $visitor = array();

	/**
	 * get_instance
	 * Insert description here
	 *
	 *
	 * @return
	 *
	 * @access
	 * @static
	 * @see
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * return transient key prefixed with plugin's prefix
	 * @param $append
	 *
	 * @return String
	 *
	 * @since 1.0.0
	 */
	public static function get_transient_key($append = ""){
		return self::TRANSIENT_PREFIX.$append;
	}

	/**
	 * __construct
	 * Class Constructor
	 *
	 * @access
	 * @static
	 * @see
	 * @since 1.0.0
	 */
	protected function __construct(){


		self::get_options();

		add_action('init', function(){
			$this->init();
		}, 20);
	}

	/**
	 * get_options
	 * Bootstrap Awesome Framework options framework
	 *
	 *
	 * @return array
	 *
	 */
	public static function get_options(){
		if(self::$OPTIONS != null) return self::$OPTIONS;

		self::$OPTIONS = get_option('qji_plugin_settings',[]);

		if(isset(self::$OPTIONS['modules']))
			self::$OPTIONS['modules'] = explode(',', self::$OPTIONS['modules']);

		global $AWESOME_FRAMEWORK;

		if(isset(self::$OPTIONS['modules']))
			$AWESOME_FRAMEWORK['ENABLED_MODULES'] = self::$OPTIONS['modules'];

		return self::$OPTIONS;
	}



	/**
	 * Register all css and javascript dependencies
	 *
	 * @since 1.0.0
	 */
	public function load_global_styles_scripts(){
		if(defined('WP_DEBUG') && WP_DEBUG){
			$asset_ext = '';
		}else{
			$asset_ext = '.min';
		}

		$OPTIONS = self::get_options();


		if(is_admin()){
		    /* dependencies used in the admin panel */
		    wp_register_script('xlsx', QJI_PLUGIN_URI."node_modules/xlsx/dist/xlsx.full.min.js", array(), QJI_PLUGIN_VER, false);
            wp_register_script('jszip', QJI_PLUGIN_URI."node_modules/xlsx/dist/jszip.js", array(), QJI_PLUGIN_VER, false);
		    wp_register_script('qji-plugin-admin', QJI_PLUGIN_URI."assets/js/admin/admin.js", array(), QJI_PLUGIN_VER, false);
            wp_localize_script('qji-plugin-core','QJI_PLUGIN_ARGS', array(
                    'API_BASE'	=>	site_url('?rest_route=/qji'),
                    'API_USER'          =>  'mybziscool@gmail.com',
                    'API_PW'            =>  '5080fa692abd9e30be2c16872e3fdc60'
                )
            );

            wp_enqueue_script('xlsx', QJI_PLUGIN_URI."node_modules/xlsx/dist/xlsx.full.min.js");
            wp_enqueue_script('jszip', QJI_PLUGIN_URI."node_modules/xlsx/dist/jszip.js");
		    wp_enqueue_script('qji-plugin-admin', QJI_PLUGIN_URI."assets/js/admin/admin.js");

            $args = [
                'API_BASE'  =>  site_url('wp-json/qji')
            ];
            wp_localize_script('qji-plugin-admin','QJI_ADMIN_ARGS', $args);

            wp_enqueue_script('qji-plugin-admin');
        }
	}



	/**
	 * Bootstrap plugin's REST resources
	 *
	 * @since 1.0.0
	 */
	public function bootstrap_api(){
		foreach(glob(QJI_PLUGIN_DIR."/framework/api/*.php") as $file):
			require_once($file);
		endforeach;
	}

    /**
     * Send new order information to Quintessence Jewelry via API
     * @param $order_id
     */
	public function qji_order_send($order_id){
	    // TODO set in customizer?
	    $url = 'http://www.quintessencejewelry.com/index.php/qjcapis/makebulkOrders.xml';
	    if(!$order_id)
	        return;

        $order = wc_get_order( $order_id );

        $productBucket = [];
        foreach ( $order->get_items() as $item_key => $item ) {
            $product = $order->get_product_from_item( $item );
            if($product->get_attribute('pa_size') == ""){
                $productBucket[] = array(
                    "sku"       =>  $product->get_sku(),
                    "qty"       =>  (String) $item->get_quantity()
                );
            }else{
                $productBucket[] = array(
                    "sku"      =>  $product->get_sku(),
                    "size"      =>  "size " . $product->get_attribute('pa_size'),
                    "qty"       =>  (String) $item->get_quantity()
                );
            }
        }

        $email = $order->get_billing_email();

        $logistic = "USPS First Class";

        $customer_po = substr($order->get_billing_first_name(), 0, 1) . substr($order->get_billing_last_name(), 0 , 1) . $order_id;

        $s_first_name = ($order->get_shipping_first_name() == "") ? "Valued" : $order->get_shipping_first_name();
        $s_last_name = ($order->get_shipping_last_name() == "") ? "Customer" : $order->get_shipping_last_name();
        $s_company = ($order->get_shipping_company() == "") ? "Jill Sara Jewelry" : $order->get_shipping_company();
        $s_address_1 = $order->get_shipping_address_1();
        $s_address_2 = $order->get_shipping_address_2();
        $s_address_3 = "";
        $s_city = $order->get_shipping_city();
        $s_state = $order->get_shipping_state();
        $s_country_name = "United States";
        $s_zip_code = $order->get_shipping_postcode();
        $s_contact_no = $order->get_billing_phone();
        $s_email = $order->get_billing_email();

        $b_first_name = $order->get_billing_first_name();
        $b_last_name = $order->get_billing_last_name();
        $b_company = $s_company;
        $b_address_1 = $order->get_billing_address_1();
        $b_address_2 = $order->get_billing_address_2();
        $b_address_3 = "";
        $b_city = $order->get_billing_city();
        $b_state = $order->get_billing_state();
        $b_country_name = "United States";
        $b_zip_code = $order->get_billing_postcode();
        $b_contact_no = $s_contact_no;
        $b_email = $s_email;

        $orderArray = array(
            "productBucket"     =>  $productBucket,
            "email"             =>  $email,
            "customer_po"       =>  $customer_po,
            "logistic"          =>  $logistic,
            "s_first_name"      =>  $s_first_name,
            "s_last_name"       =>  $s_last_name,
            "s_company"         =>  $s_company,
            "s_address_1"       =>  $s_address_1,
            "s_address_2"       =>  $s_address_2,
            "s_address_3"       =>  $s_address_3,
            "s_city"            =>  $s_city,
            "s_state"           =>  $s_state,
            "s_country_name"    =>  $s_country_name,
            "s_zip_code"        =>  $s_zip_code,
            "s_contact_no"      =>  $s_contact_no,
            "s_email"           =>  $s_email,
            "b_first_name"      =>  $b_first_name,
            "b_last_name"       =>  $b_last_name,
            "b_company"         =>  $b_company,
            "b_address_1"       =>  $b_address_1,
            "b_address_2"       =>  $b_address_2,
            "b_address_3"       =>  $b_address_3,
            "b_city"            =>  $b_city,
            "b_state"           =>  $b_state,
            "b_country_name"    =>  $b_country_name,
            "b_zip_code"        =>  $b_zip_code,
            "b_contact_no"      =>  $b_contact_no,
            "b_email"           =>  $b_email,
            "uname"             =>  "mybziscool@gmail.com",
            "pass"              =>  "5080fa692abd9e30be2c16872e3fdc60"
        );



        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($orderArray));
        try{
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($curl);
            if($result === false){
                $order->add_order_note('Order not received by Quintessence. Please log in to Quintessence and create the order manually and notify RADD Creative Support', 0, false);
                $order->add_order_note(curl_error($curl), 0, false);
            }else{
                if(stripos($result, 'Required Field Missing') || stripos($result, 'not exist') || stripos($result, 'Invalid')){
                    $order->add_order_note('Order failed to send to Quintessence due to a missing parameter. Please create the order manually with Quintessence and notify RADD Support', 0, false);
                    $order->add_order_note($result, 0, false);
                }else{
                    $order->add_order_note('Order successfully sent to Quintessence.', 0, false);
                    $order->add_order_note($result, 0, false);
                }


            }
        }catch(Exception $e){
            $order->add_order_note('Unable to reach the Quintessence backend, please create the order manually with Quintessence and notify RADD Creative support', 0, false);
        }
    }

    public function fix_gems(){
        $args = array(
            'status'    =>  'publish',
            'limit'     =>  -1
        );
        $products = wc_get_products($args);
        foreach($products as $product){
            $id = $product->id;
            $parent_id = get_term_by('slug', 'gemstone', 'product_cat')->term_id;
            // Gemstone handler
            $a = $product->get_attribute('pa_gemstone-detail');
            $maybe_term = get_term_by("name", $a, "product_cat"); //returns either the term or false

            if($maybe_term){
                // Term exists already, set it on product
                wp_set_post_terms($id, array($maybe_term->term_id), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }else{
                // Term doesn't exist yet
                $new_term = wp_insert_term(
                    $a, // category name
                    'product_cat', // taxonomy
                    array(
                        'description' => $a . ' Category', // optional
                        'slug' => str_replace(' ', '-', strtolower($a)), // optional
                        'parent' => $parent_id, // set it as a sub-category
                    )
                );
                wp_set_post_terms($id, array($new_term['term_id']), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }
        }
    }

    public function fix_metals(){
        $args = array(
            'status'    =>  'publish',
            'limit'     =>  -1
        );
        $products = wc_get_products($args);
        foreach($products as $product){
            $id = $product->id;
            $parent_id = get_term_by('slug', 'metal', 'product_cat')->term_id;
            // Gemstone handler
            $a = $product->get_attribute('pa_metal');
            $maybe_term = get_term_by("name", $a, "product_cat"); //returns either the term or false

            if($maybe_term){
                // Term exists already, set it on product
                wp_set_post_terms($id, array($maybe_term->term_id), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }else{
                // Term doesn't exist yet
                $new_term = wp_insert_term(
                    $a, // category name
                    'product_cat', // taxonomy
                    array(
                        'description' => $a . ' Category', // optional
                        'slug' => str_replace(' ', '-', strtolower($a)), // optional
                        'parent' => $parent_id, // set it as a sub-category
                    )
                );
                wp_set_post_terms($id, array($new_term['term_id']), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }
        }
    }

    public function fix_settings(){
        $args = array(
            'status'    =>  'publish',
            'limit'     =>  -1
        );
        $products = wc_get_products($args);
        foreach($products as $product){
            $id = $product->id;
            $parent_id = get_term_by('slug', 'setting', 'product_cat')->term_id;
            // Gemstone handler
            $a = $product->get_attribute('pa_setting');
            $maybe_term = get_term_by("name", $a, "product_cat"); //returns either the term or false

            if($maybe_term){
                // Term exists already, set it on product
                wp_set_post_terms($id, array($maybe_term->term_id), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }else{
                // Term doesn't exist yet
                $new_term = wp_insert_term(
                    $a, // category name
                    'product_cat', // taxonomy
                    array(
                        'description' => $a . ' Category', // optional
                        'slug' => str_replace(' ', '-', strtolower($a)), // optional
                        'parent' => $parent_id, // set it as a sub-category
                    )
                );
                wp_set_post_terms($id, array($new_term['term_id']), 'product_cat', true);
                echo 'set existing category of ' . $a . ' on product ' . $id;
            }
        }
    }


	/**
	 * Initialize plugin and hook into wordpress and other plugins's filters and actions
	 *
	 * @since 1.0.0
	 */
	private function init(){

	    add_action('fix_gems', array(&$this, 'fix_gems'), 10, 1);
        add_action('fix_metals', array(&$this, 'fix_metals'), 10, 1);

        add_action('fix_settings', array(&$this, 'fix_settings'), 10, 1);

	    // Handle sending orders to QJ
        add_action('woocommerce_order_status_completed', array(&$this, 'qji_order_send'), 10, 1);


		add_action('wp_enqueue_scripts', array(&$this, 'load_global_styles_scripts'));
		add_action('admin_enqueue_scripts', array(&$this,'load_global_styles_scripts') );
		add_action( 'rest_api_init', array(&$this,'bootstrap_api'), 60 );



	}
}QJI_Plugin::get_instance();