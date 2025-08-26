<?php
/* ====================================
 * Plugin Name: Anti-Func
 * Description: Anti-Func - это мощный многофункциональный плагин для WordPress, который заменяет необходимость редактирования файла functions.php. Плагин предоставляет более 30 полезных функций для оптимизации, безопасности и улучшения вашего сайта, включая:

- Управление скриптами и стилями
- Кастомизацию административной панели
- Систему контроля доступа и регистрации
- Оптимизацию SEO и метаданных
- Инструменты для работы с пользователями
- Шорткоды для различного функционала
- Систему мониторинга посетителей
- Защиту от спама и ботов

Все функции реализованы безопасно и не конфликтуют между собой. Плагин особенно полезен для разработчиков, которые хотят добавить кастомный функционал без риска сломать сайт. 
 * Author: Robert Bennett
 * Plugin URI: https://github.com/RobertoBennett/anti-func
 * Version: 3.0.7
 * Text Domain: Anti-Func
 * ==================================== */

/*===================================================
 * Управление HLS.js (удаление/подключение)
 * ================================================== */
function my_deregister_hls_script() {
    wp_deregister_script('m3u-player-hls-js'); // Используем правильный ID!
}
add_action('wp_enqueue_scripts', 'my_deregister_hls_script', 1000);

function custom_enqueue_hls() {
    global $post;
    
    $content = '';
    if (is_a($post, 'WP_Post')) {
        $content = $post->post_content;
    }
    
    if (preg_match('/\[m3u_player\s.+?\]/', $content)) {
        wp_enqueue_script(
            'm3u-player-hls-js',
            '/wp-content/plugins/m3u-player/assets/js/hls.js',
            array(),
            null,
            true
        );
        
        wp_add_inline_script('m3u-player-hls-js', '
            document.addEventListener("DOMContentLoaded", function() {
                if (Hls.isSupported()) {
                    document.querySelectorAll("video[data-url]").forEach(video => {
                        const hls = new Hls();
                        hls.loadSource(video.dataset.url);
                        hls.attachMedia(video);
                    });
                }
            });
        ');
    }
}
add_action('wp_enqueue_scripts', 'custom_enqueue_hls', 10);

/*===================================================
 * Центрирование шорткодов
 * ================================================== */
if (!defined('ABSPATH')) {
    exit;
}

class CenterShortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    public function init() {
        add_shortcode('center', array($this, 'center_shortcode'));
        add_shortcode('center_block', array($this, 'center_block_shortcode'));
    }
    
    public function enqueue_styles() {
        wp_add_inline_style('wp-block-library', '
            .sc-center { 
                display: flex; 
                justify-content: center; 
                margin: 20px 0; 
            }
            .sc-center-block { 
                display: block; 
                text-align: center; 
                margin: 20px auto; 
            }
        ');
    }
    
    public function center_shortcode($atts, $content = null) {
        return '<div class="sc-center">' . do_shortcode($content) . '</div>';
    }
    
    public function center_block_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'width' => 'auto'
        ), $atts);
        
        $style = 'width: ' . esc_attr($atts['width']) . ';';
        
        return '<div class="sc-center-block" style="' . $style . '">' . do_shortcode($content) . '</div>';
    }
}

new CenterShortcodes();

/*===================================================
 * Запись времени входа пользователя
 * ================================================== */
function record_user_login_time($user_login, $user = null) {
    if (!$user || !is_object($user)) {
        $user = get_user_by('login', $user_login);
    }
    
    if ($user && is_object($user) && property_exists($user, 'ID') && !empty($user->ID)) {
        $previous_login = get_user_meta($user->ID, 'last_login', true);
        if ($previous_login) {
            update_user_meta($user->ID, 'previous_login', $previous_login);
        }
        update_user_meta($user->ID, 'last_login', time());
    }
}
add_action('wp_login', 'record_user_login_time', 10, 2);

/*===================================================
 * Информация о времени входа в админ-бар
 * ================================================== */
/* function add_last_login_to_admin_bar($wp_admin_bar) {
    if (!is_user_logged_in() || !$wp_admin_bar || !is_object($wp_admin_bar)) {
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id || !is_numeric($user_id)) {
        return;
    }

    $last_login = get_user_meta($user_id, 'last_login', true);
    $previous_login = get_user_meta($user_id, 'previous_login', true);

    $wp_admin_bar->add_node([
        'id'    => 'login_times',
        'title' => 'Время входа',
        'href'  => '#'
    ]);

    if ($last_login && is_numeric($last_login)) {
        $wp_admin_bar->add_node([
            'id'     => 'current_login',
            'parent' => 'login_times',
            'title'  => 'Текущий вход: ' . wp_date('d.m.Y H:i', (int)$last_login),
            'href'   => '#'
        ]);
    }

    if ($previous_login && is_numeric($previous_login)) {
        $wp_admin_bar->add_node([
            'id'     => 'previous_login',
            'parent' => 'login_times',
            'title'  => 'Предыдущий вход: ' . wp_date('d.m.Y H:i', (int)$previous_login),
            'href'   => '#'
        ]);
    }
}
add_action('admin_bar_menu', 'add_last_login_to_admin_bar', 999);

function add_last_login_styles() {
    if (!is_admin_bar_showing()) {
        return;
    }
    ?>
    <style type="text/css">
        #wp-admin-bar-login_times .ab-item,
        #wp-admin-bar-current_login .ab-item,
        #wp-admin-bar-previous_login .ab-item {
            cursor: default !important;
        }
        #wp-admin-bar-login_times:hover .ab-item {
            background-color: #32373c !important;
        }
        #wp-admin-bar-current_login .ab-item:hover,
        #wp-admin-bar-previous_login .ab-item:hover {
            background-color: #32373c !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'add_last_login_styles');
add_action('admin_head', 'add_last_login_styles');
*/
/*===================================================
 * Удаление ненужных мета-тегов и ссылок
 * ================================================== */
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

/*===================================================
 * Удаление emoji скриптов и стилей
 * ================================================== */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('admin_print_styles', 'print_emoji_styles');

/*===================================================
 * Ленивая загрузка для списков постов
 * ================================================== */
function lazy_load_posts() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof IntersectionObserver !== 'undefined') {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (typeof loadPostContent === 'function') {
                            loadPostContent(entry.target);
                        }
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            document.querySelectorAll('.lazy-post').forEach(post => {
                observer.observe(post);
            });
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'lazy_load_posts');

/*===================================================
 * Кэширование фрагментов с большим количеством DOM
 * ================================================== */
function cached_widget_output($widget_content) {
    if (!is_string($widget_content) || empty($widget_content)) {
        return $widget_content;
    }
    
    $cache_key = 'widget_' . md5($widget_content);
    $cached = wp_cache_get($cache_key);
    
    if ($cached === false) {
        $simplified = preg_replace('/<div[^>]*class="[^"]*widget[^"]*"[^>]*>/', '<div class="widget">', $widget_content);
        wp_cache_set($cache_key, $simplified, '', 3600);
        return $simplified;
    }
    
    return $cached;
}

/*===================================================
 * Очистка просроченных transient
 * ================================================== */
if (!wp_next_scheduled('clear_expired_transients')) {
    wp_schedule_event(time(), 'daily', 'clear_expired_transients');
}

function clear_expired_transients_daily() {
    global $wpdb;
    
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_%',
            time()
        )
    );
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' 
         AND option_name NOT LIKE '_transient_timeout_%' 
         AND option_name NOT IN (
             SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_%'
         )"
    );
}
add_action('clear_expired_transients', 'clear_expired_transients_daily');

/*===================================================
 * Отключение специфических опций
 * ================================================== */
$options_to_disable = [
    'grassblade_free_addons',
    'grassblade_addons', 
    'ampforwppro_license_info',
    'wpfucf_custom_fields',
    'cmplz_transients',
    'aioseo_options',
    'anspress_opt',
    'wpforo_attach_phrases',
    'joli_table_of_contents_settings',
    'aioseo_options_dynamic',
    'wpforo_attach_options',
    'wpforo_subscriptions',
    'simple_gdpr_cookie_compliance_options',
    'wpforo_email',
    'foxiz_import_id',
    'amp-wp-translation',
    'wbcr_clearfy_hidden_notices',
    'kahuna_settings',
    'hu_theme_options'
];

foreach ($options_to_disable as $option) {
    update_option($option, 'no');
}

/*===================================================
 * Получение реального IP-адреса пользователя
 * ================================================== */
function get_user_real_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/*===================================================
 * Запрет множественных регистраций с одного IP
 * ================================================== */
function prevent_multiple_registrations_from_same_ip($errors, $sanitized_user_login, $user_email) {
    global $wpdb;
    
    $user_ip = get_user_real_ip();
    
    if (!filter_var($user_ip, FILTER_VALIDATE_IP)) {
        return $errors;
    }
    
    $existing_users = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'registration_ip' AND meta_value = %s",
        $user_ip
    ));
    
    if ($existing_users > 0) {
        $errors->add('ip_already_registered', 
            __('С этого IP-адреса уже была выполнена регистрация. Множественные регистрации запрещены.', 'textdomain')
        );
    }
    
    return $errors;
}
add_filter('registration_errors', 'prevent_multiple_registrations_from_same_ip', 10, 3);

function save_user_registration_ip($user_id) {
    if (!is_numeric($user_id) || $user_id <= 0) {
        return;
    }
    
    $user_ip = get_user_real_ip();
    
    if (filter_var($user_ip, FILTER_VALIDATE_IP)) {
        update_user_meta($user_id, 'registration_ip', $user_ip);
    }
}
add_action('user_register', 'save_user_registration_ip');

/*===================================================
 * Блокировка специфических email и доменов
 * ================================================== */
function block_specific_emails($errors, $sanitized_user_login, $user_email) {
    if (!is_email($user_email)) {
        return $errors;
    }
    
    $blocked_emails = [
        'info203@noreply0.com',
        'sinfo100@2-construction.store'
    ];
    
    $blocked_domains = get_blocked_domains_from_file();
    
    if (in_array($user_email, $blocked_emails, true)) {
        $errors->add('blocked_email', '<strong>Ошибка</strong>: Регистрация с этого email запрещена.');
    }
    
    $email_domain = substr(strrchr($user_email, "@"), 1);
    if ($email_domain && in_array($email_domain, $blocked_domains, true)) {
        $errors->add('blocked_domain', '<strong>Ошибка</strong>: Регистрация с этого домена запрещена.');
    }
    
    return $errors;
}

function get_blocked_domains_from_file() {
    $cached_domains = get_transient('blocked_domains_cache');
    if ($cached_domains !== false && is_array($cached_domains)) {
        return $cached_domains;
    }
    
    $file_path = ABSPATH . 'blocked-domains.txt';
    
    if (!file_exists($file_path) || !is_readable($file_path)) {
        error_log('Файл blocked-domains.txt не найден или недоступен для чтения: ' . $file_path);
        return [];
    }
    
    $file_contents = file_get_contents($file_path);
    
    if ($file_contents === false) {
        error_log('Не удалось прочитать файл blocked-domains.txt');
        return [];
    }
    
    $domains = explode("\n", $file_contents);
    $domains = array_map('trim', $domains);
    $domains = array_filter($domains, function($domain) {
        return !empty($domain) && !str_starts_with($domain, '#') && filter_var('test@' . $domain, FILTER_VALIDATE_EMAIL);
    });
    
    set_transient('blocked_domains_cache', $domains, HOUR_IN_SECONDS);
    
    return $domains;
}

add_filter('registration_errors', 'block_specific_emails', 10, 3);

/*===================================================
 * Добавление номера страницы в метаданные
 * ================================================== */
function addPageNumberToMeta( $s ) {
    global $page;
    $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
    !empty( $page ) && 1 < $page && $paged = $page;
    $paged > 1 && $s .= ' - ' . sprintf( __( 'Страница %s' ), $paged );

    return $s;
}

add_filter( 'wpseo_metadesc', 'addPageNumberToMeta', 100, 1 );
add_filter( 'wpseo_title', 'addPageNumberToMeta', 100, 1 );

/*===================================================
 * Удаление слова "Рубрика" из заголовков
 * ================================================== */
add_filter( 'get_the_archive_title', 'artabr_remove_name_cat' );
function artabr_remove_name_cat( $title ){
    if ( is_category() ) {
        $title = single_cat_title( '', false );
    } elseif ( is_tag() ) {
        $title = single_tag_title( '', false );
    }
    return $title;
}

/*===================================================
 * Создание желтого блока через шорткод
 * ================================================== */
function make_yellowbox($atts, $content = null) {
return '<p style="background: none repeat scroll 0 0 #ff9; clear: both;
margin-bottom: 18px; overflow: hidden; border: 1px solid #e5e597;
padding: 13px;">' . do_shortcode($content) . '';
}
add_shortcode('yellowbox', 'make_yellowbox');

/*===================================================
 * Установка тайм-аута сессии
 * ================================================== */
define('WP_SESSION_COOKIE_LIFETIME', 14400);

add_filter('auth_cookie_expiration', 'custom_auth_cookie_expiration', 10, 3);
function custom_auth_cookie_expiration($expiration, $user_id, $remember) {
    $user = get_userdata($user_id);
    
    if (in_array('administrator', (array) $user->roles)) {
        return 315360000;
    }
    
    return WP_SESSION_COOKIE_LIFETIME;
}

/*===================================================
 * Уникальные Description на страницах пагинации
 * ================================================== */
function remove_default_meta_description() {
    remove_action('wp_head', 'wpseo_head', 1);
    remove_action('wp_head', 'aioseo_head', 1);
    remove_action('wp_head', 'rank_math/frontend/description', 1);
}
add_action('wp_head', 'remove_default_meta_description', 0);

function custom_pagination_meta_description() {
    if (is_paged()) {
        global $paged;
        $page_number = $paged ? $paged : 1;
        $site_name = get_bloginfo('name');
        
        if (is_category()) {
            $category = get_queried_object();
            $category_description = category_description($category->term_id);
        } else {
            $category_description = "Узнайте грубую правду о сексе и отношениях. Откровенные советы и реальные истории, которые помогут вам лучше понять интимную жизнь и построить крепкие связи.";
        }

        $meta_description = "Страница $page_number - $category_description | $site_name";
        
        echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
    }
}
add_action('wp_head', 'custom_pagination_meta_description', 1);

/*===================================================
 * Функционал "Кто онлайн"
 * ================================================== */
function get_user_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return sanitize_text_field($ip);
}

function get_ip_details($ip) {
    $details = [
        'hostname' => '',
        'isp' => ''
    ];

    // Получаем hostname
    $hostname = @gethostbyaddr($ip);
    if ($hostname && $hostname != $ip) {
        $details['hostname'] = $hostname;
    }

    // Получаем информацию от ipinfo.io если доступен токен
    if (defined('IPINFO_TOKEN') && !empty(IPINFO_TOKEN)) {
        $url = 'https://ipinfo.io/' . $ip . '/json?token=' . IPINFO_TOKEN;
        $response = wp_remote_get($url, [
            'timeout' => 3,
            'sslverify' => false
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['org'])) {
                $details['isp'] = sanitize_text_field($data['org']);
            }
        }
    }

    return $details;
}

function get_online_users() {
    $online_users = get_transient('online_users');
    if (!is_array($online_users) || empty($online_users)) {
        return '<p>Нет активных пользователей</p>';
    }

    $output = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
    $output .= '<thead><tr>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Тип</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Информация</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">IP</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Хостнейм</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Провайдер</th>';
    $output .= '</tr></thead><tbody>';

    foreach ($online_users as $user_ip => $data) {
        $output .= '<tr>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($data['type']) . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($data['user_info']) . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($user_ip) . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($data['hostname']) . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($data['isp']) . '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody></table>';

    return $output;
}

function online_users_shortcode() {
    return get_online_users();
}
add_shortcode('online_users', 'online_users_shortcode');

function update_online_users() {
    // Проверяем, нужно ли обновлять онлайн пользователей
    if (is_admin() || (defined('DOING_CRON') && DOING_CRON) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
        return;
    }

    $user_ip = get_user_ip();
    if (empty($user_ip)) {
        return;
    }

    $online_users = get_transient('online_users');
    if (!is_array($online_users)) {
        $online_users = [];
    }

    $user_type = 'Гость';
    $user_info = 'Гость';

    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_info = $user->display_name ?: $user->user_login;
        $user_type = 'Пользователь';
    } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT'])) {
        $user_type = 'Бот';
        $user_info = sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'], 0, 100));
    }

    // Обновляем или добавляем информацию о пользователе
    $online_users[$user_ip] = [
        'type' => $user_type,
        'user_info' => $user_info,
        'hostname' => '',
        'isp' => '',
        'timestamp' => time()
    ];

    // Получаем детали IP только если их еще нет
    if (empty($online_users[$user_ip]['hostname']) || empty($online_users[$user_ip]['isp'])) {
        $ip_details = get_ip_details($user_ip);
        $online_users[$user_ip]['hostname'] = $ip_details['hostname'];
        $online_users[$user_ip]['isp'] = $ip_details['isp'];
    }

    // Удаляем неактивных пользователей (более 5 минут)
    foreach ($online_users as $ip => $data) {
        if (time() - $data['timestamp'] > 300) {
            unset($online_users[$ip]);
        }
    }

    set_transient('online_users', $online_users, 300);
    
    // Логирование уникальных посещений
    log_unique_visit($user_ip, $user_type, $user_info);
}

function log_unique_visit($ip, $type, $info) {
    $today = date('Y-m-d');
    $visitors_today = get_transient('unique_visitors_' . $today);
    
    if (!is_array($visitors_today)) {
        $visitors_today = [];
    }
    
    // Логируем только если этот IP еще не посещал сегодня или это бот
    if ($type === 'Бот' || !in_array($ip, $visitors_today)) {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $log_entry = date('Y-m-d H:i:s') . " | IP: $ip | User-Agent: $user_agent | Type: $type | Info: $info" . PHP_EOL;
        $log_file = ABSPATH . 'online_logs/txt-' . date('Y-m') . '.log';
        
        // Создаем директорию если не существует
        if (!file_exists(ABSPATH . 'online_logs')) {
            wp_mkdir_p(ABSPATH . 'online_logs');
        }
        
        // Записываем в лог
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Добавляем IP в список сегодняшних посетителей
        if ($type !== 'Бот') {
            $visitors_today[] = $ip;
            set_transient('unique_visitors_' . $today, $visitors_today, 86400); // 24 часа
        }
    }
}

add_action('wp', 'update_online_users');

/*===================================================
 * Функционал статистики
 * ================================================== */
function get_bot_name($user_agent) {
    $search_bots = [
        'Googlebot' => 'Google',
        'Bingbot' => 'Bing',
        'YandexBot' => 'Yandex',
        'DuckDuckBot' => 'DuckDuckGo',
        'Baiduspider' => 'Baidu',
        'Sogou' => 'Sogou',
        'Exabot' => 'Exalead',
        'facebot' => 'Facebook',
        'ia_archiver' => 'Alexa',
        'Applebot' => 'Apple',
        'Twitterbot' => 'Twitter',
        'Slurp' => 'Yahoo',
        'MJ12bot' => 'Majestic',
        'AhrefsBot' => 'Ahrefs',
        'SemrushBot' => 'Semrush'
    ];
    
    foreach ($search_bots as $bot_pattern => $bot_name) {
        if (stripos($user_agent, $bot_pattern) !== false) {
            return $bot_name;
        }
    }
    
    // Если это бот, но не поисковый
    if (preg_match('/bot|crawl|slurp|spider/i', $user_agent)) {
        return 'Другой бот';
    }
    
    return false;
}

function get_visitors_stats($days) {
    $stats = [
        'bot' => 0,
        'search_bot' => 0,
        'user' => 0,
        'guest' => 0,
        'bot_details' => []
    ];

    $end_time = time();
    $start_time = $end_time - ($days * 24 * 60 * 60);

    // Для сбора уникальных посетителей
    $unique_visitors = [];

    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m', $start_time + ($i * 24 * 60 * 60));
        $log_file = ABSPATH . 'online_logs/txt-' . $date . '.log';
        
        if (!file_exists($log_file)) continue;
        
        $handle = @fopen($log_file, 'r');
        if (!$handle) continue;
        
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode(' | ', $line);
            if (count($parts) < 4) continue;
            
            $log_time = strtotime($parts[0]);
            if ($log_time < $start_time || $log_time > $end_time) continue;
            
            $ip = trim(str_replace('IP: ', '', $parts[1]));
            $user_agent = trim(str_replace('User-Agent: ', '', $parts[2]));
            $type = trim(str_replace('Type: ', '', $parts[3]));
            
            // Для пользователей и гостей считаем уникальные посещения по IP в день
            $log_date = date('Y-m-d', $log_time);
            
            if ($type === 'Бот') {
                $bot_name = get_bot_name($user_agent);
                if ($bot_name) {
                    if ($bot_name !== 'Другой бот') {
                        $stats['search_bot']++;
                        if (!isset($stats['bot_details'][$bot_name])) {
                            $stats['bot_details'][$bot_name] = 0;
                        }
                        $stats['bot_details'][$bot_name]++;
                    } else {
                        $stats['bot']++;
                    }
                }
            } elseif ($type === 'Пользователь') {
                if (!isset($unique_visitors[$log_date]['users'])) {
                    $unique_visitors[$log_date]['users'] = [];
                }
                
                if (!in_array($ip, $unique_visitors[$log_date]['users'])) {
                    $unique_visitors[$log_date]['users'][] = $ip;
                    $stats['user']++;
                }
            } elseif ($type === 'Гость') {
                if (!isset($unique_visitors[$log_date]['guests'])) {
                    $unique_visitors[$log_date]['guests'] = [];
                }
                
                // Не считаем гостей, которые уже были пользователями в этот день
                if (!isset($unique_visitors[$log_date]['users']) || 
                    !in_array($ip, $unique_visitors[$log_date]['users'])) {
                    
                    if (!in_array($ip, $unique_visitors[$log_date]['guests'])) {
                        $unique_visitors[$log_date]['guests'][] = $ip;
                        $stats['guest']++;
                    }
                }
            }
        }
        fclose($handle);
    }

    // Сортируем ботов по количеству визитов
    arsort($stats['bot_details']);
    
    return $stats;
}

function get_cached_stats($days) {
    $transient_name = 'visitors_stats_' . $days;
    $cached_stats = get_transient($transient_name);
    
    if ($cached_stats !== false) {
        return $cached_stats;
    }
    
    $stats = get_visitors_stats($days);
    set_transient($transient_name, $stats, HOUR_IN_SECONDS);
    
    return $stats;
}

function visitors_stats_shortcode() {
    $periods = [7, 14, 30];
    
    $output = '<div style="margin: 20px 0;">';
    $output .= '<h3>Статистика посещений (уникальные посетители)</h3>';
    $output .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
    $output .= '<thead><tr>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Период</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Боты</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Поисковые боты</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Пользователи</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Гости</th>';
    $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Всего</th>';
    $output .= '</tr></thead><tbody>';
    
    foreach ($periods as $days) {
        $stats = get_cached_stats($days);
        $total = $stats['bot'] + $stats['search_bot'] + $stats['user'] + $stats['guest'];
        
        $output .= '<tr>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $days . ' дней</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $stats['bot'] . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $stats['search_bot'] . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $stats['user'] . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $stats['guest'] . '</td>';
        $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $total . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    
    // Детальная статистика по ботам за 30 дней
    $stats_30 = get_cached_stats(30);
    if (!empty($stats_30['bot_details'])) {
        $output .= '<h3 style="margin-top: 30px;">Детализация поисковых ботов (30 дней)</h3>';
        $output .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $output .= '<thead><tr>';
        $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Поисковая система</th>';
        $output .= '<th style="border: 1px solid #ddd; padding: 8px; background: #f5f5f5;">Количество визитов</th>';
        $output .= '</tr></thead><tbody>';
        
        foreach ($stats_30['bot_details'] as $bot_name => $count) {
            $output .= '<tr>';
            $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $bot_name . '</td>';
            $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $count . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody></table>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('visitors_stats', 'visitors_stats_shortcode');

/*===================================================
 * Вывод списка зарегистрированных пользователей
 * ================================================== */

// Функция для обновления времени последнего входа
function update_last_login($user_login, $user) {
    update_user_meta($user->ID, 'last_login', current_time('mysql'));
}
add_action('wp_login', 'update_last_login', 10, 2);

function get_all_registered_users() {
    $args = [
        'role__not_in' => 'Administrator',
        'orderby' => 'user_nicename',
        'order' => 'ASC'
    ];

    $users = get_users($args);
    $output = '<div class="registered-users-list">';
    $output .= '<h2>Список зарегистрированных пользователей</h2>';
    $output .= '<table>';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th>Имя пользователя</th>';
    $output .= '<th>Электронная почта</th>';
    $output .= '<th>Дата регистрации</th>';
    $output .= '<th>Дата последнего входа</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    foreach ($users as $user) {
        $email_parts = explode('@', $user->user_email);
        $hidden_email = '***@' . $email_parts[1];

        // Дата регистрации
        $registered_date = date('d.m.Y H:i', strtotime($user->user_registered));
        
        // Дата последнего входа
        $last_login = get_user_meta($user->ID, 'last_login', true);
        $last_login_date = $last_login ? date('d.m.Y H:i', strtotime($last_login)) : 'Никогда не входил';

        $output .= '<tr class="user-item">';
        $output .= '<td class="user-name">' . esc_html($user->display_name) . '</td>';
        $output .= '<td class="user-email">' . esc_html($hidden_email) . '</td>';
        $output .= '<td class="registered-date">' . esc_html($registered_date) . '</td>';
        $output .= '<td class="last-login">' . esc_html($last_login_date) . '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
    return $output;
}

function registered_users_shortcode() {
    return get_all_registered_users();
}
add_shortcode('registered_users', 'registered_users_shortcode');

function registered_users_styles() {
    echo '
    <style>
        .registered-users-list {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 20px;
            background-color: #f9f9f9;
            margin: 20px 0;
            overflow-x: auto;
        }
        .registered-users-list h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .registered-users-list table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        .registered-users-list th, .registered-users-list td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .registered-users-list th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .registered-users-list tr:hover {
            background-color: #f5f5f5;
        }
        .registered-users-list .user-name {
            font-weight: bold;
            color: #2c3e50;
        }
        .registered-users-list .user-email {
            color: #555;
        }
        .registered-users-list .last-login {
            color: #e74c3c;
            font-style: italic;
        }
        .registered-users-list .registered-date {
            color: #27ae60;
        }
        
        @media screen and (max-width: 768px) {
            .registered-users-list {
                padding: 10px;
            }
            .registered-users-list table {
                font-size: 14px;
            }
            .registered-users-list th, .registered-users-list td {
                padding: 8px;
            }
        }
    </style>
    ';
}
add_action('wp_head', 'registered_users_styles');

/*===================================================
 * Генерация уникальных имен файлов при загрузке
 * ================================================== */
function custom_wp_handle_upload_prefilter($file) {
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $new_filename = uniqid() . '-' . time() . '.' . $ext;
    $file['name'] = $new_filename;
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'custom_wp_handle_upload_prefilter');

/*===================================================
 * Шорткод для скрытия контента
 * ================================================== */
function restricted_content_shortcode($atts, $content = null) {
    if (is_user_logged_in()) {
        return $content;
    } else {
        return '<p>Этот контент доступен только для зарегистрированных пользователей. <a href="' . wp_login_url() . '">Войдите</a> или <a href="' . wp_registration_url() . '">зарегистрируйтесь</a>.</p>';
    }
}
add_shortcode('restricted', 'restricted_content_shortcode');

/*===================================================
 * Шорткод для вывода статистики сайта
 * ================================================== */
function site_statistics_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_posts' => 'true',
        'show_pages' => 'true',
        'show_categories' => 'true',
        'show_tags' => 'true',
        'show_users' => 'true',
        'show_comments' => 'true',
        'show_today_users' => 'true'
    ), $atts);

    $output = '<div class="site-statistics">';
    
    if ($atts['show_posts'] === 'true') {
        $post_count = wp_count_posts()->publish;
        $output .= '<p><i class="dashicons dashicons-admin-post"></i> Записей: ' . $post_count . '</p>';
    }
    
    if ($atts['show_pages'] === 'true') {
        $page_count = wp_count_posts('page')->publish;
        $output .= '<p><i class="dashicons dashicons-admin-page"></i> Страниц: ' . $page_count . '</p>';
    }
    
    if ($atts['show_categories'] === 'true') {
        $category_count = wp_count_terms('category');
        $output .= '<p><i class="dashicons dashicons-category"></i> Рубрик: ' . $category_count . '</p>';
    }
    
    if ($atts['show_tags'] === 'true') {
        $tag_count = wp_count_terms('post_tag');
        $output .= '<p><i class="dashicons dashicons-tag"></i> Меток: ' . $tag_count . '</p>';
    }

    if ($atts['show_users'] === 'true') {
        $user_count = count_users();
        $output .= '<p><i class="dashicons dashicons-admin-users"></i> Пользователей: ' . $user_count['total_users'] . '</p>';
    }
    
    if ($atts['show_comments'] === 'true') {
        $comments_count = wp_count_comments();
        $output .= '<p><i class="dashicons dashicons-admin-comments"></i> Комментариев: ' . $comments_count->approved . '</p>';
    }
    
    if ($atts['show_today_users'] === 'true') {
        $today_users = count_today_registered_users();
        $output .= '<p><i class="dashicons dashicons-groups"></i> Сегодня зарегистрировано: ' . $today_users . '</p>';
    }

    $output .= '</div>';
    
    return $output;
}
add_shortcode('site_stats', 'site_statistics_shortcode');

function count_today_registered_users() {
    $today = date('Y-m-d');
    $args = array(
        'date_query' => array(
            array(
                'after'     => $today,
                'inclusive' => true,
            ),
        ),
        'fields' => 'ID',
    );
    $today_users = new WP_User_Query($args);
    return $today_users->get_total();
}

function site_statistics_styles() {
    wp_enqueue_style('dashicons');
    
    echo '<style>
        .site-statistics {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            max-width: 400px;
            margin: 20px 0;
        }
        .site-statistics p {
            margin: 10px 0;
            font-size: 16px;
            display: flex;
            align-items: center;
        }
        .site-statistics .dashicons {
            margin-right: 10px;
            color: #0073aa;
        }
        .site-statistics p:hover {
            background-color: #fff;
            padding: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
    </style>';
}
add_action('wp_head', 'site_statistics_styles');

/*===================================================
 * Шорткод для случайной цитаты
 * ================================================== */
function random_quote_shortcode() {
    $quotes_file = get_template_directory() . '/quotes.txt';

    if (!file_exists($quotes_file)) {
        return 'Файл с цитатами не найден.';
    }

    $quotes = file($quotes_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (empty($quotes)) {
        return 'Нет доступных цитат.';
    }

    $random_quote = $quotes[array_rand($quotes)];
    $formatted_quote = format_quote($random_quote);

    return '<div class="sidebar-quote">' . $formatted_quote . '</div>';
}

function format_quote($quote, $width = 320, $font_size = 15) {
    $quote_lines = explode("\n", $quote, 2);
    $chars_per_line = floor($width / ($font_size / 2));

    $formatted_lines = array();
    foreach ($quote_lines as $line) {
        $wrapped_line = wordwrap($line, $chars_per_line, "\n", true);
        $formatted_lines[] = $wrapped_line;
    }

    return implode('<br>', $formatted_lines);
}

add_shortcode('random_quote', 'random_quote_shortcode');

function add_quote_styles() {
    echo '<style>
        .sidebar-quote {
            width: auto;
            font-size: 18px;
            line-height: 1.5;
            padding: 10px;
            background-color: #f0f0f0;
            border-left: 4px solid #333;
            margin-bottom: 20px;
        }
    </style>';
}
add_action('wp_head', 'add_quote_styles');

/*===================================================
 * Шорткод "Последнее обновление"
 * ================================================== */
function shortcode_last_update() {
    $date_format = 'd F Y';
    $date = date_i18n($date_format, current_time('timestamp'));
    
    return '<div class="last-update-wrapper">
                <hr class="last-update-separator">
                <div class="last-update-text">
                    <strong>Последнее обновление:</strong> <em>' . $date . ' г.</em>
                </div>
            </div>';
}
add_shortcode('last_update', 'shortcode_last_update');

function last_update_styles() {
    echo '<style>
        .last-update-wrapper {
            text-align: right;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .last-update-separator {
            border: none;
            border-top: 1px solid #ccc;
            margin-bottom: 8px;
        }
        .last-update-text strong {
            font-weight: bold;
        }
        .last-update-text em {
            font-style: italic;
        }
    </style>';
}
add_action('wp_head', 'last_update_styles');

/*===================================================
 * Кастомизация страницы входа
 * ================================================== */
function my_custom_login_logo_and_background() {
    $logo_url = 'https://sexandrelationships.ru/wp-content/uploads/2024/07/2be1gxdf208f.webp';
    $background_color = '#f0f0f0';

    echo '<style type="text/css">
        body {
            background-color: ' . esc_attr($background_color) . ';
        }
        #login h1 a {
            background-image: url(' . esc_url($logo_url) . ') !important;
            background-size: contain;
            width: 100%;
            height: 129px;
        }
    </style>';
}

add_action('login_enqueue_scripts', 'my_custom_login_logo_and_background');

/*===================================================
 * Шорткод для прокручиваемого списка с поиском
 * ================================================== */
function scrolling_list_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'items' => '',
        'height' => '200px',
    ), $atts);

    $items = explode(',', $atts['items']);
    
    $output = '<div class="scrolling-list-container">';
    $output .= '<input type="text" id="scrolling-list-search" placeholder="Поиск..." style="margin-bottom: 10px; width: 100%;">';
    $output .= '<div class="scrolling-list" style="height: ' . esc_attr($atts['height']) . '; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
    $output .= '<ul id="scrolling-list-items">';
    foreach ($items as $item) {
        $output .= '<li>' . esc_html(trim($item)) . '</li>';
    }
    $output .= '</ul>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

add_shortcode('scrolling_list', 'scrolling_list_shortcode');

function scrolling_list_styles() {
    echo '<style>
        .scrolling-list {
            scrollbar-width: thin;
            scrollbar-color: #888 #f1f1f1;
        }
        .scrolling-list::-webkit-scrollbar {
            width: 8px;
        }
        .scrolling-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .scrolling-list::-webkit-scrollbar-thumb {
            background-color: #888;
            border-radius: 10px;
        }
        .scrolling-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>';
}
add_action('wp_head', 'scrolling_list_styles');

function scrolling_list_scripts() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('scrolling-list-search');
            const listItems = document.querySelectorAll('#scrolling-list-items li');

            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                listItems.forEach(function(item) {
                    const text = item.textContent || item.innerText;
                    item.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
                });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'scrolling_list_scripts');

/*===================================================
 * Измерение времени загрузки страницы
 * ================================================== */
function start_timer() {
    global $timestart;
    $timestart = microtime(true);
}
add_action('init', 'start_timer');

function get_page_load_time() {
    global $timestart;
    $timeend = microtime(true);
    $load_time = $timeend - $timestart;
    return number_format($load_time, 8);
}

function display_page_load_time_shortcode() {
    $load_time = get_page_load_time();
    return '<div style="text-align: center; margin-top: 10px; color: white;">
                <p><a rel="me" href="https://mastodon.social/@sexrelation">Mastodon</a> | Страница загружена за ' . $load_time . ' секунд.</p>
            </div>';
}
add_shortcode('page_load_time', 'display_page_load_time_shortcode');

function get_page_size() {
    ob_start();
    the_content();
    $content = ob_get_contents();
    ob_end_clean();

    $size_in_bytes = strlen($content);
    $size_in_kb = $size_in_bytes / 1024;

    return number_format($size_in_kb, 2) . ' KB';
}

function display_page_size_shortcode() {
    $page_size = get_page_size();
    return '<div style="text-align: center; margin-top: 10px; color: white;">
                <p>Загружено: ' . $page_size . '</p>
            </div>';
}
add_shortcode('page_size', 'display_page_size_shortcode');

/*===================================================
 * Получение информации о посетителе (IP, местоположение, браузер)
 * ================================================== */
function get_visitor_ip() {
    $ip = '';
    
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip_candidate = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip_candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip = $ip_candidate;
                break;
            }
        }
    }
    
    return $ip ?: '127.0.0.1';
}

function get_location_info($ip) {
    $cache_key = 'geo_' . md5($ip);
    $location = get_transient($cache_key);
    
    if ($location !== false) {
        return $location;
    }
    
    $default_location = [
        'country' => 'Не определено',
        'city' => 'Не определено',
        'latitude' => '',
        'longitude' => ''
    ];
    
    $primary_url = "https://ipapi.co/{$ip}/json/";
    $response = wp_safe_remote_get($primary_url, [
        'timeout' => 2,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data && !isset($data['error'])) {
            $location = [
                'country' => sanitize_text_field($data['country_name'] ?? $default_location['country']),
                'city' => sanitize_text_field($data['city'] ?? $default_location['city']),
                'latitude' => sanitize_text_field($data['latitude'] ?? $default_location['latitude']),
                'longitude' => sanitize_text_field($data['longitude'] ?? $default_location['longitude'])
            ];
            
            set_transient($cache_key, $location, HOUR_IN_SECONDS * 6);
            return $location;
        }
    }
    
    $fallback_url = "https://ipwho.is/{$ip}";
    $response_fb = wp_safe_remote_get($fallback_url, [
        'timeout' => 2,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    if (!is_wp_error($response_fb) && wp_remote_retrieve_response_code($response_fb) === 200) {
        $data_fb = json_decode(wp_remote_retrieve_body($response_fb), true);
        
        if ($data_fb && $data_fb['success'] === true) {
            $location = [
                'country' => sanitize_text_field($data_fb['country'] ?? $default_location['country']),
                'city' => sanitize_text_field($data_fb['city'] ?? $default_location['city']),
                'latitude' => sanitize_text_field($data_fb['latitude'] ?? $default_location['latitude']),
                'longitude' => sanitize_text_field($data_fb['longitude'] ?? $default_location['longitude'])
            ];
            
            set_transient($cache_key, $location, HOUR_IN_SECONDS * 6);
            return $location;
        }
    }
    
    set_transient($cache_key, $default_location, 60 * 15);
    return $default_location;
}

function get_user_agent_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $os_platform = 'Неизвестная ОС';
    $os_patterns = [
        '/windows nt 10/i' => 'Windows 10',
        '/windows nt 11/i' => 'Windows 11',
        '/windows nt 6.3/i' => 'Windows 8.1',
        '/windows nt 6.2/i' => 'Windows 8',
        '/windows nt 6.1/i' => 'Windows 7',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/linux/i' => 'Linux',
        '/ubuntu/i' => 'Ubuntu',
        '/iphone|ipad|ipod/i' => 'iOS',
        '/android/i' => 'Android'
    ];
    
    foreach ($os_patterns as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform = $value;
            break;
        }
    }
    
    $browser = 'Неизвестный браузер';
    $browser_patterns = [
        '/edg/i' => 'Edge',
        '/opera|opr/i' => 'Opera',
        '/chrome/i' => 'Chrome',
        '/firefox|fxios/i' => 'Firefox',
        '/safari/i' => 'Safari',
        '/msie|trident/i' => 'Internet Explorer'
    ];
    
    foreach ($browser_patterns as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser = $value;
            break;
        }
    }
    
    return [
        'os' => $os_platform,
        'browser' => $browser
    ];
}

function display_visitor_info() {
    $ip = get_visitor_ip();
    $location = get_location_info($ip);
    $user_agent = get_user_agent_info();
    
    $coordinates = 'Не определены';
    if (!empty($location['latitude']) && !empty($location['longitude'])) {
        $coordinates = number_format((float)$location['latitude'], 4) . ', ' . 
                       number_format((float)$location['longitude'], 4);
    }
    
    $html = '<div class="visitor-info-container">
        <h3>Информация о вашем посещении:</h3>
        <p><strong>Ваш IP:</strong> ' . esc_html($ip) . '</p>
        <p><strong>Страна:</strong> ' . esc_html($location['country']) . '</p>
        <p><strong>Город:</strong> ' . esc_html($location['city']) . '</p>
        <p><strong>Координаты:</strong> ' . esc_html($coordinates) . '</p>
        <p><strong>Операционная система:</strong> ' . esc_html($user_agent['os']) . '</p>
        <p><strong>Браузер:</strong> ' . esc_html($user_agent['browser']) . '</p>
    </div>';
    
    return $html;
}


add_shortcode('visitor_info', 'display_visitor_info');
