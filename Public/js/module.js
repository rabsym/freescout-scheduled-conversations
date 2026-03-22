/**
 * Scheduled Conversations Module - Frontend JavaScript
 */

(function() {
    'use strict';
    
    function initModule() {
        // Wait for jQuery to be available
        if (typeof jQuery === 'undefined') {
            setTimeout(initModule, 50);
            return;
        }
        
        jQuery(document).ready(function($) {
            
            console.log('Scheduled Conversations JS loaded');
            
            var searchTimeout = null;
            
            // Customer search functionality (without Select2)
            $('#customer_search').on('keyup', function() {
                var query = $(this).val().trim();
                
                if (query.length < 2) {
                    $('#customer_results').hide().empty();
                    return;
                }
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    var searchUrl = $('#customer_search').data('search-url');
                    
                    $.ajax({
                        url: searchUrl,
                        data: { q: query },
                        success: function(data) {
                            var $results = $('#customer_results');
                            $results.empty();
                            
                            if (data.length === 0) {
                                $results.html('<div style="padding:10px; color:#999;">No customers found</div>');
                            } else {
                                $.each(data, function(i, customer) {
                                    $results.append(
                                        $('<div>')
                                            .addClass('customer-result-item')
                                            .css({
                                                padding: '10px',
                                                cursor: 'pointer',
                                                borderBottom: '1px solid #eee'
                                            })
                                            .attr('data-id', customer.id)
                                            .attr('data-text', customer.text)
                                            .text(customer.text)
                                            .hover(
                                                function() { $(this).css('background', '#f5f5f5'); },
                                                function() { $(this).css('background', 'white'); }
                                            )
                                            .click(function() {
                                                selectCustomer(customer.id, customer.text);
                                            })
                                    );
                                });
                            }
                            
                            $results.show();
                        }
                    });
                }, 300);
            });
            
            // Select customer from search results
            function selectCustomer(id, text) {
                $('#destination_customer').val(id);
                $('#selected_customer_text').text(text);
                $('#selected_customer').show();
                $('#customer_search').val('').hide();
                $('#customer_results').hide();
            }
            
            // Clear selected customer
            $('#clear_customer').click(function() {
                $('#destination_customer').val('');
                $('#selected_customer').hide();
                $('#customer_search').val('').show();
            });
            
            // Click outside to close search results
            $(document).click(function(e) {
                if (!$(e.target).closest('#dest_customer_field').length) {
                    $('#customer_results').hide();
                }
            });
            
            // Handle destination type change (internal/customer/email)
            function updateDestinationFields() {
                var destType = $('input[name="destination_type"]:checked').val();
                
                console.log('Destination type changed to:', destType);
                
                $('#destination_value_group').hide();
                $('#dest_customer_field').hide();
                $('#dest_email_field').hide();
                
                if (destType === 'customer') {
                    $('#destination_value_group').show();
                    $('#dest_customer_field').show();
                    $('#destination_email').prop('required', false);
                } else if (destType === 'email') {
                    $('#destination_value_group').show();
                    $('#dest_email_field').show();
                    $('#destination_email').prop('required', true);
                }
            }
            
            $('input[name="destination_type"]').on('change', updateDestinationFields);
            updateDestinationFields(); // Initial call
            
            // Handle frequency type change (once/daily/weekly/monthly/yearly)
            function updateFrequencyFields() {
                var freqType = $('#frequency_type').val();
                
                console.log('Frequency type changed to:', freqType);
                
                $('.frequency-config').hide();
                $('#freq_' + freqType).show();
            }
            
            $('#frequency_type').on('change', updateFrequencyFields);
            updateFrequencyFields(); // Initial call
            
            // Adjust yearly day options based on selected month
            $('select[name="yearly_month"]').on('change', function() {
                var month = parseInt($(this).val());
                var $daySelect = $('#yearly_day');
                var currentDay = parseInt($daySelect.val());
                
                var daysInMonth = new Date(2024, month, 0).getDate();
                
                $daySelect.find('option').each(function() {
                    var day = parseInt($(this).val());
                    if (day > daysInMonth) {
                        $(this).prop('disabled', true).hide();
                    } else {
                        $(this).prop('disabled', false).show();
                    }
                });
                
                if (currentDay > daysInMonth) {
                    $daySelect.val(daysInMonth);
                }
            });
        });
    }
    
    // Start module initialization
    initModule();
})();

/**
 * Global function to initialize Summernote editor
 * Called from view after editor partials are loaded
 */

// Global lock variable to prevent double initialization
var scheduledEditorLock = false;

function initScheduledConvEditor() {
    // Abort if already initializing or already initialized
    if (scheduledEditorLock) return;
    
    var $proEditor = $('.js-pro-editor');
    var $stdEditor = $('.js-std-editor');

    // MODE 1: Extended Editor (Pro) is active
    if ($proEditor.length > 0) {
        scheduledEditorLock = true;
        console.log("Initializing Extended Editor (Pro)...");

        // Force cleanup of any previous Summernote instance
        $proEditor.summernote('destroy');

        // Initialize with manual control of toolbar
        $proEditor.summernote({
            minHeight: 300,
            dialogsInBody: true,
            lang: window.lang,
            // Custom toolbar with only desired buttons (even in Pro mode)
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['color', ['color']],       // Pro feature
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture']]
            ]
        });
    } 
    // MODE 2: Standard Summernote (no Extended Editor)
    else if ($stdEditor.length > 0) {
        scheduledEditorLock = true;
        console.log("Initializing Standard Summernote...");
        
        // Force cleanup of any previous Summernote instance
        $stdEditor.summernote('destroy');
        
        // Initialize with standard toolbar
        $stdEditor.summernote({
            minHeight: 300,
            dialogsInBody: true,
            lang: window.lang,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['insert', ['link', 'picture']]
            ]
        });
    }
}

// Clean execution on document ready
$(document).ready(function() {
    // Reset lock in case of page reload
    scheduledEditorLock = false;
    
    // Single attempt at 250ms is usually sufficient and stable
    setTimeout(initScheduledConvEditor, 250);
});

// Reset lock when navigating between tabs (FreeScout PJAX)
$(document).on('pjax:start', function() {
    scheduledEditorLock = false;
});
