<?php

namespace Elementor;

class Controls_Manager {
    public const DIMENSIONS = 'dimensions';
    public const TAB_STYLE = 'style';
    public const TAB_CONTENT = 'content';
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const URL = 'url';
    public const SELECT = 'select';
    public const COLOR = 'color';
    public const MEDIA = 'media';
    public const ICONS = 'icons';
    public const SLIDER = 'slider';
    public const CHOOSE = 'choose';
}

class Group_Control_Border {
    public static function get_type(): string { return 'border'; }
}

class Group_Control_Typography {
    public static function get_type(): string { return 'typography'; }
}

class Group_Control_Background {
    public static function get_type(): string { return 'background'; }
}

class Group_Control_Box_Shadow {
    public static function get_type(): string { return 'box-shadow'; }
}

class Group_Control_Text_Shadow {
    public static function get_type(): string { return 'text-shadow'; }
}

class Widget_Base {
    public function get_name() {}
    public function get_title() {}
    public function get_icon() {}
    public function get_categories() { return []; }
    public function get_keywords() { return []; }
    protected function register_controls() {}
    protected function render() {}
    protected function add_control($id, $args = []) {}
    protected function add_group_control($group, $args = []) {}
    protected function start_controls_section($id, $args = []) {}
    protected function end_controls_section() {}
    protected function start_controls_tabs($id) {}
    protected function end_controls_tabs() {}
    protected function start_controls_tab($id, $args = []) {}
    protected function end_controls_tab() {}
    protected function add_responsive_control($id, $args = []) {}
    protected function add_render_attribute($element, $key = null, $value = null, $overwrite = false) {}
    protected function print_render_attribute_string($element) {}
    protected function get_render_attribute_string($element): string { return ''; }
    public function get_settings_for_display($setting = null) { return []; }
    protected function add_link_attributes($element, $data = []) {}
    public function get_children() { return []; }
}

class Utils {
    public static function get_placeholder_image_src(): string { return ''; }
}

class Icons_Manager {
    public static function render_icon($icon, $attributes = [], $tag = 'i') {}
}

class Plugin {
    public static $instance = null;
    public $kits_manager;
}

namespace Elementor\Core\Kits\Documents;

class Kit {
    public function register_tab($id, $args) {}
}

namespace Elementor\Core\DynamicTags;

class Tag extends \Elementor\Widget_Base {
    public function get_group() { return []; }
    public function get_categories() { return []; }
}

namespace Elementor\Core\Files\CSS;

class Post {
    public function __construct($post_id) {}
    public function update() {}
    public function delete() {}
    public function get_path(): string { return ''; }
}

namespace Elementor\Modules\DynamicTags;

class Module {
    public const TAG_GROUP = '';
    public const TEXT_CATEGORY = 'text';
    public const COLOR_CATEGORY = 'color';
    public const URL_CATEGORY = 'url';
    public const NUMBER_CATEGORY = 'number';
    public const IMAGE_CATEGORY = 'image';
    public const GALLERY_CATEGORY = 'gallery';
    public const DATETIME_CATEGORY = 'datetime';
    public const POST_CATEGORY = 'post';
    public const MEDIA_CATEGORY = 'media';
    public const SITE_CATEGORY = 'site';
}
