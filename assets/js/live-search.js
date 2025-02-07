jQuery(document).ready(function($) {
    let searchTimer;
    
    // Select and process search forms
    $('form[role="search"], .search-form, form.search').each(function() {
        let $form = $(this);
        let $input = $form.find('input[type="search"], input[name="s"]');
        
        // Add results container if not exists
        if (!$form.find('.live-search-results').length) {
            $form.append('<div class="live-search-results"></div>');
        }
        
        // Prevent form submission for live search
        $form.on('submit', function(e) {
            // Hide live search results before submitting
            $form.find('.live-search-results').hide();
            // Let the form submit normally - no preventDefault()
        });
        
        // Process input
        $input.not('.search-input-processed')
            .addClass('search-input-processed')
            .attr('autocomplete', 'off')
            .each(function() {
                if (!$(this).parent().hasClass('search-input-wrapper')) {
                    $(this).wrap('<div class="search-input-wrapper"></div>');
                }
            }).on('input', function() {
                clearTimeout(searchTimer);
                const $input = $(this);
                const $wrapper = $input.parent('.search-input-wrapper');
                const $form = $input.closest('form');
                const $results = $form.find('.live-search-results');
                const searchQuery = $input.val();
                
                // Add loading indicator if not exists
                if (!$wrapper.find('.live-search-loading').length) {
                    $wrapper.append('<div class="live-search-loading"></div>');
                }
                
                if (searchQuery.length < 3) {
                    $results.empty().hide();
                    $wrapper.find('.live-search-loading').hide();
                    return;
                }

                $results.hide();
                const $loadingIndicator = $wrapper.find('.live-search-loading');

                searchTimer = setTimeout(function() {
                    $loadingIndicator.show();
                    
                    $.ajax({
                        url: liveSearchData.ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'live_search',
                            s: searchQuery
                        },
                        success: function(response) {
                            $results.html(response).show();
                        }
                    }).always(function() {
                        $loadingIndicator.hide();
                    });
                }, 500);
            });
    });

    // Close results when clicking outside
    $(document).on('click', function(event) {
        if (!$(event.target).closest('form').length) {
            $('.live-search-results').hide();
        }
    });
});