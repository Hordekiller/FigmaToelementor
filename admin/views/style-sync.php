<div class="wrap hello-figma-style-sync">
    <h1><?php esc_html_e('Figma Style Sync', 'hello-figma'); ?></h1>

    <p><?php esc_html_e('Sync your Figma design system colors and typography with Elementor global styles.', 'hello-figma'); ?></p>

    <div class="hello-figma-card">
        <h3><?php esc_html_e('Global Colors Sync', 'hello-figma'); ?></h3>
        <p><?php esc_html_e('Convert Figma color styles to Elementor global colors.', 'hello-figma'); ?></p>
        <button type="button"
                class="button button-primary"
                id="hello-figma-sync-colors"
                data-file-key="<?php echo esc_attr(get_option('hello_figma_file_key', '')); ?>">
            <?php esc_html_e('Sync Colors', 'hello-figma'); ?>
        </button>
    </div>

    <div class="hello-figma-card">
        <h3><?php esc_html_e('Global Typography Sync', 'hello-figma'); ?></h3>
        <p><?php esc_html_e('Convert Figma text styles to Elementor global typography.', 'hello-figma'); ?></p>
        <button type="button"
                class="button button-primary"
                id="hello-figma-sync-typography"
                data-file-key="<?php echo esc_attr(get_option('hello_figma_file_key', '')); ?>">
            <?php esc_html_e('Sync Typography', 'hello-figma'); ?>
        </button>
    </div>

    <div class="hello-figma-card">
        <h3><?php esc_html_e('Full Sync', 'hello-figma'); ?></h3>
        <p><?php esc_html_e('Sync all Figma styles (colors + typography) at once.', 'hello-figma'); ?></p>
        <button type="button"
                class="button button-hero button-primary"
                id="hello-figma-sync-all"
                data-file-key="<?php echo esc_attr(get_option('hello_figma_file_key', '')); ?>">
            <?php esc_html_e('Sync All Styles', 'hello-figma'); ?>
        </button>
    </div>

    <div id="hello-figma-sync-result"></div>
</div>
