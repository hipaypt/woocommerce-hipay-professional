<?php

class WC_HiPayProfessional_Blocks_Integration extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    protected $name = 'hipayprofessional'; // Deve corresponder ao ID do método de pagamento
	
    public function initialize() {
        $this->settings = get_option('woocommerce_hipayprofessional_settings', array());
    }

    public function is_active() {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
		
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-hipayprofessional-blocks-integration',
            plugins_url('../assets/js/hipayprofessional-blocks.js', __FILE__),
            array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'),
            '1.0.0',
            true
        );
        
        $args = array(
            'image' => WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/../images/hipay_logo-'.$this->settings['payment_image'].'.png',
        );
    
        wp_localize_script(
            'wc-hipayprofessional-blocks-integration', 
            'hipayProfessionalData', 
            $args 
        );

        if( ! wp_script_is( 'wc-hipayprofessional-blocks-integration', 'enqueued' ) ) {
            wp_enqueue_script('wc-hipayprofessional-blocks-integration');       
        }   
           
        return array('wc-hipayprofessional-blocks-integration');
    }

    public function get_payment_method_data() {
        return array(
            'title'       => $this->settings['method_title'] ?? 'HiPay Professional',
            'description' => $this->settings['method_description'] ?? 'Pay with Credit Card or local payment methods.',
            'supports'    => array('products'),
        );
    }
}
