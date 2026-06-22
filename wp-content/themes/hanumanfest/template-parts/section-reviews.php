<?php
$reviews = new WP_Query([
  'post_type' => 'review',
  'posts_per_page' => -1,
  'orderby' => 'date',
  'order' => 'DESC'
]);

if ($reviews->have_posts()):
  $uniq = 'reviewsCarousel' . uniqid();
?>

  <button class="hf-gallery-arrow hf-gallery-arrow--reviews hf-gallery-prev" aria-label="Предыдущее">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"></polyline>
    </svg>
  </button>

  <div class="container">
    <div id="<?php echo esc_attr($uniq); ?>">
      <div class="hf-gallery-track">
        <?php while ($reviews->have_posts()): $reviews->the_post(); ?>
          <?php
            $avatar = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
            $name = get_the_title();
            $excerpt = wp_trim_words(get_the_content(), 25);
          ?>
          <div class="hf-review-card fade-in">
            <div class="hf-review-inner text-center text-white">
              <?php if ($avatar): ?>
                <div class="hf-review-avatar mx-auto mb-3" style="background-image:url('<?php echo esc_url($avatar); ?>')"></div>
              <?php endif; ?>
              <h5 class="hf-review-name mb-2"><?php echo esc_html($name); ?></h5>
              <p class="hf-review-excerpt mb-3"><?php echo esc_html($excerpt); ?></p>
              <button class="hf-review-readmore btn btn-outline-light btn-sm">
                Читать полностью
              </button>
              <div class="review-modal-data" style="display:none;">
                <div class="review-content">
                  <?php the_content(); ?>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
    </div>
  </div>
  <button class="hf-gallery-arrow hf-gallery-arrow--reviews hf-gallery-next" aria-label="Следующее">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"></polyline>
    </svg>
  </button>
<?php endif; ?>