jQuery(function($) {
    'use strict';

    console.log('--- CCIF Checkout Script Loaded ---');

    // Ensure ccifData and cities exist to prevent errors
    if (typeof ccifData === 'undefined' || !ccifData.cities) {
        console.error('CCIF Iran Checkout: City data is not available.');
        return;
    }

    console.log('Received data from PHP (ccifData):', ccifData);
    var cities = ccifData.cities;
    console.log('Cities object being used:', cities);
    var requiredStar = ' <abbr class="required" title="required">*</abbr>';

    /**
     * Toggles the visibility of fields for Real vs. Legal persons.
     */
    function togglePersonFields() {
        var personType = $('#billing_person_type').val();
        var $realPersonWrapper = $('.ccif-real-person-fields-wrapper');
        var $legalPersonWrapper = $('.ccif-legal-person-fields-wrapper');

        if (personType === 'real') {
            $realPersonWrapper.show();
            $legalPersonWrapper.hide();
        } else if (personType === 'legal') {
            $legalPersonWrapper.show();
            $realPersonWrapper.hide();
        } else {
            $realPersonWrapper.hide();
            $legalPersonWrapper.hide();
        }
    }

    /**
     * Updates the 'required' status of fields based on the invoice checkbox.
     */
    function updateRequiredStatus() {
        var isInvoiceRequested = $('#billing_invoice_request').is(':checked');

        // Target all fields within the person/company box
        $('.person-info-box .form-row').each(function() {
            var $wrapper = $(this);
            var $label = $wrapper.find('label');
            var $input = $wrapper.find('input, select');

            // Set the required property on the input/select element
            $input.prop('required', isInvoiceRequested);

            // Manually add or remove the asterisk to the label for visual feedback
            if (isInvoiceRequested) {
                if ($label.find('.required').length === 0) {
                    $label.append(requiredStar);
                }
            } else {
                $label.find('.required').remove();
            }
        });

        // Trigger the WooCommerce event to update its validation state
        $(document.body).trigger('update_checkout');
    }

    /**
     * Populates the city dropdown based on the selected state.
     */
    function populateCities() {
        console.log('--- Fired populateCities() ---');
        var state = $('#billing_state').val();
        var $cityField = $('#billing_city');
        console.log('Selected state value:', state);


        // Remember the current value if it exists
        var currentCity = $cityField.val();

        $cityField.empty().append('<option value="">' + 'ابتدا استان را انتخاب کنید' + '</option>');

        if (state && cities[state]) {
            console.log('Found cities for this state:', cities[state]);
            $.each(cities[state], function(index, cityName) {
                // Create new option, select it if it matches the remembered value
                $cityField.append($('<option>', {
                    value: cityName,
                    text: cityName,
                    selected: cityName === currentCity
                }));
            });
        } else {
            console.log('Could not find cities for state "' + state + '". Please check if it exists in the ccifData.cities object.');
        }
        console.log('--- Finished populateCities() ---');
    }

    // --- Event Handlers ---
    $('body').on('change', '#billing_person_type', togglePersonFields);
    $('body').on('change', '#billing_invoice_request', updateRequiredStatus);
    $('body').on('change', '#billing_state', populateCities);

    // --- Initial Execution on Page Load ---
    togglePersonFields();
    updateRequiredStatus();

    // Populate cities on load if a state is already selected (e.g., on form validation error)
    if ($('#billing_state').val()) {
        // A small delay might be necessary if other scripts are manipulating the checkout form.
        setTimeout(populateCities, 100);
    }
});
