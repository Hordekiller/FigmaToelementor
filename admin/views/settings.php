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
                           value="<?php echo esc_attr((new \HelloFigma\Figma_API())->get_token() ? '********' : ''); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('figd_xxxxx...', 'hello-figma'); ?>">
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
