<?php
$infos = get_posts([
  'post_type' => 'info_block',
  'posts_per_page' => -1,
  'orderby' => 'menu_order',
  'order' => 'ASC'
]);

if ($infos):
?>
  <div class="container" id="infoblocks">
    <?php foreach ($infos as $index => $post): setup_postdata($post); ?>
      <?php
        $image = get_the_post_thumbnail_url($post->ID, 'large');
        $reverse = $index % 2 === 1 ? 'flex-row-reverse' : '';
        $has_image = !empty($image);
      ?>

      <section class="info-block row g-0 <?php echo esc_attr($reverse); ?>">
              <h2 class="text-brand-main mt-5 text-brand-main--info text-center <?php echo $has_image ? '' : 'text-brand-xs'?>"><?php the_title(); ?></h2>

        <?php if ($has_image): ?>
          <div class="col-md-6 info-image" style="background-image: url('<?php echo esc_url($image); ?>');"></div>
          <div class="col-md-6 info-text fade-in">
        <?php else: ?>
          <div class="col-12 info-text fade-in text-black">
        <?php endif; ?>
            <div class="info-inner p-4">
              <div class="info-content"><?php the_content(); ?></div>
            </div>
          </div>
      </section>

    <?php endforeach; wp_reset_postdata(); ?>
  </div>
<?php endif; ?>