<?php
/*
Plugin Name: WR Showcase
Text Domain: wr-showcase
Description: Demo showcase system with category support and landing page shortcode.
Version: 1.0.0
Author: WisdomRain
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('WR_SHOWCASE_DIR', plugin_dir_path(__FILE__));

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
.wr-showcase-wrap{ width:100%; max-width:1200px; margin:0 auto; padding:0 12px; }
.wr-showcase-wrap *{ box-sizing:border-box; }
.wr-showcase-grid{
  display:grid;
  gap:24px;
  justify-content:center;
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

/* Ensure cards don't stretch weirdly in some themes */
.wr-showcase-grid > .wr-showcase-card{ width:100%; }
.wr-showcase-card{
  border:1px solid rgba(0,0,0,.08);
  border-radius:18px;
  overflow:hidden;
  background:#fff;
  box-shadow:0 6px 18px rgba(0,0,0,.06);
  display:flex;
  flex-direction:column;
  min-width:0;
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
.wr-showcase-imgbtn{
  position:absolute;
  left:14px;
  top:14px;
  z-index:3;
  background:rgba(17,17,17,.86);
  color:#fff;
  border:0;
  border-radius:999px;
  padding:10px 14px;
  font-weight:800;
  letter-spacing:.2px;
  cursor:pointer;
}
.wr-showcase-imgbtn:hover{ opacity:.92; }
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

/* Tabs */
.wr-showcase-toolbar{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
  justify-content:space-between;
  margin:0 0 16px;
}
.wr-showcase-tabs{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
}
.wr-tab{
  /* theme overrides fix */
  appearance:none;
  -webkit-appearance:none;
  border:1px solid rgba(0,0,0,.18) !important;
  background:#fff !important;
  color:#111 !important;
  padding:10px 14px;
  border-radius:999px;
  cursor:pointer;
  font-weight:700;
  line-height:1;
  opacity:1 !important;
}
.wr-tab.is-active{
  background:#111 !important;
  color:#fff !important;
  border-color:#111 !important;
}
.wr-showcase-search{
  display:flex;
  gap:10px;
  align-items:center;
}
.wr-showcase-search input{
  width:min(360px, 70vw);
  padding:10px 14px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.18) !important;
  background:#fff !important;
  color:#111 !important;
}

/* Modal (Image viewer) */
.wr-modal{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.72);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:999999;
  padding:22px;
}
.wr-modal.is-open{ display:flex; }
.wr-modal-inner{
  width:min(1100px, 96vw);
  background:#111;
  border-radius:18px;
  overflow:hidden;
  position:relative;
  box-shadow:0 18px 60px rgba(0,0,0,.35);
}
.wr-modal-imgwrap{
  background:#000;
  aspect-ratio:16/9;
  display:flex;
  align-items:center;
  justify-content:center;
}
.wr-modal-imgwrap img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}
.wr-modal-close{
  position:absolute;
  top:10px;
  right:10px;
  background:rgba(255,255,255,.12);
  color:#fff;
  border:0;
  border-radius:999px;
  width:42px;
  height:42px;
  cursor:pointer;
  font-size:18px;
  opacity:1 !important;
}
.wr-modal-close:hover{
  background:rgba(255,255,255,.18);
}
.wr-modal-nav{
  position:absolute;
  top:50%;
  transform:translateY(-50%);
  background:rgba(255,255,255,.22) !important;
  color:#fff !important;
  border:0;
  border-radius:999px;
  width:54px;
  height:54px;
  cursor:pointer;
  font-size:20px;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:1 !important;
  visibility:visible !important;
  pointer-events:auto !important;
  text-shadow:0 1px 2px rgba(0,0,0,.6);
}
.wr-modal-nav:hover{ background:rgba(255,255,255,.30) !important; }
.wr-modal-prev{ left:12px; }
.wr-modal-next{ right:12px; }
.wr-modal-title{
  padding:14px 16px;
  color:#fff;
  font-weight:800;
  background:rgba(0,0,0,.35);
}

/* Landing (single showcase) */
.wr-showcase-landing{ width:100%; }
.wr-showcase-landing-inner{
  width:min(1100px, 92vw);
  margin:0 auto;
  padding:36px 0 60px;
}
.wr-showcase-landing-head{
  text-align:left;
  margin:0 0 22px;
}
.wr-showcase-landing-title{
  margin:0 0 10px;
  font-size:40px;
  line-height:1.1;
}
.wr-showcase-landing-desc{
  margin:0 0 16px;
  opacity:.75;
  font-size:16px;
}
.wr-showcase-landing-cta{
  position:sticky;
  top:18px;
  z-index:50;
  display:flex;
  justify-content:flex-start;
  margin:18px 0 10px;
}
.wr-showcase-landing-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:12px 16px;
  border-radius:999px;
  background:#111;
  color:#fff;
  text-decoration:none;
  font-weight:800;
}
.wr-showcase-landing-btn:hover{ opacity:.92; }
.wr-showcase-landing-gallery{ display:flex; flex-direction:column; gap:16px; }
.wr-shot{
  border-radius:18px;
  overflow:hidden;
  background:#f3f4f6;
  border:1px solid rgba(0,0,0,.08);
  box-shadow:0 12px 30px rgba(0,0,0,.08);
}
.wr-shot img{
  width:100%;
  height:auto;
  display:block;
}

/* FULL-WIDTH mode: images go edge-to-edge, no theme background showing */
.wr-showcase-landing.wr-mode-full .wr-showcase-landing-inner{
  width:100%;
  max-width:none;
  margin:0;
  padding:28px 0 60px;
}
/* Keep header boxed for readability */
.wr-showcase-landing.wr-mode-full .wr-showcase-landing-head{
  width:min(1100px, 92vw);
  margin:0 auto 18px;
  padding:0 12px;
}
/* Gallery becomes full width */
.wr-showcase-landing.wr-mode-full .wr-showcase-landing-gallery{
  width:100%;
  gap:0;
}
/* Each screenshot spans full viewport width */
.wr-showcase-landing.wr-mode-full .wr-shot{
  border-radius:0;
  border:0;
  box-shadow:none;
  background:#000;
}
/* True edge-to-edge trick even inside theme containers */
.wr-showcase-landing.wr-mode-full .wr-shot{
  width:100vw;
  margin-left:calc(50% - 50vw);
  margin-right:calc(50% - 50vw);
}
.wr-showcase-landing.wr-mode-full .wr-shot img{
  width:100%;
  height:auto;
  display:block;
}
CSS;

    // JS: hover rotate + tabs + search + modal gallery
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

  // ---------- Modal gallery ----------
  var modal = null;
  var modalImg = null;
  var modalTitle = null;
  var modalImages = [];
  var modalIndex = 0;

  function ensureModal(){
    if(modal) return;
    modal = document.getElementById('wr-showcase-modal');
    if(!modal) return;
    modalImg = modal.querySelector('img[data-wr-modal-img]');
    modalTitle = modal.querySelector('[data-wr-modal-title]');
    modal.addEventListener('click', function(e){
      if(e.target === modal) closeModal();
    });
    var closeBtn = modal.querySelector('[data-wr-modal-close]');
    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    var prevBtn = modal.querySelector('[data-wr-modal-prev]');
    var nextBtn = modal.querySelector('[data-wr-modal-next]');
    if(prevBtn) prevBtn.addEventListener('click', function(){ step(-1); });
    if(nextBtn) nextBtn.addEventListener('click', function(){ step(1); });

    document.addEventListener('keydown', function(e){
      if(!modal.classList.contains('is-open')) return;
      if(e.key === 'Escape') closeModal();
      if(e.key === 'ArrowLeft') step(-1);
      if(e.key === 'ArrowRight') step(1);
    });
  }

  function openModal(images, title){
    ensureModal();
    if(!modal) return;
    modalImages = (images || []).filter(Boolean);
    modalIndex = 0;
    if(modalTitle) modalTitle.textContent = title || '';
    renderModal();
    modal.classList.add('is-open');
    document.documentElement.style.overflow = 'hidden';
  }

  function closeModal(){
    if(!modal) return;
    modal.classList.remove('is-open');
    document.documentElement.style.overflow = '';
  }

  function renderModal(){
    if(!modalImg) return;
    if(!modalImages.length){
      modalImg.src = '';
      return;
    }
    if(modalIndex < 0) modalIndex = modalImages.length - 1;
    if(modalIndex >= modalImages.length) modalIndex = 0;
    modalImg.src = modalImages[modalIndex];
  }

  function step(dir){
    if(!modalImages.length) return;
    modalIndex += dir;
    renderModal();
  }

  // ---------- Tabs + search filtering ----------
  function initFiltering(scope){
    var tabs = scope.querySelectorAll('.wr-tab');
    var cards = scope.querySelectorAll('.wr-showcase-card');
    var search = scope.querySelector('input[data-wr-search]');
    var activeTerm = 'all';
    var q = '';

    function apply(){
      cards.forEach(function(card){
        var term = card.getAttribute('data-term') || '';
        var title = (card.getAttribute('data-title') || '').toLowerCase();
        var okTerm = (activeTerm === 'all') ? true : (term.split(' ').indexOf(activeTerm) !== -1);
        var okQ = !q ? true : title.indexOf(q) !== -1;
        card.style.display = (okTerm && okQ) ? '' : 'none';
      });
    }

    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        tabs.forEach(function(x){ x.classList.remove('is-active'); });
        btn.classList.add('is-active');
        activeTerm = btn.getAttribute('data-term') || 'all';
        apply();
      });
    });

    if(search){
      search.addEventListener('input', function(){
        q = (search.value || '').trim().toLowerCase();
        apply();
      });
    }

    apply();
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.wr-showcase-card').forEach(initCard);

    // Modal open buttons
    document.querySelectorAll('[data-wr-open-gallery]').forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var card = btn.closest('.wr-showcase-card');
        if(!card) return;
        var media = card.querySelector('.wr-showcase-media');
        if(!media) return;
        var images = parseImages(media);
        var title = card.getAttribute('data-title') || '';
        openModal(images, title);
      });
    });

    // Filtering scope (wrapper)
    document.querySelectorAll('.wr-showcase-wrap').forEach(initFiltering);
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
 * Template override for single wr_showcase
 */
function wr_showcase_single_template($template) {
    if (is_singular('wr_showcase')) {
        $custom = WR_SHOWCASE_DIR . 'templates/single-wr_showcase.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
}
add_filter('single_template', 'wr_showcase_single_template');

/**
 * Admin UX: Show shortcodes in list tables (Items + Categories)
 */

// --- Items list: add Shortcode column
function wr_showcase_items_columns($columns) {
    // Put shortcode after title if possible
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['wr_shortcode'] = 'Shortcode';
        }
    }
    if (!isset($new['wr_shortcode'])) {
        $new['wr_shortcode'] = 'Shortcode';
    }
    return $new;
}
add_filter('manage_edit-wr_showcase_columns', 'wr_showcase_items_columns');

function wr_showcase_items_column_content($column, $post_id) {
    if ($column !== 'wr_shortcode') return;
    $sc = '[wr_showcase id="' . absint($post_id) . '"]';
    echo '<code style="user-select:all;">' . esc_html($sc) . '</code>';
    echo '<button type="button" class="button button-small wr-copy-shortcode" data-sc="' . esc_attr($sc) . '" style="margin-left:8px;">Copy</button>';
}
add_action('manage_wr_showcase_posts_custom_column', 'wr_showcase_items_column_content', 10, 2);

// --- Categories list: add Shortcode column
function wr_showcase_cat_columns($columns) {
    $columns['wr_shortcode'] = 'Shortcode';
    return $columns;
}
add_filter('manage_edit-wr_showcase_cat_columns', 'wr_showcase_cat_columns');

function wr_showcase_cat_column_content($content, $column_name, $term_id) {
    if ($column_name !== 'wr_shortcode') return $content;
    $term = get_term($term_id, 'wr_showcase_cat');
    if (!$term || is_wp_error($term)) return $content;

    $sc = '[wr_showcase category="' . $term->slug . '"]';
    $html  = '<code style="user-select:all;">' . esc_html($sc) . '</code>';
    $html .= '<button type="button" class="button button-small wr-copy-shortcode" data-sc="' . esc_attr($sc) . '" style="margin-left:8px;">Copy</button>';
    return $html;
}
add_filter('manage_wr_showcase_cat_custom_column', 'wr_showcase_cat_column_content', 10, 3);

// --- Add a small helper box above the categories table: global shortcode
function wr_showcase_cat_page_helper_note() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-wr_showcase_cat') return;
    $sc = '[wr_showcase]';
    echo '<div class="notice notice-info" style="padding:10px 12px;margin-top:12px;">';
    echo '<strong>Global Showcase Shortcode:</strong> ';
    echo '<code style="user-select:all;">' . esc_html($sc) . '</code>';
    echo '<button type="button" class="button button-small wr-copy-shortcode" data-sc="' . esc_attr($sc) . '" style="margin-left:8px;">Copy</button>';
    echo '</div>';
}
add_action('admin_notices', 'wr_showcase_cat_page_helper_note');

// --- Admin JS for "Copy" buttons
function wr_showcase_admin_copy_js($hook) {
    // Only load on Showcase Items list and Showcase Categories page
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    $is_items = ($screen->id === 'edit-wr_showcase');
    $is_cats  = ($screen->id === 'edit-wr_showcase_cat');
    if (!$is_items && !$is_cats) return;

    $js = <<<JS
(function(){
  function copyText(text){
    if(navigator.clipboard && window.isSecureContext){
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); } catch(e){}
    document.body.removeChild(ta);
    return Promise.resolve();
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('.wr-copy-shortcode');
    if(!btn) return;
    e.preventDefault();
    var sc = btn.getAttribute('data-sc') || '';
    copyText(sc).then(function(){
      var old = btn.textContent;
      btn.textContent = 'Copied';
      setTimeout(function(){ btn.textContent = old; }, 900);
    });
  });
})();
JS;
    wp_add_inline_script('jquery-core', $js);
}
add_action('admin_enqueue_scripts', 'wr_showcase_admin_copy_js');

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
        'tabs' => 'true',
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
    $tabs_enabled = (empty($atts['category']) && strtolower((string)$atts['tabs']) !== 'false');

    echo '<div class="wr-showcase-wrap">';

    // Tabs toolbar (only when no fixed category)
    if ($tabs_enabled) {
        $terms = get_terms([
            'taxonomy' => 'wr_showcase_cat',
            'hide_empty' => true,
        ]);

        echo '<div class="wr-showcase-toolbar">';
        echo '<div class="wr-showcase-tabs">';
        echo '<button type="button" class="wr-tab is-active" data-term="all">Tümü</button>';
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $t) {
                echo '<button type="button" class="wr-tab" data-term="' . esc_attr($t->slug) . '">' . esc_html($t->name) . '</button>';
            }
        }
        echo '</div>';
        echo '<div class="wr-showcase-search">';
        echo '<input type="search" data-wr-search placeholder="Demo ara...">';
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="wr-showcase-grid columns-' . esc_attr($cols) . '">';

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();

            $post_id = get_the_ID();
            $title = get_the_title();

            // Terms for filtering
            $slugs = [];
            $item_terms = get_the_terms($post_id, 'wr_showcase_cat');
            if (!empty($item_terms) && !is_wp_error($item_terms)) {
                foreach ($item_terms as $it) $slugs[] = $it->slug;
            }

            echo '<div class="wr-showcase-card" data-title="' . esc_attr($title) . '" data-term="' . esc_attr(implode(' ', $slugs)) . '">';

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
            // "İNCELE" button opens modal
            echo '<button type="button" class="wr-showcase-imgbtn" data-wr-open-gallery="1">İNCELE</button>';
            if ($main) {
                echo '<img class="wr-showcase-main" src="' . esc_url($main) . '" alt="' . esc_attr(get_the_title()) . '">';
            } else {
                echo '<img class="wr-showcase-main" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="">';
            }
            echo '</div>';

            echo '<div class="wr-showcase-body">';
            echo '<h3>' . esc_html($title) . '</h3>';
            echo '<div class="wr-showcase-excerpt">' . get_the_excerpt() . '</div>';

            // Live preview should go to Demo URL (external) if set
            $demo_url = get_post_meta($post_id, '_wr_demo_url', true);
            $demo_url = $demo_url ? esc_url($demo_url) : '';

            if ($demo_url) {
                echo '<a class="wr-showcase-btn" href="' . $demo_url . '" target="_blank" rel="noopener noreferrer">Canlı Önizle</a>';
            } else {
                // fallback to single page
                echo '<a class="wr-showcase-btn" href="' . get_permalink() . '">Canlı Önizle</a>';
            }

            echo '</div>';
            echo '</div>';

        endwhile;
        wp_reset_postdata();
    else :
        echo '<p>No showcase items found.</p>';
    endif;

    echo '</div>';

    // Modal markup (once per shortcode output)
    echo '
    <div class="wr-modal" id="wr-showcase-modal" aria-hidden="true">
      <div class="wr-modal-inner" role="dialog" aria-modal="true">
        <button class="wr-modal-close" type="button" data-wr-modal-close aria-label="Close">✕</button>
        <button class="wr-modal-nav wr-modal-prev" type="button" data-wr-modal-prev aria-label="Previous">‹</button>
        <button class="wr-modal-nav wr-modal-next" type="button" data-wr-modal-next aria-label="Next">›</button>
        <div class="wr-modal-imgwrap">
          <img data-wr-modal-img src="" alt="">
        </div>
        <div class="wr-modal-title" data-wr-modal-title></div>
      </div>
    </div>';

    echo '</div>'; // .wr-showcase-wrap

    return ob_get_clean();
}
add_shortcode('wr_showcase', 'wr_showcase_shortcode');
