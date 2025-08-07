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

    private $order_notes_field = [];
    private $custom_billing_fields = [
        'billing_person_type',
        'billing_invoice_request',
        'billing_national_code',
        'billing_economic_code',
    ];

    public function __construct() {
        // Override the default billing form rendering with our custom layout
        add_action( 'woocommerce_before_checkout_billing_form', [ $this, 'output_custom_billing_form_start' ], 5 );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'output_custom_billing_form_end' ] );

        // Hook into checkout fields to manage them
        add_filter( 'woocommerce_checkout_fields', [ $this, 'customize_checkout_fields' ] );

        // Modify field arguments, e.g., to remove '(optional)' text
        add_filter( 'woocommerce_form_field_args', [ $this, 'remove_optional_text' ], 10, 3 );

        // Save custom fields to user meta
        add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'save_custom_fields_to_user_meta' ], 10, 2 );

        // Pre-populate custom fields from user meta
        add_filter( 'woocommerce_checkout_get_value', [ $this, 'get_custom_field_value_from_user_meta' ], 10, 2 );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function save_custom_fields_to_user_meta( $customer_id, $posted_data ) {
        if ( ! $customer_id ) {
            return; // Only for logged-in users
        }

        foreach ( $this->custom_billing_fields as $field_key ) {
            if ( isset( $posted_data[ $field_key ] ) ) {
                $value = sanitize_text_field( $posted_data[ $field_key ] );
                update_user_meta( $customer_id, $field_key, $value );
            }
        }
    }

    public function get_custom_field_value_from_user_meta( $value, $key ) {
        // If there's already a value (e.g., from a failed submission), don't override it.
        if ( ! empty( $value ) ) {
            return $value;
        }

        // Check if the user is logged in and the key is one of our custom fields.
        if ( is_user_logged_in() && in_array( $key, $this->custom_billing_fields ) ) {
            $saved_value = get_user_meta( get_current_user_id(), $key, true );
            if ( $saved_value ) {
                return $saved_value;
            }
        }

        return $value;
    }

    public function customize_checkout_fields( $fields ) {
        // --- 1. Add or Modify Custom Fields ---

        $fields['billing']['billing_invoice_request'] = [
            'type'     => 'checkbox',
            'label'    => 'درخواست صدور فاکتور رسمی',
            'class'    => ['form-row-wide', 'ccif-group-invoice'],
            'priority' => 5,
        ];

        $fields['billing']['billing_person_type'] = [
            'type'     => 'select',
            'label'    => 'نوع شخص',
            'class'    => ['form-row-wide', 'ccif-group-person', 'ccif-group-start'], // Mark as start of a group
            'priority' => 15,
            'options'  => [
                ''      => 'انتخاب کنید',
                'real'  => 'حقیقی',
                'legal' => 'حقوقی',
            ],
        ];

        // --- 2. Adjust Standard Fields ---

        // Person info fields
        $fields['billing']['billing_first_name']['class'] = ['form-row-first', 'ccif-group-person'];
        $fields['billing']['billing_first_name']['priority'] = 20;

        $fields['billing']['billing_last_name']['class'] = ['form-row-last', 'ccif-group-person'];
        $fields['billing']['billing_last_name']['priority'] = 30;

        $fields['billing']['billing_national_code'] = [
            'label'       => 'کد ملی',
            'placeholder' => '۱۰ رقم بدون خط تیره',
            'required'    => false,
            'class'       => ['form-row-wide', 'ccif-group-person'],
            'priority'    => 40,
        ];

        // Use standard company field, just change the label and add classes
        $fields['billing']['billing_company']['label'] = 'نام شرکت';
        $fields['billing']['billing_company']['class'] = ['form-row-first', 'ccif-group-person'];
        $fields['billing']['billing_company']['priority'] = 50;

        $fields['billing']['billing_economic_code'] = [
            'label'       => 'شناسه ملی/اقتصادی',
            'required'    => false,
            'class'       => ['form-row-last', 'ccif-group-person', 'ccif-group-end'], // Mark as end of a group
            'priority'    => 60,
        ];

        // Address Fields
        $fields['billing']['billing_state']['class'] = ['form-row-first', 'ccif-group-address', 'ccif-group-start'];
        $fields['billing']['billing_state']['priority'] = 70;

        $fields['billing']['billing_city']['class'] = ['form-row-last', 'ccif-group-address'];
        $fields['billing']['billing_city']['priority'] = 80;

        $fields['billing']['billing_address_1']['class'] = ['form-row-wide', 'ccif-group-address'];
        $fields['billing']['billing_address_1']['priority'] = 90;
        $fields['billing']['billing_address_1']['placeholder'] = 'خیابان، کوچه، پلاک، واحد';


        $fields['billing']['billing_postcode']['class'] = ['form-row-first', 'ccif-group-address'];
        $fields['billing']['billing_postcode']['priority'] = 100;

        $fields['billing']['billing_phone']['class'] = ['form-row-last', 'ccif-group-address', 'ccif-group-end'];
        $fields['billing']['billing_phone']['priority'] = 110;

        // --- 3. Move Order Notes ---
        if (isset($fields['order']['order_comments'])) {
            $fields['billing']['order_comments'] = $fields['order']['order_comments'];
            $fields['billing']['order_comments']['label'] = 'توضیحات تکمیلی';
            $fields['billing']['order_comments']['class'] = ['form-row-wide', 'ccif-group-notes', 'ccif-group-start', 'ccif-group-end'];
            $fields['billing']['order_comments']['priority'] = 120;
            unset($fields['order']['order_comments']);
        }

        // Unset fields we don't use
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_company_name']); // Remove my old custom one
        unset($fields['billing']['billing_agent_first_name']);
        unset($fields['billing_agent_last_name']);


        return $fields;
    }

    public function remove_optional_text( $args, $key, $value ) {
        // This function removes the "(optional)" text from the labels of non-required fields.
        if ( ! $args['required'] && isset($args['label']) ) {
            // This regex is more flexible. It looks for an optional whitespace or &nbsp;
            // followed by the <span class="optional">...</span> tag and removes it.
            $args['label'] = preg_replace( '/(\s|&nbsp;)?<span class="optional">.*?<\/span>/i', '', $args['label'] );
        }
        return $args;
    }

    private function normalize_persian_string($string) {
        // Replace common Arabic characters with Persian equivalents for better matching.
        $string = str_replace(['ي', 'ك', 'آ'], ['ی', 'ک', 'ا'], $string);
        // Remove non-breaking spaces and trim whitespace from the beginning and end.
        $string = trim(str_replace('&nbsp;', ' ', $string));
        return $string;
    }

    private function load_iran_data() {
        // Ensure WooCommerce is active to avoid fatal errors.
        if ( ! class_exists('WooCommerce') ) {
            return ['states' => [], 'cities' => []];
        }

        // Get the official list of states from WooCommerce for Iran.
        // This returns an array like: [ 'TEH' => 'تهران', 'KHZ' => 'خوزستان', ... ]
        $wc_states = WC()->countries->get_states('IR');

        if (empty($wc_states)) {
            return ['states' => [], 'cities' => []];
        }

        // Load the city data from our custom JSON file.
        $json_file = plugin_dir_path(__FILE__) . 'assets/data/iran_data-2.json';
        if (!file_exists($json_file)) {
            // If the city file is missing, still return the official states for the dropdown.
            return ['states' => $wc_states, 'cities' => []];
        }
        $custom_data = json_decode(file_get_contents($json_file), true);

        // Create a reverse map of normalized state names to state codes for robust lookup.
        // e.g., [ 'تهران' => 'TEH', 'خوزستان' => 'KHZ', ... ]
        $normalized_name_to_code_map = [];
        foreach ($wc_states as $code => $name) {
            $normalized_name_to_code_map[$this->normalize_persian_string($name)] = $code;
        }

        $cities = [];
        if (is_array($custom_data)) {
            foreach ($custom_data as $province) {
                if (isset($province['name']) && isset($province['cities'])) {
                    $normalized_province_name = $this->normalize_persian_string($province['name']);
                    // Find the official WooCommerce code for the current province name.
                    if (isset($normalized_name_to_code_map[$normalized_province_name])) {
                        $state_code = $normalized_name_to_code_map[$normalized_province_name];
                        // Use the official state code as the key for the cities array.
                        $cities[$state_code] = $province['cities'];
                    }
                }
            }
        }

        // Return the official WC states for the dropdown and the cities keyed by official codes.
        return ['states' => $wc_states, 'cities' => $cities];
    }

    public function enqueue_assets() {
        if ( ! is_checkout() ) return;
        wp_enqueue_script( 'ccif-checkout-js', plugin_dir_url( __FILE__ ) . 'assets/js/ccif-checkout.js', ['jquery'], '6.0', true );
        wp_localize_script( 'ccif-checkout-js', 'ccifData', [ 'cities' => $this->load_iran_data()['cities'] ] );
        wp_enqueue_style( 'ccif-checkout-css', plugin_dir_url( __FILE__ ) . 'assets/css/ccif-checkout.css', [], '6.0' );
    }
}

new CCIF_Iran_Checkout_Rebuild();
