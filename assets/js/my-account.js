jQuery(document).ready(function ($) {
    const fetchEarningsData = () => {
        $.ajax({
            url: FootballTipsMyAccountAjax.ajax_url,
            method: 'POST',
            data: { action: 'fetch_earnings_data' },
            success: function (response) {
                if (response.success) {
                    const data = response.data;

                    // Update earnings dashboard
                    $('#total-earnings').text(data.total_earnings.toFixed(2));
                    $('#current-month-earnings').text(data.current_month_earnings.toFixed(2));
                    $('#subscriber-count').text(data.total_subscribers);
                } else {
                    alert('Failed to fetch earnings data.');
                }
            },
            error: function () {
                alert('An error occurred while fetching earnings data.');
            },
        });
    };

    // Fetch data on page load
    fetchEarningsData();
});
