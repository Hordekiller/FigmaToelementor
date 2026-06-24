<div class="wrap hello-figma-templates">
    <h1><?php esc_html_e('Figma Templates', 'hello-figma'); ?></h1>

    <?php if (empty($templates)): ?>
        <div class="notice notice-info">
            <p><?php esc_html_e('No Figma templates imported yet. Go to Dashboard to convert your first design.', 'hello-figma'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Title', 'hello-figma'); ?></th>
                    <th><?php esc_html_e('Type', 'hello-figma'); ?></th>
                    <th><?php esc_html_e('File Key', 'hello-figma'); ?></th>
                    <th><?php esc_html_e('Imported', 'hello-figma'); ?></th>
                    <th><?php esc_html_e('Actions', 'hello-figma'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $template):
                    $figma_data = (new \HelloFigma\Template_Manager())->get_figma_data($template->ID);
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(get_the_title($template)); ?></strong>
                        </td>
                        <td>
                            <?php
                            $terms = wp_get_object_terms($template->ID, 'elementor_library_type', ['fields' => 'names']);
                            echo esc_html(implode(', ', $terms));
                            ?>
                        </td>
                        <td>
                            <code><?php echo esc_html($figma_data['file_key'] ?? ''); ?></code>
                        </td>
                        <td><?php echo esc_html(get_the_date('Y-m-d H:i', $template)); ?></td>
                        <td class="actions">
                            <a href="<?php echo esc_url(add_query_arg(['post' => $template->ID, 'action' => 'elementor'], admin_url('post.php'))); ?>"
                               class="button button-small button-primary"
                               title="<?php esc_attr_e('Edit with Elementor', 'hello-figma'); ?>">
                                <?php esc_html_e('Edit', 'hello-figma'); ?>
                            </a>
                            <button type="button"
                                    class="button button-small export-template"
                                    data-post-id="<?php echo esc_attr($template->ID); ?>">
                                <?php esc_html_e('Export', 'hello-figma'); ?>
                            </button>
                            <button type="button"
                                    class="button button-small delete-template"
                                    data-post-id="<?php echo esc_attr($template->ID); ?>">
                                <?php esc_html_e('Delete', 'hello-figma'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
