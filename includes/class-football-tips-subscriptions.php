<?php

class Football_Tips_Subscriptions
{
    public static function init()
    {
        //add_action('updated_user_meta', [__CLASS__, 'create_subscription_product'], 10, 4);
        add_action('woocommerce_subscription_payment_complete', [__CLASS__, 'handle_subscription_payment']);
        add_action('woocommerce_subscription_renewal_payment_complete', [__CLASS__, 'handle_subscription_payment']);
    }

    public static function create_subscription_product($user_id)
    {
        error_log("Creating subscription product for user ID: {$user_id}");

        // Fetch necessary user meta
        $tips_visibility = get_user_meta($user_id, 'tips_visibility', true);
        $subscription_price = get_user_meta($user_id, 'tips_subscription_price', true);

        // Ensure the visibility is private and price is valid
        if ($tips_visibility !== 'private') {
            error_log("Tips visibility is not private for user ID: {$user_id}");
            return;
        }

        // Default to $10.00 if price is not set or invalid
        $subscription_price = $subscription_price && $subscription_price > 0 ? $subscription_price : 10.00;

        // Check if a product already exists
        $existing_product_id = get_user_meta($user_id, 'tips_subscription_product_id', true);
        if ($existing_product_id && get_post_status($existing_product_id) === 'publish') {
            error_log("Subscription product already exists for user ID: {$user_id}");
            return;
        }

        try {
            // Create a new subscription product
            $product = new WC_Product_Subscription();
            $product->set_name("Tips Subscription for " . get_userdata($user_id)->display_name);
            $product->set_regular_price($subscription_price);
            $product->set_status('publish');
            $product->set_description('Get access to all tips from this tipster.');
            $product->set_catalog_visibility('hidden'); // Hide from shop
            $product->set_virtual(true);

            // Set billing period and interval using meta fields
            $product->update_meta_data('_subscription_period', 'month'); // Billing period (e.g., 'day', 'week', 'month', 'year')
            $product->update_meta_data('_subscription_period_interval', 1); // Billing interval (e.g., every 1 month)

            // Set the user's avatar as the product image
            $avatar_url = bp_core_fetch_avatar([
                'item_id' => $user_id,
                'type' => 'full',
                'html' => false,
            ]);

            $attachment_id = self::upload_image_to_media_library($avatar_url, $user_id);
            if ($attachment_id) {
                $product->set_image_id($attachment_id);
            }

            // Save the product and link to the user
            $product_id = $product->save();
            if ($product_id) {
                update_post_meta($product_id, '_tipster_id', $user_id);
                update_user_meta($user_id, 'tips_subscription_product_id', $product_id);
                error_log("Successfully created subscription product (ID: {$product_id}) for user ID: {$user_id}");
            } else {
                error_log("Failed to save the subscription product for user ID: {$user_id}");
            }
        } catch (Exception $e) {
            error_log("Error creating subscription product for user ID: {$user_id}. Error: " . $e->getMessage());
        }
    }




    /**
     * Upload an external image to the WordPress media library.
     *
     * @param string $image_url URL of the image.
     * @param int $user_id User ID (used for naming the file).
     * @return int|false Attachment ID on success, false on failure.
     */
    private static function upload_image_to_media_library($image_url, $user_id)
    {
        // Get the file name from the URL
        $file_name = 'user-' . $user_id . '-avatar.jpg';
        $upload_dir = wp_upload_dir();

        // Prepare file path
        $file_path = $upload_dir['path'] . '/' . $file_name;

        // Download the image
        $image_data = file_get_contents($image_url);
        if (!$image_data) {
            return false;
        }

        // Save the image to the uploads directory
        file_put_contents($file_path, $image_data);

        // Prepare the file for WordPress media library
        $file_type = wp_check_filetype($file_name, null);
        $attachment_data = [
            'guid'           => $upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name($file_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        // Insert into the media library
        $attachment_id = wp_insert_attachment($attachment_data, $file_path);

        // Generate metadata for the attachment
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    public static function handle_subscription_payment($subscription)
    {
        // Get subscription product and tipster ID
        $items = $subscription->get_items();
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $tipster_id = get_post_meta($product_id, '_tipster_id', true); // Store tipster ID in product meta
            if (!$tipster_id) {
                continue;
            }

            // Calculate earnings after commission
            $total_amount = $subscription->get_total();
            $commission = 0.25; // 25% commission
            $net_earnings = $total_amount * (1 - $commission);

            // Credit to tipster's WooWallet
            woo_wallet()->wallet->credit(
                $tipster_id,
                $net_earnings,
                'Earnings from subscription #' . $subscription->get_id(),
                'subscription_earning'
            );
        }
    }
}

Football_Tips_Subscriptions::init();
