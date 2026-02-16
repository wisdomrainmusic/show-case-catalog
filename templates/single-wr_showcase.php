<?php
if ( ! defined('ABSPATH') ) exit;
get_header();

global $post;
$post_id = $post->ID;

$demo_url = get_post_meta($post_id, '_wr_demo_url', true);
$demo_url = $demo_url ? esc_url($demo_url) : '';

$gallery  = get_post_meta($post_id, '_wr_gallery_ids', true);
$ids = [];
if (!empty($gallery)) {
  $ids = array_filter(array_map('absint', explode(',', $gallery)));
}

?>
<div class="wr-showcase-landing wr-mode-full">
  <div class="wr-showcase-landing-inner">
    <header class="wr-showcase-landing-head">
      <h1 class="wr-showcase-landing-title"><?php echo esc_html(get_the_title($post_id)); ?></h1>
      <?php if (has_excerpt($post_id)) : ?>
        <p class="wr-showcase-landing-desc"><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
      <?php endif; ?>

      <?php if ($demo_url): ?>
        <div class="wr-showcase-landing-cta">
          <a class="wr-showcase-landing-btn" href="<?php echo $demo_url; ?>" target="_blank" rel="noopener noreferrer">Canlı Önizle</a>
        </div>
      <?php endif; ?>
    </header>

    <div class="wr-showcase-landing-gallery">
      <?php
      // If featured image exists, show it first
      if (has_post_thumbnail($post_id)) {
        echo '<div class="wr-shot">'. get_the_post_thumbnail($post_id, 'full') .'</div>';
      }

      // Then show gallery images stacked
      foreach ($ids as $id) {
        $img = wp_get_attachment_image($id, 'full');
        if ($img) {
          echo '<div class="wr-shot">'.$img.'</div>';
        }
      }

      // If none, show editor content fallback
      if (!has_post_thumbnail($post_id) && empty($ids)) {
        echo '<div class="wr-showcase-landing-fallback">';
        while (have_posts()) { the_post(); the_content(); }
        echo '</div>';
      }
      ?>
    </div>
  </div>
</div>
<?php
get_footer();
