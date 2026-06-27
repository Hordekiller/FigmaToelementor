<?php
$has_token = $has_token ?? false;
$stats = $stats ?? ['total' => 0, 'recent' => []];
?>
<div class="wrap hello-figma-dashboard">
    <h1><?php esc_html_e('Hello Figma Sync', 'hello-figma'); ?></h1>

    <!-- Status Cards -->
    <div class="hello-figma-status-cards">
        <div class="hello-figma-card">
            <h3><?php esc_html_e('Figma Connection', 'hello-figma'); ?></h3>
            <div class="card-status">
                <?php if ($has_token) : ?>
                    <span class="status-connected"><?php esc_html_e('✅ Connected', 'hello-figma'); ?></span>
                <?php else : ?>
                    <span class="status-disconnected"><?php esc_html_e('❌ Not Connected', 'hello-figma'); ?></span>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=hello-figma-settings')); ?>">
                        <?php esc_html_e('Configure Token', 'hello-figma'); ?>
                    </a></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="hello-figma-card">
            <h3><?php esc_html_e('Imported Templates', 'hello-figma'); ?></h3>
            <p class="card-stat"><?php echo esc_html($stats['total']); ?></p>
        </div>

        <?php if (!empty(get_option('hello_figma_file_key', ''))) : ?>
        <div class="hello-figma-card">
            <h3><?php esc_html_e('Default File', 'hello-figma'); ?></h3>
            <p><code><?php echo esc_html(get_option('hello_figma_file_key', '')); ?></code></p>
            <button type="button" id="hello-figma-browse-default" class="button">
                <?php esc_html_e('Browse Frames', 'hello-figma'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Step 1: Enter File Key -->
    <div class="hello-figma-step" id="step-1">
        <div class="hello-figma-card">
            <h2><?php esc_html_e('Step 1: Enter Figma File Key', 'hello-figma'); ?></h2>
            <p><?php esc_html_e('Paste your Figma URL or file key.', 'hello-figma'); ?></p>
            <form id="hello-figma-load-file">
                <?php wp_nonce_field('hello_figma_nonce', '_wpnonce'); ?>
                <input type="text"
                       id="figma-file-key-input"
                       name="file_key"
                       value="<?php echo esc_attr(get_option('hello_figma_file_key', '')); ?>"
                       placeholder="<?php esc_attr_e('e.g. https://www.figma.com/file/abc123DEF/Design or abc123DEF', 'hello-figma'); ?>"
                       class="regular-text"
                       required
                       style="width:500px; max-width:100%;">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Load Frames', 'hello-figma'); ?>
                </button>
            </form>
            <div id="step-1-loading" class="hello-figma-loading" style="display:none;">
                <p><?php esc_html_e('Fetching file structure from Figma...', 'hello-figma'); ?></p>
            </div>
            <div id="step-1-error" class="hello-figma-error" style="display:none;"></div>
        </div>
    </div>

    <!-- Step 2-Review: Section Preview & Type Overrides -->
    <div class="hello-figma-step" id="step-2-review" style="display:none;">
        <div class="hello-figma-card">
            <h2><?php esc_html_e('Review Sections', 'hello-figma'); ?></h2>
            <p><?php esc_html_e('Review each section of the frame and override the detected type if needed before final import.', 'hello-figma'); ?></p>
            <div id="hello-figma-review-grid" class="hello-figma-review-grid">
                <!-- Sections rendered by JS -->
            </div>
            <div class="hello-figma-review-actions" style="margin-top:20px;">
                <button type="button" id="hello-figma-confirm-import" class="button button-primary">
                    <?php esc_html_e('Confirm & Import', 'hello-figma'); ?>
                </button>
                <button type="button" id="hello-figma-back-to-frames" class="button">
                    <?php esc_html_e('Back', 'hello-figma'); ?>
                </button>
            </div>
            <div id="step-2-review-error" class="hello-figma-error" style="display:none;"></div>
        </div>
    </div>

    <!-- Step 2: Frame Browser -->
    <div class="hello-figma-step" id="step-2" style="display:none;">
        <div class="hello-figma-card">
            <h2>
                <?php esc_html_e('Step 2: Select a Frame to Import', 'hello-figma'); ?>
                <span id="figma-file-name" style="font-size:14px;color:#666;font-weight:normal;margin-left:10px;"></span>
            </h2>
            <div id="hello-figma-frame-grid" class="hello-figma-frame-grid">
                <!-- Frames rendered by JS -->
            </div>
            <div id="step-2-loading" class="hello-figma-loading" style="display:none;">
                <p><?php esc_html_e('Loading preview images...', 'hello-figma'); ?></p>
            </div>
        </div>
    </div>

    <!-- Step 3: Convert Result -->
    <div class="hello-figma-step" id="step-3" style="display:none;">
        <div class="hello-figma-card success" id="step-3-content">
            <h2><?php esc_html_e('✅ Import Successful!', 'hello-figma'); ?></h2>
            <p id="step-3-message"><?php esc_html_e('Template has been created.', 'hello-figma'); ?></p>
            <p>
                <a id="step-3-edit-link" href="#" class="button button-primary" target="_blank">
                    <?php esc_html_e('Edit with Elementor', 'hello-figma'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hello-figma-templates')); ?>" class="button">
                    <?php esc_html_e('View All Templates', 'hello-figma'); ?>
                </a>
                <button type="button" id="step-3-import-another" class="button">
                    <?php esc_html_e('Import Another', 'hello-figma'); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- Convert Progress -->
    <div id="hello-figma-convert-progress" style="display:none;">
        <div class="hello-figma-card">
            <h3><?php esc_html_e('Importing...', 'hello-figma'); ?></h3>
            <div class="hello-figma-progress-track">
                <div class="hello-figma-progress-bar" id="hello-figma-progress-bar"
                    role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <p id="hello-figma-progress-label" class="hello-figma-progress-label"><?php esc_html_e('Starting...', 'hello-figma'); ?></p>
            <p id="hello-figma-progress-detail" class="hello-figma-progress-detail"></p>
        </div>
    </div>

    <!-- Recent Imports -->
    <?php if (!empty($stats['recent'])) : ?>
        <div class="hello-figma-recent">
            <h2><?php esc_html_e('Recent Imports', 'hello-figma'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'hello-figma'); ?></th>
                        <th><?php esc_html_e('Type', 'hello-figma'); ?></th>
                        <th><?php esc_html_e('Date', 'hello-figma'); ?></th>
                        <th><?php esc_html_e('Actions', 'hello-figma'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent'] as $template) : ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($template)); ?></td>
                            <td><?php echo esc_html(implode(', ', wp_get_object_terms($template->ID, 'elementor_library_type', ['fields' => 'names']))); ?></td>
                            <td><?php echo esc_html(get_the_date('', $template)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['post' => $template->ID, 'action' => 'elementor'], admin_url('post.php'))); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Edit', 'hello-figma'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Logs -->
<div class="hello-figma-card" style="margin-top: 20px;">
    <h3><?php esc_html_e('Recent Activity Log', 'hello-figma'); ?></h3>
    <div style="max-height:300px;overflow-y:auto;background:#f5f5f5;padding:10px;
                border:1px solid #ddd;border-radius:4px;font-family:monospace;
                font-size:12px;line-height:1.5;white-space:pre-wrap;">
        <?php
        $log_contents = \HelloFigma\Logger::get_latest_log_contents(50);
        echo $log_contents !== '' ? esc_html($log_contents) : esc_html__('No recent log entries.', 'hello-figma');
        ?>
    </div>
    <p style="margin-top:8px;color:#666;font-size:12px;">
        <?php esc_html_e('Last 50 lines from today\'s import log.', 'hello-figma'); ?>
    </p>
</div>
