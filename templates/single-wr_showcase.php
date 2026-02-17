<?php
if ( ! defined('ABSPATH') ) exit;
get_header();

global $post;
$post_id = $post->ID;

$demo_url    = get_post_meta($post_id, '_wr_demo_url', true);
$live_url    = get_post_meta($post_id, '_wr_live_preview_url', true);
$gallery     = get_post_meta($post_id, '_wr_gallery_ids', true);

$demo_url = $demo_url ? esc_url($demo_url) : '';
$live_url = $live_url ? esc_url($live_url) : '';
$cta_url = $live_url ?: $demo_url;

$ids = [];
if (!empty($gallery)) {
  $ids = array_filter(array_map('absint', explode(',', $gallery)));
}

// Category label (first term)
$terms = wp_get_post_terms($post_id, 'wr_showcase_cat');
$cat_name = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : 'E-Ticaret';

// Optional: preset tokens (colors/fonts) from sidebar meta box
$preset_tokens = function_exists('wr_sc_get_preset_tokens') ? wr_sc_get_preset_tokens($post_id) : [];

// Build CSS vars from saved tokens (default skin)
$wr_vars = [];
if (!empty($preset_tokens['primary'])) $wr_vars[] = "--wr-primary:" . esc_attr($preset_tokens['primary']);
if (!empty($preset_tokens['dark'])) $wr_vars[] = "--wr-dark:" . esc_attr($preset_tokens['dark']);
if (!empty($preset_tokens['bg'])) $wr_vars[] = "--wr-bg:" . esc_attr($preset_tokens['bg']);
if (!empty($preset_tokens['footer'])) $wr_vars[] = "--wr-footer:" . esc_attr($preset_tokens['footer']);
if (!empty($preset_tokens['link'])) $wr_vars[] = "--wr-link:" . esc_attr($preset_tokens['link']);
if (!empty($preset_tokens['body_font'])) $wr_vars[] = "--wr-body-font:" . esc_attr($preset_tokens['body_font']);
if (!empty($preset_tokens['heading_font'])) $wr_vars[] = "--wr-heading-font:" . esc_attr($preset_tokens['heading_font']);

$title = get_the_title($post_id);

?>
<div class="wr-showcase-landing wr-mode-full" data-post-id="<?php echo esc_attr($post_id); ?>">
  <style>
    <?php if (!empty($wr_vars)) : ?>
      /* Define preset variables at page level so footer/wrappers can read them */
      body.single-wr_showcase{<?php echo implode(';', $wr_vars); ?>;}
      /* Also define on landing container (useful if theme resets vars on inner containers) */
      .wr-showcase-landing{<?php echo implode(';', $wr_vars); ?>;}
    <?php endif; ?>

    /* Minimal theming hooks (safe overrides) */
    /* Background: themes often paint wrappers (#page/.site), so cover common containers */
    html body.single-wr_showcase,
    body.single-wr_showcase #page,
    body.single-wr_showcase .site,
    body.single-wr_showcase .site-content,
    body.single-wr_showcase .content-area{
      background-color: var(--wr-bg, transparent) !important;
    }
    /* Also paint our landing container so it visibly changes even if theme wrappers don't */
    .wr-showcase-landing{
      background-color: var(--wr-bg, transparent) !important;
      color: var(--wr-dark, inherit);
      font-family: var(--wr-body-font, inherit);
    }
    .wr-showcase-landing h1,.wr-showcase-landing h2,.wr-showcase-landing h3{
      font-family: var(--wr-heading-font, inherit);
    }
    .wr-showcase-landing a{ color: var(--wr-link, inherit); }
    .wr-showcase-landing-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding: 10px 16px;
      border-radius: 999px;
      text-decoration:none;
      background: var(--wr-primary, #111) !important;
      color: #fff !important;
      border: 1px solid transparent;
      font-weight: 700;
      line-height: 1;
    }
    .wr-showcase-landing-btn:hover{
      background: var(--wr-dark, var(--wr-primary, #111)) !important;
      color: #fff;
    }
    /* Top-right CTA placement (safe, single page only) */
    .wr-showcase-landing-head{ position: relative; }
    .wr-showcase-landing-cta{ position: absolute; top: 0; right: 0; }
    @media (max-width: 768px){
      .wr-showcase-landing-cta{ position: static; margin-top: 14px; text-align: center; }
    }

    .wr-seo-cta{ margin: 14px 0 0; }

    .wr-seo-figure{ margin: 18px 0; }
    .wr-seo-figure img{ width:100%; height:auto; border-radius:14px; display:block; }
    .wr-seo-figure figcaption{ font-size: 13px; opacity: .75; margin-top: 8px; }


    /* Top bar / Header color (scoped to this showcase page only) */
    body.single-wr_showcase .hmpro-region-header_top,
    body.single-wr_showcase .hmpro-header-builder .hmpro-region-header_top .hmpro-builder-row{
      background-color: var(--wr-primary, inherit) !important;
    }
    body.single-wr_showcase .hmpro-region-header_top a{
      color: #fff !important;
    }

    /* Main heading picks preset color */
    body.single-wr_showcase .wr-showcase-landing-head h1,
    body.single-wr_showcase .entry-title{
      color: var(--wr-dark, inherit) !important;
    }


    /* Footer background (only affects this single showcase page) */
    body.single-wr_showcase footer,
    body.single-wr_showcase #colophon,
    body.single-wr_showcase .site-footer{
      background-color: var(--wr-footer, transparent) !important;
    }
  </style>
  <div class="wr-showcase-landing-inner">

    <header class="wr-showcase-landing-head">
      <div class="wr-showcase-kicker"><?php echo esc_html($cat_name); ?> Demo</div>
      <h1 class="wr-showcase-landing-title"><?php echo esc_html($title); ?></h1>
      <?php if (has_excerpt($post_id)) : ?>
        <p class="wr-showcase-landing-desc"><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
      <?php endif; ?>

      <?php if ($cta_url): ?>
        <div class="wr-showcase-landing-cta">
          <a class="wr-showcase-landing-btn" href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener noreferrer">Canlı Önizle</a>
        </div>
      <?php endif; ?>
    </header>

    <div class="wr-showcase-landing-gallery">
      <?php
      $shot_index = 1;
      // Featured image first (optional)
      if (has_post_thumbnail($post_id)) {
        $thumb = get_the_post_thumbnail(
          $post_id,
          'full',
          [
            'class' => 'wr-shot-img',
            'alt' => function_exists('wr_sc_build_screenshot_alt') ? wr_sc_build_screenshot_alt($post_id, $shot_index, 'ana sayfa') : get_the_title($post_id),
            'loading' => 'eager',
            'decoding' => 'async',
          ]
        );
        echo '<div class="wr-shot"><div class="wr-browserbar"><span></span><span></span><span></span></div>' . $thumb . '</div>';
        $shot_index++;
      }

      foreach ($ids as $id) {
        $img = wp_get_attachment_image(
          $id,
          'full',
          false,
          [
            'class' => 'wr-shot-img',
            'alt' => function_exists('wr_sc_build_screenshot_alt') ? wr_sc_build_screenshot_alt($post_id, $shot_index, 'sayfa') : get_the_title($post_id),
            'loading' => 'lazy',
            'decoding' => 'async',
          ]
        );
        if ($img) {
          echo '<div class="wr-shot"><div class="wr-browserbar"><span></span><span></span><span></span></div>' . $img . '</div>';
          $shot_index++;
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

    <?php echo function_exists('wr_sc_render_auto_seo_block') ? wr_sc_render_auto_seo_block($post_id) : ''; ?>

  </div>
</div>

<?php
// FAQ Schema (JSON-LD)
if (function_exists('wr_sc_faq_schema_jsonld')) {
  $schema = wr_sc_faq_schema_jsonld($post_id);
  echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>

<?php
get_footer();
