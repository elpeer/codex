<?php
/**
 * Plugin Name: Elevate WhatsApp Button
 * Plugin URI: https://elevate-digital.example
 * Description: Floating WhatsApp button with desktop/mobile display modes and full customization.
 * Version: 1.1.0
 * Author: Elevate Digital Studio
 * Author URI: https://elevate-digital.example
 * License: GPL-2.0+
 * Text Domain: elevate-whatsapp-button
 */

if (!defined('ABSPATH')) {
    exit;
}

class Elevate_WhatsApp_Button {
    private string $option_name = 'ewb_settings';
    private string $stats_option_name = 'ewb_click_stats';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_button']);
        add_action('wp_ajax_nopriv_ewb_track_click', [$this, 'track_click']);
        add_action('wp_ajax_ewb_track_click', [$this, 'track_click']);
    }

    public function add_settings_page(): void {
        add_options_page(
            'WhatsApp Button',
            'WhatsApp Button',
            'manage_options',
            'elevate-whatsapp-button',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting($this->option_name, $this->option_name, [$this, 'sanitize_settings']);

        add_settings_section('ewb_general_section', 'הגדרות כלליות', '__return_false', 'elevate-whatsapp-button');

        $fields = [
            'phone_number' => 'מספר וואטסאפ (כולל קידומת מדינה, ללא +)',
            'button_text' => 'טקסט הכפתור בדסקטופ',
            'preset_message' => 'הודעת פתיחה (אפשר להשתמש במשתנה {page_url})',
            'append_source_to_message' => 'להוסיף את כתובת העמוד אוטומטית להודעה',
            'desktop_position' => 'מיקום בדסקטופ',
            'mobile_mode' => 'תצוגה במובייל',
            'mobile_position' => 'מיקום במובייל',
        ];

        foreach ($fields as $field => $label) {
            add_settings_field('ewb_' . $field, $label, [$this, 'render_field'], 'elevate-whatsapp-button', 'ewb_general_section', ['field' => $field]);
        }
    }

    public function sanitize_settings(array $input): array {
        return [
            'phone_number' => preg_replace('/\D+/', '', $input['phone_number'] ?? ''),
            'button_text' => sanitize_text_field($input['button_text'] ?? 'דברו איתנו בוואטסאפ'),
            'preset_message' => sanitize_textarea_field($input['preset_message'] ?? 'היי, אשמח לקבל פרטים נוספים. הגעתי מהעמוד: {page_url}'),
            'append_source_to_message' => !empty($input['append_source_to_message']) ? '1' : '0',
            'desktop_position' => in_array(($input['desktop_position'] ?? ''), ['left_bottom', 'right_bottom'], true) ? $input['desktop_position'] : 'left_bottom',
            'mobile_mode' => in_array(($input['mobile_mode'] ?? ''), ['icon_only', 'full_button'], true) ? $input['mobile_mode'] : 'icon_only',
            'mobile_position' => in_array(($input['mobile_position'] ?? ''), ['left_bottom', 'center_bottom', 'right_bottom'], true) ? $input['mobile_position'] : 'center_bottom',
        ];
    }

    private function get_settings(): array {
        $defaults = [
            'phone_number' => '',
            'button_text' => 'דברו איתנו בוואטסאפ',
            'preset_message' => 'היי, אשמח לקבל פרטים נוספים. הגעתי מהעמוד: {page_url}',
            'append_source_to_message' => '1',
            'desktop_position' => 'left_bottom',
            'mobile_mode' => 'icon_only',
            'mobile_position' => 'center_bottom',
        ];

        return wp_parse_args(get_option($this->option_name, []), $defaults);
    }

    private function get_click_stats(): array {
        $defaults = ['total_clicks' => 0, 'pages' => []];
        return wp_parse_args(get_option($this->stats_option_name, []), $defaults);
    }

    public function render_field(array $args): void {
        $settings = $this->get_settings();
        $field = $args['field'];
        $name = $this->option_name . '[' . $field . ']';

        switch ($field) {
            case 'phone_number':
            case 'button_text':
                printf('<input type="text" class="regular-text" name="%s" value="%s" />', esc_attr($name), esc_attr($settings[$field]));
                break;
            case 'append_source_to_message':
                printf('<label><input type="checkbox" name="%s" value="1" %s /> שלח לוואטסאפ גם את העמוד שממנו המשתמש פנה.</label>', esc_attr($name), checked('1', $settings[$field], false));
                break;
            case 'preset_message':
                printf('<textarea name="%s" rows="4" class="large-text">%s</textarea><p class="description">אפשר להכניס בטקסט את המשתנה <code>{page_url}</code> כדי להחליף אוטומטית לכתובת העמוד.</p>', esc_attr($name), esc_textarea($settings[$field]));
                break;
            case 'desktop_position':
                $this->render_select($name, $settings[$field], ['left_bottom' => 'שמאל למטה', 'right_bottom' => 'ימין למטה']);
                break;
            case 'mobile_mode':
                $this->render_select($name, $settings[$field], ['icon_only' => 'אייקון בלבד', 'full_button' => 'כפתור צף עם טקסט']);
                break;
            case 'mobile_position':
                $this->render_select($name, $settings[$field], ['left_bottom' => 'שמאל למטה', 'center_bottom' => 'ממורכז למטה', 'right_bottom' => 'ימין למטה']);
                break;
        }
    }

    private function render_select(string $name, string $current, array $options): void {
        echo '<select name="' . esc_attr($name) . '">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $stats = $this->get_click_stats();
        ?>
        <div class="wrap">
            <h1>Elevate WhatsApp Button</h1>
            <p>יוצר התבנית: <strong>Elevate Digital Studio</strong></p>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('elevate-whatsapp-button');
                submit_button('שמירת הגדרות');
                ?>
            </form>

            <hr />
            <h2>סטטיסטיקות קליקים</h2>
            <p><strong>סה"כ לחיצות:</strong> <?php echo esc_html((string) $stats['total_clicks']); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>עמוד מקור</th>
                        <th>מספר לחיצות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stats['pages'])): ?>
                        <?php foreach ($stats['pages'] as $page_url => $count): ?>
                            <tr>
                                <td><code><?php echo esc_html($page_url); ?></code></td>
                                <td><?php echo esc_html((string) $count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">עדיין אין נתוני קליקים.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function enqueue_assets(): void {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style('elevate-whatsapp-button-style', plugin_dir_url(__FILE__) . 'assets/whatsapp-button.css', [], '1.1.0');
        wp_enqueue_script('elevate-whatsapp-button-script', plugin_dir_url(__FILE__) . 'assets/whatsapp-button.js', [], '1.1.0', true);

        wp_localize_script('elevate-whatsapp-button-script', 'ewbData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ewb_track_click_nonce'),
        ]);
    }

    public function track_click(): void {
        check_ajax_referer('ewb_track_click_nonce', 'nonce');

        $source_page = isset($_POST['sourcePage']) ? esc_url_raw(wp_unslash($_POST['sourcePage'])) : '';
        if (empty($source_page)) {
            wp_send_json_error(['message' => 'Missing source page']);
        }

        $stats = $this->get_click_stats();
        $stats['total_clicks'] = (int) $stats['total_clicks'] + 1;

        if (!isset($stats['pages'][$source_page])) {
            $stats['pages'][$source_page] = 0;
        }
        $stats['pages'][$source_page] = (int) $stats['pages'][$source_page] + 1;
        arsort($stats['pages']);

        update_option($this->stats_option_name, $stats, false);
        wp_send_json_success(['total' => $stats['total_clicks']]);
    }

    public function render_button(): void {
        $settings = $this->get_settings();
        if (empty($settings['phone_number'])) {
            return;
        }

        $desktop_class = 'ewb-desktop-' . $settings['desktop_position'];
        $mobile_class = 'ewb-mobile-' . $settings['mobile_position'];
        $mode_class = 'ewb-mobile-mode-' . $settings['mobile_mode'];
        $append_source = $settings['append_source_to_message'] === '1' ? '1' : '0';
        ?>
        <a class="ewb-button <?php echo esc_attr($desktop_class . ' ' . $mobile_class . ' ' . $mode_class); ?>"
           href="#"
           target="_blank"
           rel="noopener noreferrer"
           data-phone="<?php echo esc_attr($settings['phone_number']); ?>"
           data-message="<?php echo esc_attr($settings['preset_message']); ?>"
           data-append-source="<?php echo esc_attr($append_source); ?>"
           aria-label="WhatsApp Contact Button">
            <span class="ewb-text"><?php echo esc_html($settings['button_text']); ?></span>
            <span class="ewb-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.11 17.18c-.26-.13-1.53-.75-1.77-.84-.24-.09-.42-.13-.59.13-.17.26-.68.84-.83 1.01-.15.17-.3.2-.56.07-.26-.13-1.11-.41-2.11-1.3-.78-.69-1.31-1.53-1.46-1.79-.15-.26-.02-.4.11-.53.11-.11.26-.3.39-.45.13-.15.17-.26.26-.43.09-.17.04-.32-.02-.45-.07-.13-.59-1.43-.81-1.95-.21-.51-.43-.44-.59-.45-.15-.01-.32-.01-.49-.01-.17 0-.45.07-.69.32-.24.26-.91.89-.91 2.16s.93 2.51 1.06 2.68c.13.17 1.82 2.78 4.42 3.89.62.27 1.11.43 1.49.55.63.2 1.21.17 1.67.1.51-.08 1.53-.62 1.75-1.22.22-.6.22-1.11.15-1.22-.06-.11-.24-.17-.5-.3z" fill="currentColor"/>
                    <path d="M16 3C8.84 3 3 8.84 3 16c0 2.57.75 5.08 2.17 7.24L3.2 29l5.95-1.91A12.9 12.9 0 0016 29c7.16 0 13-5.84 13-13S23.16 3 16 3zm0 23.4c-2.09 0-4.13-.56-5.92-1.63l-.42-.25-3.53 1.13 1.15-3.44-.27-.44A10.35 10.35 0 015.6 16C5.6 10.23 10.23 5.6 16 5.6S26.4 10.23 26.4 16 21.77 26.4 16 26.4z" fill="currentColor"/>
                </svg>
            </span>
        </a>
        <?php
    }
}

new Elevate_WhatsApp_Button();
