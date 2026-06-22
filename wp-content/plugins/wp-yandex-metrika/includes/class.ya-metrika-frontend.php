<?php

class YaMetrikaFrontend
{
    protected static $instance;

    private function __construct()
    {
        add_action('init', [$this, 'onInit'], 1);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets'], 1);
        add_action('wp_enqueue_scripts', [$this, 'registerInlineScripts'], 2);
        add_action('wp_head', [$this, 'printPageCounters'], 15);
        add_action('wp_footer', [$this, 'printPagePixels'], 1);
    }

    public function onInit(){
		$domain = parse_url(home_url(), PHP_URL_HOST);
        $baseDomain = YaMetrikaHelpers::getBaseDomain($domain);
        $expires = time() + 31536000;
        $path = '/';
        $domain = '.'.$baseDomain;
		$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';

        if (isset($_COOKIE['_ym_uid'])) {
			setcookie('_ym_uid', $_COOKIE['_ym_uid'], $expires, $path, $domain, $secure);
		}

        if (isset($_COOKIE['_ym_d'])) {
			setcookie('_ym_d', time(), $expires, $path, $domain, $secure);
		}
    }

    public function registerAssets(){
        $options = YaMetrika::getInstance()->options;

        if(isset($options['counters']) && !empty($options['counters'])) {
            wp_enqueue_script(YAM_SLUG . '_YmEc', plugins_url('/assets/YmEc.min.js', YAM_FILE), [], YAM_VER);
            wp_enqueue_script(YAM_SLUG . '_frontend', plugins_url('/assets/frontend.min.js', YAM_FILE), ['jquery'], YAM_VER);
        }
    }

    public function registerInlineScripts(){
        $options = YaMetrika::getInstance()->options;
        $dataLayer = isset($options['data_layer']) ? $options['data_layer'] : "DataLayer";
        if(isset($options['counters']) && !empty($options['counters']))
            wp_add_inline_script(YAM_SLUG . '_YmEc', "window.tmpwpym={datalayername:'".$dataLayer."',counters:JSON.parse('".json_encode($options['counters'])."'),targets:JSON.parse('".json_encode($options['custom_targets'])."')};");
    }

    public function printPagePixels()
    {
        $options = YaMetrika::getInstance()->options;
        foreach ($options['counters'] as $counter) {
            if (empty($counter['number'])) continue;
            ?>
            <noscript>
                <div>
                    <img src="https://mc.yandex.ru/watch/<?php echo esc_attr($counter['number']); ?>" style="position:absolute; left:-9999px;" alt=""/>
                </div>
            </noscript>
            <?php
        }
    }

    public function printPageCounters()
    {
        $options = YaMetrika::getInstance()->options;

        $dataLayer = isset($options['data_layer']) ? $options['data_layer'] : "DataLayer";
        foreach ($options['counters'] as $counter) {
            if (empty($counter['number'])) continue;
            $this->printCounter($counter['number'], $counter['webvisor'], $dataLayer);
        }
    }

    public function printCounter($number, $webvisor = true, $dataLayer = "DataLayer")
    {
        ?>
        <!-- Yandex.Metrica counter -->
        <script type="text/javascript">
            (function (m, e, t, r, i, k, a) {
                m[i] = m[i] || function () {
                    (m[i].a = m[i].a || []).push(arguments)
                };
                m[i].l = 1 * new Date();
                k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
            })

            (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

            ym("<?php echo esc_js($number); ?>", "init", {
                clickmap: true,
                trackLinks: true,
                accurateTrackBounce: true,
                webvisor: <?php echo $webvisor ? 'true' : 'false'; ?>,
                ecommerce: "<?php echo $dataLayer;?>",
                params: {
                    __ym: {
                        "ymCmsPlugin": {
                            "cms": "wordpress",
                            "cmsVersion":"<?php echo esc_js(YaMetrikaHelpers::getWPVersion()); ?>",
                            "pluginVersion": "<?php echo esc_js(YAM_VER); ?>",
                            "ymCmsRip": "<?php echo esc_js(YaMetrikaHelpers::getClientRIP()) ; ?>"
                        }
                    }
                }
            });
        </script>
        <!-- /Yandex.Metrica counter -->
        <?php
    }


    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
