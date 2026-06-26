<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?Plugin $instance = null;

    private Admin $admin;
    private Figma_API $figma_api;
    private Elementor_Renderer $renderer;
    private Style_Sync $style_sync;
    private Template_Manager $template_manager;
    private Image_Handler $image_handler;
    private Asset_Manager $asset_manager;
    private Compatibility $compatibility;

    private function __construct()
    {
        $this->init_services();
        $this->init_hooks();
    }

    public static function instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_services(): void
    {
        $this->compatibility = new Compatibility();
        $this->asset_manager = new Asset_Manager();
        $this->figma_api = new Figma_API();
        $this->renderer = new Elementor_Renderer($this->figma_api);
        $this->style_sync = new Style_Sync($this->figma_api);
        $this->template_manager = new Template_Manager();
        $this->image_handler = new Image_Handler($this->figma_api);
        $this->admin = new Admin($this);
    }

    private function init_hooks(): void
    {
        add_action('init', [$this, 'init'], 0);
        register_activation_hook(HELLO_FIGMA_FILE, [$this, 'activate']);
        register_deactivation_hook(HELLO_FIGMA_FILE, [$this, 'deactivate']);
    }

    public function init(): void
    {
        if (!$this->compatibility->check()) {
            return;
        }

        $this->asset_manager->init();
        $this->admin->init();

        add_action('elementor/init', [$this, 'on_elementor_init']);
        add_action('elementor/editor/after_enqueue_scripts', [$this->asset_manager, 'enqueue_elementor_editor_assets']);
    }

    public function on_elementor_init(): void
    {
        $this->style_sync->init();
        $this->register_category();
        $this->register_widgets();
        $this->register_dynamic_tags();
    }

    private function register_category(): void
    {
        add_action('elementor/elements/categories_registered', function ($categories_manager): void {
            $categories_manager->add_category(
                'figma-category',
                [
                    'title' => esc_html__('Figma Elements', 'hello-figma'),
                    'icon' => 'eicon-favorite',
                ]
            );
        });
    }

    private function register_widgets(): void
    {
        add_action('elementor/widgets/register', function ($widgets_manager): void {
            $widgets = [
                new Widgets\Figma_Container(),
                new Widgets\Figma_Button(),
                new Widgets\Figma_Image(),
                new Widgets\Figma_Heading(),
                new Widgets\Figma_Icon_Box(),
                new Widgets\Figma_Section(),
            ];

            foreach ($widgets as $widget) {
                $widgets_manager->register($widget);
            }
        });
    }

    private function register_dynamic_tags(): void
    {
        add_action('elementor/dynamic_tags/register', function ($dynamic_tags): void {
            $dynamic_tags->register(new DynamicTags\Figma_Field());
            $dynamic_tags->register(new DynamicTags\Figma_Text());
        });

        add_action('elementor/dynamic_tags/register_groups', function ($dynamic_tags): void {
            $dynamic_tags->register_group('figma-group', [
                'title' => esc_html__('Figma', 'hello-figma'),
            ]);
        });
    }

    public function activate(): void
    {
        $this->compatibility->check_or_die();
        $this->template_manager->create_tables();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function get_admin(): Admin
    {
        return $this->admin;
    }

    public function get_figma_api(): Figma_API
    {
        return $this->figma_api;
    }

    public function get_renderer(): Elementor_Renderer
    {
        return $this->renderer;
    }

    public function get_style_sync(): Style_Sync
    {
        return $this->style_sync;
    }

    public function get_template_manager(): Template_Manager
    {
        return $this->template_manager;
    }

    public function get_image_handler(): Image_Handler
    {
        return $this->image_handler;
    }

    public function get_asset_manager(): Asset_Manager
    {
        return $this->asset_manager;
    }

    public function get_compatibility(): Compatibility
    {
        return $this->compatibility;
    }
}
