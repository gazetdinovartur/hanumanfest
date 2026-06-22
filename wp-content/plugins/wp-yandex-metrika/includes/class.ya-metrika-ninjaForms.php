<?php

class YaMetrikaNinjaForms
{
	protected static $instance;

    private function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets'], 1);
        add_action( 'admin_notices', [$this, 'printAlert'] );
    }

    public function registerAssets(){
        wp_enqueue_script(YAM_SLUG.'_ninja-forms', plugins_url('/assets/ninjaForms.min.js', YAM_FILE), ['jquery'], YAM_VER, true);
    }

    public function printAlert() {
        echo '<div class="notice notice-info is-dismissible"><p>'.__('To collect statistics from Ninja Forms subscription forms use the settings interface on the Yandex.Metrica plugin page.', 'wp-yandex-metrika').'</p></div>';
    }

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
