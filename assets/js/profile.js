jQuery(document).ready(function ($) {
    const fetchUserTips = (page = 1) => {
        const userId = $('#profile-tips-container').data('user-id');

        if (!userId) {
            console.error('User ID not found in the HTML.');
            return;
        }

        // Show spinner and hide content
        $('#profile-tips-container .tips-spinner').show();
        $('#profile-tips-container table, .pagination').hide();

        $.ajax({
            url: FootballTipsProfileAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_user_tips',
                user_id: userId,
                page: page,
            },
            success: function (response) {
                if (response.success) {
                    $('#profile-tips-container').html(response.data.html);

                    // Reattach pagination click handler
                    $('.pagination-button').on('click', function () {
                        const selectedPage = $(this).data('page');
                        fetchUserTips(selectedPage);
                    });
                } else {
                    alert('Failed to fetch tips.');
                }
            },
            error: function () {
                alert('Failed to fetch tips.');
            },
            complete: function () {
                // Hide spinner and show content
                $('#profile-tips-container .tips-spinner').hide();
                $('#profile-tips-container table, .pagination').show();
            },
        });
    };

    // Initial fetch
    fetchUserTips();

    // Attach pagination click handler
    $(document).on('click', '.pagination-button', function () {
        const page = $(this).data('page');
        fetchUserTips(page);
    });
});
