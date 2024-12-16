jQuery(document).ready(function ($) {
    const fetchAvailableBalance = () => {
        // Fetch the user's available balance via AJAX
        $.ajax({
            url: FootballTipsPayoutsAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_earnings_data',
            },
            success: function (response) {
                if (response.success) {
                    $('#current-balance').text(response.data.available_balance.toFixed(2));
                } else {
                    alert(response.data.message || 'Error fetching balance.');
                }
            },
        });
    };

    $('#submit-payout-request').on('click', function () {
        const amount = $('#payout-amount').val();

        if (!amount || amount <= 0) {
            alert('Please enter a valid payout amount.');
            return;
        }

        $.ajax({
            url: FootballTipsPayoutsAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'request_payout',
                amount: amount,
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    fetchAvailableBalance(); // Refresh balance
                    // Optionally refresh the table
                } else {
                    alert(response.data.message || 'Error processing request.');
                }
            },
        });
    });

    fetchAvailableBalance();
});
