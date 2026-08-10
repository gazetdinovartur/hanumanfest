<?php
$schedules = get_posts([
  'post_type' => 'program',
  'posts_per_page' => 3,
  'orderby' => 'menu_order',
  'order' => 'ASC'
]);

if ($schedules):
?>
<div class="row g-4 justify-content-center text-white">
  <?php foreach ($schedules as $day): 
    $bg = get_the_post_thumbnail_url($day->ID, 'large');
  ?>
  <div class="col-md-4 col-sm-12">
    <div class="schedule-card" style="background-image: url('<?php echo esc_url($bg); ?>');">
      <div class="schedule-overlay"></div>
      <div class="schedule-content">
        <h3 class="schedule-title mb-3"><?php echo esc_html(get_the_title($day)); ?></h3>
        <div class="schedule-text">
          <?php echo wpautop($day->post_content); ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>