// admin_rss_fetcher.js
jQuery(document).ready(function($) {
    // Handle "Fetch Posts Now" button click
    $('#sheapgamer-rss-fetch-now').on('click', function() { // Updated ID
        var $button = $(this);
        var $resultDiv = $('#sheapgamer-rss-fetch-result'); // Updated ID
        var $logDisplay = $('#sheapgamer-rss-fetcher-log-display'); // Updated ID

        // Show loading message
        $resultDiv.removeClass('success error').addClass('loading').html('<span class="spinner is-active"></span> ' + sheapgamerRssFetcher.fetching_message);
        $button.prop('disabled', true); // Disable button during fetch

        $.ajax({
            url: sheapgamerRssFetcher.ajax_url, // Updated object name
            type: 'POST',
            data: {
                action: 'sheapgamer_rss_fetch_posts', // Updated action
                nonce: sheapgamerRssFetcher.nonce_fetch_posts // Updated object name
            },
            success: function(response) {
                // Remove loading state
                $resultDiv.removeClass('loading');

                if (response.success) {
                    $resultDiv.addClass('success').html(response.data.message);
                    // Reload logs
                    $.ajax({
                        url: sheapgamerRssFetcher.ajax_url, // Updated object name
                        type: 'POST',
                        data: {
                            action: 'sheapgamer_rss_fetcher_get_logs',
                            nonce: sheapgamerRssFetcher.nonce_get_logs // Use the correct nonce for fetching logs
                        },
                        success: function(logResponse) {
                            if (logResponse.success) {
                                $logDisplay.html(logResponse.data.logs_html);
                            } else {
                                console.error('Error fetching logs:', logResponse.data.message);
                                $resultDiv.append('<br>Error updating logs.');
                            }
                        },
                        error: function() {
                            console.error('AJAX error fetching logs.');
                            $resultDiv.append('<br>AJAX error updating logs.');
                        }
                    });

                } else {
                    $resultDiv.addClass('error').html(sheapgamerRssFetcher.fetch_error_message + ': ' + response.data.message); // Updated object name
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Remove loading state
                $resultDiv.removeClass('loading');
                $resultDiv.addClass('error').html(sheapgamerRssFetcher.fetch_error_message + ': ' + textStatus); // Updated object name
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
            },
            complete: function() {
                $button.prop('disabled', false); // Re-enable button
            }
        });
    });

    // Handle "Clear Fetcher Logs" button click
    $('#sheapgamer-rss-fetcher-clear-logs').on('click', function() { // Updated ID
        if (!confirm('Are you sure you want to clear all fetcher logs? This action cannot be undone.')) {
            return;
        }

        var $button = $(this);
        var $resultDiv = $('#sheapgamer-rss-fetch-result'); // Updated ID
        var $logDisplay = $('#sheapgamer-rss-fetcher-log-display'); // Updated ID

        $button.prop('disabled', true); // Disable button during clear
        $resultDiv.removeClass('success error loading').html('Clearing logs...'); // Add temporary message

        $.ajax({
            url: sheapgamerRssFetcher.ajax_url, // Updated object name
            type: 'POST',
            data: {
                action: 'sheapgamer_rss_fetcher_clear_logs', // Updated action
                nonce: sheapgamerRssFetcher.nonce_clear_logs // Updated object name
            },
            success: function(response) {
                if (response.success) {
                    $resultDiv.removeClass('error').addClass('success').html(response.data.message);
                    $logDisplay.html('<p>' + response.data.message + '</p>'); // Clear the log display
                } else {
                    $resultDiv.removeClass('success').addClass('error').html(response.data.message);
                }
            },
            error: function() {
                $resultDiv.removeClass('success').addClass('error').html('AJAX error clearing logs.');
            },
            complete: function() {
                $button.prop('disabled', false); // Re-enable button
            }
        });
    });
});
