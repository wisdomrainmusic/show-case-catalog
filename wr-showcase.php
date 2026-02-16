<?php
/*
Plugin Name: WR Showcase
Text Domain: wr-showcase
Description: Demo showcase system with category support and landing page shortcode.
Version: 1.0.0
Author: WisdomRain
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register Custom Post Type
 */
function wr_showcase_register_post_type() {

    register_post_type('wr_showcase', [
        'labels' => [
            'name' => 'Showcase Items',
            'singular_name' => 'Showcase Item',
        ],
        'public' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'showcase-item'],
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'wr_showcase_register_post_type');


/**
 * Register Taxonomy
 */
function wr_showcase_register_taxonomy() {

    register_taxonomy('wr_showcase_cat', 'wr_showcase', [
        'labels' => [
            'name' => 'Showcase Categories',
            'singular_name' => 'Showcase Category',
        ],
        'hierarchical' => true,
        'public' => true,
        'rewrite' => ['slug' => 'showcase-category'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'wr_showcase_register_taxonomy');

/**
 * Admin: Meta boxes for Showcase Item
 */
function wr_showcase_add_meta_boxes() {
    add_meta_box(
        'wr_showcase_media_box',
        'Showcase Media',
        'wr_showcase_render_media_box',
        'wr_showcase',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'wr_showcase_add_meta_boxes');

function wr_showcase_render_media_box($post) {
    wp_nonce_field('wr_showcase_save_meta', 'wr_showcase_meta_nonce');

    $demo_url = get_post_meta($post->ID, '_wr_demo_url', true);
    $gallery  = get_post_meta($post->ID, '_wr_gallery_ids', true); // comma separated IDs

    ?>
    <p>
        <label><strong>Demo URL</strong></label><br>
        <input type="url" name="wr_demo_url" value="<?php echo esc_attr($demo_url); ?>" style="width:100%;" placeholder="https://demo-site.com/">
    </p>

    <p>
        <label><strong>Gallery Images (up to 5)</strong></label>
    </p>

    <div id="wr-gallery-preview" style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php
        if (!empty($gallery)) {
            $ids = array_filter(array_map('absint', explode(',', $gallery)));
            foreach ($ids as $id) {
                $thumb = wp_get_attachment_image($id, 'thumbnail');
                if ($thumb) {
                    echo '<div class="wr-thumb" data-id="' . esc_attr($id) . '">' . $thumb . '</div>';
                }
            }
        }
        ?>
    </div>

    <input type="hidden" id="wr_gallery_ids" name="wr_gallery_ids" value="<?php echo esc_attr($gallery); ?>">
    <p style="margin-top:10px;">
        <button type="button" class="button" id="wr-gallery-upload">Select Images</button>
        <button type="button" class="button" id="wr-gallery-clear">Clear</button>
        <span style="margin-left:10px;color:#666;">(Choose up to 5 images)</span>
    </p>
    <?php
}

/**
 * Save meta
 */
function wr_showcase_save_meta($post_id) {
    if (!isset($_POST['wr_showcase_meta_nonce']) || !wp_verify_nonce($_POST['wr_showcase_meta_nonce'], 'wr_showcase_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['wr_demo_url'])) {
        update_post_meta($post_id, '_wr_demo_url', esc_url_raw($_POST['wr_demo_url']));
    }

    if (isset($_POST['wr_gallery_ids'])) {
        // enforce max 5 images
        $ids = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['wr_gallery_ids']))));
        $ids = array_slice($ids, 0, 5);
        update_post_meta($post_id, '_wr_gallery_ids', implode(',', $ids));
    }
}
add_action('save_post_wr_showcase', 'wr_showcase_save_meta');

/**
 * Admin scripts for media uploader
 */
function wr_showcase_admin_assets($hook) {
    global $post;

    if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post->post_type) && $post->post_type === 'wr_showcase') {
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', wr_showcase_admin_js());
    }
}
add_action('admin_enqueue_scripts', 'wr_showcase_admin_assets');

function wr_showcase_admin_js() {
    return <<<JS
jQuery(function($){
    let frame;

    function renderPreview(ids){
        const preview = $('#wr-gallery-preview');
        preview.empty();
        if(!ids.length) return;
        ids.forEach(function(id){
            wp.media.attachment(id).fetch().then(function(){
                const url = wp.media.attachment(id).get('sizes')?.thumbnail?.url || wp.media.attachment(id).get('url');
                preview.append('<div class="wr-thumb" data-id="'+id+'"><img src="'+url+'" style="width:90px;height:auto;border:1px solid #ddd;border-radius:6px;"></div>');
            });
        });
    }

    $('#wr-gallery-upload').on('click', function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }

        frame = wp.media({
            title: 'Select up to 5 images',
            button: { text: 'Use these images' },
            multiple: true
        });

        frame.on('select', function(){
            const selection = frame.state().get('selection').toArray();
            const ids = selection.map(att => att.id).slice(0,5);
            $('#wr_gallery_ids').val(ids.join(','));
            renderPreview(ids);
        });

        frame.open();
    });

    $('#wr-gallery-clear').on('click', function(){
        $('#wr_gallery_ids').val('');
        $('#wr-gallery-preview').empty();
    });

    // initial
    const initial = ($('#wr_gallery_ids').val() || '').split(',').filter(Boolean).map(Number);
    if(initial.length){ renderPreview(initial); }
});
JS;
}

/**
 * Frontend assets (CSS + JS)
 */
function wr_showcase_frontend_assets() {
    // Basic CSS
    $css = <<<CSS
.wr-showcase-grid{
  display:grid;
  gap:24px;
}
.wr-showcase-grid.columns-2{ grid-template-columns:repeat(2,minmax(0,1fr)); }
.wr-showcase-grid.columns-3{ grid-template-columns:repeat(3,minmax(0,1fr)); }
.wr-showcase-grid.columns-4{ grid-template-columns:repeat(4,minmax(0,1fr)); }
@media (max-width: 1024px){
  .wr-showcase-grid.columns-4{ grid-template-columns:repeat(2,minmax(0,1fr)); }
  .wr-showcase-grid.columns-3{ grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 640px){
  .wr-showcase-grid.columns-4,
  .wr-showcase-grid.columns-3,
  .wr-showcase-grid.columns-2{ grid-template-columns:repeat(1,minmax(0,1fr)); }
}
.wr-showcase-card{
  border:1px solid rgba(0,0,0,.08);
  border-radius:18px;
  overflow:hidden;
  background:#fff;
  box-shadow:0 6px 18px rgba(0,0,0,.06);
}
.wr-showcase-media{
  position:relative;
  aspect-ratio: 16 / 10;
  background:#f3f4f6;
  overflow:hidden;
}
.wr-showcase-media img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.wr-showcase-body{
  padding:18px 18px 20px;
}
.wr-showcase-card h3{
  margin:0 0 8px;
  font-size:22px;
  line-height:1.15;
}
.wr-showcase-excerpt{
  opacity:.8;
  margin:0 0 16px;
}
.wr-showcase-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:100%;
  padding:12px 16px;
  border-radius:999px;
  text-decoration:none;
  background:#111;
  color:#fff;
  font-weight:600;
}
.wr-showcase-btn:hover{ opacity:.92; }
.wr-badge{
  position:absolute;
  left:14px;
  top:14px;
  background:rgba(17,17,17,.85);
  color:#fff;
  padding:8px 12px;
  border-radius:999px;
  font-size:13px;
  font-weight:700;
  letter-spacing:.2px;
}
CSS;

    // Basic JS: rotate gallery images on hover
    $js = <<<JS
(function(){
  function parseImages(el){
    try {
      var raw = el.getAttribute('data-images') || '[]';
      var arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr.filter(Boolean) : [];
    } catch(e){ return []; }
  }

  function initCard(card){
    var media = card.querySelector('.wr-showcase-media');
    if(!media) return;
    var img = media.querySelector('img.wr-showcase-main');
    if(!img) return;

    var images = parseImages(media);
    if(images.length < 2) return;

    var idx = 0;
    var timer = null;
    var first = images[0];

    function show(i){
      idx = i % images.length;
      img.src = images[idx];
    }

    function start(){
      if(timer) return;
      timer = setInterval(function(){
        show(idx + 1);
      }, 1200);
    }

    function stop(){
      if(timer){
        clearInterval(timer);
        timer = null;
      }
      img.src = first;
      idx = 0;
    }

    card.addEventListener('mouseenter', start);
    card.addEventListener('mouseleave', stop);
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.wr-showcase-card').forEach(initCard);
  });
})();
JS;

    // Enqueue inline (no extra files)
    wp_register_style('wr-showcase-inline', false, [], '1.0.0');
    wp_enqueue_style('wr-showcase-inline');
    wp_add_inline_style('wr-showcase-inline', $css);

    wp_register_script('wr-showcase-inline', '', [], '1.0.0', true);
    wp_enqueue_script('wr-showcase-inline');
    wp_add_inline_script('wr-showcase-inline', $js);
}
add_action('wp_enqueue_scripts', 'wr_showcase_frontend_assets');

/**
 * Default Categories
 */
function wr_showcase_default_categories() {

    $categories = [
        'Kadın Giyim',
        'Erkek Giyim',
        'Kadın Ayakkabı',
        'Erkek Ayakkabı',
        'Anne & Bebek',
        'Petshop',
        'Elektronik',
        'Spor & Outdoor',
        'Kozmetik',
        'Ev & Yaşam',
        'Saat & Aksesuar',
        'Takı & Mücevher',
        'Avukat & Hukuk',
        'Doktor / Klinik',
        'Psikolog & Danışman',
        'Mimarlık & İnşaat',
        'Estetik Klinik',
        'Gayrimenkul',
        'Restoran & Kafe'
    ];

    foreach ($categories as $cat) {

        if ( ! term_exists($cat, 'wr_showcase_cat') ) {
            wp_insert_term($cat, 'wr_showcase_cat');
        }
    }
}


/**
 * Plugin Activation Hook
 */
function wr_showcase_activate() {

    wr_showcase_register_post_type();
    wr_showcase_register_taxonomy();
    flush_rewrite_rules();
    wr_showcase_default_categories();
}
register_activation_hook(__FILE__, 'wr_showcase_activate');


/**
 * Basic Shortcode
 * Usage:
 * [wr_showcase]
 * [wr_showcase category="kadin-giyim"]
 */
function wr_showcase_shortcode($atts) {

    $atts = shortcode_atts([
        'category' => '',
        'columns' => 3,
    ], $atts);

    $args = [
        'post_type' => 'wr_showcase',
        'posts_per_page' => -1,
    ];

    if (!empty($atts['category'])) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'wr_showcase_cat',
                'field' => 'slug',
                'terms' => $atts['category'],
            ]
        ];
    }

    $query = new WP_Query($args);

    ob_start();

    $cols = max(1, min(6, (int) ($atts['columns'] ?? 3)));
    echo '<div class="wr-showcase-grid columns-' . esc_attr($cols) . '">';

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();

            echo '<div class="wr-showcase-card">';

            // Build image list (featured + gallery up to 5)
            $images = [];
            if (has_post_thumbnail()) {
                $images[] = get_the_post_thumbnail_url(get_the_ID(), 'large');
            }
            $gallery = get_post_meta(get_the_ID(), '_wr_gallery_ids', true);
            if (!empty($gallery)) {
                $ids = array_filter(array_map('absint', explode(',', $gallery)));
                foreach ($ids as $id) {
                    $url = wp_get_attachment_image_url($id, 'large');
                    if ($url) {
                        $images[] = $url;
                    }
                }
            }
            // De-dup and cap
            $images = array_values(array_unique(array_filter($images)));
            $main = !empty($images[0]) ? $images[0] : '';

            echo '<div class="wr-showcase-media" data-images="' . esc_attr(wp_json_encode($images)) . '">';
            if ($main) {
                echo '<img class="wr-showcase-main" src="' . esc_url($main) . '" alt="' . esc_attr(get_the_title()) . '">';
            } else {
                echo '<img class="wr-showcase-main" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="">';
            }
            echo '</div>';

            echo '<div class="wr-showcase-body">';
            echo '<h3>' . get_the_title() . '</h3>';
            echo '<div class="wr-showcase-excerpt">' . get_the_excerpt() . '</div>';
            echo '<a class="wr-showcase-btn" href="' . get_permalink() . '">Canlı Önizle</a>';
            echo '</div>';
            echo '</div>';

        endwhile;
        wp_reset_postdata();
    else :
        echo '<p>No showcase items found.</p>';
    endif;

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('wr_showcase', 'wr_showcase_shortcode');
