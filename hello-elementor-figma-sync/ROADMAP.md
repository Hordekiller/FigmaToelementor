# 🗺️ نقشه راه افزونه Hello Elementor Figma Sync

> تبدیل مستقیم طرح‌های فیگما به قالب‌های المنتور (همگام با Hello Elementor + Elementor Pro)

---

## 📋 نمای کلی

| بخش | توضیح |
|-----|-------|
| **هدف** | افزونه وردپرسی برای تبدیل خودکار فریم‌های فیگما به ویجت‌های المنتور |
| **تم پایه** | Hello Elementor (سازگاری کامل با هوک‌های اختصاصی آن) |
| **المنتور** | Elementor Pro (با Fallback برای Free) |
| **نوع اتصال** | Figma REST API با Personal Access Token |
| **زبان** | PHP 8.0+ OOP با PSR-4 Autoloading |
| **ساختار** | معماری کلین با Namespace `HelloFigma` و DI |

---

## 📊 تحلیل رقبا

| افزونه | نصب فعال | نقاط قوت | نقاط ضعف |
|--------|---------|----------|----------|
| **UiChemy** | 9000+ | بلوغ، پشتیبانی از ۳ بیلدر | نیاز به Figma Plugin، وابستگی ابری |
| **Figmentor** | 300+ | سادگی، یک کلیک | نیاز به API Token ابری |
| **Figma To Elementor** (Community) | 21k+ کاربر | رایگان، پراستفاده | JSON دستی، بدون پشتیبانی |
| **OnPress WP** | جدید | متن‌باز کامل | ناپایدار، تازه کار |

**فرصت:** هیچ رقیبی به صورت بومی روی **Hello Elementor** و **Elementor Pro** متمرکز نیست.

---

## 🧱 معماری پوشه‌ها

```
hello-elementor-figma-sync/
├── hello-elementor-figma-sync.php       # فایل اصلی (Bootstrap)
├── includes/
│   ├── class-plugin.php                 # کلاس اصلی (Singleton + DI)
│   ├── class-admin.php                  # پنل مدیریت
│   ├── class-figma-api.php              # کلاینت REST API فیگما
│   ├── class-elementor-renderer.php     # موتور تبدیل (قلب افزونه)
│   ├── class-template-manager.php       # مدیریت کتابخانه قالب
│   ├── class-style-sync.php             # سینک استایل‌های گلوبال
│   ├── class-image-handler.php          # مدیریت تصاویر و آپلود
│   ├── class-asset-manager.php          # مدیریت CSS/JS
│   └── class-compatibility.php          # چک‌های سازگاری
├── admin/
│   ├── views/
│   │   ├── dashboard.php                # داشبورد اصلی
│   │   ├── templates.php                # کتابخانه قالب
│   │   ├── settings.php                 # تنظیمات
│   │   └── style-sync.php               # سینک استایل
│   ├── js/admin.js                       # اسکریپت ادمین
│   └── css/admin.css                     # استایل ادمین
├── widgets/                              # ویجت‌های المنتور
│   ├── class-figma-container.php
│   ├── class-figma-button.php
│   ├── class-figma-image.php
│   ├── class-figma-heading.php
│   ├── class-figma-icon-box.php
│   └── class-figma-section.php
├── dynamic-tags/                         # Dynamic Tags المنتور پرو
│   ├── class-figma-field.php
│   └── class-figma-text.php
├── languages/
│   └── hello-elementor-figma-fa_IR.po
└── readme.txt
```

---

## 🎯 فازبندی اجرایی (۸ هفته)

### ✅ فاز ۱: هسته اصلی (هفته ۱-۲)

**فایل‌ها:**
- `hello-elementor-figma-sync.php` — بوت‌استرپ، header comments, autoloader
- `includes/class-plugin.php` — Singleton، DI Container، hooks
- `includes/class-compatibility.php` — چک ورژن PHP, WP, Elementor
- `includes/class-asset-manager.php` — مدیریت CSS/JS

**اهداف:**
- [x] فعال‌سازی و غیرفعال‌سازی افزونه
- [x] چک وابستگی‌ها با نوتیفیکیشن
- [x] بارگذاری autoloader
- [x] پنل ادمین اولیه
- [x] i18n پشتیبانی (فارسی + انگلیسی)

### ✅ فاز ۲: اتصال فیگما (هفته ۲-۳)

**فایل:** `includes/class-figma-api.php`

**اهداف:**
- [x] کلاینت کامل REST API فیگما (GET, POST)
- [x] دریافت File Nodes و Children
- [x] دریافت Styles (colors, typography, effects)
- [x] دریافت Variables (Design Tokens)
- [x] دیپلود تصاویر (`GET /v1/images`)
- [x] کش Transient API ضد Rate Limit
- [x] مدیریت Error Handling

### ✅ فاز ۳: موتور تبدیل (هفته ۳-۵)

**فایل:** `includes/class-elementor-renderer.php` ← **قلب افزونه**

**جدول Mapping:**

| نوع فیگما | ویجت المنتور | کنترل‌ها |
|-----------|--------------|---------|
| `TEXT` | heading / text-editor | content, size, color, align |
| `RECTANGLE` | container | background, border, radius |
| `ELLIPSE` | container | border-radius: 50% |
| `FRAME` | container | flex, padding, gap |
| `GROUP` | container | flex-wrap |
| `VECTOR` | image / icon | SVG inline |
| `INSTANCE` | widget سفارشی | متغیر |
| `BOOLEAN_OPERATION` | icon | SVG path |

**اهداف:**
- [x] Parse Recursive Figma Node Tree → Elementor JSON
- [x] استخراج `fills` → background
- [x] استخراج `strokes` → border
- [x] استخراج `effects` → box-shadow, filter
- [x] استخراج `autoLayout` → flexbox
- [x] استخراج `cornerRadius` → border-radius
- [x] استخراج `opacity` → opacity
- [x] استخراج `constraints` → responsive
- [x] خروجی JSON معتبر المنتور

**الگوی خروجی:**
```json
{
  "type": "container",
  "settings": {
    "content_width": "full",
    "flex_direction": "row",
    "padding": { "unit": "px", "top": 20, "bottom": 20 },
    "background_background": "classic",
    "background_color": "#FFFFFF"
  },
  "elements": [
    {
      "type": "heading",
      "settings": {
        "title": "متن نمونه",
        "header_size": "h2",
        "typography_font_family": "Vazirmatn",
        "typography_font_size": { "unit": "px", "size": 28 }
      }
    }
  ]
}
```

### ✅ فاز ۴: سینک استایل گلوبال (هفته ۵-۶)

**فایل:** `includes/class-style-sync.php`

**اهداف:**
- [x] استخراج `localStyles` از فیگما
- [x] نگاشت رنگ → Elementor Global Colors (`--e-global-color-*`)
- [x] نگاشت تایپوگرافی → Global Typography
- [x] پشتیبانی Elementor v4 Atomic Elements
- [x] سینک Design Tokens → CSS Custom Properties
- [x] هوک `elementor/kit/register_tabs`

**فایل:** `includes/class-template-manager.php`

**اهداف:**
- [x] ذخیره قالب‌های تبدیل شده در `wp_posts` (post_type: elementor_library)
- [x] دسته‌بندی با taxonomy
- [x] پرایرود قالب در Elementor
- [x] خروجی JSON قابل import

### ✅ فاز ۵: ویجت‌های سفارشی (هفته ۶-۷)

**فایل‌ها:** `widgets/*`

| ویجت | کلاس | توضیح |
|------|------|-------|
| Container | `Figma_Container` | Flexbox container با auto-layout |
| Button | `Figma_Button` | دکمه با آیکون و auto-layout |
| Image | `Figma_Image` | تصویر با constraints |
| Heading | `Figma_Heading` | تیتر با Rich Text |
| Icon Box | `Figma_Icon_Box` | آیکون + متن |
| Section | `Figma_Section` | سکشن کامل با Auto Width |

### ✅ فاز ۶: ویژگی‌های پیشرفته (هفته ۷-۸)

**اهداف:**
- [x] **Live Preview** — پیش‌نمایش تب‌های فیگما در المنتور
- [x] **Image Auto-Upload** — دیپلود خودکار به Media Library
- [x] **Responsive Breakpoints** — نگاشت constraints → responsive
- [x] **Batch Export** — خروجی چند فریم
- [x] **Dynamic Tags** — تگ‌های داینامیک المنتور پرو
- [x] **RTL Support** — پشتیبانی کامل فارسی/عربی
- [x] **Undo/History** — تاریخچه تبدیل
- [x] **One-Click Sync** — سینک یک کلیکی

---

## 🔌 APIهای کلیدی المنتور

```php
// ثبت ویجت
add_action('elementor/widgets/register', function($widgets_manager) {
    $widgets_manager->register(new \HelloFigma\Widgets\Figma_Container());
});

// ثبت کنترل سفارشی
add_action('elementor/controls/register', function($controls_manager) {
    $controls_manager->register(new \HelloFigma\Controls\Figma_Color());
});

// ثبت Dynamic Tag
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    $dynamic_tags->register(new \HelloFigma\DynamicTags\Figma_Field());
});

// سینک استایل گلوبال
add_action('elementor/kit/register_tabs', function($kit) {
    $kit->register_tab('hello-figma-sync', [
        'label' => __('Figma Sync', 'hello-figma'),
        'callback' => [Figma_Sync_Tab::class, 'render'],
    ]);
});

// هوک Hello Elementor
add_filter('hello_elementor_add_theme_support', '__return_true');
add_filter('elementor/frontend/builder_content_data', [$this, 'inject_figma_styles']);
```

---

## 🚀 پس از نسخه ۱.۰

| نسخه | ویژگی |
|------|-------|
| **v1.1** | AI Layout Detection (تشخیص هوشمند layout با LLM) |
| **v1.2** | Component Library (کامپوننت‌های فیگما → ویجت) |
| **v1.3** | Auto-Animate (Smart Animate → CSS transitions) |
| **v1.4** | Collaboration Mode (چند کاربر همزمان) |
| **v2.0** | Figma MCP Server (پروتکل مدرن برای ادیتورها) |

---

## 📐 تصمیمات معماری

| گزینه | انتخاب | دلیل |
|-------|--------|------|
| Autoloader | **PSR-4** + Composer یا دستی | استاندارد، maintainable |
| Container | **DI Simple** (غیر از Singleton اصلی) | تست‌پذیری |
| Namespace | `HelloFigma` | از Collision جلوگیری |
| Strict Types | `declare(strict_types=1)` | امنیت نوع داده |
| PHP Version | **8.0+** | Property promotion, union types, match |
| Elementor Version | **3.25+** | پشتیبانی از Atomic Elements v4 |
| Figma API | **REST API v1** | بدون نیاز به پلاگین فیگما |

---

## 📝 نکات فنی

- **Rate Limiting:** Figma API محدودیت ۲ درخواست/ثانیه دارد → کش با Transient API
- **Image Download:** تصاویر از طریق `GET /v1/images` با تبدیل به Base64
- **Error Handling:** تمام خطاهای API با `wp_remote_get` و `try/catch` مدیریت شود
- **Performance:** پردازش سنگین با WP Cron در پس‌زمینه
- **Security:** تمام توکن‌ها با `wp_options` و رمزگذاری ذخیره شوند
