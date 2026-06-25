<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Component_Detector {
    private const PATTERNS = [
        'slider'      => ['slider', 'اسلایدر'],
        'carousel'    => ['carousel', 'کاروسل'],
        'hero'         => ['hero', 'هرو', 'بنر اصلی'],
        'testimonial'  => ['testimonial', 'review', 'نظرات', 'نظر کاربران'],
        'pricing'      => ['pricing', 'price table', 'قیمت', 'تعرفه'],
        'faq'          => ['faq', 'سوالات متداول', 'پرسش'],
        'footer'       => ['footer', 'فوتر', 'پاورقی'],
        'header'       => ['header', 'navbar', 'هدر', 'نوار بالا'],
        'gallery'      => ['gallery', 'گالری'],
        'team'         => ['team', 'تیم', 'اعضای تیم'],
        'cta'          => ['cta', 'call to action', 'فراخوان'],
        'newsletter'   => ['newsletter', 'خبرنامه'],
        'blog'         => ['blog', 'posts', 'وبلاگ', 'مقالات'],
        'stats'        => ['stats', 'counter', 'آمار', 'شمارنده'],
        'video'        => ['video section', 'ویدیو'],
        'map'          => ['map', 'نقشه'],
        'social'       => ['social', 'شبکه اجتماعی'],
        'breadcrumb'   => ['breadcrumb', 'مسیر صفحه'],
    ];

    /**
     * @return string|null Canonical component_type key, or null if no pattern matched.
     * If multiple patterns match, return the FIRST match in PATTERNS array order.
     */
    public static function detect(string $layer_name): ?string {
        $haystack = mb_strtolower(trim($layer_name));
        if ($haystack === '') {
            return null;
        }
        foreach (self::PATTERNS as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                    return $type;
                }
            }
        }
        return null;
    }
}
