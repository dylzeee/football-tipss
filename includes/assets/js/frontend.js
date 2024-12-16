(function ($) {
    $(document).ready(function () {
        // Handle sport selection.
        $('#sport-select').on('change', function () {
            const sport = $(this).val();

            // Reset dependent fields and results when sport changes.
            $('#events-wrapper').html('');
            $('#markets-wrapper').html('');
            $('#odds-wrapper').html('');
            $('#stake-wrapper').hide();
            $('#stake-input').val('');
            $('#returns-wrapper').hide();
            $('#submit-wrapper').html('');

            if (!sport) {
                $('#events-wrapper').html('<p>Please select a league.</p>');
                return;
            }

            // Show loading message for events.
            $('#events-wrapper').html('<p>Loading events...</p>');

            // Fetch events via AJAX.
            $.post(FootballTipsAjax.ajax_url, {
                action: 'fetch_events',
                sport: sport,
            })
                .done(function (response) {
                    if (response.success) {
                        let html = '<label for="event-select">Select Event:</label>';
                        html += '<select id="event-select" name="event">';
                        html += '<option value="">Choose an event</option>';

                        response.data.events.forEach(function (event) {
                            html += `<option value="${event.id}" data-commence-time="${event.commence_time}">${event.home_team} vs ${event.away_team}</option>`;
                        });

                        html += '</select>';
                        html += '<input type="hidden" id="commence-time" name="commence_time" />'; // Hidden field for commence_time
                        $('#events-wrapper').html(html);
                    } else {
                        $('#events-wrapper').html('<p>' + response.data.message + '</p>');
                    }
                })
                .fail(function () {
                    $('#events-wrapper').html('<p>Failed to load events.</p>');
                });
        });

        // Handle event selection.
        $(document).on('change', '#event-select', function () {
            const selectedOption = $('#event-select option:selected');
            const eventId = selectedOption.val();

            // Reset dependent fields and results when event changes.
            $('#markets-wrapper').html('');
            $('#odds-wrapper').html('');
            $('#stake-wrapper').hide();
            $('#stake-input').val('');
            $('#returns-wrapper').hide();
            $('#submit-wrapper').html('');

            // Update the hidden commence_time field.
            const commenceTime = selectedOption.data('commence-time');
            $('#commence-time').val(commenceTime);

            if (!eventId) {
                $('#markets-wrapper').html('<p>Please select an event.</p>');
                return;
            }

            // Populate markets with predefined options.
            const markets = [
                { key: 'h2h', name: 'Home/Draw/Away (H2H)' },
                { key: 'draw_no_bet', name: 'Draw No Bet' },
                { key: 'double_chance', name: 'Double Chance' },
                { key: 'btts', name: 'Both Teams to Score' },
                { key: 'alternate_spreads', name: 'Asian Handicap' },
                { key: 'totals', name: 'Total Goals Over/Under' },
                { key: 'team_totals', name: 'Team Total Goals' },
                { key: 'alternate_totals', name: 'Alternate Totals' },
                { key: 'totals_h1', name: '1st Half Total Goals' },
                { key: 'totals_h2', name: '2nd Half Total Goals' },
                { key: 'player_goal_scorer_anytime', name: 'Anytime Goal Scorer' },
            ];

            let html = '<label for="market-select">Select Market:</label>';
            html += '<select id="market-select" name="market">';
            html += '<option value="">Choose a market</option>';

            markets.forEach(function (market) {
                html += `<option value="${market.key}">${market.name}</option>`;
            });

            html += '</select>';
            $('#markets-wrapper').html(html);
        });

        // Handle market selection.
        $(document).on('change', '#market-select', function () {
            const marketKey = $(this).val();
            const eventId = $('#event-select').val();
            const sportKey = $('#sport-select').val();

            // Reset dependent fields when market changes.
            $('#odds-wrapper').html('');
            $('#stake-wrapper').hide();
            $('#stake-input').val('');
            $('#returns-wrapper').hide();
            $('#submit-wrapper').html('');

            if (!marketKey) {
                $('#odds-wrapper').html('<p>Please select a market.</p>');
                return;
            }

            // Show loading message for odds.
            $('#odds-wrapper').html('<p>Loading odds...</p>');

            // Fetch odds via AJAX.
            $.post(FootballTipsAjax.ajax_url, {
                action: 'fetch_odds',
                event_id: eventId,
                sport_key: sportKey,
                market_key: marketKey,
            })
                .done(function (response) {
                    if (response.success) {
                        let html = '<label for="odds-select">Selection:</label>';
                        html += '<select id="odds-select" name="odds">';
                        html += '<option value="">Choose a selection</option>';

                        const outcomes = response.data.odds;

                        // Sort outcomes by point (numerically).
                        outcomes.sort((a, b) => {
                            const pointA = parseFloat(a.point) || 0;
                            const pointB = parseFloat(b.point) || 0;
                            return pointA - pointB;
                        });

                        // Format and populate the dropdown.
                        outcomes.forEach(function (outcome) {
                            const formattedPrice = `$${parseFloat(outcome.price).toFixed(2)}`;
                            let formattedOption;

                            if (response.data.market_name === 'team_totals' && outcome.description) {
                                const team = outcome.description;
                                const point = outcome.point ? ` ${outcome.point}` : '';
                                formattedOption = `${team} ${outcome.name} ${point} - ${formattedPrice}`;
                            } else if (response.data.market_name === 'player_goal_scorer_anytime' && outcome.description) {
                                const player = outcome.description;
                                formattedOption = `${player} - ${formattedPrice}`;
                            } else {
                                let point = outcome.point ? ` ${outcome.point}` : '';
                                if (response.data.market_name === 'alternate_spreads') {
                                    point = outcome.point > 0 ? ` +${outcome.point}` : ` ${outcome.point}`;
                                }
                                formattedOption = `${outcome.name} ${point} - ${formattedPrice}`;
                            }

                            html += `<option value="${outcome.price}">${formattedOption}</option>`;
                        });

                        html += '</select>';
                        $('#odds-wrapper').html(html);
                    } else {
                        $('#odds-wrapper').html('<p>' + response.data.message + '</p>');
                    }
                })
                .fail(function () {
                    $('#odds-wrapper').html('<p>Failed to load odds.</p>');
                });
        });

        // Handle odds selection.
        $(document).on('change', '#odds-select', function () {
            const selectedOdds = $(this).val();
            if (!selectedOdds) {
                $('#stake-wrapper').hide();
                return;
            }

            // Fetch the user's points balance.
            $.post(FootballTipsAjax.ajax_url, {
                action: 'get_user_points',
            }).done(function (response) {
                if (response.success) {
                    const userPoints = response.data.points;
                    $('#stake-wrapper').show();
                    $('#user-balance').text(`Your Points Balance: ${userPoints}`);
                } else {
                    alert(response.data.message || 'Could not fetch points balance.');
                }
            });
        });

        // Handle stake input change.
        $(document).on('input', '#stake-input', function () {
            const stake = parseFloat($(this).val());
            const odds = parseFloat($('#odds-select').val());

            if (!isNaN(stake) && stake > 0 && !isNaN(odds)) {
                const potentialReturn = stake * odds;
                const profitOrLoss = potentialReturn - stake;

                const outputHtml =
                    profitOrLoss > 0
                        ? `<div class="returns-wrapper positive">Potential Profit: $${profitOrLoss.toFixed(2)}</div>`
                        : `<div class="returns-wrapper negative">Potential Loss: $${Math.abs(profitOrLoss).toFixed(2)}</div>`;

                $('#returns-wrapper').html(outputHtml).show();
            } else {
                $('#returns-wrapper').hide();
            }

            validateForm();
        });

        // Form validation for enabling the submit button.
        function validateForm() {
            const sport = $('#sport-select').val();
            const event = $('#event-select').val();
            const market = $('#market-select').val();
            const odds = $('#odds-select').val();
            const stake = $('#stake-input').val();

            if (sport && event && market && odds && stake) {
                $('#submit-wrapper').html('<button type="button" id="submit-tip">Submit</button>');
            } else {
                $('#submit-wrapper').html('');
            }
        }

        // Handle form submission.
        $(document).on('click', '#submit-tip', function () {
            const preStake = parseInt($('#stake-input').val());
            const balance = parseInt($('#user-balance').text().replace(/\D/g, ''));

            if (preStake > balance) {
                alert('You do not have enough points to place this stake.');
                return;
            }
            const sportKey = $('#sport-select').val();
            const eventId = $('#event-select').val();
            const marketKey = $('#market-select').val();
            const odds = $('#odds-select').val();
            const stake = $('#stake-input').val();

            // Get user-friendly market name and selection from the dropdowns.
            const marketName = $('#market-select option:selected').text();
            const selection = $('#odds-select option:selected').text();
            const sport_title = $('#sport-select option:selected').text();
            const event_name = $('#event-select option:selected').text();
            const commence_time = $('#commence-time').val();

            $.post(FootballTipsAjax.ajax_url, {
                action: 'submit_tip',
                sport_key: sportKey,
                event_id: eventId,
                market_key: marketKey,
                odds: odds,
                stake: stake,
                market_name: marketName,
                selection: selection,
                sport_title: sport_title, // Get sport title
                commence_time: commence_time,
                event_name: event_name,
            })
                .done(function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Failed to submit tip: ' + response.data.message);
                    }
                })
                .fail(function () {
                    alert('An error occurred while submitting your tip.');
                });
        });

    });
})(jQuery);
