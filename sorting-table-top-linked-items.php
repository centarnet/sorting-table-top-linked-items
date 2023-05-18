<?php
/**
 * Plugin Name: Sorting Table for Top Linked Items
 * Plugin URI: http://www.web4y.com
 * Description: A plugin that adds sorting functionality to tables for top linked items.
 * Version: 1.0
 * Author: Goran Zoric
 * Author URI: http://www.web4y.com
 * License: GPL2
 */

// Aktivacija plugin-a
function sorting_table_activate() {
    // Kreiranje tablice u bazi podataka
    global $wpdb;
    $table_name = $wpdb->prefix . 'sorting_table';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        post_id INT(11) NOT NULL,
        likes INT(11) NOT NULL DEFAULT 0,
        dislikes INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'sorting_table_activate');

// Deaktivacija plugin-a
function sorting_table_deactivate() {
    // Brisanje tablice iz baze podataka
    global $wpdb;
    $table_name = $wpdb->prefix . 'sorting_table';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_deactivation_hook(__FILE__, 'sorting_table_deactivate');

// Funkcija za prikazivanje like i dislike dugmadi te brojača
function sorting_table_display_buttons($column, $post_id) {
    if ($column === 'sorting_table_likes') {
        $likes = get_post_meta($post_id, 'likes', true);
        $dislikes = get_post_meta($post_id, 'dislikes', true);
        $current_user = wp_get_current_user();

        $like_class = 'sorting-table-like';
        $dislike_class = 'sorting-table-dislike';

        // Provjera je li korisnik već likeao ili dislikeao
        if (is_user_logged_in() && get_user_meta($current_user->ID, 'sorting_table_liked_' . $post_id, true)) {
            $like_class .= ' active';
            $dislike_class .= ' disabled';
        } elseif (is_user_logged_in() && get_user_meta($current_user->ID, 'sorting_table_disliked_' . $post_id, true)) {
            $like_class .= ' disabled';
            $dislike_class .= ' active';
        }

        // Ispisivanje like i dislike dugmadi te brojača
        echo '<div class="sorting-table-buttons">';
        echo '<button class="' . esc_attr($like_class) . '" data-post-id="' . esc_attr($post_id) . '"><img src="' . esc_attr(plugin_dir_url(__FILE__) . 'assets/images/like.png') . '" alt="Like"></button>';
        echo '<span class="sorting-table-likes">' . esc_html($likes) . '</span>';
        echo '<button class="' . esc_attr($dislike_class) . '" data-post-id="' . esc_attr($post_id) . '"><img src="' . esc_attr(plugin_dir_url(__FILE__) . 'assets/images/dislike.png') . '" alt="Dislike"></button>';
        echo '<span class="sorting-table-dislikes">' . esc_html($dislikes) . '</span>';
        echo '</div>';
    }
}

// Učitavanje skripte za rukovanje klikovima na dugmad
function sorting_table_enqueue_scripts() {
    wp_enqueue_script('sorting-table-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'sorting_table_enqueue_scripts');

// Umetanje like i dislike dugmadi te brojača u zadnji stupac tablice
function sorting_table_add_buttons($column, $post_id) {
    sorting_table_display_buttons($column, $post_id);
}
add_action('manage_posts_custom_column', 'sorting_table_add_buttons', 10, 2);
add_action('manage_pages_custom_column', 'sorting_table_add_buttons', 10, 2);

// Obrada AJAX zahtjeva za like i dislike akcije
function sorting_table_handle_ajax() {
    $response = array();

    if (isset($_POST['action']) && $_POST['action'] === 'sorting_table_like_dislike') {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $action = isset($_POST['action_type']) ? $_POST['action_type'] : '';
        $current_user = wp_get_current_user();

        if ($post_id && ($action === 'like' || $action === 'dislike') && is_user_logged_in()) {
            $user_id = $current_user->ID;

            $like_meta_key = 'sorting_table_liked_' . $post_id;
            $dislike_meta_key = 'sorting_table_disliked_' . $post_id;

            $liked = get_user_meta($user_id, $like_meta_key, true);
            $disliked = get_user_meta($user_id, $dislike_meta_key, true);

            if ($action === 'like') {
                if (!$liked) {
                    // Povećanje broja likeova za post
                    $likes = get_post_meta($post_id, 'likes', true);
                    update_post_meta($post_id, 'likes', $likes + 1);

                    // Označavanje da je korisnik likeao post
                    update_user_meta($user_id, $like_meta_key, 1);

                    // Uklanjanje označavanja da je korisnik dislikeao post, ako je prethodno dislikeao
                    if ($disliked) {
                        update_user_meta($user_id, $dislike_meta_key, 0);
                    }

                    $response['success'] = true;
                    $response['message'] = 'Post liked.';
                } else {
                    $response['success'] = false;
                    $response['message'] = 'You have already liked this post.';
                }
            } elseif ($action === 'dislike') {
                if (!$disliked) {
                    // Povećanje broja dislikeova za post
                    $dislikes = get_post_meta($post_id, 'dislikes', true);
                    update_post_meta($post_id, 'dislikes', $dislikes + 1);

                    // Označavanje da je korisnik dislikeao post
                    update_user_meta($user_id, $dislike_meta_key, 1);

                    // Uklanjanje označavanja da je korisnik likeao post, ako je prethodno likeao
                    if ($liked) {
                        update_user_meta($user_id, $like_meta_key, 0);
                    }

                    $response['success'] = true;
                    $response['message'] = 'Post disliked.';
                } else {
                    $response['success'] = false;
                    $response['message'] = 'You have already disliked this post.';
                }
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'Invalid request.';
        }
    }

    // Slanje JSON odgovora
    wp_send_json($response);
}
add_action('wp_ajax_sorting_table_like_dislike', 'sorting_table_handle_ajax');
add_action('wp_ajax_nopriv_sorting_table_like_dislike', 'sorting_table_handle_ajax');
