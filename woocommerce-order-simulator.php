<?php
 /**
  * Plugin Name: Standard Order Simulator for WooCommerce
  * Description: Automate orders to generate WooCommerce storefronts at scale for testing purposes.
  * Version: 1.0.0
  * Author: Riddhesh Sanghvi
  * 
  * This program is free software: you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  * 
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  * 
  * You should have received a copy of the GNU General Public License
  * along with this program.  If not, see <http://www.gnu.org/licenses/>.
  */

class WC_Order_Simulator {

    private $users   = array();
    public $settings = array();

    public function __construct() {
      if ( defined( 'ABSPATH' ) ) {
        
        register_activation_hook( __FILE__, array($this, 'install') );

        add_filter( 'woocommerce_get_settings_pages', array($this, 'settings_page') );

        $this->settings = self::get_settings();
      }

    }

    public function install() {
        global $wpdb;

        $wpdb->hide_errors();
        $collate = '';

        if ( method_exists($wpdb, 'has_cap') ) {
            if ( $wpdb->has_cap('collation') ) {
                if( ! empty($wpdb->charset ) ) $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
                if( ! empty($wpdb->collate ) ) $collate .= " COLLATE $wpdb->collate";
            }
        } else {
            if ( $wpdb->supports_collation() ) {
                if( ! empty($wpdb->charset ) ) $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
                if( ! empty($wpdb->collate ) ) $collate .= " COLLATE $wpdb->collate";
            }
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE fakenames (
number int(11) NOT NULL AUTO_INCREMENT,
gender varchar(6) NOT NULL,
givenname varchar(20) NOT NULL,
surname varchar(23) NOT NULL,
streetaddress varchar(100) NOT NULL,
city varchar(100) NOT NULL,
state varchar(22) NOT NULL,
zipcode varchar(15) NOT NULL,
country varchar(2) NOT NULL,
countryfull varchar(100) NOT NULL,
emailaddress varchar(100) NOT NULL,
username varchar(25) NOT NULL,
password varchar(25) NOT NULL,
telephonenumber tinytext NOT NULL,
maidenname varchar(20) NOT NULL,
birthday varchar(10) NOT NULL,
company varchar(70) NOT NULL,
PRIMARY KEY  (number)
) $collate";
        dbDelta( $sql );

        $count = $wpdb->get_var("SELECT COUNT(*) FROM fakenames");

        if ( $count == 0 ) {
            $lines = explode( "\n", file_get_contents( dirname(__FILE__) .'/fakenames.sql' ) );

            foreach ( $lines as $sql )
                $wpdb->query($sql);
        }
      
      self::create_user();
      self::create_orders_on_init();
    }

    public function settings_page( $settings ) {
        $settings[] = include( 'class-wc-settings-order-simulator.php' );

        return $settings;
    }

    public function create_orders_on_init() {
        $this->create_orders();
    }

    public static function get_settings() {
        
        $defaults = array(
            'orders_per_hour'       => 200,
            'products'              => array('1496','1492','1501','1502','1509'),
            'min_order_products'    => 1,
            'max_order_products'    => 5,
            'create_users'          => false,
            'payment_method'        => 'auto',
            'shipping_method'       => 'auto',
            'order_completed_pct'   => 90,
            'order_processing_pct'  => 5,
            'order_failed_pct'      => 5
        );
        
        return $defaults;
    }

    public function create_orders() {
        global $wpdb, $woocommerce;

        if ( empty( $this->settings['orders_per_hour'] ) ) {
            return;
        }

        set_time_limit(0);

        $woocommerce->init();
        $woocommerce->frontend_includes();

        $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );

        // Class instances
        require_once WC()->plugin_path() .'/includes/abstracts/abstract-wc-session.php';
        $woocommerce->session  = new WC_Session_Handler();
        $woocommerce->cart     = new WC_Cart();                                    // Cart class, stores the cart contents
        $woocommerce->customer = new WC_Customer();                                // Customer class, handles data such as customer location

        $woocommerce->countries = new WC_Countries();
        $woocommerce->checkout = new WC_Checkout();
        //$woocommerce->product_factory = new WC_Product_Factory();                      // Product Factory to create new product instances
        $woocommerce->order_factory   = new WC_Order_Factory();                        // Order Factory to create new order instances
        $woocommerce->integrations    = new WC_Integrations();                         // Integrations class


        // clear cart
        if (! defined('WOOCOMMERCE_CHECKOUT')) define('WOOCOMMERCE_CHECKOUT', true);
        $woocommerce->cart->empty_cart();

        $product_ids = $this->settings['products'];

        for ( $x = 0; $x < $this->settings['orders_per_hour']; $x++ ) {
            $cart           = array();          
            $num_products   = ($x%5)+1;
            $create_user    = false;

            $user_id = self::get_random_user($x);
          
            for ( $i = 0; $i < $num_products; $i++ ) {
                $product_id = $product_ids[$i];
                $woocommerce->cart->add_to_cart( $product_id, 1 );
            }

            // process checkout
            $data = array(
                'billing_country'   => get_user_meta( $user_id, 'billing_country', true ),
                'billing_first_name'=> get_user_meta( $user_id, 'billing_first_name', true ),
                'billing_last_name' => get_user_meta( $user_id, 'billing_last_name', true ),
                'billing_company'   => '',
                'billing_address_1' => get_user_meta( $user_id, 'billing_address_1', true ),
                'billing_address_2' => '',
                'billing_city'      => get_user_meta( $user_id, 'billing_city', true ),
                'billing_state'     => get_user_meta( $user_id, 'billing_state', true ),
                'billing_postcode'  => get_user_meta( $user_id, 'billing_postcode', true ),
                'billing_email'     => get_user_meta( $user_id, 'billing_email', true ),
                'billing_phone'     => get_user_meta( $user_id, 'billing_phone', true ),

                'shipping_country'   => get_user_meta( $user_id, 'shipping_country', true ),
                'shipping_first_name'=> get_user_meta( $user_id, 'shipping_first_name', true ),
                'shipping_last_name' => get_user_meta( $user_id, 'shipping_last_name', true ),
                'shipping_company'   => '',
                'shipping_address_1' => get_user_meta( $user_id, 'shipping_address_1', true ),
                'shipping_address_2' => '',
                'shipping_city'      => get_user_meta( $user_id, 'shipping_city', true ),
                'shipping_state'     => get_user_meta( $user_id, 'shipping_state', true ),
                'shipping_postcode'  => get_user_meta( $user_id, 'shipping_postcode', true ),
                'shipping_email'     => get_user_meta( $user_id, 'shipping_email', true ),
                'shipping_phone'     => get_user_meta( $user_id, 'shipping_phone', true )
            );
            $checkout = new WC_Checkout();

            $woocommerce->cart->calculate_totals();

            $order_id = $checkout->create_order( $data );

            if ( $order_id ) {
                update_post_meta( $order_id, '_payment_method', 'bacs' );
                update_post_meta( $order_id, '_payment_method_title', 'Bacs' );

                update_post_meta( $order_id, '_shipping_method', 'free_shipping' );
                update_post_meta( $order_id, '_shipping_method_title', 'Free Shipping' );

                update_post_meta( $order_id, '_customer_user', absint( $user_id ) );

                foreach ( $data as $key => $value ) {
                    update_post_meta( $order_id, '_'.$key, $value );
                }

                do_action( 'woocommerce_checkout_order_processed', $order_id, $data );

                $order = new WC_Order($order_id);

                // figure out the order status
                $status = 'completed';
                $rand = $x%100;
                $completed_pct  = $this->settings['order_completed_pct']; // e.g. 90
                $processing_pct = $completed_pct + $this->settings['order_processing_pct']; // e.g. 90 + 5
                $failed_pct     = $processing_pct + $this->settings['order_failed_pct']; // e.g. 95 + 5

                if ( $this->settings['order_completed_pct'] > 0 && $rand <= $completed_pct ) {
                    $status = 'completed';
                } elseif ( $this->settings['order_processing_pct'] > 0 && $rand <= $processing_pct ) {
                    $status = 'processing';
                } elseif ( $this->settings['order_failed_pct'] > 0 && $rand <= $failed_pct ) {
                    $status = 'failed';
                }

                if ( $status == 'failed' ) {
                    $order->update_status( $status );
                } else {
                    $order->payment_complete();
                    $order->update_status( $status );
                }
            }

            // clear cart
            $woocommerce->cart->empty_cart();
        }
    }

    public function create_user() {
        global $wpdb;

        $user_id = 0;

        for($i=5;$i<105;$i++){
              $user_row = $wpdb->get_row("SELECT * FROM fakenames where number=$i");

              $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}users WHERE user_login = '{$user_row->username}'");

              $unique = ($count == 0) ? true : false;
            if(!$unique){
              continue;
            }else{
              echo "Creating user: $user_row->username.".PHP_EOL;
            }



          $user = array(
              'user_login'    => $user_row->username,
              'user_pass'     => 'admin',
              'user_email'    => $user_row->emailaddress,
              'first_name'    => $user_row->givenname,
              'last_name'     => $user_row->surname,
              'role'          => 'customer'
          );

          $user_id = wp_insert_user( $user );

          // billing/shipping address
          $meta = array(
              'billing_country'       => $user_row->country,
              'billing_first_name'    => $user_row->givenname,
              'billing_last_name'     => $user_row->surname,
              'billing_address_1'     => $user_row->streetaddress,
              'billing_city'          => $user_row->city,
              'billing_state'         => $user_row->state,
              'billing_postcode'      => $user_row->zipcode,
              'billing_email'         => $user_row->emailaddress,
              'billing_phone'         => $user_row->telephonenumber,
              'shipping_country'      => $user_row->country,
              'shipping_first_name'   => $user_row->givenname,
              'shipping_last_name'    => $user_row->surname,
              'shipping_address_1'    => $user_row->streetaddress,
              'shipping_city'         => $user_row->city,
              'shipping_state'        => $user_row->state,
              'shipping_postcode'     => $user_row->zipcode,
              'shipping_email'        => $user_row->emailaddress,
              'shipping_phone'        => $user_row->telephonenumber
          );

          foreach ($meta as $key => $value) {
              update_user_meta( $user_id, $key, $value );
          }
        }
    }

    public function get_random_user($x) {
        if ( !$this->users ) {
            $this->users  = get_users( array('role' => 'Customer', 'fields' => 'ID') );
        }

        $length = count($this->users);
        $idx    = ($x%75)+1;

        return $this->users[$idx];
    }

}

$GLOBALS['wc_order_simulator'] = new WC_Order_Simulator();
