<?php
$faqs = get_posts([
  'post_type' => 'faq',
  'posts_per_page' => -1,
  'orderby' => 'menu_order',
  'order' => 'ASC'
]);
if ($faqs):
  $uniq = 'faq' . uniqid();
?>
  <div class="container">
    <div class="faq-list" id="<?php echo esc_attr($uniq); ?>">
      <?php foreach ($faqs as $faq): ?>
        <div class="faq-item border-bottom pt-3">
          <button class="faq-question w-100 text-start d-flex justify-content-between align-items-center">
            <span><?php echo esc_html($faq->post_title); ?></span>
            <span class="faq-icon">+</span>
          </button>
          <div class="faq-answer mt-2">
            <?php echo wpautop($faq->post_content); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
<?php endif; ?>