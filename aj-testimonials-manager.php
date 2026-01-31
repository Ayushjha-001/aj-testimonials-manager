<?php
/**
 * Plugin Name: AJ Testimonials Manager
 * Description: Manage testimonials with ratings, shortcode display, and REST API.
 * Version: 1.0
 * Author: Aj
 */

if (!defined('ABSPATH')) exit;

// Register Custom Post Type
add_action('init', 'ajtm_register_cpt');

function ajtm_register_cpt() {
    register_post_type('aj_testimonial', [
        'labels' => [
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial'
        ],
        'public' => true,
        'menu_icon' => 'dashicons-testimonial',
        'supports' => ['title', 'editor'],
        'show_in_rest' => true
    ]);
}

// Add Meta Box
add_action('add_meta_boxes', 'ajtm_add_meta_boxes');

function ajtm_add_meta_boxes() {
    add_meta_box(
        'ajtm_meta_box',
        'Testimonial Details',
        'ajtm_render_meta_box',
        'aj_testimonial',
        'normal',
        'high'
    );
}

function ajtm_render_meta_box($post) {
    wp_nonce_field('ajtm_save_meta', 'ajtm_nonce');

    $client = get_post_meta($post->ID, '_ajtm_client', true);
    $rating = get_post_meta($post->ID, '_ajtm_rating', true);

    ?>

    <p>
        <label>Client Name:</label><br>
        <input type="text" name="ajtm_client" value="<?php echo esc_attr($client); ?>" style="width:100%;">
    </p>

    <p>
        <label>Rating (1–5):</label><br>
        <select name="ajtm_rating">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                echo '<option value="' . $i . '" ' . selected($rating, $i, false) . '>' . $i . '</option>';
            }
            ?>
        </select>
    </p>

    <?php
}

// Save Meta Box Data
add_action('save_post', 'ajtm_save_meta');

function ajtm_save_meta($post_id) {

    if (!isset($_POST['ajtm_nonce']) || !wp_verify_nonce($_POST['ajtm_nonce'], 'ajtm_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['ajtm_client'])) {
        update_post_meta($post_id, '_ajtm_client', sanitize_text_field($_POST['ajtm_client']));
    }

    if (isset($_POST['ajtm_rating'])) {
        update_post_meta($post_id, '_ajtm_rating', intval($_POST['ajtm_rating']));
    }
}

// Shortcode to display testimonials
add_shortcode('aj_testimonials', 'ajtm_render_testimonials');

function ajtm_render_testimonials($atts) {

    $args = shortcode_atts([
        'count' => 5
    ], $atts);

    $query = new WP_Query([
        'post_type' => 'aj_testimonial',
        'posts_per_page' => intval($args['count'])
    ]);

    if (!$query->have_posts()) return '<p>No testimonials found.</p>';

    ob_start();

    echo '<div class="aj-testimonials">';

    while ($query->have_posts()) {
        $query->the_post();

        $client = get_post_meta(get_the_ID(), '_ajtm_client', true);
        $rating = get_post_meta(get_the_ID(), '_ajtm_rating', true);

        echo '<div class="aj-testimonial">';
        echo '<h3>' . esc_html(get_the_title()) . '</h3>';
        echo apply_filters('the_content', get_the_content());
        echo '<strong>Client:</strong> ' . esc_html($client) . '<br>';
        echo '<strong>Rating:</strong> ' . esc_html($rating) . '/5';
        echo '</div>';
    }

    echo '</div>';

    wp_reset_postdata();

    return ob_get_clean();
}

// Register REST API route
add_action('rest_api_init', function () {

    register_rest_route('aj/v1', '/testimonials', [
        'methods'  => 'GET',
        'callback' => 'ajtm_rest_get_testimonials'
    ]);

});

function ajtm_rest_get_testimonials() {

    $query = new WP_Query([
        'post_type'      => 'aj_testimonial',
        'posts_per_page'=> 10
    ]);

    $data = [];

    while ($query->have_posts()) {
        $query->the_post();

        $data[] = [
            'id'      => get_the_ID(),
            'title'   => get_the_title(),
            'content' => apply_filters('the_content', get_the_content()),
            'client'  => get_post_meta(get_the_ID(), '_ajtm_client', true),
            'rating'  => get_post_meta(get_the_ID(), '_ajtm_rating', true),
        ];
    }

    wp_reset_postdata();

    return rest_ensure_response($data);
}
