<div class="wrap hello-figma-settings">
    <h1><?php esc_html_e('Hello Figma Sync Settings', 'hello-figma'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('hello_figma_settings', '_hello_figma_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="figma_pat"><?php esc_html_e('Figma Personal Access Token', 'hello-figma'); ?></label>
                </th>
                <td>
                    <input type="password"
                           id="figma_pat"
                           name="figma_pat"
                           value=""
                           class="regular-text"
                            placeholder="<?php echo (new \HelloFigma\Figma_API())->has_token()
                                ? esc_attr__('(current token hidden — enter new value to replace)', 'hello-figma')
                                : esc_attr__('figd_xxxxx...', 'hello-figma'); ?>"
                           autocomplete="off">
                    <?php if ((new \HelloFigma\Figma_API())->has_token()) : ?>
                        <p class="description">
                            <?php esc_html_e('A token is already configured. Leave blank to keep it.', 'hello-figma'); ?>
                        </p>
                    <?php endif; ?>
                    <p class="description">
                        <?php esc_html_e('Generate a Personal Access Token from Figma Settings > Account > Personal Access Tokens.', 'hello-figma'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="figma_file_key"><?php esc_html_e('Default Figma File Key', 'hello-figma'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="figma_file_key"
                           name="figma_file_key"
                           value="<?php echo esc_attr(get_option('hello_figma_file_key', '')); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('e.g. abc123DEFghi', 'hello-figma'); ?>">
                    <p class="description">
                        <?php esc_html_e('The default Figma file key. Found in the URL: figma.com/file/KEY/...', 'hello-figma'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit"
                    name="hello_figma_save_settings"
                    class="button button-primary">
                <?php esc_html_e('Save Settings', 'hello-figma'); ?>
            </button>
        </p>
    </form>

    <hr>

    <?php
    $figma_api = new \HelloFigma\Figma_API();
    $has_token = $figma_api->has_token();
    $expiry_info = $figma_api->get_token_expiry_info();
    $token_valid = $has_token ? $figma_api->test_token() : false;
    ?>

    <div class="hello-figma-card" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;">
        <h3><?php esc_html_e('Figma Connection Status', 'hello-figma'); ?></h3>
        <p>
            <?php if (!$has_token) : ?>
                <span style="color: #d63638; font-weight: 600;"><?php esc_html_e('❌ No token configured', 'hello-figma'); ?></span>
            <?php elseif ($token_valid) : ?>
                <span style="color: #00a32a; font-weight: 600;"><?php esc_html_e('✅ Connected', 'hello-figma'); ?></span>
            <?php else : ?>
                <span style="color: #d63638; font-weight: 600;"><?php esc_html_e('❌ Invalid token', 'hello-figma'); ?></span>
            <?php endif; ?>
        </p>
        <?php if ($expiry_info) : ?>
            <p style="color: #666;"><?php echo esc_html($expiry_info); ?></p>
        <?php endif; ?>
    </div>

    <form method="post" action="" style="margin-bottom: 20px;">
        <?php wp_nonce_field('hello_figma_clear_cache', '_figma_cache_nonce'); ?>
        <button type="submit"
                name="hello_figma_clear_cache"
                class="button"
                onclick="return confirm('<?php echo esc_js(__('Clear all cached Figma data? This may slow down the next request.', 'hello-figma')); ?>');">
            <?php esc_html_e('Clear Figma Cache', 'hello-figma'); ?>
        </button>
    </form>

    <div class="hello-figma-help">
        <h2><?php esc_html_e('How to Get Your Figma Token', 'hello-figma'); ?></h2>
        <ol>
            <li><?php esc_html_e('Log in to your Figma account.', 'hello-figma'); ?></li>
            <li><?php esc_html_e('Go to Settings > Account > Personal Access Tokens.', 'hello-figma'); ?></li>
            <li><?php esc_html_e('Click "Generate new token" and give it a name.', 'hello-figma'); ?></li>
            <li><?php esc_html_e('Copy the token and paste it above.', 'hello-figma'); ?></li>
        </ol>

        <h2><?php esc_html_e('Finding Your File Key', 'hello-figma'); ?></h2>
        <p><?php esc_html_e('Open your Figma design file. The URL looks like:', 'hello-figma'); ?></p>
        <code>https://www.figma.com/file/<strong>abc123DEFghi</strong>/My-Design</code>
        <p><?php esc_html_e('The part between "file/" and "/My-Design" is your file key.', 'hello-figma'); ?></p>
    </div>
</div>
