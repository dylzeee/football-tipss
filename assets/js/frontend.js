(function ($) {
    $(document).ready(function () {
        // Handle sport selection.
        $('#sport-select').on('change', function () {
            const sport = $(this).val();

            if (!sport) {
                $('#events-wrapper').html('<p>Please select a league.</p>');
                $('#markets-wrapper').html('');
                $('#odds-wrapper').html('');
                return;
            }

            // Show loading message for events.
            $('#events-wrapper').html('<p>Loading events...</p>');
            $('#markets-wrapper').html('');
            $('#odds-wrapper').html('');

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
                            html += `<option value="${event.id}">${event.home_team} vs ${event.away_team}</option>`;
                        });

                        html += '</select>';
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
            const eventId = $(this).val();

            if (!eventId) {
                $('#markets-wrapper').html('<p>Please select an event.</p>');
                $('#odds-wrapper').html('');
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
                // { key: 'player_first_goal_scorer', name: 'First Goal Scorer' },
                // { key: 'player_shots_on_target', name: 'Shots on Target' },
            ];

            let html = '<label for="market-select">Select Market:</label>';
            html += '<select id="market-select" name="market">';
            html += '<option value="">Choose a market</option>';

            markets.forEach(function (market) {
                html += `<option value="${market.key}">${market.name}</option>`;
            });

            html += '</select>';
            $('#markets-wrapper').html(html);
            $('#odds-wrapper').html('');
        });

        // Handle market selection.
        $(document).on('change', '#market-select', function () {
            const marketKey = $(this).val();
            const eventId = $('#event-select').val();
            const sportKey = $('#sport-select').val();

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

                        console.log("outcomes brah = ", outcomes);


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
                            console.log("Market name is ", response.data.market_name);
                            if (response.data.market_name === 'team_totals' && outcome.description) {
                                // For "team_totals", include description.name.
                                const team = outcome.description;
                                const point = outcome.point ? ` ${outcome.point}` : '';
                                formattedOption = `${team} ${outcome.name} ${point} - ${formattedPrice}`;
                            } else if (response.data.market_name === 'player_goal_scorer_anytime' && outcome.description) {
                                // For "team_totals", include description.name.
                                const player = outcome.description;
                                formattedOption = `${player} - ${formattedPrice}`;
                            } else {
                                // Default format for other markets.
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

            if (selectedOdds) {
                $('#submit-wrapper').html('<button type="button" id="submit-tip">Submit</button>');
            } else {
                $('#submit-wrapper').html('');
            }
        });

        // Handle form submission.
        $(document).on('click', '#submit-tip', function () {
            const sportKey = $('#sport-select').val();
            const eventId = $('#event-select').val();
            const marketKey = $('#market-select').val();
            const odds = $('#odds-select').val();

            // Gather event details.
            const eventDetails = {
                sport_title: $('#sport-title').data('value'),
                commence_time: $('#commence-time').data('value'),
                home_team: $('#home-team').data('value'),
                away_team: $('#away-team').data('value'),
            };

            $.post(FootballTipsAjax.ajax_url, {
                action: 'submit_tip',
                sport_key: sportKey,
                event_id: eventId,
                market_key: marketKey,
                odds: odds,
                ...eventDetails,
            })
                .done(function (response) {
                    if (response.success) {
                        alert('Tip submitted successfully!');
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
