<?php
/*
 * Template Name: Расписание
 *
 * Как создать страницу:
 * 1. Админка → Страницы → Добавить новую (например, «Программа»).
 * 2. В блоке «Атрибуты страницы» выбрать шаблон «Расписание».
 * 3. Задать постоянную ссылку: /program/ или /schedule/.
 * 4. Опубликовать — виджет подтянет данные из API автоматически.
 */
get_header();
?>

<main class="page-content py-5">
  <div class="container hf-schedule-page">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
      <h1 class="text-brand-main text-center mb-4"><?php the_title(); ?></h1>
      <?php if (get_the_content()) : ?>
        <div class="text-center mb-4"><?php the_content(); ?></div>
      <?php endif; ?>
    <?php endwhile; endif; ?>

    <?php get_template_part('template-parts/section', 'schedule', ['layout' => 'full']); ?>
  </div>
</main>

<?php get_footer(); ?>
