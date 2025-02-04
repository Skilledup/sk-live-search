jQuery(document).ready(function($) {
    let searchTimer;
    
    // Target all search inputs
    $('form[role="search"] input[type="search"], .search-form input[type="search"], form.search input[name="s"]').on('input', function() {
        clearTimeout(searchTimer);
        const $input = $(this);
        const $form = $input.closest('form');
        const $results = $form.find('.live-search-results');
        const searchQuery = $input.val();
        
        if (searchQuery.length < 3) {
            $results.empty().hide();
            return;
        }

        searchTimer = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'live_search',
                    s: searchQuery
                },
                success: function(response) {
                    $results.html(response).show();
                }
            });
        }, 500);
    });

    // Close results when clicking outside
    $(document).on('click', function(event) {
        if (!$(event.target).closest('form').length) {
            $('.live-search-results').hide();
        }
    });
});