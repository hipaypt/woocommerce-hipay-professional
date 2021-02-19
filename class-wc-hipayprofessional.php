<?php
/*
Plugin Name: WooCommerce HiPay Professional
Plugin URI: https://github.com/hipaypt/woocommerce-hipay-professional
Description: WooCommerce Plugin for Hipay Professional.
Version: 1.1.13
Text Domain: hipayprofessional
Author: HiPay Portugal
Author URI: https://github.com/hipaypt
*/

add_action('plugins_loaded', 'woocommerce_hipayprofessional_init', 0);

function woocommerce_hipayprofessional_init() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class WC_HipayProfessional extends WC_Payment_Gateway  {
		
		public function __construct() {

			global $woocommerce;
			global $wpdb;

			$this->id = 'hipayprofessional';

			load_plugin_textdomain( $this->id, false, basename( dirname( __FILE__ ) ) . '/languages' ); 

			$this->woocommerce_version = $woocommerce->version;
			if ( version_compare( $woocommerce->version, '3.0', ">=" ) ) 
				$this->woocommerce_version_check = true;
			else
				$this->woocommerce_version_check = false;
	
			$this->plugin_table = $wpdb->prefix . 'woocommerce_hipayprofessional';

			$this->hipay_webservice_live_category_url 		= 'https://payment.hipay.com/order/list-categories/id/';			
			$this->hipay_webservice_sandbox_category_url 	= 'https://test-payment.hipay.com/order/list-categories/id/';			
			$this->hipay_webservice_live_transaction_url 	= 'https://ws.hipay.com/soap/transaction-v2?wsdl';
			$this->hipay_webservice_sandbox_transaction_url = 'https://test-ws.hipay.com/soap/transaction-v2?wsdl';
			$this->hipay_webservice_live_payment_url 		= 'https://ws.hipay.com/soap/payment-v2/generate?wsdl';
			$this->hipay_webservice_sandbox_payment_url 	= 'https://test-ws.hipay.com/soap/payment-v2/generate?wsdl';

			$this->has_fields 			= false;
			$this->method_title     	= __('HiPay Professional', 'hipayprofessional' );
			$this->method_description  	= __( 'Pay with Credit Card or local payment methods.', 'hipayprofessional' );

			$this->init_form_fields();
			$this->init_settings();
			$this->sandbox 				= $this->get_option('sandbox');
			$this->emailCallback 		= $this->get_option('hw_emailcallback');
			$this->shoplogo 			= $this->get_option('hw_shoplogo');
			$this->rating 				= $this->get_option('hw_rating');
			$this->salt					= $this->get_option('salt');
			$this->max_value 			= $this->get_option('hw_max_value');
			$this->min_value 			= $this->get_option('hw_min_value');
			$this->stockonpayment 		= $this->get_option('stockonpayment');
			
			$this->timeLimitDays 		= $this->get_option('timeLimitDays');
			$this->authorized_languages = array("pt_PT","en_GB","en_US","es_ES","it_IT","fr_FR", "fr_BE", "de_DE", "nl_NL", "nl_BE", "pt_BR", "pl_PL" );
			$this->default_language		= $this->get_option('hw_default_language');
			$this->log_activity			= $this->get_option('hw_log_activity');
			
			if ( $this->log_activity == "yes" ){
				if (!file_exists(dirname(__FILE__) . '/logs')) {
					mkdir(dirname(__FILE__) . '/logs');
				}
				if (!file_exists(dirname(__FILE__) . '/logs/index.php')) {
					file_put_contents(dirname(__FILE__) . '/logs/index.php','<?php exit;');
				}			
			}
				
			$this->account_details 		= get_option( 'woocommerce_hipay_accounts',
				array(
					array(
						'hp_m_currency'   	=> $this->get_option( 'hp_m_currency' ),
						'hp_m_username' 	=> $this->get_option( 'hp_m_username' ),
						'hp_m_password'     => $this->get_option( 'hp_m_password' ),
						'hp_m_website'      => $this->get_option( 'hp_m_website' ),
						'hp_m_category'     => $this->get_option( 'hp_m_category' ),
						'hp_m_store'        => $this->get_option( 'hp_m_store' ),
					),
				)
			);


			$this->language_details 	= get_option( 'woocommerce_hipay_languages',
				array(
					array(
						'hp_m_title'   			=> $this->get_option( 'hp_m_title' ),
						'hp_m_description' 		=> $this->get_option( 'hp_m_description' ),
						'hp_m_title_payments'   => $this->get_option( 'hp_m_title_payments' ),
						'hp_m_logo'      		=> $this->get_option( 'hp_m_logo' ),
					),
				)
			);

			$this->title 				= $this->get_option('title');
			$this->description 			= $this->get_option('description');
			$this->title_payment_window = $this->get_option('title_payment_window');
			$this->payment_image 		= $this->get_option('payment_image');
			$this->icon 				= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/hipay_logo-'.$this->payment_image.'.png';

			$locale = get_locale();
			if ( isset($this->language_details[$locale]["hp_m_title"])) 
				if ($this->language_details[$locale]["hp_m_title"] != "") $this->title 			= $this->language_details[$locale]["hp_m_title"];

			if ( isset($this->language_details[$locale]["hp_m_description"])) 
				if ($this->language_details[$locale]["hp_m_description"] != "") $this->description 		= $this->language_details[$locale]["hp_m_description"];

			if ( isset($this->language_details[$locale]["hp_m_title_payments"])) 
				if ($this->language_details[$locale]["hp_m_title_payments"] != "") $this->title_payment_window 	= $this->language_details[$locale]["hp_m_title_payments"];

			if ( isset($this->language_details[$locale]["hp_m_logo"])) {
				if ($this->language_details[$locale]["hp_m_title"] != "") {
					$this->payment_image 	= $this->language_details[$locale]["hp_m_logo"];
					$this->icon 			= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/hipay_logo-'.$this->payment_image.'.png';
				}
			}

			add_action('woocommerce_api_wc_hipayprofessional', array($this, 'check_callback_response') );
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
			add_action('woocommerce_thankyou_hipayprofessional', array($this, 'thanks_page'));

		}


		function init_form_fields() {
			
			global $wpdb;
		   	
			if($wpdb->get_var("show tables like '$this->plugin_table'") != $this->plugin_table)
			{
				$charset_collate = $wpdb->get_charset_collate();
				$sql = "CREATE TABLE $this->plugin_table (
				  `id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  `reference` varchar(520) NOT NULL,
				  `processed` tinyint(4) NOT NULL DEFAULT '0',
				  `order_id` bigint(20) NOT NULL,
				  `processed_date` datetime NOT NULL,
				  `amount` varchar(7) NOT NULL,
				  `status` varchar(7) NOT NULL,
				  `operation` varchar(17) NOT NULL,
				UNIQUE KEY id (id)
				) $charset_collate;";

				
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);

				if ($this->log_activity == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => Table create => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}				
				
			} 

			$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'hipayprofessional' ),
							'type' => 'checkbox',
							'label' => __( 'Activate Hipay Professional payments', 'hipayprofessional' ),
							'default' => 'yes'
						),

			'sandbox' => array(
							'title' => __( 'Sandbox', 'hipayprofessional' ),
							'type' => 'checkbox',
							'label' => __( 'Use sandbox accounts (Test platform)', 'hipayprofessional' ),
							'default' => 'no'
						),

			'title' => array(
							'title' => __( 'Default Checkout title', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Payment title during checkout.', 'hipayprofessional' ),
							'default' => __( 'Hipay Professional.', 'hipayprofessional' )
						),
			'description' => array(
							'title' => __( 'Default Checkout message', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Payment message on the checkout page.', 'hipayprofessional' ),
							'default' => __( 'Pay with Credit Card or local payment methods.', 'hipayprofessional' )
						),

			'payment_image' => array(
							'title' => __( 'Default payment image', 'hipayprofessional' ), 
							'type' => 'select', 
							'description' => __( 'Default image used during the checkout process.', 'hipayprofessional' ), 
							'options'     => array(
        						'03' => __('VISA + Mastercard + Maestro', 'hipayprofessional' ),
        						'01' => __('Multibanco + Payshop + VISA + Mastercard + Maestro', 'hipayprofessional' ),
        						'02' => __('Multibanco', 'hipayprofessional' )        						
        					)  
						),


			'title_payment_window' => array(
							'title' => __( 'Default payment window title', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Payment message on the Hipay payment window.', 'hipayprofessional' ),
							'default' => __( '#%s', 'hipayprofessional' )
						),

			'language_details' => array(
				'type'        => 'language_details',
			),

			'account_details' => array(
				'type'        => 'account_details',
			),
		
			'hw_emailcallback' => array(
							'title' => __( 'Technical Email', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Receives the notification results.', 'hipayprofessional' ),
							'required' => true
						),			
			'hw_shoplogo' => array(
							'title' => __( 'Store / Website Logo', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Logo on the Hipay payment window.', 'hipayprofessional' ),
							'required' => false
						),			

			'hw_rating' => array(
							'title' => __( 'Rating', 'hipayprofessional' ), 
							'type' => 'select', 
							'description' => '', 
							'options'     => array(
        						'ALL' => __('All ages', 'hipayprofessional' ),
        						'+18' => __('+ 18 Years', 'hipayprofessional' ),
        						'+16' => __('+ 16 Years', 'hipayprofessional' ),
        						'+12' => __('+ 12 Years', 'hipayprofessional' )   		)  
						),

			'hw_default_language' => array(
							'title' => __( 'Default Language', 'hipayprofessional' ), 
							'type' => 'select', 
							'description' => '', 
							'options'     => array(
        						'pt_PT' => __('Portuguese - Portugal', 'hipayprofessional' ),
        						'en_GB' => __('English', 'hipayprofessional' ),
        						'en_US' => __('English - USA', 'hipayprofessional' ),
        						'es_ES' => __('Spanish', 'hipayprofessional' ),
        						'it_IT' => __('Italian', 'hipayprofessional' ),
        						'fr_FR' => __('French', 'hipayprofessional' ),
        						'fr_BE' => __('French - Belgium', 'hipayprofessional' ),
        						'de_DE' => __('German', 'hipayprofessional' ),
        						'nl_NL' => __('Dutch', 'hipayprofessional' ),
        						'nl_BE' => __('Dutch - Belgium', 'hipayprofessional' ),
        						'pt_BR' => __('Portuguese - Brazil', 'hipayprofessional' ),
        						'pl_PL' => __('Polish', 'hipayprofessional' )
        					)	
						),
			'hw_min_value' => array(
							'title' => __( 'Minimum amount', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Minimum amount to use this payment method.', 'hipayprofessional' ),
							'required' => true,
							'default' => 1
						),
			'hw_max_value' => array(
							'title' => __( 'Maximum amount', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Minimum amount to use this payment method.', 'hipayprofessional' ),
							'required' => true,
							'default' => 99999
						),
			'stockonpayment' => array(
							'title' => __( 'Reduce stock', 'hipayprofessional' ),
							'type' => 'checkbox',
							'description' => __( 'Stock is reduced only after payment confirmation.', 'hipayprofessional' ),
							'default' => 'no'

						),
			'salt' => array(
							'title' => __( 'Encrypt Key', 'hipayprofessional' ),
							'type' => 'text',
							'description' => __( 'Do not change after first use.', 'hipayprofessional' ),
							'required' => true,
							'default' => uniqid()
						),
			'hw_log_activity' => array(
							'title' => __( 'Log plugin activity', 'hipayprofessional' ),
							'type' => 'checkbox',
							'description' => __( 'Use only for debug purposes.', 'hipayprofessional' ),
							'default' => 'no'
						),

			);

		}


		function hipay_professional_get_locale_country($locale){
			
			switch ($locale) {
				case 'pt_PT': return __('Portuguese - Portugal', 'hipayprofessional' );
	        	case 'en_GB': return __('English', 'hipayprofessional' );
	        	case 'en_US': return __('English - USA', 'hipayprofessional' );
	        	case 'es_ES': return __('Spanish', 'hipayprofessional' );
	        	case 'it_IT': return __('Italian', 'hipayprofessional' );
	        	case 'fr_FR': return __('French', 'hipayprofessional' );
	        	case 'fr_BE': return __('French - Belgium', 'hipayprofessional' );
	        	case 'de_DE': return __('German', 'hipayprofessional' );
	        	case 'nl_NL': return __('Dutch', 'hipayprofessional' );
	        	case 'nl_BE': return __('Dutch - Belgium', 'hipayprofessional' );
	        	case 'pt_BR': return __('Portuguese - Brazil', 'hipayprofessional' );
	        	case 'pl_PL': return __('Polish', 'hipayprofessional' );
			}
		}


		public function generate_language_details_html() {

			ob_start();
			$authorized_languages = get_available_languages();
			if (count($authorized_languages) > 0) {
			?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php _e( 'Titles per language', 'hipayprofessional' ); ?></th>
    <td class="" id="hipay_languages">
        <table class="widefat wc_input_table sortable" style="padding:10px;">
            <tbody class="hipay_languages_table">
                <?php
							$i = -1;
							foreach ( $authorized_languages as $language ) {
									$i++;
									echo '<tr class=""><td><b><br>
										'.$this->hipay_professional_get_locale_country($language).'</b><br><br><p class="description"> 
										'.__( 'Checkout title', 'hipayprofessional' ).'</p>
										<input type="text" value="' . esc_attr( $this->language_details[$language]['hp_m_title'] ) . '" name="hp_m_title[' . $language . ']" class="input-text regular-input" /><br>
										<p class="description">'.__( 'Checkout message', 'hipayprofessional' ).'</p><input type="text" value="' . esc_attr( wp_unslash( $this->language_details[$language]['hp_m_description'] ) ) . '" name="hp_m_description[' . $language . ']" /><br>
										<p class="description">'.__( 'Title on Hipay payment window', 'hipayprofessional' ).'</p><input type="text" value="' . esc_attr( $this->language_details[$language]['hp_m_title_payments'] ) . '" name="hp_m_title_payments[' . $language . ']" /><br>
										<p class="description">'.__( 'Payment image during checkout', 'hipayprofessional' ).'</p>
										<select name="hp_m_logo['.$language.']" >';
											echo '<option value="03"';
											if ($this->language_details[$language]['hp_m_logo'] == "03") echo " SELECTED";
											echo ">".__('VISA + Mastercard + Maestro', 'hipayprofessional' ).'</option>';	
											echo '<option value="01"';
											if ($this->language_details[$language]['hp_m_logo'] == "01") echo " SELECTED";
											echo ">". __('Multibanco + Payshop + VISA + Mastercard + Maestro', 'hipayprofessional' ).'</option>';	
											echo '<option value="02"';
											if ($this->language_details[$language]['hp_m_logo'] == "02") echo " SELECTED";
											echo ">".__('Multibanco', 'hipayprofessional' ).'</option>';	
										echo '</select>									
										<br><br></td>
									</tr>';
							}
							?>
            </tbody>
        </table>
    </td>
</tr>
<?php
			}
			return ob_get_clean();

		}


		public function generate_account_details_html() {

			ob_start();
			?>
<tr valign="top">
    <th scope="row" class="titledesc"><?php _e( 'Account per Currency', 'hipayprofessional' ); ?></th>
    <td class="forminp" id="hipay_accounts">
        <table class="widefat wc_input_table sortable" cellspacing="0">
            <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <th><?php _e( 'Currency', 'hipayprofessional' ); ?></th>
                    <th><?php _e( 'API User', 'hipayprofessional' ); ?></th>
                    <th><?php _e( 'API Password', 'hipayprofessional' ); ?></th>
                    <th><?php _e( 'Website ID', 'hipayprofessional' ); ?></th>
                    <th><?php _e( 'Category ID', 'hipayprofessional' ); ?></th>
                    <th><?php _e( 'Shop ID ', 'hipayprofessional' ); ?></th>
                </tr>
            </thead>
            <tbody class="accounts">
                <?php
							$i = -1;
							if ( $this->account_details ) {
								foreach ( $this->account_details as $account ) {
									$i++;

									echo '<tr class="account">
										<td class="sort"></td>
										<td><select name="hp_m_currency[' . $i . ']" >
											<option value="EUR"';
											if ($account['hp_m_currency'] == "EUR") echo " SELECTED";
											echo '>Euro</option><option value="GBP"';
											if ($account['hp_m_currency'] == "GBP") echo " SELECTED";
											echo '>British Pound</option><option value="USD"';
											if ($account['hp_m_currency'] == "USD") echo " SELECTED";
											echo '>American Dolar</option><option value="AUD"';
											if ($account['hp_m_currency'] == "AUD") echo " SELECTED";
											echo '>Australian Dolar</option><option value="CAD"';
											if ($account['hp_m_currency'] == "CAD") echo " SELECTED";
											echo '>Canadian Dolar</option><option value="SEK"';
											if ($account['hp_m_currency'] == "SEK") echo " SELECTED";
											echo '>Swedish Krona</option><option value="PLN"';
											if ($account['hp_m_currency'] == "PLN") echo " SELECTED";
											echo '>Polish Zloty</option><option value="CHF"';
											if ($account['hp_m_currency'] == "CHF") echo " SELECTED";
											echo '>Swiss Franc</option>
										</select></td>
										<td><input type="text" value="' . esc_attr( $account['hp_m_username'] ) . '" name="hp_m_username[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['hp_m_password'] ) ) . '" name="hp_m_password[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['hp_m_website'] ) . '" name="hp_m_website[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['hp_m_category'] ) . '" name="hp_m_category[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['hp_m_store'] ) . '" name="hp_m_store[' . $i . ']" /></td>
									</tr>';
								}
							}
							?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7"><a href="#"
                            class="add button"><?php _e( '+ Add account', 'hipayprofessional' ); ?></a> <a href="#"
                            class="remove_rows button"><?php _e( 'Remove selected account(s)', 'hipayprofessional' ); ?></a>
                    </th>
                </tr>
            </tfoot>
        </table>
        <script type="text/javascript">
        jQuery(function() {
            jQuery('#hipay_accounts').on('click', 'a.add', function() {

                var size = jQuery('#hipay_accounts').find('tbody .account').length;

                jQuery('<tr class="account">\
										<td class="sort"></td>\
										<td><select name="hp_m_currency[' + size + ']" ><option value="EUR">Euro</option><option value="GBP">British Pound</option><option value="USD">American Dolar</option><option value="AUD">Australian Dolar</option><option value="CAD">Canadian Dolar</option><option value="SEK">Swedish Krona</option><option value="PLN">Polish Zloty</option><option value="CHF">Swiss Franc</option></select></td>\
										<td><input type="text" name="hp_m_username[' + size + ']" /></td>\
										<td><input type="text" name="hp_m_password[' + size + ']" /></td>\
										<td><input type="text" name="hp_m_website[' + size + ']" /></td>\
										<td><input type="text" name="hp_m_category[' + size + ']" /></td>\
										<td><input type="text" name="hp_m_store[' + size + ']" /></td>\
									</tr>').appendTo('#hipay_accounts table tbody');

                return false;
            });
        });
        </script>
    </td>
</tr>
<?php
			return ob_get_clean();

		}


		public function save_account_details() {

			$accounts = array();

			if ( isset( $_POST['hp_m_username'] ) ) {

				$hp_m_currency   = array_map( 'wc_clean', $_POST['hp_m_currency'] );
				$hp_m_username      = array_map( 'wc_clean', $_POST['hp_m_username'] );
				$hp_m_password      = array_map( 'wc_clean', $_POST['hp_m_password'] );
				$hp_m_website           = array_map( 'wc_clean', $_POST['hp_m_website'] );
				$hp_m_category            = array_map( 'wc_clean', $_POST['hp_m_category'] );
				$hp_m_store            = array_map( 'wc_clean', $_POST['hp_m_store'] );

				foreach ( $hp_m_username as $i => $name ) {
					if ( ! isset( $hp_m_username[ $i ] ) ) 	continue;
					
					if ($hp_m_category[ $i ] == "" && $hp_m_website[ $i ] != ""){

						$ws_url = $this->hipay_webservice_live_category_url;
						if ($this->sandbox == "yes")
							$ws_url = $this->hipay_webservice_sandbox_category_url;


						try {

							$result = file_get_contents($ws_url. $hp_m_website[ $i ]);
							$obj = new SimpleXMLElement(trim($result));

							if ( isset( $obj->result[0]->status ) ) {
								if ( $obj->result[0]->status == "error" )
									$hp_m_category[ $i ] = "";
							}
							else {

								if (isset($obj->categoriesList[0])) {
									$d = $obj->categoriesList[0]->children();
									foreach($d as $xml2) {
										$hp_m_category[ $i ] = (string)$xml2->attributes()->id ;										
									}
								}

							}	

						} catch (Exception $e) {
							//var_dump($e);
						}
					}


					$accounts[ $hp_m_currency[ $i ]] = array(
						'hp_m_currency'   	=>  $hp_m_currency[ $i ],
						'hp_m_username' 	=> $hp_m_username[ $i ],
						'hp_m_password'     => $hp_m_password[ $i ],
						'hp_m_website'      => $hp_m_website[ $i ],
						'hp_m_category'     => $hp_m_category[ $i ],
						'hp_m_store'        => $hp_m_store[ $i ],
					);

				}
			}

			$languages = array();	

			if (isset($_POST['hp_m_title']))
				$hp_m_title   			= array_map( 'wc_clean', $_POST['hp_m_title'] );
			else 
				$hp_m_title = "";
			
			if (isset($_POST['hp_m_description']))
				$hp_m_description   	= array_map( 'wc_clean', $_POST['hp_m_description'] );
			else
				$hp_m_description  = "";
			
			if (isset($_POST['hp_m_title_payments']))
				$hp_m_title_payments   	= array_map( 'wc_clean', $_POST['hp_m_title_payments'] );
			else  
				$hp_m_title_payments   	="";
			
			if (isset($_POST['hp_m_logo']))		
				$hp_m_logo   			= array_map( 'wc_clean', $_POST['hp_m_logo'] );
            else 
				$hp_m_logo   			="";
			
			foreach ( $this->authorized_languages as $language ) {

				if (isset($hp_m_title[$language])){					
					$languages[$language] = array(
						'hp_m_title'			=>  $hp_m_title[$language],
						'hp_m_description'		=>  $hp_m_description[$language],
						'hp_m_title_payments'	=>  $hp_m_title_payments[$language],
						'hp_m_logo'   			=>  $hp_m_logo[$language],
					);
				}

			}
			
			update_option( 'woocommerce_hipay_accounts', $accounts );
			update_option( 'woocommerce_hipay_languages', $languages );

		}



		public function admin_options() {

		    global $wpdb;

			$soap_active = false;
			$simplexml_active = false;
			$has_webservice_access = false;
			$has_webservice_access_config = false;
			if (extension_loaded('soap')) 
				$soap_active = true;
			if (extension_loaded('simplexml')) 
				$simplexml_active = true;
			
				
			?>
<h3><?php _e('Payments with HiPay Professional', 'hipayprofessional'); ?></h3>
<p></p>

<table class="wc_emails widefat" cellspacing="0">
    <tbody>
        <tr>
            <td class="wc-email-settings-table-status">
                <?php
					if ($soap_active){ ?>
                <span class="status-enabled"></span>
                <?php
					} else	{ ?>
                <span class="status-disabled"></span>
                <?php
					}	?>
            </td>

            <td class="wc-email-settings-table-name"><?php _e( 'SOAP LIB', 'hipayprofessional' ); ?></td>

            <td>
                <?php
					if (!$soap_active) _e( 'Please install and activate SOAP Library.', 'hipayprofessional' );       
					?>
            </td>
        </tr>

        <tr>
            <td class="wc-email-settings-table-status">
                <?php
					if ($simplexml_active){ ?>
                <span class="status-enabled"></span>
                <?php
					} else	{ ?>
                <span class="status-disabled"></span>
                <?php
					}	?>
            </td>
            <td class="wc-email-settings-table-name"><?php _e( 'SimpleXML', 'hipayprofessional' ); ?></td>
            <td>
                <?php
					if (!$simplexml_active) _e( 'Please install and activate SimpleXML.', 'hipayprofessional' );       
					?>
            </td>
        </tr>


        <tr>
            <td class="wc-email-settings-table-status">

            </td>
            <td class="wc-email-settings-table-name">
                <?php _e( 'Plugin HowTo', 'hipayprofessional' ); ?><br>
                <a href="<?php echo WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/docs/WOOCOMMERCE_HIPAY_PROFESSIONAL_EN.pdf'?>"
                    target="_blank"><?php _e( 'English Version', 'hipayprofessional' ); ?></a><br>
                <a href="<?php echo WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/docs/WOOCOMMERCE_HIPAY_PROFESSIONAL_PT.pdf'?>"
                    target="_blank"><?php _e( 'Portuguese Version', 'hipayprofessional' ); ?></a>

            </td>
            <td alig="left">

            </td>
        </tr>


    </tbody>
</table>

<table class="form-table">
    <?php
			$this->generate_settings_html();
			?>
</table>

<p><?php _e('Please ensure that you have Woocommerce REST API activated.', 'hipayprofessional'); ?></p>

<?php
		}


		function thanks_page($order_id) {

			global $woocommerce;
            		global $myref;

			$order = new WC_Order( $order_id );

			if ($this->log_activity == 'yes'){
				error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
			}				

			$cur_payment_method = get_post_meta( $order->id, '_payment_method', true );
			if ($cur_payment_method == 'hipayprofessional' && ($order->post->post_status == "wc-pending" || $order->post->post_status == "wc-on-hold") ) 
				$order->update_status('on-hold', __('Waiting payment confirmation from Hipay.', 'hipayprofessional'));

			$woocommerce->cart->empty_cart();
			unset($_SESSION['order_awaiting_payment']);

		}



	    function process_payment( $order_id ) {

			global $woocommerce;
		    	global $wpdb;

			if ($this->log_activity == 'yes'){
				error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
			}

			$order = new WC_Order( $order_id );
			if ($this->woocommerce_version_check)
				$order_total = $order->get_total();
			else
				$order_total = $order->order_total;

			$result = $this->GenerateReference($order_id,$order_total,$order->get_order_key());
			if ($result->generateResult->code == 0) {

				if ($this->log_activity == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => " . $result->generateResult->redirectUrl . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}				
				
				$wpdb->insert( $this->plugin_table, array( 'reference' => $result->generateResult->redirectUrl, 'order_id' => $order_id, 'amount' => $order_total ) );
				if ($this->stockonpayment != "yes") wc_reduce_stock_levels( $order_id );	//$order->reduce_order_stock();
				$order->add_order_note(__('Payment URL:', 'hipayprofessional') . " " . $result->generateResult->redirectUrl );
		
	    		return array('result' 	=> 'success','redirect'	=> 	$result->generateResult->redirectUrl      	);
		
			} else {
				
				if ($this->log_activity == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => " . $result->generateResult->description . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}					
				$order->add_order_note($result->generateResult->description );
				return;

			}

    	}


		private function hipay_professional_get_locale(){
			
			$locale = get_locale();
			if ( !in_array($locale, $this->authorized_languages) ) $locale = $this->default_language;
			return $locale;
		}



		function GenerateReference($order_id, $order_value,$order_key)
		{

			global $woocommerce;

			if ($this->log_activity == 'yes'){
				error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
			}
			
			$ws_url = $this->hipay_webservice_live_payment_url;
			if ($this->sandbox == "yes")
				$ws_url = $this->hipay_webservice_sandbox_payment_url;

			$ch = sha1($this->salt.$order_id);
			$permalink_structure = get_option( 'permalink_structure' );		
			if ($permalink_structure == "")
				$callback_url = site_url().'?wc-api=WC_HipayProfessional&order=' . $order_id . "&" . "ch=" . $ch;
			else
				$callback_url = site_url().'/wc-api/WC_HipayProfessional/?order=' . $order_id . "&" . "ch=" . $ch;

			$order = new WC_Order( $order_id );              
			$billing_email = $order->get_billing_email(); 

			$current_currency = get_woocommerce_currency();
			$account = $this->account_details[$current_currency];

			$freedata = array();

			$freedata[] = array(
		    		'key' => 'order_key',
		      		'value' => $order_key
		    	);
			
			$order_check = sha1($order_id.$account["hp_m_password"]);
			$freedata[] = array(
		    		'key' => 'order_check',
		      		'value' => $order_check
		    	);


			try
			{

				$contextOptions = array(
					'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
					));

				$sslContext = stream_context_create($contextOptions);

				$soapParams =  array(
					'trace' => 1,
					'exceptions' => true,
					'cache_wsdl' => WSDL_CACHE_NONE,
					'stream_context' => $sslContext
					);

				$client = new SoapClient($ws_url,$soapParams);

				$language = $this->hipay_professional_get_locale();

				$ip=$_SERVER['REMOTE_ADDR'];
				$currentDate = date('Y-m-dTH:i:s');
				$uid = md5($currentDate.uniqid());
				$title_payment_window = sprintf($this->title_payment_window,$order_id);
				
				$parameters = new stdClass(); 
				$parameters->parameters = array(
					'wsLogin' => $account["hp_m_username"],
					'wsPassword' => $account["hp_m_password"],
					'websiteId' => $account["hp_m_website"],
					'categoryId' => $account["hp_m_category"],
					'currency' => $current_currency,
					'amount' => $order_value,
					'rating' => $this->rating,
					'locale' => $language,
					'customerIpAddress' => $ip,
					'merchantReference' => $order_id,
					'description' => $title_payment_window,
					'executionDate' => $currentDate,
					'manualCapture' => 0,
					'customerEmail' => $billing_email,
					'merchantComment' => '',
					'emailCallback' => $this->emailCallback,
					'urlCallback' => $callback_url,
					'urlAccept' => $order->get_checkout_order_received_url(),
					'urlDecline' =>  $order->get_cancel_order_url_raw(),
					'urlCancel' => $order->get_cancel_order_url_raw(), 
					'urlLogo' => $this->shoplogo,
					'freeData' => $freedata
					//'items' => 		
				);					

				if ($account["hp_m_store"] != "") $parameters->parameters["shopId"] =  $account["hp_m_store"];
				
				$result = $client->generate($parameters);
				return $result;

			}
			catch (Exception $e){
				$result = new \stdClass;
				$result->generateResult->code = -1;
				$result->generateResult->description = $e->getMessage();
				return $result;
			}


		}


		function check_callback_response() {

			global $woocommerce;
			global $wpdb;

			$order_id = $_GET["order"];
			$ch = $_GET["ch"];

			if ($this->log_activity == 'yes'){
				error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
			}
			
			if ($ch == sha1($this->salt.$order_id)){

				try
				{

					$xml = $_POST['xml'];

					$operation = '';
					$status = '';
					$date = '';
					$time = '';
					$transid = '';
					$origAmount = '';
					$origCurrency = '';
					$idformerchant = '';
					$merchantdatas = array();
					$ispayment = true;

					$xml = trim($xml);
					$xml_count = strpos($xml,"<mapi>");
					$xml_len = strlen($xml);
					$xml = substr($xml,$xml_count,$xml_len - $xml_count);

					$obj = new SimpleXMLElement($xml);
					if (isset($obj->result[0]->operation))
						$operation=$obj->result[0]->operation;
					else
						$ispayment =  false;

					if (isset($obj->result[0]->status))
						$status=$obj->result[0]->status;
					else 
						$ispayment =  false;

					if (isset($obj->result[0]->date))
						$date=$obj->result[0]->date;
					else 
						$ispayment =  false;

					if (isset($obj->result[0]->time))
						$time=$obj->result[0]->time;
					else 
						$ispayment =  false;

					if (isset($obj->result[0]->transid))
						$transid=(string)$obj->result[0]->transid;
					else 
						$ispayment =  false;

					if (isset($obj->result[0]->origAmount))
						$origAmount=(string)$obj->result[0]->origAmount;
					else 
						$ispayment =  false;

					if (isset($obj->result[0]->origCurrency))
						$origCurrency=(string)$obj->result[0]->origCurrency;
					else 
						$ispayment = false;

					if (isset($obj->result[0]->idForMerchant))
						$idformerchant=$obj->result[0]->idForMerchant;
					else 
						$ispayment =  false;


					if ($status=="ok" && $operation=="capture") {

						$order = new WC_Order( $order_id );
	
						if ($this->stockonpayment == "yes") {
								wc_reduce_stock_levels( $order_id );
								$order->add_order_note(__('Stock updated after payment.', 'hipayprofessional') );
						}
						$order->update_status('processing', __("Payment successful for transaction", 'hipayprofessional' ) . " " . $transid, 0 );
						$wpdb->update( $wpdb->prefix . 'woocommerce_hipayprofessional' , array( 'status' => $status,'operation' => $operation,'processed' => 1, 'processed_date' => date('Y-m-d H:i:s')), array('order_id' =>$order_id, 'processed' => 0 ) );

					} elseif ($status=="waiting" && $operation == "authorization") {

						$order = new WC_Order( $order_id );
                        $order->add_order_note(__('Authorization OK. Waiting for capture. Operation', 'hipayprofessional') . ": " .  $operation . " " . __("Status", 'hipayprofessional') . ": " . $status);

					} elseif ($status!="ok" || $operation != "authorization") {

						$order = new WC_Order( $order_id );
						$order->add_order_note('Operação: ' .  $operation . " => Status: " . $status);
						$order->update_status('failed', __("Payment refused. Operation", 'hipayprofessional') . ": " .  $operation . " " . __("Status", 'hipayprofessional') . ": " . $status,   " " .$transid, 0 );
						$wpdb->update( $wpdb->prefix . 'woocommerce_hipayprofessional' , array( 'status' => $status,'operation' => $operation,'processed' => 1, 'processed_date' => date('Y-m-d H:i:s')), array('order_id' =>$order_id, 'processed' => 0 ) );

					} else {

                        $order = new WC_Order( $order_id );
                        $order->add_order_note(__('Authorization OK. Waiting for capture. Operation', 'hipayprofessional') . ": " .  $operation . " " . __("Status", 'hipayprofessional') . ": " . $status);

					}

				}
				catch (Exception $e){
					$error = $e->getMessage();
					$order = new WC_Order( $order_id );
					$order->add_order_note($error);
					if ($this->log_activity == 'yes'){
						error_log(date('Y-m-d H:i:s') . " => " .$error . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
					}
					return false;
				}


			}

			return true;


		}

	}	


	function filter_hipayprofessional_gateway( $methods ) {
		
		global $woocommerce;
		global $wpdb;

		$plugin_option =get_option( 'woocommerce_hipayprofessional_settings');

		if ($plugin_option['hw_log_activity'] == 'yes'){
			error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
		}
		
		if (isset($woocommerce->cart)){

			$hw_min_value = $plugin_option['hw_min_value'];
			$hw_max_value = $plugin_option['hw_max_value'];

			$account_currencies = get_option( 'woocommerce_hipay_accounts');
			$current_currency = get_woocommerce_currency();
						
			if (array_key_exists($current_currency, $account_currencies)) {
			    
				$currency_symbol = get_woocommerce_currency_symbol();
				$total_amount = $woocommerce->cart->get_total();
				$total_amount = str_replace($currency_symbol,"", $total_amount);
				$thousands_sep = wp_specialchars_decode(stripslashes(get_option( 'woocommerce_price_thousand_sep')), ENT_QUOTES);
				$total_amount = str_replace($thousands_sep,"", $total_amount);
				$decimals_sep = wp_specialchars_decode(stripslashes(get_option( 'woocommerce_price_decimal_sep')), ENT_QUOTES);
				if ( $decimals_sep != ".") $total_amount = str_replace($decimals_sep,".", $total_amount);
				$total_amount = floatval( preg_replace( '#[^\d.]#', '',  $total_amount) );
				if ( $total_amount > 0 &&  ($total_amount > $hw_max_value || $total_amount < $hw_min_value )) {
					if ($plugin_option['hw_log_activity'] == 'yes'){
						error_log(date('Y-m-d H:i:s') . " => Amount not permitted: " . $total_amount . " for min: " . $hw_min_value . " and max: " . $hw_max_value . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
					}
					unset($methods['hipayprofessional']); 
				}
				elseif ($plugin_option['hw_log_activity'] == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => Currency matches. Method is available " . $total_amount . " " . $hw_max_value . " " . $hw_min_value  . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}

			} else
			{
				if ($plugin_option['hw_log_activity'] == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => Currency does not match . " . $current_currency . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}
				unset($methods['hipayprofessional']); 
			}
		}
		return $methods;
	}



	function add_hipayprofessional_gateway( $methods ) {

		$methods[] = 'WC_HipayProfessional'; return $methods;
		
	}


	function update_stocks_cancelled_order( $order_id,  $order  ){

		global $woocommerce;
		global $wpdb;

		$wpdb->update( $wpdb->prefix . 'woocommerce_hipayprofessional' , array( 'status' => 'ok','operation' => 'cancelled','processed' => 1, 'processed_date' => date('Y-m-d H:i:s')), array('order_id' =>$order_id, 'processed' => 0 ) );

		$cur_payment_method = get_post_meta( $order_id, '_payment_method', true );

		if ( $cur_payment_method == 'hipayprofessional' ) {

			$plugin_option =get_option( 'woocommerce_hipayprofessional_settings');
			if ($plugin_option['hw_log_activity'] == 'yes'){
				error_log(date('Y-m-d H:i:s') . " => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
			}			

			if ($plugin_option['stockonpayment'] == "no"){

				$products = $order->get_items();
				if ($plugin_option['hw_log_activity'] == 'yes'){
					error_log(date('Y-m-d H:i:s') . " => Update stocks on cancel => " .__FUNCTION__. PHP_EOL,3,dirname(__FILE__) . '/logs/' . date('Y-m-d'). '.log');
				}
				foreach ( $products as $product ) 
				{

					$qt = $product['qty'];
					$product_id = $product['product_id'];
					$variation_id = (int)$product['variation_id'];
					
					if ($variation_id > 0 ) {
						$pv = New WC_Product_Variation( $variation_id );
						if ($pv->managing_stock()){
							$pv->increase_stock($qt);
	                        			$order->add_order_note($p->get_title() . ' ('.$product_id. ') #'.$variation_id. ' stock +'.$qt );

						} else {
							$p = New WC_Product( $product_id );
							$p->increase_stock($qt);
	                        			$order->add_order_note($p->get_title() . ' ('.$product_id. ') stock +'.$qt );
						}

					} else {
						$p = New WC_Product( $product_id );
						$p->increase_stock($qt);
						$order->add_order_note( $p->get_title() . ' ('.$product_id. ') stock +'.$qt );
					}
				}
			}	
		}	
	}


	add_filter('woocommerce_available_payment_gateways', 'filter_hipayprofessional_gateway' );
	add_filter('woocommerce_payment_gateways', 'add_hipayprofessional_gateway' );
	add_action('woocommerce_order_status_pending_to_cancelled', 'update_stocks_cancelled_order', 10, 2 );
	add_action('woocommerce_order_status_pending_to_failed', 'update_stocks_cancelled_order', 10, 2 );

}
