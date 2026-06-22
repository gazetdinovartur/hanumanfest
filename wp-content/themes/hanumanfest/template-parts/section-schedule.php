<?php
/**
 * Reusable schedule widget shell.
 *
 * @var array $args {
 *     @type string $layout 'compact' (homepage) or 'full' (dedicated page)
 * }
 */
$layout = isset($args['layout']) && $args['layout'] === 'full' ? 'full' : 'compact';
?>
<div class="hf-schedule" data-hf-schedule data-layout="<?php echo esc_attr($layout); ?>">
  <div class="hf-schedule__loading text-center py-4">
    <div class="spinner-border text-brand-main" role="status">
      <span class="visually-hidden">Загрузка расписания…</span>
    </div>
  </div>
</div>
<p class="hf-schedule__note text-muted text-center small mt-3 mb-0">
  Возможны корректировки в расписании и расширение программы.
</p>
