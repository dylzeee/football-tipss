<?php

class Football_Tips_Admin_Payouts
{
    public static function init()
    {
        add_action('admin_menu', function () {
            add_menu_page(
                'Payout Requests',
                'Payout Requests',
                'manage_options',
                'tipster-payout-requests',
                [__CLASS__, 'render_payout_requests_page'],
                'dashicons-money'
            );
        });
    }

    public static function render_payout_requests_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tipster_payout_requests';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request_id = intval($_POST['request_id']);
            $action = $_POST['action'];

            if ($action === 'approve') {
                // Mark as approved
                $wpdb->update($table_name, ['status' => 'approved'], ['id' => $request_id]);

                // Debit the user's wallet
                $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $request_id));
                woo_wallet()->wallet->debit($request->tipster_id, $request->amount, 'Payout approved', 'payout');
            } elseif ($action === 'reject') {
                // Mark as rejected
                $wpdb->update($table_name, ['status' => 'rejected'], ['id' => $request_id]);
            }
        }

        $requests = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending'");
?>
        <h1>Payout Requests</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?php echo esc_html($request->id); ?></td>
                        <td><?php echo esc_html(get_userdata($request->tipster_id)->display_name); ?></td>
                        <td><?php echo wc_price($request->amount); ?></td>
                        <td><?php echo esc_html(ucfirst($request->status)); ?></td>
                        <td><?php echo esc_html($request->request_date); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                                <button type="submit" name="action" value="approve" class="button button-primary">Approve</button>
                                <button type="submit" name="action" value="reject" class="button button-secondary">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php
    }
}

Football_Tips_Admin_Payouts::init();
