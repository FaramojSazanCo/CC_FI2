<?php
/**
 * Plugin Name: CCIF Iran Checkout (Fresh Rebuild)
 * Description: A plugin to customize the WooCommerce checkout form for Iran.
 * Version: 6.0
 * Author: Your Name
 * Text Domain: ccif-iran-checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CCIF_Iran_Checkout_Rebuild {

    public function __construct() {
        // Override the default billing form rendering with our custom layout
        add_action( 'woocommerce_before_checkout_billing_form', [ $this, 'output_custom_billing_form_start' ], 5 );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'output_custom_billing_form_end' ] );

        // Remove the default form
        add_filter('woocommerce_checkout_billing', '__return_false');

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    private function load_iran_data() {
        $json_file = plugin_dir_path( __FILE__ ) . 'assets/data/iran_data-2.json';
        if ( ! file_exists( $json_file ) ) {
            error_log('CCIF DEBUG: JSON file not found at ' . $json_file);
            return [ 'states' => [], 'cities' => [] ];
        }
        $data = json_decode( file_get_contents( $json_file ), true );
        $states = [];
        $cities = [];
        foreach ( $data as $province ) {
            $slug = sanitize_title( $province['name'] );
            $states[ $slug ] = $province['name'];
            $cities[ $slug ] = $province['cities'];
        }
        error_log('CCIF DEBUG: load_iran_data() successfully processed data.');
        error_log('CCIF DEBUG: Total states loaded: ' . count($states));
        error_log('CCIF DEBUG: Sample state key (slug): ' . print_r(array_key_first($cities), true));

        return [ 'states' => $states, 'cities' => $cities ];
    }

    public function get_all_checkout_fields() {
        $iran_data = $this->load_iran_data();
        $fields = [];

        // --- Define All Fields ---
        $fields['billing_invoice_request'] = ['type' => 'checkbox', 'label' => 'درخواست صدور فاکتور رسمی', 'class' => ['form-row-wide', 'ccif-invoice-request-field']];

        $fields['billing_person_type'] = ['type' => 'select', 'label' => 'نوع شخص', 'class' => ['form-row-wide', 'ccif-person-field'], 'options' => ['' => 'انتخاب کنید', 'real' => 'حقیقی', 'legal' => 'حقوقی']];

        $fields['billing_first_name'] = ['label' => 'نام', 'class' => ['form-row-first', 'ccif-person-field', 'ccif-real-person-field']];
        $fields['billing_last_name'] = ['label' => 'نام خانوادگی', 'class' => ['form-row-last', 'ccif-person-field', 'ccif-real-person-field']];
        $fields['billing_national_code'] = ['label' => 'کد ملی', 'class' => ['form-row-wide', 'ccif-person-field', 'ccif-real-person-field'], 'placeholder' => '۱۰ رقم بدون خط تیره'];

        $fields['billing_company_name'] = ['label' => 'نام شرکت', 'class' => ['form-row-first', 'ccif-person-field', 'ccif-legal-person-field']];
        $fields['billing_economic_code'] = ['label' => 'شناسه ملی/اقتصادی', 'class' => ['form-row-last', 'ccif-person-field', 'ccif-legal-person-field']];
        $fields['billing_agent_first_name'] = ['label' => 'نام نماینده', 'class' => ['form-row-first', 'ccif-person-field', 'ccif-legal-person-field']];
        $fields['billing_agent_last_name'] = ['label' => 'نام خانوادگی نماینده', 'class' => ['form-row-last', 'ccif-person-field', 'ccif-legal-person-field']];

        $fields['billing_state'] = ['type' => 'select', 'label' => 'استان', 'required' => true, 'class' => ['form-row-first', 'ccif-address-field'], 'options' => [ '' => 'انتخاب کنید' ] + $iran_data['states']];
        $fields['billing_city'] = ['type' => 'select', 'label' => 'شهر', 'required' => true, 'class' => ['form-row-last', 'ccif-address-field'], 'options' => [ '' => 'ابتدا استان را انتخاب کنید' ]];
        $fields['billing_address_1'] = ['label' => 'آدرس دقیق', 'required' => true, 'placeholder' => 'خیابان، کوچه، پلاک، واحد', 'class' => ['form-row-wide', 'ccif-address-field']];
        $fields['billing_postcode'] = ['label' => 'کد پستی', 'required' => true, 'type' => 'tel', 'class' => ['form-row-first', 'ccif-address-field']];
        $fields['billing_phone'] = ['label' => 'شماره تماس', 'required' => true, 'type' => 'tel', 'class' => ['form-row-last', 'ccif-address-field']];

        return $fields;
    }

    public function output_custom_billing_form_start($checkout) {
        $all_fields = $this->get_all_checkout_fields();

        echo '<div class="ccif-checkout-form">';

        // Box 1: Invoice Request
        echo '<div class="ccif-box invoice-request-box">';
        woocommerce_form_field('billing_invoice_request', $all_fields['billing_invoice_request'], $checkout->get_value('billing_invoice_request'));
        echo '</div>';

        // Box 2: Person/Company Info
        echo '<div class="ccif-box person-info-box"><h2>اطلاعات خریدار</h2>';
        woocommerce_form_field('billing_person_type', $all_fields['billing_person_type'], $checkout->get_value('billing_person_type'));

        echo '<div class="ccif-real-person-fields-wrapper">';
        woocommerce_form_field('billing_first_name', $all_fields['billing_first_name'], $checkout->get_value('billing_first_name'));
        woocommerce_form_field('billing_last_name', $all_fields['billing_last_name'], $checkout->get_value('billing_last_name'));
        woocommerce_form_field('billing_national_code', $all_fields['billing_national_code'], $checkout->get_value('billing_national_code'));
        echo '</div>';

        echo '<div class="ccif-legal-person-fields-wrapper">';
        woocommerce_form_field('billing_company_name', $all_fields['billing_company_name'], $checkout->get_value('billing_company_name'));
        woocommerce_form_field('billing_economic_code', $all_fields['billing_economic_code'], $checkout->get_value('billing_economic_code'));
        woocommerce_form_field('billing_agent_first_name', $all_fields['billing_agent_first_name'], $checkout->get_value('billing_agent_first_name'));
        woocommerce_form_field('billing_agent_last_name', $all_fields['billing_agent_last_name'], $checkout->get_value('billing_agent_last_name'));
        echo '</div>';
        echo '</div>';

        // Box 3: Address Info
        echo '<div class="ccif-box address-info-box"><h2>اطلاعات ارسال</h2>';
        woocommerce_form_field('billing_state', $all_fields['billing_state'], $checkout->get_value('billing_state'));
        woocommerce_form_field('billing_city', $all_fields['billing_city'], $checkout->get_value('billing_city'));
        woocommerce_form_field('billing_address_1', $all_fields['billing_address_1'], $checkout->get_value('billing_address_1'));
        woocommerce_form_field('billing_postcode', $all_fields['billing_postcode'], $checkout->get_value('billing_postcode'));
        woocommerce_form_field('billing_phone', $all_fields['billing_phone'], $checkout->get_value('billing_phone'));
        echo '</div>';
    }

    public function output_custom_billing_form_end() {
        echo '</div>'; // Close .ccif-checkout-form
    }

    public function enqueue_assets() {
        if ( ! is_checkout() ) return;

        $iran_data = $this->load_iran_data();
        $data_to_localize = [ 'cities' => $iran_data['cities'] ];

        error_log('CCIF DEBUG: enqueue_assets() is preparing to localize data.');
        error_log('CCIF DEBUG: Sample of data being localized (first key): ' . print_r(key($data_to_localize['cities']), true));

        wp_enqueue_script( 'ccif-checkout-js', plugin_dir_url( __FILE__ ) . 'assets/js/ccif-checkout.js', ['jquery'], '6.0', true );
        wp_localize_script( 'ccif-checkout-js', 'ccifData', $data_to_localize );
        wp_enqueue_style( 'ccif-checkout-css', plugin_dir_url( __FILE__ ) . 'assets/css/ccif-checkout.css', [], '6.0' );
    }
}

new CCIF_Iran_Checkout_Rebuild();
