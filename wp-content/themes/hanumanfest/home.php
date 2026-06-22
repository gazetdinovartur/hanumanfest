<?php
/*
 * Template Name: Главная — Хануман Фест
 */
get_header();

$hero_date       = get_field('event_dates');
$hero_title_1    = get_field('title_main');
$hero_title_2    = get_field('title_secondary');
$hero_image      = get_field('title_image');
$masters_image   = get_field('masters_image');
$hero_contacts   = get_field('contacts');
$features        = get_field('features');
?>

<section class="hero d-flex flex-column justify-content-center align-items-center text-center text-white" id="hero"
         style="background-image: url('<?php echo esc_url($hero_image); ?>'); background-size: cover; background-position: center;">
           <div class="hero-overlay"></div>

    <div class="hero-content container">
        <?php if ($hero_date): ?>
            <p class="hero-date text-brand-accent fw-bold fs-1"><?php echo esc_html($hero_date); ?></p>
            <p class="date-dot">•</p>
        <?php endif; ?>

        <?php if ($hero_title_1): ?>
            <p class="hero-subtitle-top fs-3"><?php echo esc_html($hero_title_1); ?></p>
        <?php endif; ?>

        <?php if ($hero_title_2): ?>
            <h1 class="hero-title text-brand-main display-2 fw-bold"><?php the_title()?></h1>
        <?php endif; ?>

        <div class="hero-underline mx-auto"></div>
        <p class="hero-subtitle-bottom fs-4"><?php echo $hero_title_2 ?></p>
        <br><br>
        <a href="#register" class="btn btn-lg btn-brand-main text-white fw-bold px-4 py-3 mt-3">Участвовать</a>
    </div>

    <div class="hero-footer">
        <a href="#about" class="scroll-down">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="19 9 12 16 5 9"></polyline>
            </svg>
        </a>
    </div>
</section>

<!-- ABOUT -->
<section class="about py-5 text-center" id="about">
    <div class="container">
    <?php the_content()?>
    </div>
<section class="py-5 festival-highlights container">
  <div class="highlights-grid tiles">

    <div class="tiles-column left">
      <div class="tile big">Море йоги, музыки и творчества</div>
      <div class="tile big">Экологически-чистое место среди полей, озёр и лесов</div>
      <div class="tile big">Проживание в палатках и домиках — одна большая семья</div>
      <div class="tile big">Детская зона в течение дня</div>
      <div class="tile big">Несколько площадок для практик в месте силы</div>
      <div class="tile big">Кухня, столовая, детская площадка, баня</div>
      <div class="tile big">Вегетарианское питание от шеф-повара</div>
      <div class="tile big">
        Другие активности — правИло, ходули, восточный базар, баня и пармастера
      </div>
    </div>

    <div class="tiles-column right">
      <div class="tile">Углехождение и Гвоздестояние</div>
      <div class="tile">Хатха и Раджа-йога</div>
      <div class="tile">Кундалини и Гонг-сатори</div>
      <div class="tile">Женский класс и Ароматерапия</div>
      <div class="tile">Дыхание и Шаманские практики</div>
      <div class="tile">Ошо и Телесная терапия</div>
      <div class="tile">Пранаяма и Медитация</div>
      <div class="tile">Регрессия и Саунд-хиллинг</div>
      <div class="tile">Парная йога и Мандала</div>
      <div class="tile">Трайбл и Йога-вокал</div>
      <div class="tile">Лекции и Мастер-классы</div>
      <div class="tile">Ягья — огненная церемония</div>
      <div class="tile">Джемы у костра</div>
      <div class="tile">Киртан и Бхаджаны</div>
      <div class="tile">Хороводы и Барабаны</div>
      <div class="tile">Экстатик-дэнс и Концерт</div>
      <div class="tile wide">ХОЛИ</div>
    </div>

  </div>
</section>
</section>

<?php
$video_left  = get_field('promo_video_left');
$video_right = get_field('promo_video_right');
if ($video_left && $video_right):
?>
<section class="promo-video-split">
  <div class="promo-video promo-video--left">
    <video src="<?= esc_url($video_left); ?>"autoplay muted loop controls playsinline preload="metadata"></video>
  </div>
  <div class="promo-video promo-video--right">
    <video src="<?= esc_url($video_right); ?>"autoplay muted loop controls playsinline preload="metadata"></video>
  </div>
</section>
<?php endif; ?>

<section class="special-guests py-5" id="guests">
  <div class="container">
    <h2 class="text-brand-main text-center mb-5">Специальные гости</h2>
    <?php get_template_part('template-parts/section', 'guests'); ?>
  </div>
</section>

<section class="special-guests py-5  bg-light" id="musicians">
  <div class="container">
    <h2 class="text-brand-main text-center mb-5">Музыканты</h2>
    <?php get_template_part('template-parts/section', 'musicians'); ?>
  </div>
</section>

<section class="masters-section py-5" id="masters">
<!-- style="background-image: url('<?php echo esc_url($masters_image); ?>'); background-size: cover; background-position: center;" -->
<!-- <div class="masters-overlay"></div> -->
  <div class="container">
    <h2 class="text-brand-main text-center mb-5">Мастера и практики</h2>
    <?php get_template_part('template-parts/section', 'masters'); ?>
  </div>

    <div class="modal-overlay"></div>
    <button class="modal-close" aria-label="Закрыть">&times;</button>
    <div class="modal">
    <div class="modal-inner">
        <div class="modal-content"></div>
    </div>
</section>

<section class="schedule-section py-5 bg-light" id="program">
  <div class="container">
    <h2 class="text-center text-brand-main mb-5">Программа фестиваля</h2>
    <?php //get_template_part('template-parts/section', 'program'); ?>
    <h4 class="text-brand-accent mt-5" style="text-align: center;"><a href="https://clck.su/peQdI" class="contact-link" target="blank">Подробное расписание Хануман-Феста 2026</a> <br /> Возможны корректировки в расписании и расширение программы.
    </h4>
    </div>
</section>

<section class="how-it-was py-5 bg-light" id="late">
  <h2 class="text-brand-main text-center mb-5">Как это было</h2>
    <?php get_template_part('template-parts/section', 'carousel'); ?>
</section>

<?php get_template_part('template-parts/section', 'infoblocks'); ?>

<section class="py-5" id="cost">
  <div class="container">
    <h2 class="text-center text-brand-main mb-5">Ценность</h2>

    <div class="accordion" id="pricingAccordion">

      <!-- До 1 марта -->
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMarch1">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarch1" aria-expanded="false" aria-controls="collapseMarch1">
            При оплате до 10 марта
          </button>
        </h2>
        <div id="collapseMarch1" class="accordion-collapse collapse" aria-labelledby="headingMarch1" data-bs-parent="#pricingAccordion">
          <div class="accordion-body">
            <ul class="list-unstyled mb-0">
              <li><strong>3600 ₽</strong> — своё жильё (домик или палатка), без питания</li>
              <li><strong>6400 ₽</strong> — своё жильё, с питанием</li>
              <li><strong>4600 ₽</strong> — наша палатка, без питания</li>
              <li><strong>7400 ₽</strong> — наша палатка, с питанием</li>
              <li class="mt-3"><strong>2000 ₽</strong> — участие 1 день, без питания (без ночёвки)</li>
              <li><strong>3400 ₽</strong> — участие 1 день, с питанием (без ночёвки)</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- До 15 апреля -->
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingApril15">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseApril15" aria-expanded="false" aria-controls="collapseApril15">
            При оплате до 15 апреля
          </button>
        </h2>
        <div id="collapseApril15" class="accordion-collapse collapse" aria-labelledby="headingApril15" data-bs-parent="#pricingAccordion">
          <div class="accordion-body">
            <ul class="list-unstyled mb-0">
              <li><strong>4200 ₽</strong> — своё жильё, без питания</li>
              <li><strong>7000 ₽</strong> — своё жильё, с питанием</li>
              <li><strong>5200 ₽</strong> — наша палатка, без питания</li>
              <li><strong>8000 ₽</strong> — наша палатка, с питанием</li>
              <li class="mt-3"><strong>2400 ₽</strong> — 1 день, без питания (без ночёвки)</li>
              <li><strong>3800 ₽</strong> — 1 день, с питанием (без ночёвки)</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- До 1 июня -->
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingJune1">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJune1" aria-expanded="false" aria-controls="collapseJune1">
            При оплате до 1 июня
          </button>
        </h2>
        <div id="collapseJune1" class="accordion-collapse collapse" aria-labelledby="headingJune1" data-bs-parent="#pricingAccordion">
          <div class="accordion-body">
            <ul class="list-unstyled mb-0">
              <li><strong>4800 ₽</strong> — своё жильё, без питания</li>
              <li><strong>7600 ₽</strong> — своё жильё, с питанием</li>
              <li><strong>5800 ₽</strong> — наша палатка, без питания</li>
              <li><strong>8600 ₽</strong> — наша палатка, с питанием</li>
              <li class="mt-3"><strong>2800 ₽</strong> — 1 день, без питания (без ночёвки)</li>
              <li><strong>4200 ₽</strong> — 1 день, с питанием (без ночёвки)</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- После 1 июня -->
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingAfterJune">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAfterJune" aria-expanded="true" aria-controls="collapseAfterJune">
            При оплате после 1 июня
          </button>
        </h2>
        <div id="collapseAfterJune" class="accordion-collapse collapse show" aria-labelledby="headingAfterJune" data-bs-parent="#pricingAccordion">
          <div class="accordion-body">
            <ul class="list-unstyled mb-0">
              <li><strong>5400 ₽</strong> — своё жильё, без питания</li>
              <li><strong>8200 ₽</strong> — своё жильё, с питанием</li>
              <li><strong>6400 ₽</strong> — наша палатка, без питания</li>
              <li><strong>9200 ₽</strong> — наша палатка, с питанием</li>
              <li class="mt-3"><strong>3200 ₽</strong> — 1 день, без питания (без ночёвки)</li>
              <li><strong>4600 ₽</strong> — 1 день, с питанием (без ночёвки)</li>
            </ul>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div class="container">

    <h4 class="text-brand-main mt-5">🔥 Скидки:</h4>
    <ul>
      <li>50% для детей и подростков до 16 лет включительно</li>
      <li>N% для семей и групп товарищей, где N- количество участников группы. (Скидка действует при централизованной оплате за всю группу, дети и подростки до 16 лет в количество участников не входят)</li>
    </ul>
    <h4 class="text-brand-accent mt-5" style="text-align: center;">Тем, кто бронирует нашу палатку, предоставляется палатка, спальник и коврик. Палатка без соседей. На каждого участника по палатке, на семьи и группы друзей палатки большего размера.
    </h4>
    </div>
</section>
<section class="register d-flex flex-column justify-content-center align-items-center" id="register">
    <div class="container">
        <h2 class="text-brand-main text-center mb-5">Регистрация и оплата</h2>
        <?php echo do_shortcode('[forminator_form id="156"]'); ?>
    </div>
</section>

<section class="reviews-section hf-reviews-carousel py-5">
    <h2 class="text-brand-main text-center mb-5">Отзывы участников</h2>
    <?php get_template_part('template-parts/section', 'reviews'); ?>
</section>

<section class="faq-section">
  <div class="container  py-5">
      <h2 class="text-brand-main text-center mb-3">Вопросы и ответы</h2>
      <?php get_template_part('template-parts/section-faq', 'faq'); ?>
  </div>
</section>

<div class="container">
<h4 class="text-brand-accent mt-5 mb-5" style="text-align: center;">Приглашаются к сотрудничеству мастера и умельцы для участия в ярмарке, а также, ведущие мастер-классов. Пишите: <a href="https://vk.com/skazka_108" class="contact-link">vk.com/skazka_108</a> или <a href="https://t.me/+79222108041" class="contact-link">+7 9222 108 041 TG </a></h4>
</div>

<!-- SCROLL UP -->
<a href="#hero" class="scroll-up" id="scroll-up">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
        <polyline points="5 15 12 8 19 15"></polyline>
    </svg>
</a>

<?php get_footer(); ?>