<?php
if ( ! defined('ABSPATH') ) exit;
get_header();

global $post;
$post_id = $post->ID;

$demo_url    = get_post_meta($post_id, '_wr_demo_url', true);
$gallery     = get_post_meta($post_id, '_wr_gallery_ids', true);
$preset_name = get_post_meta($post_id, '_wr_preset_name', true);
$preset_key  = get_post_meta($post_id, '_wr_preset_key', true);

$demo_url = $demo_url ? esc_url($demo_url) : '';

$ids = [];
if (!empty($gallery)) {
  $ids = array_filter(array_map('absint', explode(',', $gallery)));
}

// Category label (first term)
$terms = wp_get_post_terms($post_id, 'wr_showcase_cat');
$cat_name = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : 'E-Ticaret';

/**
 * Preset profile analyzer (tone/style keywords)
 */
function wr_sc_preset_profile($preset_key, $preset_name){
  $s = strtolower(trim($preset_key ?: $preset_name));

  $map = [
    'rose' => ['tone'=>'zarif ve feminen', 'style'=>'pastel tonlu butik tasarım', 'mood'=>'romantik ve yumuşak'],
    'blush' => ['tone'=>'nazik ve modern', 'style'=>'soft-pastel mağaza arayüzü', 'mood'=>'sıcak ve samimi'],
    'champagne' => ['tone'=>'lüks ve sofistike', 'style'=>'premium butik vitrin tasarımı', 'mood'=>'elit ve şık'],
    'nude' => ['tone'=>'minimal ve sade', 'style'=>'temiz çizgili modern arayüz', 'mood'=>'dingin ve güven veren'],
    'navy' => ['tone'=>'güçlü ve kurumsal', 'style'=>'minimal ve prestijli arayüz', 'mood'=>'ciddi ve profesyonel'],
    'steel' => ['tone'=>'modern ve sağlam', 'style'=>'net kontrastlı kurumsal tasarım', 'mood'=>'güven veren'],
    'graphite' => ['tone'=>'premium ve karizmatik', 'style'=>'koyu tonlu modern vitrin', 'mood'=>'iddialı ve güçlü'],
    'green' => ['tone'=>'doğal ve ferah', 'style'=>'modern ve nefes alan arayüz', 'mood'=>'organik ve güvenilir'],
    'olive' => ['tone'=>'doğal ve premium', 'style'=>'soft koyu tonlu arayüz', 'mood'=>'sakin ve kaliteli'],
    'lavender' => ['tone'=>'soft ve dengeli', 'style'=>'pastel odaklı butik tasarım', 'mood'=>'rahatlatıcı'],
    'mauve' => ['tone'=>'zarif ve modern', 'style'=>'butik moda vitrin dili', 'mood'=>'sofistike'],
    'charcoal' => ['tone'=>'minimal ve kurumsal', 'style'=>'koyu-gri modern arayüz', 'mood'=>'ciddi'],
    'midnight' => ['tone'=>'premium ve kurumsal', 'style'=>'koyu tonlu prestijli vitrin', 'mood'=>'lüks'],
  ];

  foreach($map as $k => $v){
    if (strpos($s, $k) !== false) return $v;
  }

  return ['tone'=>'modern', 'style'=>'çağdaş e-ticaret tasarımı', 'mood'=>'profesyonel'];
}

$profile = wr_sc_preset_profile($preset_key, $preset_name);

// SEO content generator (safe, non-spam, 350-550 words range)
$title = get_the_title($post_id);
$preset_label = $preset_name ? $preset_name : ($preset_key ? $preset_key : 'Preset');

?>
<div class="wr-showcase-landing wr-mode-full">
  <div class="wr-showcase-landing-inner">

    <header class="wr-showcase-landing-head">
      <div class="wr-showcase-kicker"><?php echo esc_html($cat_name); ?> Demo</div>
      <h1 class="wr-showcase-landing-title"><?php echo esc_html($title); ?></h1>
      <?php if (has_excerpt($post_id)) : ?>
        <p class="wr-showcase-landing-desc"><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
      <?php endif; ?>

      <div class="wr-showcase-meta">
        <?php if ($preset_name || $preset_key): ?>
          <span class="wr-pill"><?php echo esc_html($preset_label); ?></span>
          <span class="wr-pill"><?php echo esc_html($profile['tone']); ?></span>
        <?php endif; ?>
      </div>

      <?php if ($demo_url): ?>
        <div class="wr-showcase-landing-cta">
          <a class="wr-showcase-landing-btn" href="<?php echo esc_url($demo_url); ?>" target="_blank" rel="noopener noreferrer">Canlı Önizle</a>
        </div>
      <?php endif; ?>
    </header>

    <div class="wr-showcase-landing-gallery">
      <?php
      // Featured image first (optional)
      if (has_post_thumbnail($post_id)) {
        echo '<div class="wr-shot"><div class="wr-browserbar"><span></span><span></span><span></span></div>'. get_the_post_thumbnail($post_id, 'full') .'</div>';
      }

      foreach ($ids as $id) {
        $img = wp_get_attachment_image($id, 'full');
        if ($img) {
          echo '<div class="wr-shot"><div class="wr-browserbar"><span></span><span></span><span></span></div>'.$img.'</div>';
        }
      }

      // Fallback to content if no images
      if (!has_post_thumbnail($post_id) && empty($ids)) {
        echo '<div class="wr-showcase-landing-fallback">';
        while (have_posts()) { the_post(); the_content(); }
        echo '</div>';
      }
      ?>
    </div>

    <section class="wr-seo-block">
      <h2><?php echo esc_html($cat_name); ?> E-Ticaret Demo Sitesi – <?php echo esc_html($preset_label); ?> Tasarım</h2>

      <p>
        Bu demo sayfası, <strong><?php echo esc_html($cat_name); ?></strong> sektöründe satış yapmak isteyen markalar için hazırlanmış örnek bir e-ticaret vitrinidir.
        <strong><?php echo esc_html($profile['tone']); ?></strong> bir tasarım dili ve <strong><?php echo esc_html($profile['style']); ?></strong> yaklaşımıyla,
        kullanıcıların ürünleri hızlıca keşfetmesini ve satın alma adımlarını sorunsuz tamamlamasını hedefler.
      </p>

      <p>
        Landing sayfasında gördüğünüz ekran görüntüleri, mağazanın ana sayfa akışını “aşağı kaydırıyormuşsunuz” hissiyle anlatır.
        Böylece ziyaretçi; vitrin alanlarını, ürün liste düzenini, ürün detay mantığını ve genel görsel dilini tek sayfada net biçimde değerlendirir.
        <?php if ($demo_url): ?>Dilerseniz üstteki <strong>Canlı Önizle</strong> butonuyla gerçek demoyu da açabilirsiniz.<?php endif; ?>
      </p>

      <h3>Bu Demoda Neler Var?</h3>
      <ul>
        <li>Modern ana sayfa ve vitrin alanları</li>
        <li>Kategori / ürün liste deneyimi</li>
        <li>Ürün detay sayfası hissi (görseller ve içerik akışı)</li>
        <li>Mobil uyumlu düzen mantığı</li>
        <li>SEO uyumlu yapı ve performans odaklı yerleşim</li>
      </ul>

      <p>
        <strong><?php echo esc_html($preset_label); ?></strong> preset’i, markanın algısını güçlendirmek için renk/typography dengesini hedefleyen bir kurgu sunar.
        Eğer bu demo stilini kendi sitenize uyarlamak isterseniz, aynı altyapı farklı preset’lerle hızlıca çeşitlendirilebilir.
      </p>
    </section>

  </div>
</div>

<?php
get_footer();
