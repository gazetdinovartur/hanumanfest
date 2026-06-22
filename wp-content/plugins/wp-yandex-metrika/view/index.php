<div class="yam">
    <div class="yam__wrapper">
        <div class="yam__header">
<!--            <img class="yam__logo" src="--><?php //echo esc_attr(YaMetrikaHelpers::getAssetUrl('yam-logo.svg')); ?><!--" alt="Yandex.Metrica">-->
            <img class="yam__logo" src="<?php echo esc_attr(YaMetrikaHelpers::getAssetUrl(__('metrica-en.svg', 'wp-yandex-metrika'))); ?>" alt="Yandex.Metrica">
        </div>
        <div class="yam__description">
            <?php
            printf(__('To get started, specify the relevant Yandex.Metrica tag numbers and fill in the company information. You can find the tag number on the <a href="https://metrika.yandex.com/list" target="_blank">"My tags"</a> page next to the site address in Yandex.Metrica. <br>You can create a new tag in the Yandex.Metrica interface via the <a href="https://metrika.yandex.com/" target="_blank">link</a>.', 'wp-yandex-metrika'), 'https://metrika.yandex.ru/list', 'https://metrika.yandex.ru/'); ?>
<!--            printf(__('Чтобы начать работу, укажите номера нужных счетчиков Метрики и данные о компании. Номер счетчика находится на странице <a target="_blank" href="https://metrika.yandex.ru/list?">Мои счетчики</a> рядом с адресом сайта. Создать новый счетчик можно в интерфейсе Метрики', 'wp-yandex-metrika'), 'https://metrika.yandex.ru/list', 'https://metrika.yandex.ru/'); ?>-->
        </div>
        <div class="yam__settings">
        <form method="POST" action="options.php">
            <?php
            // slug страницы на которой выводится форма,
            // совпадает с названием группы ($option_group) в API опций
            settings_fields( YAM_PAGE_SLUG );

            // slug страницы на которой выводится форма
            YaMetrikaBackend::do_settings_sections( YAM_PAGE_SLUG );
            ?>
            <div class="yam-spoiler">
                <div class="yam-buttons-wrap">
                    <?php submit_button(__('Save', 'wp-yandex-metrika'));?>
                    <div class="yam-spoiler__btn yam-spoiler__btn--show active"><?php echo __('View logs', 'wp-yandex-metrika'); ?></div>
                    <div class="yam-spoiler__btn yam-spoiler__btn--hide"><?php echo __('Hide logs', 'wp-yandex-metrika'); ?></div>
                </div>
                <div class="yam-spoiler__content">
                    <div class="yam-copy-button"></div>
                    <div class="yam-logs">
                        <?php YaMetrikaLogs::getInstance()->printLogs(); ?>
                    </div>
                </div>
            </div>
        </form>
        </div>
    </div>
</div>
