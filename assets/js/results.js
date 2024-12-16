jQuery(document).ready(function ($) {
    const fetchUserResults = (page = 1) => {
        const userId = $('#profile-results-container').data('user-id');

        if (!userId) {
            console.error('User ID not found in the HTML.');
            return;
        }

        // Show spinner and hide content
        $('#profile-results-container .results-spinner').show();
        $('#profile-results-container table, .pagination').hide();

        $.ajax({
            url: FootballTipsResultsAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_user_results',
                user_id: userId,
                page: page,
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data.html;

                    // Replace table body and pagination only
                    $('#profile-results-container tbody').html($(data).find('tbody').html());
                    $('.pagination').html($(data).find('.pagination').html());

                    // Reattach pagination click handler
                    $('.pagination-button').on('click', function () {
                        const selectedPage = $(this).data('page');
                        fetchUserResults(selectedPage);
                    });

                    // Highlight the active page
                    $('.pagination-button').removeClass('active'); // Remove active class from all buttons
                    $(`.pagination-button[data-page="${page}"]`).addClass('active'); // Add active class to the current button
                } else {
                    alert('Failed to fetch tips.');
                }
            },
            error: function () {
                alert('Failed to fetch tips.');
            },
            complete: function () {
                // Hide spinner and show content
                $('#profile-results-container .results-spinner').hide();
                $('#profile-results-container table, .pagination').show();
            },
        });
    };


    // Initial fetch
    fetchUserResults();

    // Attach pagination click handler
    $(document).on('click', '.pagination-button', function () {
        const page = $(this).data('page');
        fetchUserResults(page);
    });
});
