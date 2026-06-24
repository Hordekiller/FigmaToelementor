/**
 * Elementor Editor JS — Direct element creation via $e.run() commands.
 *
 * Instead of importing a JSON file, you can use the Commands API to
 * programmatically create containers and widgets inside the editor.
 *
 * @see https://developers.elementor.com/js-api/js-api-commands/
 *
 * Usage (browser console while in Elementor editor):
 *   $e.run('hello-figma/import-template', { ...jsonData });
 *
 * Or via the WordPress AJAX endpoint:
 *   helloFigmaEditor.insertTemplate(fileKey, nodeId);
 */

(function ($) {
    'use strict';

    if (typeof elementor === 'undefined') {
        return;
    }

    const helloFigmaEditor = {

        /**
         * Create an Elementor element from our internal JSON representation.
         *
         * @param {Object} element  Element data: { elType, widgetType?, settings, elements }
         * @param {Object} options  { container: parentId | 'root', at: index }
         * @return {Promise<string>} Created element ID
         */
        async createElement(element, options = {}) {
            const container = options.container === 'root'
                ? elementor.getPreviewContainer()
                : elementor.getContainer(options.container);

            const elData = {
                elType: element.elType,
                isInner: element.isInner || false,
                settings: element.settings || {},
                elements: element.elements || []
            };

            if (element.widgetType) {
                elData.widgetType = element.widgetType;
            }

            // Use the Commands API to create the element
            const result = await $e.run('document/elements/create', {
                container: container,
                model: elData,
                options: {
                    at: options.at != null ? options.at : -1,
                }
            });

            const newId = result.id;

            // Recursively create nested elements
            if (element.elements && element.elements.length > 0) {
                for (let i = 0; i < element.elements.length; i++) {
                    await this.createElement(element.elements[i], {
                        container: newId,
                        at: i
                    });
                }
            }

            return newId;
        },

        /**
         * Full template import from Figma JSON.
         *
         * @param {Object} templateData  Template structure: { title, content: [...] }
         */
        async insertTemplate(templateData) {
            if (!templateData || !templateData.content) {
                console.error('[HelloFigma] Invalid template data');
                return;
            }

            const content = templateData.content;
            const title = templateData.title || 'Figma Import';

            for (let i = 0; i < content.length; i++) {
                await this.createElement(content[i], {
                    container: 'root',
                    at: i
                });
            }

            // Notify user
            $e.run('document/elements/settings', {
                container: elementor.getPreviewContainer(),
                settings: { hello_figma_note: 'Imported from Figma: ' + title }
            });

            console.log('[HelloFigma] Template imported successfully:', title);
        },

        /**
         * Fetch Figma data via AJAX and insert directly into Elementor editor.
         *
         * @param {string} fileKey  Figma file key
         * @param {string} nodeId   Frame node ID
         */
        async importFromFigma(fileKey, nodeId) {
            const url = helloFigmaEditorData?.ajaxUrl || ajaxurl;
            try {
                const response = await $.post(url, {
                    action: 'hello_figma_convert',
                    nonce: helloFigmaEditorData?.nonce || '',
                    file_key: fileKey,
                    node_id: nodeId,
                    title: 'Figma Import',
                    format: 'json'
                });

                if (response.success) {
                    await this.insertTemplate(response.data.template);
                } else {
                    console.error('[HelloFigma] Conversion failed:', response.data.message);
                }
            } catch (err) {
                console.error('[HelloFigma] AJAX error:', err);
            }
        }
    };

    // Register a custom command for easy access
    $e.commands.register('hello-figma', 'import-template', {
        validateArgs: (args) => {
            if (!args.template) {
                throw new Error('Missing template data');
            }
        },
        apply: async (args) => {
            await helloFigmaEditor.insertTemplate(args.template);
        }
    });

    // Expose globally
    window.helloFigmaEditor = helloFigmaEditor;

    console.log('[HelloFigma] Editor integration loaded. Use helloFigmaEditor.insertTemplate()');

})(jQuery);
