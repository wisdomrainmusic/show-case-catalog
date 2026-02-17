<?php
/*
Plugin Name: WR Showcase
Text Domain: wr-showcase
Description: Demo showcase system with category support and landing page shortcode.
Version: 1.3.5
Author: WisdomRain
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('WR_SHOWCASE_DIR', plugin_dir_path(__FILE__));

// Preset library storage (CSV import)
define('WR_SC_PRESETS_OPTION', 'wr_sc_preset_library');

/**
 * Admin: Preset Library (CSV import)
 */
function wr_sc_get_preset_library(){
    $lib = get_option(WR_SC_PRESETS_OPTION, []);
    return is_array($lib) ? $lib : [];
}

function wr_sc_parse_csv_delimiter($line){
    $candidates = [',',';','\t','|'];
    $best = ',';
    $best_count = 0;
    foreach ($candidates as $d){
        $count = substr_count($line, $d);
        if ($count > $best_count){
            $best_count = $count;
            $best = ($d === '\t') ? "\t" : $d;
        }
    }
    return $best;
}

function wr_sc_normalize_hex($v){
    $v = trim((string)$v);
    if ($v === '') return '';
    if ($v[0] !== '#') $v = '#'.$v;
    return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $v) ? strtoupper($v) : '';
}

function wr_sc_register_admin_menu(){
    add_submenu_page(
        'edit.php?post_type=wr_showcase',
        'Preset Library',
        'Preset Library',
        'manage_options',
        'wr_sc_preset_library',
        'wr_sc_render_preset_library_page'
    );
}
add_action('admin_menu', 'wr_sc_register_admin_menu');

function wr_sc_render_preset_library_page(){
    if (!current_user_can('manage_options')) return;

    $message = '';
    if (isset($_POST['wr_sc_import_csv']) && check_admin_referer('wr_sc_import_csv_nonce', 'wr_sc_import_csv_nonce_field')){
        if (!empty($_FILES['wr_sc_csv']['tmp_name'])){
            $tmp = $_FILES['wr_sc_csv']['tmp_name'];
            $fh = fopen($tmp, 'r');
            if ($fh){
                $firstLine = fgets($fh);
                if ($firstLine === false){
                    $message = 'CSV file is empty.';
                } else {
                    $delimiter = wr_sc_parse_csv_delimiter($firstLine);
                    rewind($fh);

                    $header = fgetcsv($fh, 0, $delimiter);
                    $header = array_map(function($h){ return strtolower(trim((string)$h)); }, (array)$header);

                    // required minimal columns
                    $required = ['name','primary','dark','bg','footer','link','body_font','heading_font'];
                    $missing = array_diff($required, $header);
                    if (!empty($missing)){
                        $message = 'Missing columns: '. esc_html(implode(', ', $missing));
                    } else {
                        $index = array_flip($header);
                        $lib = [];
                        $count = 0;
                        while (($row = fgetcsv($fh, 0, $delimiter)) !== false){
                            $name = trim((string)($row[$index['name']] ?? ''));
                            if ($name === '') continue;
                            $key = sanitize_title($name);

                            $preset = [
                                'name' => $name,
                                'primary' => wr_sc_normalize_hex($row[$index['primary']] ?? ''),
                                'dark' => wr_sc_normalize_hex($row[$index['dark']] ?? ''),
                                'bg' => wr_sc_normalize_hex($row[$index['bg']] ?? ''),
                                'footer' => wr_sc_normalize_hex($row[$index['footer']] ?? ''),
                                'link' => wr_sc_normalize_hex($row[$index['link']] ?? ''),
                                'body_font' => sanitize_text_field($row[$index['body_font']] ?? ''),
                                'heading_font' => sanitize_text_field($row[$index['heading_font']] ?? ''),
                                // optional helpers
                                'short_desc' => sanitize_textarea_field($row[$index['short_desc']] ?? ($row[$index['short_description']] ?? '')),
                                'focus_keyword' => sanitize_text_field($row[$index['focus_keyword']] ?? ''),
                            ];

                            $lib[$key] = $preset;
                            $count++;
                        }

                        update_option(WR_SC_PRESETS_OPTION, $lib, false);
                        $message = 'Imported presets: '. intval($count);
                    }
                }
                fclose($fh);
            }
        } else {
            $message = 'No file uploaded.';
        }
    }

    $lib = wr_sc_get_preset_library();
    echo '<div class="wrap">';
    echo '<h1>Preset Library</h1>';
    echo '<p>Upload your preset CSV once. Columns: <code>name, primary, dark, bg, footer, link, body_font, heading_font</code> (optional: <code>short_desc, focus_keyword</code>).</p>';
    if ($message) echo '<div class="notice notice-info"><p>'. esc_html($message) .'</p></div>';

    echo '<form method="post" enctype="multipart/form-data" style="margin:16px 0;">';
    wp_nonce_field('wr_sc_import_csv_nonce', 'wr_sc_import_csv_nonce_field');
    echo '<input type="file" name="wr_sc_csv" accept=".csv,text/csv" required> ';
    echo '<button class="button button-primary" type="submit" name="wr_sc_import_csv" value="1">Import CSV</button>';
    echo '</form>';

    if (!empty($lib)){
        echo '<h2>Loaded Presets ('. count($lib) .')</h2>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Name</th><th>Primary</th><th>Dark</th><th>BG</th><th>Link</th><th>Body font</th><th>Heading font</th>';
        echo '</tr></thead><tbody>';
        foreach ($lib as $k => $p){
            echo '<tr>';
            echo '<td><strong>'. esc_html($p['name'] ?? $k) .'</strong><br><code>'. esc_html($k) .'</code></td>';
            echo '<td>'. esc_html($p['primary'] ?? '') .'</td>';
            echo '<td>'. esc_html($p['dark'] ?? '') .'</td>';
            echo '<td>'. esc_html($p['bg'] ?? '') .'</td>';
            echo '<td>'. esc_html($p['link'] ?? '') .'</td>';
            echo '<td>'. esc_html($p['body_font'] ?? '') .'</td>';
            echo '<td>'. esc_html($p['heading_font'] ?? '') .'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}

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
        'rewrite' => ['slug' => 'e-ticaret-site-demolari'],
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

    // Sidebar: Preset info (user requested)
    add_meta_box(
        'wr_showcase_preset_box',
        'Preset Settings',
        'wr_showcase_render_preset_box',
        'wr_showcase',
        'side',
        'default'
    );

    // Sidebar: Preset design tokens (colors/fonts)
    add_meta_box(
        'wr_showcase_preset_tokens_box',
        'Preset Design Tokens',
        'wr_showcase_render_preset_tokens_box',
        'wr_showcase',
        'side',
        'default'
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
        <label><strong>Gallery Images (recommended 5–15)</strong></label>
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
        <span style="margin-left:10px;color:#666;">(You can select up to 15 images. The landing page shows them in your chosen order.)</span>
    </p>
    <?php
}

function wr_showcase_render_preset_box($post){
    $preset_name = get_post_meta($post->ID, '_wr_preset_name', true);
    $preset_key  = get_post_meta($post->ID, '_wr_preset_key', true);
    $preset_sel  = get_post_meta($post->ID, '_wr_preset_lib_key', true);
    $focus_kw    = get_post_meta($post->ID, '_wr_focus_keyword', true);
    $lib         = function_exists('wr_sc_get_preset_library') ? wr_sc_get_preset_library() : [];
    ?>
    <?php if (!empty($lib)): ?>
      <p style="margin:0 0 10px;">
          <label><strong>Preset (from library)</strong></label>
          <select name="wr_preset_lib_key" style="width:100%;">
              <option value="">— Select —</option>
              <?php foreach($lib as $k => $p): ?>
                  <option value="<?php echo esc_attr($k); ?>" <?php selected($preset_sel, $k); ?>><?php echo esc_html($p['name'] ?? $k); ?></option>
              <?php endforeach; ?>
          </select>
          <small style="color:#666;">Upload CSV once: <em>Showcase Items → Preset Library</em>. Selecting a preset can auto-fill tokens + focus keyword on save.</small>
      </p>
    <?php else: ?>
      <input type="hidden" name="wr_preset_lib_key" value="<?php echo esc_attr($preset_sel); ?>">
    <?php endif; ?>

    <p style="margin:0 0 8px;">
        <label><strong>Preset Name</strong></label>
        <input type="text" name="wr_preset_name" value="<?php echo esc_attr($preset_name); ?>" style="width:100%;" placeholder="Kadın Giyim 1 Soft Rose">
        <small style="color:#666;">Example: “Kadın Giyim 1 Soft Rose” or “Steel Navy”.</small>
    </p>
    <p style="margin:10px 0 0;">
        <label><strong>Preset Key (optional)</strong></label>
        <input type="text" name="wr_preset_key" value="<?php echo esc_attr($preset_key); ?>" style="width:100%;" placeholder="soft-rose">
        <small style="color:#666;">Leave empty to auto-generate from Preset Name.</small>
    </p>

    <p style="margin:12px 0 0;">
        <label><strong>Focus Keyword (optional)</strong></label>
        <input type="text" name="wr_focus_keyword" value="<?php echo esc_attr($focus_kw); ?>" style="width:100%;" placeholder="erkek giyim e-ticaret demo sitesi">
        <small style="color:#666;">If empty, we auto-generate from category + preset.</small>
        <?php $auto_kw = wr_sc_keywords_for_post($post->ID); ?>
        <input type="hidden" name="wr_auto_focus_keyword" value="<?php echo esc_attr($auto_kw['primary'] ?? ''); ?>">
    </p>
    
    <p style="margin:12px 0 0;">
        <label><strong>Live Preview Button URL (optional)</strong></label>
        <input type="url" name="wr_live_preview_url" value="<?php echo esc_attr(get_post_meta($post->ID, '_wr_live_preview_url', true)); ?>" style="width:100%;" placeholder="https://...">
        <small style="color:#666;">If set, the “Canlı Önizle” button will use this link (independent from Demo URL).</small>
    </p>
<?php
}

function wr_showcase_render_preset_tokens_box($post){
    $fields = [
        'primary' => 'Primary Color',
        'dark' => 'Dark Color',
        'bg' => 'Background Color',
        'footer' => 'Footer Color',
        'link' => 'Link Color',
        'body_font' => 'Body Font',
        'heading_font' => 'Heading Font',
    ];
    echo '<div class="wr-sc-tokens" style="display:grid;gap:10px;">';
    foreach ($fields as $key => $label) {
        $val = get_post_meta($post->ID, '_wr_preset_' . $key, true);
        echo '<div>';
        echo '<label style="display:block;font-weight:700;margin:0 0 4px;">' . esc_html($label) . '</label>';
        $ph = (strpos($key, 'font') !== false) ? 'Inter / Poppins / DM Serif Display…' : '#RRGGBB';
        echo '<input type="text" name="wr_preset_' . esc_attr($key) . '" value="' . esc_attr($val) . '" style="width:100%;" placeholder="' . esc_attr($ph) . '">';
        echo '</div>';
    }
    echo '<small style="color:#666;display:block;line-height:1.35;">Bu alanlara preset renk/font bilgilerini girerseniz, landing sayfası SEO içeriği ve görsel alt metinleri otomatik olarak bu detayları kullanır.</small>';
    echo '</div>';
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

    // Preset fields (sidebar)
    if (isset($_POST['wr_preset_lib_key'])) {
        $sel = sanitize_text_field($_POST['wr_preset_lib_key']);
        update_post_meta($post_id, '_wr_preset_lib_key', $sel);

        // If preset selected from library, pull values into tokens + helpers
        if ($sel && function_exists('wr_sc_get_preset_library')) {
            $lib = wr_sc_get_preset_library();
            if (isset($lib[$sel]) && is_array($lib[$sel])) {
                $p = $lib[$sel];
                if (!empty($p['name'])) update_post_meta($post_id, '_wr_preset_name', sanitize_text_field($p['name']));
                update_post_meta($post_id, '_wr_preset_key', $sel);

                update_post_meta($post_id, '_wr_preset_primary', sanitize_text_field($p['primary'] ?? ''));
                update_post_meta($post_id, '_wr_preset_dark', sanitize_text_field($p['dark'] ?? ''));
                update_post_meta($post_id, '_wr_preset_bg', sanitize_text_field($p['bg'] ?? ''));
                update_post_meta($post_id, '_wr_preset_footer', sanitize_text_field($p['footer'] ?? ''));
                update_post_meta($post_id, '_wr_preset_link', sanitize_text_field($p['link'] ?? ''));
                update_post_meta($post_id, '_wr_preset_body_font', sanitize_text_field($p['body_font'] ?? ''));
                update_post_meta($post_id, '_wr_preset_heading_font', sanitize_text_field($p['heading_font'] ?? ''));

                if (!empty($p['short_desc'])) update_post_meta($post_id, '_wr_preset_short_desc', sanitize_textarea_field($p['short_desc']));
                if (!empty($p['focus_keyword'])) update_post_meta($post_id, '_wr_focus_keyword', sanitize_text_field($p['focus_keyword']));
            }
        }
    }

    if (isset($_POST['wr_preset_name'])) {
        $name = sanitize_text_field($_POST['wr_preset_name']);
        update_post_meta($post_id, '_wr_preset_name', $name);
    }
    if (isset($_POST['wr_preset_key'])) {
        $key = sanitize_text_field($_POST['wr_preset_key']);
        if (!$key) {
            $name = isset($_POST['wr_preset_name']) ? sanitize_text_field($_POST['wr_preset_name']) : '';
            $key = $name ? sanitize_title($name) : '';
        }
        update_post_meta($post_id, '_wr_preset_key', $key);
    }

    if (isset($_POST['wr_focus_keyword'])) {
        update_post_meta($post_id, '_wr_focus_keyword', sanitize_text_field($_POST['wr_focus_keyword']));
    }

    // Rank Math focus keyword helper (avoid blank score)
    $existing_rm_focus = get_post_meta($post_id, 'rank_math_focus_keyword', true);
    if (!$existing_rm_focus) {
        $focus = get_post_meta($post_id, '_wr_focus_keyword', true);
        if (!$focus) {
            $cats = wp_get_post_terms($post_id, 'wr_showcase_cat');
            $cat_name = (!is_wp_error($cats) && !empty($cats)) ? $cats[0]->name : '';
            // User preference: focus keyword should always be Category + "E-Ticaret Sitesi"
            $focus = trim($cat_name ? ($cat_name . ' e-ticaret sitesi') : 'e-ticaret sitesi');
        }
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus);
    }

    // Preset tokens (sidebar)
    $token_keys = ['primary','dark','bg','footer','link','body_font','heading_font'];
    foreach ($token_keys as $k) {
        $in = 'wr_preset_' . $k;
        if (!isset($_POST[$in])) continue;
        $val = sanitize_text_field($_POST[$in]);
        update_post_meta($post_id, '_wr_preset_' . $k, $val);
    }

    if (isset($_POST['wr_gallery_ids'])) {
        // enforce max 15 images (landing scroll story)
        $ids = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['wr_gallery_ids']))));
        $ids = array_slice($ids, 0, 15);
        update_post_meta($post_id, '_wr_gallery_ids', implode(',', $ids));
    }
}
add_action('save_post_wr_showcase', 'wr_showcase_save_meta');

/**
 * Helpers: Category + Preset + Keywords + Auto SEO content
 */
function wr_sc_get_primary_category_name($post_id){
    $terms = wp_get_post_terms($post_id, 'wr_showcase_cat');
    if (!empty($terms) && !is_wp_error($terms)) return $terms[0]->name;
    return 'E-Ticaret';
}

function wr_sc_get_preset_label($post_id){
    $preset_name = get_post_meta($post_id, '_wr_preset_name', true);
    $preset_key  = get_post_meta($post_id, '_wr_preset_key', true);
    $preset_label = $preset_name ? $preset_name : ($preset_key ? $preset_key : 'Preset');
    return $preset_label;
}

function wr_sc_get_preset_tokens($post_id){
    $keys = ['primary','dark','bg','footer','link','body_font','heading_font'];
    $out = [];
    foreach ($keys as $k) {
        $v = trim((string)get_post_meta($post_id, '_wr_preset_' . $k, true));
        if ($v !== '') $out[$k] = $v;
    }
    return $out;
}

function wr_sc_keywords_for_post($post_id){
    $cat = wr_sc_get_primary_category_name($post_id);
    $preset = wr_sc_get_preset_label($post_id);
    $lower = function($s){
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    };
    $cat_lc = $lower($cat);
    $preset_lc = $lower($preset);

    // Focus keyword format (primary): Category + "e-ticaret sitesi"
    $primary = $cat_lc . ' e-ticaret sitesi';

    $secondary = [
        $cat_lc . ' e-ticaret tasarımı',
        'hazır ' . $cat_lc . ' e-ticaret sitesi',
        'SEO uyumlu ' . $cat_lc . ' e-ticaret sitesi',
        'mobil uyumlu ' . $cat_lc . ' e-ticaret sitesi',
        $cat_lc . ' online mağaza örneği',
    ];

    $long_tail = [
        $preset_lc . ' tasarımlı ' . $cat_lc . ' e-ticaret sitesi',
        $cat_lc . ' için hazır butik e-ticaret altyapısı',
        'performans odaklı ' . $cat_lc . ' online mağaza tasarımı',
        'dönüşüm artıran ' . $cat_lc . ' vitrin yerleşimi',
        $cat_lc . ' ürün liste sayfası örneği',
        $cat_lc . ' ürün detay sayfası tasarım örneği',
    ];

    // Small rotation seed per-post (avoid identical text across pages)
    $seed = (int)$post_id;
    $pick = function(array $arr, $n) use ($seed){
        $arr = array_values(array_unique(array_filter($arr)));
        if (!$arr) return [];
        // deterministic shuffle
        $shuffled = $arr;
        for ($i = count($shuffled)-1; $i > 0; $i--) {
            $j = ($seed + $i * 37) % ($i+1);
            $tmp = $shuffled[$i];
            $shuffled[$i] = $shuffled[$j];
            $shuffled[$j] = $tmp;
        }
        return array_slice($shuffled, 0, max(1,(int)$n));
    };

    return [
        'cat' => $cat,
        'preset' => $preset,
        'primary' => $primary,
        'secondary' => $pick($secondary, 4),
        'long_tail' => $pick($long_tail, 4),
    ];
}

function wr_sc_build_screenshot_alt($post_id, $index, $kind = ''){
    $kw = wr_sc_keywords_for_post($post_id);
    $preset = $kw['preset'];
    $kind = $kind ? $kind : 'ekran görüntüsü';
    $n = max(1, (int)$index);
    return sprintf('%s – %s tasarım %s %d', $kw['primary'], $preset, $kind, $n);
}

function wr_sc_get_faq_items($post_id){
    $kw = wr_sc_keywords_for_post($post_id);
    $cat = $kw['cat'];
    $preset = $kw['preset'];
    $demo_url = get_post_meta($post_id, '_wr_demo_url', true);
    $has_demo = !empty($demo_url);

    // Image for SEO block (first gallery image or featured image)
    $gallery_ids = get_post_meta($post_id, '_wr_gallery_ids', true);
    $img_id = 0;
    if (!empty($gallery_ids)) {
        $tmp = array_filter(array_map('absint', explode(',', (string)$gallery_ids)));
        if (!empty($tmp)) $img_id = (int)reset($tmp);
    }
    if (!$img_id) {
        $feat = get_post_thumbnail_id($post_id);
        if ($feat) $img_id = (int)$feat;
    }
    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'large') : '';

    $faqs = [
        [
            'q' => 'Bu demo gerçek bir e-ticaret sitesi mi?',
            'a' => 'Evet. Bu sayfada gördüğünüz ekran görüntüleri gerçek bir demo mağazanın akışını temsil eder. Canlı önizleme bağlantısı varsa, tüm sayfaları doğrudan inceleyebilirsiniz.',
        ],
        [
            'q' => $cat . ' için bu tasarım SEO uyumlu mu?',
            'a' => 'Evet. Yapı; okunabilir başlık hiyerarşisi, açıklayıcı içerik ve görsel alt metinleri ile SEO temellerine uygun olacak şekilde kurgulanmıştır. İçerik, ' . $cat . ' arama niyetine göre hazırlanır.',
        ],
        [
            'q' => 'Mobil uyumluluk nasıl?',
            'a' => 'Düzen, mobil cihazlarda akıcı bir vitrin deneyimi hedefler. Ürün listeleri, menü ve CTA bileşenleri küçük ekranlarda daha rahat kullanılacak şekilde ölçeklenir.',
        ],
        [
            'q' => 'Renk presetini değiştirebilir miyim?',
            'a' => 'Evet. ' . $preset . ' sadece bir örnektir. Aynı altyapı farklı renk / tipografi presetleriyle hızlıca çeşitlendirilebilir; böylece marka kimliğinize uygun görünüm elde edebilirsiniz.',
        ],
    ];

    if ($has_demo) {
        $faqs[] = [
            'q' => 'Canlı önizleme linki ne işe yarar?',
            'a' => 'Canlı önizleme linki, demoyu ayrı sekmede açarak gerçek kullanıcı akışını (ana sayfa, kategori, ürün, sepet vb.) incelemenizi sağlar.',
        ];
    }

    return $faqs;
}

function wr_sc_faq_schema_jsonld($post_id){
    $faqs = wr_sc_get_faq_items($post_id);
    $main = [];
    foreach ($faqs as $f) {
        $main[] = [
            '@type' => 'Question',
            'name' => wp_strip_all_tags((string)$f['q']),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => wp_strip_all_tags((string)$f['a']),
            ],
        ];
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $main,
    ];
}

function wr_sc_render_auto_seo_block($post_id){
    $kw = wr_sc_keywords_for_post($post_id);
    $tokens = wr_sc_get_preset_tokens($post_id);
    $demo_url = get_post_meta($post_id, '_wr_demo_url', true);
    $has_demo = !empty($demo_url);

    // Image for SEO block (first gallery image or featured image)
    $gallery_ids = get_post_meta($post_id, '_wr_gallery_ids', true);
    $img_id = 0;
    if (!empty($gallery_ids)) {
        $tmp = array_filter(array_map('absint', explode(',', (string)$gallery_ids)));
        if (!empty($tmp)) $img_id = (int)reset($tmp);
    }
    if (!$img_id) {
        $feat = get_post_thumbnail_id($post_id);
        if ($feat) $img_id = (int)$feat;
    }
    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'large') : '';

    $cat = $kw['cat'];
    $preset = $kw['preset'];
    $primary = $kw['primary'];
    $sec = $kw['secondary'];
    $lt = $kw['long_tail'];

    $token_bits = [];
    if (!empty($tokens['primary'])) $token_bits[] = 'primary renk: ' . $tokens['primary'];
    if (!empty($tokens['bg'])) $token_bits[] = 'arka plan: ' . $tokens['bg'];
    if (!empty($tokens['heading_font'])) $token_bits[] = 'başlık fontu: ' . $tokens['heading_font'];
    if (!empty($tokens['body_font'])) $token_bits[] = 'gövde fontu: ' . $tokens['body_font'];
    $token_line = $token_bits ? ' (Preset detayları: ' . implode(' • ', $token_bits) . ')' : '';

    $variants_intro = [
        'Bu demo sayfası, <strong>' . esc_html($cat) . '</strong> sektöründe satış yapmak isteyen markalar için hazırlanmış örnek bir vitrindir. ' . esc_html($preset) . ' tasarım yaklaşımı; sade, hızlı ve satış odaklı bir akış hedefler.',
        '<strong>' . esc_html($cat) . '</strong> için hazırlanmış bu örnek çalışma, gerçek bir mağaza akışını tek sayfada görmenizi sağlar. ' . esc_html($preset) . ' preset’i ile renk/typography dengesi vurgulanır.',
        'Bu sayfa, ' . esc_html($cat) . ' işletmeleri için örnek bir <em>e-ticaret vitrin kurgusu</em> sunar. ' . esc_html($preset) . ' görünümüyle modern bir mağaza dili hedeflenmiştir.',
    ];
    $variants_benefit = [
        'Amaç; ziyaretçinin ürünleri hızla keşfetmesi, kategori–ürün liste–ürün detay geçişlerini net biçimde anlaması ve CTA noktalarını rahatça görmesidir.',
        'Bu yapı; ürün keşfi, kategori gezinmesi ve satın alma adımlarını sadeleştiren bir yerleşim mantığına dayanır. Özellikle mobil kullanımda hız ve okunabilirlik ön plandadır.',
        'Kurgunun odağı; vitrin alanlarını öne çıkarırken, ürün detay hissini güçlendirmek ve satın alma yolculuğunu gereksiz adımlardan arındırmaktır.',
    ];
    $pick = function(array $arr) use ($post_id){
        if (!$arr) return '';
        return $arr[$post_id % count($arr)];
    };

    $intro = $pick($variants_intro);
    $benefit = $pick($variants_benefit);

    ob_start();
    ?>
    <section class="wr-seo-block" id="seo-block">

      <?php if (!empty($img_url)) : ?>
        <figure class="wr-seo-figure" style="margin:24px auto;max-width:980px;">
          <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($primary); ?>" loading="lazy" style="width:100%;height:auto;border-radius:18px;border:1px solid rgba(0,0,0,.08);">
        </figure>
      <?php endif; ?>

      <h2><?php echo esc_html($cat); ?> E-Ticaret Sitesi – <?php echo esc_html($preset); ?> Tasarım</h2>

      <h3><?php echo esc_html($primary); ?> için modern vitrin kurgusu</h3>

      <p><?php echo wp_kses_post($intro); ?></p>

      <p>
        Bu landing kurgusu özellikle <strong><?php echo esc_html($primary); ?></strong> araması yapan kullanıcıların niyetine göre hazırlanır: ziyaretçi, ana sayfa vitrinini aşağı doğru kaydırıyormuş gibi ilerler ve sayfa bölümlerini sırayla değerlendirir.
        Bu sayede <strong>hazır mağaza örneği</strong> arayanlar için; kategori düzeni, ürün liste deneyimi ve sayfa hiyerarşisi tek bakışta anlaşılır.
        <?php if ($token_line): ?><?php echo esc_html($token_line); ?><?php endif; ?>
      </p>

      <p>
        <?php echo esc_html($benefit); ?>
        Eğer hedefiniz <?php echo esc_html($cat); ?> ürünlerinde daha yüksek dönüşüm ise, bu tip bir yerleşim; vitrinde öne çıkan kategoriler, popüler ürün blokları ve sade bir gezinme ile “ilk izlenim” kalitesini yükseltir.
      </p>

      <?php
        // Add an on-page image with focus keyword in ALT for Rank Math checks.
        $img_alt = $primary;
        $first_id = 0;
        $gallery = get_post_meta($post_id, '_wr_gallery_ids', true);
        if (!empty($gallery)) {
          $ids = array_filter(array_map('absint', explode(',', (string)$gallery)));
          $first_id = $ids[0] ?? 0;
        }
        if (!$first_id) {
          $first_id = get_post_thumbnail_id($post_id);
        }
      ?>
      <?php if ($first_id): ?>
        <figure class="wr-seo-figure">
          <?php echo wp_get_attachment_image($first_id, 'large', false, ['alt' => $img_alt, 'loading' => 'lazy']); ?>
          <figcaption><?php echo esc_html($primary); ?> örnek görünüm</figcaption>
        </figure>
      <?php endif; ?>

      <h3>Bu demoda neler var?</h3>
      <ul>
        <li>Modern ana sayfa vitrin akışı ve banner alanları</li>
        <li>Kategori / ürün liste sayfası mantığı (filtrelenebilir ürün düzeni hissi)</li>
        <li>Ürün detay deneyimi: görsel–bilgi dengesi ve satın alma aksiyonu</li>
        <li>Mobil uyumlu yerleşim yaklaşımı (responsive blok hiyerarşisi)</li>
        <li>SEO temelleri: açıklayıcı metin, odak anahtar kelime ve görsel alt metinleri</li>
      </ul>

      <h3><?php echo esc_html($primary); ?> kimler için uygun?</h3>
      <p>
        Bu örnek; butik işletmeler, hızlı ürün güncelleyen markalar, kampanya odaklı vitrin isteyen mağazalar ve özellikle <em><?php echo esc_html($sec[0] ?? $primary); ?></em> arayan girişimler için uygundur.
        Ayrıca <?php echo esc_html($lt[0] ?? 'hazır e-ticaret altyapısı'); ?> gibi uzun kuyruklu aramalarda, ziyaretçinin aradığı “örnek sayfa akışı”nı net biçimde gösterir.
      </p>

      <h3><?php echo esc_html($primary); ?> için SEO ve performans notları</h3>
      <p>
        Bu sayfadaki metin, <strong><?php echo esc_html($primary); ?></strong> odağı korunarak hazırlanmıştır. Başlık hiyerarşisi (H2/H3), görsel alt metni ve iç bağlantılar; arama motorlarının sayfa konusunu daha net anlamasına yardımcı olur.
        Ayrıca görsellerin boyutu, mobilde hızlı yüklenme ve iyi bir kullanıcı deneyimi için kritik olduğu için; galeri görsellerini optimize etmek dönüşümü ve SEO sinyallerini destekler.
      </p>

      <p>
        <?php echo esc_html($preset); ?> preset’i; marka algısını güçlendirmek için renk ve tipografi dengesini hedefleyen bir kurgu sunar.
        Aynı altyapı, farklı preset setleriyle hızlıca çeşitlendirilebilir.
        Böylece “tasarım dili”ni değiştirmeden, farklı hedef kitlelere uygun alternatifler üretilebilir: kurumsal, minimal, premium veya daha enerjik görünümler.
      </p>

      <?php if ($has_demo): ?>
        <p>
          Canlı demoyu incelemek için üstteki <strong>Canlı Önizle</strong> butonunu kullanabilirsiniz.
          Bu bağlantı, gerçek akışı (ana sayfa, kategori, ürün ve ödeme adımları) görmenizi sağlar ve tasarım kararlarını daha net değerlendirmenize yardımcı olur.
        </p>
      <?php endif; ?>


      <h3>Teklif Al</h3>
      <p>
        Bu demo kurgusunu kendi markanıza uyarlamak ve hızlıca yayına almak için bir teklif isteyebilirsiniz.
        <a href="<?php echo esc_url( home_url('/teklif-al/') ); ?>" rel="noopener">Teklif Al</a> sayfasından 1–2 dakikada talep oluşturun; size uygun paket ve kurulum planını paylaşalım.
      </p>
      <p class="wr-seo-cta">
        <a class="wr-showcase-landing-btn" href="<?php echo esc_url( home_url('/teklif-al/') ); ?>" rel="noopener">Teklif Al</a>
      </p>

      <p>
        Dış kaynağa örnek olarak, e-ticaret altyapı prensiplerini incelemek için
        <a href="<?php echo esc_url('https://woocommerce.com/'); ?>" target="_blank" rel="noopener">WooCommerce</a>
        sayfasına göz atabilirsiniz.
      </p>

      <h3>Sık Sorulan Sorular</h3>
      <div class="wr-faq">
        <?php foreach (wr_sc_get_faq_items($post_id) as $f): ?>
          <details class="wr-faq-item">
            <summary><?php echo esc_html($f['q']); ?></summary>
            <div class="wr-faq-a"><p><?php echo esc_html($f['a']); ?></p></div>
          </details>
        <?php endforeach; ?>
      </div>

    </section>
    <?php

    return ob_get_clean();
}

/**
 * Rank Math Content Analysis: Provide dynamic shortcode content to the analyzer.
 * Rank Math cannot "see" shortcode-rendered content by default, so we expose a plain-text
 * version of our SEO block to the content analyzer in the editor.
 */
function wr_sc_get_rankmath_content($post_id){
    if (!function_exists('wr_sc_render_auto_seo_block')) return '';
    $html = wr_sc_render_auto_seo_block($post_id);

    // Keep minimal HTML so Rank Math can detect headings (H2/H3), images (alt), and links.
    $allowed = [
        'h2' => [],
        'h3' => [],
        'p'  => [],
        'ul' => [],
        'li' => [],
        'a'  => ['href' => true, 'title' => true, 'rel' => true],
        'strong' => [],
        'em' => [],
        'img' => ['src' => true, 'alt' => true, 'loading' => true],
        'br' => [],
    ];
    $html = wp_kses($html, $allowed);

    // Normalize whitespace a bit to avoid noisy analysis, but keep tags.
    $html = preg_replace('/\s+/', ' ', $html);
    return trim($html);
}

function wr_sc_rankmath_integration_enqueue($hook){
    global $post;
    if ( ! ($hook === 'post.php' || $hook === 'post-new.php') ) return;
    if ( empty($post) || !isset($post->post_type) || $post->post_type !== 'wr_showcase' ) return;

    // Load only if Rank Math analyzer is present.
    $deps = ['wp-hooks', 'rank-math-analyzer'];
    wp_enqueue_script(
        'wr-sc-rankmath-integration',
        plugins_url('rank-math-integration.js', __FILE__),
        $deps,
        '1.0.2',
        true
    );

    $seo_text = wr_sc_get_rankmath_content($post->ID);
    wp_localize_script('wr-sc-rankmath-integration', 'WR_SC_RM', [
        'content' => $seo_text,
    ]);
}
add_action('admin_enqueue_scripts', 'wr_sc_rankmath_integration_enqueue');

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
            title: 'Select up to 15 images',
            button: { text: 'Use these images' },
            multiple: true
        });

        frame.on('select', function(){
            const selection = frame.state().get('selection').toArray();
            const ids = selection.map(att => att.id).slice(0,15);
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

/* -------- Single Landing (scroll story) -------- */
.wr-showcase-landing{ width:100%; }
.wr-showcase-landing-inner{
  width:100%;
  max-width:none;
  margin:0;
  padding:28px 0 60px;
}
.wr-showcase-landing-head{
  width:min(1100px, 92vw);
  margin:0 auto 18px;
  padding:0 12px;
}
.wr-showcase-kicker{
  font-weight:800;
  letter-spacing:.2px;
  opacity:.7;
  margin:0 0 8px;
}
.wr-showcase-landing-title{
  margin:0 0 10px;
  font-size:40px;
  line-height:1.1;
}
.wr-showcase-landing-desc{
  margin:0 0 14px;
  opacity:.75;
  font-size:16px;
}
.wr-showcase-meta{ display:flex; gap:8px; flex-wrap:wrap; margin:0 0 10px; }
.wr-pill{
  display:inline-flex;
  padding:6px 10px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.10);
  background:rgba(255,255,255,.65);
  font-weight:700;
  font-size:12px;
}
.wr-showcase-landing-cta{
  position:sticky;
  top:18px;
  z-index:50;
  display:flex;
  justify-content:flex-start;
  margin:14px 0 6px;
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

.wr-showcase-landing-gallery{ width:100%; }

/* Full-width but with breathing space + border (your request) */
.wr-shot{
  width:calc(100vw - 48px);
  margin-left:calc(50% - 50vw + 24px);
  margin-right:calc(50% - 50vw + 24px);
  border-radius:14px;
  border:1px solid rgba(0,0,0,.08);
  box-shadow:0 10px 30px rgba(0,0,0,.06);
  background:#000;
  overflow:hidden;
  position:relative;
}
.wr-shot img{
  width:100%;
  height:auto;
  display:block;
}

/* Browser frame effect */
.wr-browserbar{
  height:34px;
  background:rgba(0,0,0,.55);
  display:flex;
  align-items:center;
  padding:0 12px;
  gap:8px;
}
.wr-browserbar span{
  width:10px;
  height:10px;
  border-radius:50%;
  background:rgba(255,255,255,.55);
  display:inline-block;
}

.wr-seo-block{
  width:min(1100px, 92vw);
  margin:26px auto 0;
  padding:0 12px;
}
.wr-seo-block h2{ margin:0 0 10px; font-size:26px; line-height:1.2; }
.wr-seo-block h3{ margin:18px 0 8px; font-size:18px; }
.wr-seo-block p{ margin:0 0 12px; opacity:.88; }
.wr-seo-block ul{ margin:0 0 12px 18px; opacity:.9; }

/* Preset pills (header) */
.wr-showcase-meta{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.wr-pill strong{ font-weight:700; }

/* FAQ */
.wr-faq{ margin-top:10px; }
.wr-faq-item{
  border:1px solid rgba(0,0,0,.08);
  border-radius:14px;
  padding:10px 12px;
  margin:10px 0;
  background:rgba(255,255,255,.35);
}
.wr-faq-item summary{
  cursor:pointer;
  font-weight:700;
  list-style:none;
}
.wr-faq-item summary::-webkit-details-marker{ display:none; }
.wr-faq-a{ margin-top:8px; }

/* Pagination */
.wr-pagination{ display:flex; justify-content:center; margin:22px 0 0; }
.wr-pagination .page-numbers{ list-style:none; padding:0; margin:0; display:flex; gap:10px; align-items:center; }
.wr-pagination .page-numbers a,
.wr-pagination .page-numbers span{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:38px;
  height:38px;
  padding:0 10px;
  border-radius:999px;
  border:1px solid rgba(0,0,0,.10);
  background:#fff;
  text-decoration:none;
  font-weight:700;
}
.wr-pagination .page-numbers .current{
  background:#111;
  color:#fff;
  border-color:#111;
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
 * Rank Math: Auto title/description/focus keyword when fields are empty
 * (Only for wr_showcase single pages)
 */
function wr_sc_rm_get_cat_name($post_id){
    $terms = wp_get_post_terms($post_id, 'wr_showcase_cat');
    if (!empty($terms) && !is_wp_error($terms)) return $terms[0]->name;
    return 'E-Ticaret';
}

function wr_sc_rm_auto_title($title){
    if (!is_singular('wr_showcase')) return $title;
    $post_id = get_queried_object_id();
    if (!$post_id) return $title;
    $custom = get_post_meta($post_id, 'rank_math_title', true);
    if ($custom) return $title; // user filled it

    if (function_exists('wr_sc_keywords_for_post')) {
        $kw = wr_sc_keywords_for_post($post_id);
        $preset = !empty($kw['preset']) ? (' – ' . $kw['preset']) : '';
        return $kw['cat'] . ' E-Ticaret Demo Sitesi' . $preset . ' | ' . get_the_title($post_id);
    }

    $cat = wr_sc_rm_get_cat_name($post_id);
    $preset = get_post_meta($post_id, '_wr_preset_name', true);
    $preset = $preset ? ' – ' . $preset : '';
    return $cat . ' E-Ticaret Demo Sitesi' . $preset . ' | ' . get_the_title($post_id);
}
add_filter('rank_math/frontend/title', 'wr_sc_rm_auto_title', 20);

function wr_sc_rm_auto_description($desc){
    if (!is_singular('wr_showcase')) return $desc;
    $post_id = get_queried_object_id();
    if (!$post_id) return $desc;
    $custom = get_post_meta($post_id, 'rank_math_description', true);
    if ($custom) return $desc;

    if (function_exists('wr_sc_keywords_for_post')) {
        $kw = wr_sc_keywords_for_post($post_id);
        $preset_txt = !empty($kw['preset']) ? ($kw['preset'] . ' tasarımıyla ') : '';
        $sec = !empty($kw['secondary']) ? implode(', ', array_slice($kw['secondary'], 0, 3)) : '';
        $lt = !empty($kw['long_tail']) ? ($kw['long_tail'][0]) : '';
        $tail = trim($sec . ($lt ? (', ' . $lt) : ''));
        $tail = $tail ? (' Anahtar kelimeler: ' . $tail . '.') : '';
        return $kw['cat'] . ' için hazırlanmış ' . $preset_txt . 'SEO uyumlu e-ticaret demo sitesi. Vitrin, kategori ve ürün akışı tek sayfada.' . $tail;
    }

    $cat = wr_sc_rm_get_cat_name($post_id);
    $preset = get_post_meta($post_id, '_wr_preset_name', true);
    $preset_txt = $preset ? ($preset . ' tasarımıyla ') : '';
    return $cat . ' için hazırlanmış ' . $preset_txt . 'modern e-ticaret demo sitesi. Ana sayfa akışı, vitrin ve UX örnekleri tek sayfada. Canlı önizleyin.';
}
add_filter('rank_math/frontend/description', 'wr_sc_rm_auto_description', 20);

function wr_sc_rm_auto_focus_keyword($keywords){
    if (!is_singular('wr_showcase')) return $keywords;
    $post_id = get_queried_object_id();
    if (!$post_id) return $keywords;
    $custom = get_post_meta($post_id, 'rank_math_focus_keyword', true);
    if ($custom) return $keywords;
    if (function_exists('wr_sc_keywords_for_post')) {
        $kw = wr_sc_keywords_for_post($post_id);
        return sanitize_text_field($kw['primary']);
    }
    $cat = wr_sc_rm_get_cat_name($post_id);
    return sanitize_text_field($cat . ' e-ticaret demo');
}
add_filter('rank_math/frontend/focus_keyword', 'wr_sc_rm_auto_focus_keyword', 20);


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
        // Back-compat with old runtime shortcode: [hmps_showcase per_page="6" paging="pagination"]
        'per_page' => 0,
        'paging' => '',
    ], $atts);

    $per_page = absint($atts['per_page'] ?? 0);
    $paging_mode = strtolower(trim((string)($atts['paging'] ?? '')));
    $enable_paging = ($per_page > 0) && ($paging_mode === 'pagination' || $paging_mode === 'true' || $paging_mode === '1');

    // Use a dedicated query arg so we don't fight with theme pagination.
    $current_page = 1;
    if ($enable_paging) {
        $current_page = isset($_GET['hmps_page']) ? max(1, absint($_GET['hmps_page'])) : 1;
    }

    $args = [
        'post_type' => 'wr_showcase',
        'posts_per_page' => $enable_paging ? $per_page : -1,
    ];

    if ($enable_paging) {
        $args['paged'] = $current_page;
    }

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

    // Pagination
    if ($enable_paging && !is_wp_error($query) && $query->max_num_pages > 1) {
        $base = remove_query_arg('hmps_page');
        $base = add_query_arg('hmps_page', '%#%', $base);

        $links = paginate_links([
            'base'      => $base,
            'format'    => '',
            'current'   => $current_page,
            'total'     => (int) $query->max_num_pages,
            'type'      => 'array',
            'prev_next' => true,
            'prev_text' => '‹',
            'next_text' => '›',
        ]);

        if (!empty($links) && is_array($links)) {
            echo '<nav class="wr-pagination" aria-label="Pagination"><ul class="page-numbers">';
            foreach ($links as $l) {
                echo '<li>' . $l . '</li>';
            }
            echo '</ul></nav>';
        }
    }

    return ob_get_clean();
}
add_shortcode('wr_showcase', 'wr_showcase_shortcode');
// Old runtime shortcode alias
add_shortcode('hmps_showcase', 'wr_showcase_shortcode');


/**
 * Admin: auto-fill preset fields on selection (edit screen)
 */
add_action('admin_enqueue_scripts', function($hook){
    global $typenow;
    if ($typenow !== 'wr_showcase') return;

    // Only on post edit/new screens
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $lib = function_exists('wr_sc_get_preset_library') ? wr_sc_get_preset_library() : [];
    // Normalize library to the exact keys JS expects
    $norm = [];
    foreach ($lib as $k => $p) {
        if (!is_array($p)) continue;
        $norm[$k] = [
            'name' => $p['name'] ?? $k,
            'slug' => $p['slug'] ?? $k,
            'primary' => $p['primary'] ?? '',
            'dark' => $p['dark'] ?? '',
            'bg' => $p['bg'] ?? '',
            'footer' => $p['footer'] ?? '',
            'link' => $p['link'] ?? '',
            'body_font' => $p['body_font'] ?? '',
            'heading_font' => $p['heading_font'] ?? '',
            'focus_keyword' => $p['focus_keyword'] ?? '',
        ];
    }

    wp_enqueue_script('wr-sc-preset-autofill', plugins_url('admin-preset-autofill.js', __FILE__), [], '1.0.0', true);
    wp_localize_script('wr-sc-preset-autofill', 'WR_SC_PRESET_LIB', $norm);
});
