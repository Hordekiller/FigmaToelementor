<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Style_Sync {
    private Figma_API $figma_api;

    public const SYNCED_STYLES_OPTION = 'hello_figma_synced_styles';
    private const KIT_META_KEY = 'hello_figma_figma_data';

    public function __construct(Figma_API $figma_api) {
        $this->figma_api = $figma_api;
    }

    public function init(): void {
        add_action('elementor/kit/register_tabs', [$this, 'register_kit_tab'], 10, 1);
        add_filter('elementor/editor/localize_settings', [$this, 'add_editor_localizations']);
    }

    public function register_kit_tab($kit): void {
        $kit->register_tab('hello-figma-sync', [
            'label' => __('Figma Sync', 'hello-figma'),
            'callback' => [$this, 'render_kit_tab'],
        ]);
    }

    public function render_kit_tab(): void {
        $file_key = get_option('hello_figma_file_key', '');
        $synced = get_option(self::SYNCED_STYLES_OPTION, []);
        ?>
        <div class="hello-figma-sync-tab">
            <h2><?php esc_html_e('Figma Style Sync', 'hello-figma'); ?></h2>
            <p><?php esc_html_e('Sync your Figma design tokens with Elementor global styles.', 'hello-figma'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('hello_figma_sync_styles', '_figma_sync_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="figma_file_key"><?php esc_html_e('Figma File Key', 'hello-figma'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="figma_file_key"
                                   name="figma_file_key"
                                   value="<?php echo esc_attr($file_key); ?>"
                                   class="regular-text"
                                   placeholder="e.g. abc123DEFghi">
                            <p class="description">
                                <?php esc_html_e('Paste the Figma file key from the URL (figma.com/file/KEY/...).', 'hello-figma'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <button type="submit" name="hello_figma_fetch_styles" class="button button-primary">
                    <?php esc_html_e('Fetch & Sync Figma Styles', 'hello-figma'); ?>
                </button>
            </form>

            <?php if (!empty($synced)): ?>
                <hr>
                <h3><?php esc_html_e('Synced Styles', 'hello-figma'); ?></h3>
                <p><?php echo esc_html(sprintf(
                    /* translators: %d: Number of synced styles */
                    __('%d styles synced from Figma.', 'hello-figma'),
                    count($synced)
                )); ?></p>
                <ul>
                    <?php foreach ($synced as $style): ?>
                        <li>
                            <strong><?php echo esc_html($style['name']); ?></strong>
                            (<?php echo esc_html($style['type']); ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_editor_localizations(array $config): array {
        $synced = get_option(self::SYNCED_STYLES_OPTION, []);

        if (!empty($synced)) {
            $figma_config = [
                'colors' => $this->get_synced_colors($synced),
                'typography' => $this->get_synced_typography($synced),
            ];

            $config['helloFigma'] = $figma_config;
        }

        return $config;
    }

    /**
     * Sync Figma colors to Elementor global colors.
     */
    public function sync_colors(string $file_key): array {
        $styles = $this->figma_api->get_styles($file_key);
        if ($styles === null || !isset($styles['meta']['styles'])) {
            return [];
        }

        $kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
        $settings = $kit->get_settings();
        $synced = [];
        $color_index = 1;

        foreach ($styles['meta']['styles'] as $style) {
            if (($style['style_type'] ?? '') !== 'FILL') {
                continue;
            }

            $color = $this->extract_color_from_style($style);
            if ($color === null) {
                continue;
            }

            $var_name = "figma-color-{$color_index}";
            $settings["custom_colors"][] = [
                '_id' => $var_name,
                'title' => $style['name'] ?? "Figma Color {$color_index}",
                'color' => $color,
            ];

            $synced[] = [
                'name' => $style['name'],
                'type' => 'color',
                'value' => $color,
                'var' => $var_name,
            ];

            $color_index++;
        }

        $kit->update_settings($settings);
        update_option(self::SYNCED_STYLES_OPTION, $synced);

        return $synced;
    }

    /**
     * Sync Figma typography to Elementor global typography.
     */
    public function sync_typography(string $file_key): array {
        $styles = $this->figma_api->get_styles($file_key);
        if ($styles === null || !isset($styles['meta']['styles'])) {
            return [];
        }

        $kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
        $settings = $kit->get_settings();
        $synced = [];
        $typo_index = 1;

        foreach ($styles['meta']['styles'] as $style) {
            if (($style['style_type'] ?? '') !== 'TEXT') {
                continue;
            }

            $style_data = $style['style'] ?? [];
            if (empty($style_data)) {
                continue;
            }

            $settings["system_typography"][] = [
                '_id' => "figma-typo-{$typo_index}",
                'title' => $style['name'] ?? "Figma Typography {$typo_index}",
                'typography_font_family' => $style_data['fontFamily'] ?? '',
                'typography_font_size' => [
                    'unit' => 'px',
                    'size' => $style_data['fontSize'] ?? 16,
                ],
                'typography_font_weight' => (string) ($style_data['fontWeight'] ?? '400'),
                'typography_line_height' => [
                    'unit' => 'px',
                    'size' => $style_data['lineHeightPx'] ?? ($style_data['fontSize'] ?? 16) * 1.5,
                ],
                'typography_letter_spacing' => [
                    'unit' => 'px',
                    'size' => $style_data['letterSpacing'] ?? 0,
                ],
            ];

            $synced[] = [
                'name' => $style['name'],
                'type' => 'typography',
                'value' => $style_data,
                'var' => "figma-typo-{$typo_index}",
            ];

            $typo_index++;
        }

        $kit->update_settings($settings);
        update_option(self::SYNCED_STYLES_OPTION, $synced);

        return $synced;
    }

    /**
     * Handle form submission for style sync.
     */
    public function handle_form_submission(): void {
        if (!isset($_POST['hello_figma_fetch_styles'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_figma_sync_nonce'] ?? '', 'hello_figma_sync_styles')) {
            wp_die(__('Security check failed.', 'hello-figma'));
        }

        $file_key = sanitize_text_field($_POST['figma_file_key'] ?? '');
        if (empty($file_key)) {
            return;
        }

        update_option('hello_figma_file_key', $file_key);

        if (!$this->figma_api->has_token()) {
            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-warning"><p>' .
                    esc_html__('Please configure your Figma Personal Access Token first.', 'hello-figma') .
                    '</p></div>';
            });
            return;
        }

        $colors = $this->sync_colors($file_key);
        $typography = $this->sync_typography($file_key);

        $message = sprintf(
            /* translators: 1: Number of colors synced, 2: Number of typography styles synced */
            __('Synced %1$d colors and %2$d typography styles from Figma.', 'hello-figma'),
            count($colors),
            count($typography)
        );

        add_action('admin_notices', function () use ($message): void {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        });
    }

    private function get_synced_colors(array $synced): array {
        return array_values(array_filter($synced, fn($s) => $s['type'] === 'color'));
    }

    private function get_synced_typography(array $synced): array {
        return array_values(array_filter($synced, fn($s) => $s['type'] === 'typography'));
    }

    private function extract_color_from_style(array $style): ?string {
        $paint = $style['paint'] ?? $style['fills'][0] ?? null;
        if ($paint === null || ($paint['type'] ?? '') !== 'SOLID') {
            return null;
        }

        $color = $paint['color'] ?? [];
        if (empty($color)) {
            return null;
        }

        $r = (int) round(($color['r'] ?? 0) * 255);
        $g = (int) round(($color['g'] ?? 0) * 255);
        $b = (int) round(($color['b'] ?? 0) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
