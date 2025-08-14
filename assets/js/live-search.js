jQuery(document).ready(function ($) {
    let searchTimer;
    let activeSearchInput = null;
    let selectedResultIndex = -1;

    // Generate unique IDs for ARIA relationships
    function generateUniqueId(prefix) {
        return prefix + '-' + Math.random().toString(36).substr(2, 9);
    }

    // Update ARIA attributes based on results state
    function updateARIAAttributes($input, $results, hasResults, isExpanded) {
        const resultsId = $results.attr('id');
        
        // Set basic attributes
        $input.attr('aria-expanded', isExpanded ? 'true' : 'false');
        
        // Only set aria-owns when we actually have results
        if (hasResults && isExpanded) {
            $input.attr('aria-owns', resultsId);
        } else {
            $input.removeAttr('aria-owns');
        }
        
        // Only set aria-activedescendant when we have a valid selection
        if (selectedResultIndex >= 0 && hasResults && isExpanded) {
            const selectedId = $results.find('[data-result-index="' + (selectedResultIndex + 1) + '"]').attr('id');
            if (selectedId) {
                $input.attr('aria-activedescendant', selectedId);
            } else {
                $input.removeAttr('aria-activedescendant');
            }
        } else {
            $input.removeAttr('aria-activedescendant');
        }
        
        // Set aria-live on results container when needed
        if (hasResults && isExpanded) {
            $results.attr('aria-live', 'polite');
        } else {
            $results.removeAttr('aria-live');
        }
    }

    // Navigate through search results with keyboard
    function navigateResults($input, $results, direction) {
        const $resultItems = $results.find('[role="option"]');
        const totalResults = $resultItems.length;
        
        if (totalResults === 0) return;

        // Remove previous selection
        $resultItems.removeClass('live-search-selected').attr('aria-selected', 'false');

        if (direction === 'down') {
            selectedResultIndex = selectedResultIndex >= totalResults - 1 ? 0 : selectedResultIndex + 1;
        } else if (direction === 'up') {
            selectedResultIndex = selectedResultIndex <= 0 ? totalResults - 1 : selectedResultIndex - 1;
        }

        // Apply selection to new item
        const $selectedItem = $resultItems.eq(selectedResultIndex);
        $selectedItem.addClass('live-search-selected').attr('aria-selected', 'true');

        // Update aria-activedescendant
        const selectedId = $selectedItem.attr('id') || 'result-' + (selectedResultIndex + 1);
        $selectedItem.attr('id', selectedId);
        $input.attr('aria-activedescendant', selectedId);

        // Scroll selected item into view
        if ($selectedItem.length) {
            const resultsContainer = $results[0];
            const selectedElement = $selectedItem[0];
            
            if (selectedElement.offsetTop < resultsContainer.scrollTop) {
                resultsContainer.scrollTop = selectedElement.offsetTop;
            } else if (selectedElement.offsetTop + selectedElement.offsetHeight > 
                      resultsContainer.scrollTop + resultsContainer.offsetHeight) {
                resultsContainer.scrollTop = selectedElement.offsetTop + selectedElement.offsetHeight - resultsContainer.offsetHeight;
            }
        }
    }

    // Activate selected result (navigate to link)
    function activateSelectedResult($results) {
        const $selectedItem = $results.find('.live-search-selected');
        if ($selectedItem.length) {
            const $link = $selectedItem.find('a');
            if ($link.length) {
                // Trigger click on the link
                window.location.href = $link.attr('href');
            }
        }
    }

    // Close results and reset state
    function closeResults($input, $results) {
        $results.hide().empty();
        selectedResultIndex = -1;
        updateARIAAttributes($input, $results, false, false);
        activeSearchInput = null;
    }

    // Select and process search forms
    $('form[role="search"], .search-form, form.search').each(function () {
        let $form = $(this);
        let $input = $form.find('input[type="search"], input[name="s"]');

        // Add results container if not exists
        if (!$form.find('.live-search-results').length) {
            $form.append('<div class="live-search-results"></div>');
        }

        const $results = $form.find('.live-search-results');
        const resultsId = generateUniqueId('live-search-results');
        const inputId = $input.attr('id') || generateUniqueId('live-search-input');
        
        // Set up ARIA attributes
        $input.attr('id', inputId);
        $results.attr({
            'id': resultsId,
            'role': 'listbox',
            'aria-label': 'Search suggestions'
        });

        // Prevent form submission for live search
        $form.on('submit', function (e) {
            // If a result is selected, activate it instead of submitting
            if (selectedResultIndex >= 0 && $results.is(':visible')) {
                e.preventDefault();
                activateSelectedResult($results);
                return;
            }
            // Hide live search results before submitting
            closeResults($input, $results);
        });

        // Process input
        $input.not('.search-input-processed')
            .addClass('search-input-processed')
            .attr({
                'autocomplete': 'off',
                'role': 'combobox',
                'aria-autocomplete': 'list',
                'aria-expanded': 'false',
                'aria-haspopup': 'listbox'
            })
            .each(function () {
                if (!$(this).parent().hasClass('search-input-wrapper')) {
                    $(this).wrap('<div class="search-input-wrapper"></div>');
                }
            })
            .on('input', function () {
                clearTimeout(searchTimer);
                const $input = $(this);
                const $wrapper = $input.parent('.search-input-wrapper');
                const $form = $input.closest('form');
                const $results = $form.find('.live-search-results');
                const searchQuery = $input.val();

                activeSearchInput = $input;
                selectedResultIndex = -1;

                // Add loading indicator if not exists
                if (!$wrapper.find('.live-search-loading').length) {
                    $wrapper.append('<div class="live-search-loading" aria-hidden="true"></div>');
                }

                if (searchQuery.length < 3) {
                    closeResults($input, $results);
                    $wrapper.find('.live-search-loading').hide();
                    return;
                }

                $results.hide();
                const $loadingIndicator = $wrapper.find('.live-search-loading');

                searchTimer = setTimeout(function () {
                    $loadingIndicator.show();

                    $.ajax({
                        url: liveSearchData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'live_search',
                            s: searchQuery,
                            nonce: liveSearchData.nonce
                        },
                        success: function (response) {
                            $results.html(response);
                            const hasResults = $results.find('[role="option"]').length > 0;
                            
                            if (hasResults) {
                                $results.show();
                                updateARIAAttributes($input, $results, true, true);
                                
                                // Announce results to screen readers
                                const resultCount = $results.find('[role="option"]').length;
                                const announcement = resultCount === 1 ? 
                                    '1 suggestion available' : 
                                    resultCount + ' suggestions available';
                                
                                // Create or update announcement element
                                let $announcement = $('#live-search-announcement');
                                if (!$announcement.length) {
                                    $announcement = $('<div id="live-search-announcement" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
                                    $('body').append($announcement);
                                }
                                $announcement.text(announcement);
                            } else {
                                updateARIAAttributes($input, $results, false, false);
                                $results.show(); // Still show "no results" message
                            }
                        }
                    }).always(function () {
                        $loadingIndicator.hide();
                    });
                }, 500);
            })
            .on('keydown', function (e) {
                const $input = $(this);
                const $form = $input.closest('form');
                const $results = $form.find('.live-search-results');

                if (!$results.is(':visible') || $results.find('[role="option"]').length === 0) {
                    return;
                }

                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        navigateResults($input, $results, 'down');
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        navigateResults($input, $results, 'up');
                        break;
                    case 'Enter':
                        if (selectedResultIndex >= 0) {
                            e.preventDefault();
                            activateSelectedResult($results);
                        }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        closeResults($input, $results);
                        break;
                    case 'Tab':
                        // Close results when tabbing away
                        setTimeout(function () {
                            if (!$input.is(':focus')) {
                                closeResults($input, $results);
                            }
                        }, 0);
                        break;
                }
            })
            .on('focus', function () {
                activeSearchInput = $(this);
            })
            .on('blur', function () {
                const $input = $(this);
                // Delay closing to allow for result clicks
                setTimeout(function () {
                    if (activeSearchInput && activeSearchInput[0] === $input[0]) {
                        const $results = $input.closest('form').find('.live-search-results');
                        closeResults($input, $results);
                    }
                }, 150);
            });
    });

    // Handle clicks on search results
    $(document).on('click', '.live-search-result, .live-search-more-results', function (e) {
        e.preventDefault();
        const $link = $(this).find('a');
        if ($link.length) {
            window.location.href = $link.attr('href');
        }
    });

    // Close results when clicking outside
    $(document).on('click', function (event) {
        if (!$(event.target).closest('form').length && !$(event.target).closest('.live-search-results').length) {
            $('.live-search-results').hide();
            selectedResultIndex = -1;
            activeSearchInput = null;
        }
    });
});