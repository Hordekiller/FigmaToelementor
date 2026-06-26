# تحلیل جامع پروژه Figma to Elementor

## فهرست
1. [تحلیل مشکلات کاربران](#تحلیل-مشکلات-کاربران)
2. [بررسی مستندات و API](#بررسی-مستندات-و-api)
3. [شکاف‌ها و نواقص پلاگین](#شکاف‌ها-و-نواقص-پلاگین)
4. [مشکلات امنیتی](#مشکلات-امنیتی)
5. [مشکلات تجربه کاربری](#مشکلات-تجربه-کاربری)
6. [راهکارهای پیشنهادی](#راهکارهای-پیشنهادی)

---

## تحلیل مشکلات کاربران

### استک‌اورفلو (Stack Overflow)

| موضوع | تعداد سوال | وضعیت |
|-------|-----------|--------|
| تبدیل Figma به WordPress/Elementor | ~356 سوال | اکثراً بی‌پاسخ |
| عدم تطابق رندر فونت (Figma vs Browser) | 79239410, 78237417 | مشکلی حل نشده |
| مشکلات گرادیانت (Circular, Angular) | 79013129, 77820180 | نیاز به محاسبه دقیق CSS |
| تطابق سایه (Shadow) | 78499114 | نیاز به تبدیل دقیق |
| استخراج Design Tokens به JSON | 78723863 | فقط Enterprise |
| عدم پشتیبانی از حالت‌های Hover/Interactive | 78526979 | API فیگما پشتیبانی نمی‌کند |

**نتیجه کلیدی:** هیچ راه‌حل خودکار کاملی برای تبدیل Figma به Elementor وجود ندارد. کاربران مجبورند طراحی را دستی بازسازی کنند.

### ردیت (Reddit)

- **r/WordPress و r/elementor:** کاربران مدام به دنبال راهی برای وارد کردن سریع طراحی Figma به Elementor هستند
- **شکایت اصلی:** پلاگین‌های موجود (UiChemy: 9k نصب، Figmentor: 300 نصب) کیفیت کافی برای استفاده تولیدی ندارند
- **نداشتن Round-Trip:** تغییرات در Elementor به Figma برنمی‌گردد

### Figma Community Forum

- **16,000+ پست** در "Ask the Community"
- **6,200+ درخواست ویژگی** در "Suggest a Feature"
- درخواست‌های مکرر: خروجی خودکار CSS/HTML، همگام‌سازی با CMS

---

## بررسی مستندات و API

### Figma REST API

| اندپوینت | وضعیت در پلاگین | توضیح |
|----------|----------------|-------|
| `GET /v1/files/:key` | ✅ پیاده‌سازی شده | دریافت فایل کامل |
| `GET /v1/files/:key/nodes` | ✅ پیاده‌سازی شده | دریافت نود مشخص |
| `GET /v1/images/:key` | ✅ پیاده‌سازی شده | دانلود تصاویر |
| `GET /v1/files/:key/styles` | ✅ پیاده‌سازی شده | دریافت استایل‌ها |
| `GET /v1/files/:key/components` | ❌ پیاده‌سازی نشده | دریافت کامپوننت‌ها |
| `GET /v1/files/:key/variables/local` | ⚠️ کد هست اما استفاده نمی‌شود | دریافت متغیرها (Enterprise) |
| `GET /v1/teams/:team_id/styles` | ✅ پیاده‌سازی شده | استایل‌های تیمی |

**محدودیت‌های بحرانی API فیگما:**
1. حالت‌های تعاملی (Hover, Pressed, Drag) در REST API وجود ندارند
2. موقعیت‌های مطلق (Absolute Positioning) در API خروجی داده می‌شود اما Elementor مبتنی بر Flexbox است
3. متغیرها (Variables) فقط برای طرح Enterprise در دسترس است
4. هیچ اندپوینتی برای ایجاد/ویرایش نودها وجود ندارد
5. خروجی CSS وجود ندارد - فقط JSON خام

### Elementor Template JSON Structure

- **`elType`**: `container` (جدید) یا `section` (قدیمی)
- **`widgetType`**: `heading`, `text-editor`, `image`, `button`, `accordion`, `image-carousel`, `image-gallery`
- **تفاوت بنیادین**: Elementor از Flexbox استفاده می‌کند، Figma از موقعیت مطلق Pixel-based

---

## شکاف‌ها و نواقص پلاگین

### ⚠️ بحرانی

| # | مشکل | فایل | خط | وضعیت | توضیح |
|---|------|------|----|--------|--------|
| 1 | **زاویه گرادیانت ثابت (180°) بود** | `class-elementor-renderer.php` | 437 | ✅ **رفع شد** | `gradientHandlePositions` با `atan2(dx, -dy)` محاسبه می‌شود. پشتیبانی از Radial/ Angular/ Diamond |
| 2 | **متغیرهای Figma (Variables) پیاده‌سازی نشده** | `class-figma-api.php` | 160-173 | ⏳ اولویت پایین | نیاز به Figma Enterprise plan |
| 3 | **آیکون widgets فال‌بک بود** | `class-elementor-renderer.php` | 330-340 | ✅ **رفع شد** | BOOLEAN_OPERATION/STAR/POLYGON → image widget با Figma export |
| 4 | **دکمه متن را از child TEXT نمی‌گرفت** | `class-elementor-renderer.php` | 717 | ✅ **رفع شد** | `find_text_nodes_in_subtree()` برای یافتن متن واقعی |
| 5 | **مقادیر meta در Dynamic Tags ذخیره نمی‌شد** | `class-template-manager.php` | 56-57 | ✅ **رفع شد** | `_hello_figma_node_name` و `_hello_figma_file_name` ذخیره می‌شوند |

### 🔶 مهم

| # | مشکل | فایل | خط | توضیح |
|---|------|------|----|--------|
| 6 | **پردازش فرم در حین رندر انجام می‌شد** | `class-admin.php` | 82 | ✅ **رفع شد** | انتقال `handle_form_submission()` به `admin_init` از طریق `handle_style_sync_form()` |
| 7 | **پارامتر type در syncStyles نادیده گرفته می‌شد** | `class-admin.php` | 269-293 | ✅ **رفع شد** | حالا `type` را می‌خواند و فقط نوع درخواستی را sync می‌کند |
| 8 | **Token به صورت Plaintext در wp_options ذخیره می‌شود** | `class-figma-api.php` | 22 | ⏳ نیاز به بررسی | می‌توان از `wp_salt()` یا رمزگذاری استفاده کرد |
| 9 | **Resolution تصاویر بعد از conversion انجام می‌شود** | `class-elementor-renderer.php` | 190 | ⏳ معماری فعلی | قابل بهبود با progress indicator |
| 10 | **مقادیر گرادیانت Stop به درستی محاسبه نمی‌شد** | `class-elementor-renderer.php` | 427-431 | ✅ **رفع شد** | الان `position` واقعی از API خوانده می‌شود |

### 🔸 متوسط

| # | مشکل | فایل | خط | توضیح |
|---|------|------|----|--------|
| 11 | **Compatibility کلاس new instance می‌سازد** | `admin/views/settings.php` | 20-21, 61 | `new \HelloFigma\Figma_API()` به جای استفاده از سینگلتون |
| 12 | **Timeout 60 ثانیه برای فایل‌های بزرگ کافی نیست** | `class-figma-api.php` | 227 | `wp_remote_get` با timeout=60 - فایل‌های بزرگ فیگما ممکن است timeout بخورند |
| 13 | **Cache TTL قابل تنظیم نیست** | `class-figma-api.php` | 10 | همه فایلها `HOUR_IN_SECONDS`، استایل‌ها `2 ساعت` - مدیر سایت نمی‌تواند تنظیم کند |
| 14 | **حداکثر 50 نود برای preview** | `admin/js/admin.js` | 174 | `nodeIds.slice(0, 50)` - اگر بیش از 50 فریم باشد بقیه preview ندارند |
| 15 | **گزارش خطا generic است** | `class-elementor-renderer.php` | 186 | "Failed to convert Figma file" - کاربر نمی‌فهمد دقیقاً چه مشکلی پیش آمده |
| 16 | **مقادیر stroke weight اشتباه گرفته می‌شد** | `class-elementor-renderer.php` | 497 | ✅ **رفع شد** | الان per-side values را هندل می‌کند (`top`, `right`, `bottom`, `left`) |
| 17 | **حالت `INNER_SHADOW` در Elementor پشتیبانی نمی‌شود** | `class-elementor-renderer.php` | 523 | ⏳ محدودیت Elementor | قابل بهبود نیست |
| 18 | **کمبود widgetهای ویدئو، لوگو، نقشه** | `widgets/` | - | ⏳ اولویت پایین | Component_Detector تشخیص می‌دهد اما widget ندارد |
| 19 | **Has_widget_inner_wrapper ناقص** | `widgets/class-figma-section.php` | 31 | فقط دو widget این متد را override کرده‌اند |
| 20 | **Log فایل حاوی Token redacted شده است ولی endpointها ذخیره می‌شوند** | `class-logger.php` | 43 | ممکن است اطلاعات حساس در لاگ فایل ذخیره شود |

### 🔹 جزیی / Low Priority

| # | مشکل | توضیح |
|---|------|--------|
| 21 | **هیچ نوار پیشرفتی برای import وجود ندارد** | فقط یک spinner ساده |
| 22 | **نمی‌توان یک فریم خاص را دوباره وارد کرد** | برای به‌روزرسانی باید کل قالب را حذف و دوباره ساخت |
| 23 | **جستجو/فیلتر در لیست templateها وجود ندارد** | فقط یک جدول ساده |
| 24 | **Style Sync قبل از اجرا پیش‌نمایش نمی‌دهد** | کاربر نمی‌داند چه استایل‌هایی sync می‌شوند |
| 25 | **Component Library از Figma پشتیبانی نمی‌شود** | INSTANCEها به container ساده تبدیل می‌شوند |
| 26 | **Batch Import چند فریم وجود ندارد** | باید تک‌تک فریم‌ها را import کرد |
| 27 | **Responsive breakpoints از Figma variants** | Figma variants به breakpoint تبدیل نمی‌شوند |
| 28 | **Two-way sync وجود ندارد** | یک‌طرفه است |
| 29 | **SVG Support ضعیف** | VECTOR/BOOLEAN_OPERATION به icon با fallback تبدیل می‌شوند |
| 30 | **خصوصیات فونت (Font fallback, vertical trim) انتقال نمی‌یابند** | تفاوت رندر بین فیگما و مرورگر |

---

## مشکلات امنیتی

| # | مشکل | شدت | توضیح |
|---|------|------|--------|
| 1 | Token در wp_options به صورت plaintext | **HIGH** | هر کس با دسترسی به دیتابیس می‌تواند token را بخواند |
| 2 | لاگ فایل در wp-content/uploads | **MEDIUM** | خطاها و endpointها در `/hello-figma-logs/import-{date}.log` ذخیره می‌شوند |
| 3 | عدم بررسی دسترسی در admin views | **LOW** | فقط AJAX handlers دسترسی `manage_options` را چک می‌کنند |

---

## مشکلات تجربه کاربری

1. **نبود Wizard مرحله‌ای واقعی** - فرآیند import طولانی است اما feedback کافی نیست
2. **عدم نمایش خطاهای دقیق** - بیشتر خطاها به صورت "Failed to convert" نمایش داده می‌شوند
3. **پیش‌نمایش ضعیف از نتیجه** - کاربر نمی‌تواند قبل از import ببیند خروجی چه شکلی می‌شود
4. **بدون Diff/مقایسه** - نمی‌توان طراحی فیگما را با خروجی Elementor مقایسه کرد
5. **زمان انتظار بدون اطلاع** - برای فایل‌های بزرگ، کاربر نمی‌داند چقدر باید صبر کند

---

## راهکارهای پیشنهادی

### اولویت بالا (باید فوری رفع شود)

1. **محاسبه زاویه گرادیانت**
   - خواندن `gradientHandlePositions` و محاسبه زاویه واقعی
   - پشتیبانی از Radial gradient

2. **ذخیره‌سازی امن Token**
   - استفاده از `wp_salt()` یا `openssl_encrypt` برای رمزگذاری

3. **رفع Bug پردازش فرم**
   - انتقال `handle_form_submission()` به `admin_init` در `class-admin.php:18`

4. **بهبود آیکون و وکتور**
   - استفاده از Figma Image API برای خروجی SVG
   - ذخیره SVG در media library

5. **رفع Dynamic Tags**
   - ذخیره `_hello_figma_node_name` و `_hello_figma_file_name` هنگام save_template

### اولویت متوسط

6. **Chunked import برای فایل‌های بزرگ**
   - افزایش timeout
   - اضافه کردن progress bar واقعی
   - پردازش تدریجی

7. **Widgetهای جدید**
   - Figma_Video
   - Figma_Logo
   - Figma_Map
   - Figma_Social_Icons

8. **Style Sync پیشرفته**
   - پیش‌نمایش قبل از sync
   - انتخاب انتخابی استایل‌ها
   - پشتیبانی از Figma Variables

9. **Batch Import**
   - انتخاب چند فریم و import یکجا
   - هر فریم به عنوان یک template جدا

### اولویت پایین (برای جاده راه)

10. **Two-way Sync** - تغییرات Elementor به Figma برگردد
11. **Component Library** - Figma Components به Elementor widget templates
12. **Responsive Variants** - Figma variants به breakpoint settings
13. **Interactive States** - حداقل mapping دستی برای hover states
14. **SVG کامل** - وکتورها به صورت SVG واقعی ذخیره شوند

---

## خلاصه آمار کدبیس

| معیار | مقدار |
|-------|-------|
| تعداد فایل‌های PHP | 21 |
| خطوط کل PHP | ~4,500 |
| فایل‌های JS | 2 (admin.js + editor.js) |
| فایل‌های CSS | 1 (admin.css) |
| Widget‌های سفارشی | 6 (Container, Button, Image, Heading, Icon Box, Section) |
| Dynamic Tags | 2 (Field, Text) |
| کلاس‌های Core | 11 |
| تست خودکار | ❌ هیچ تستی وجود ندارد |
| CI/CD | ✅ GitHub Actions (PHPCS + PHPStan) |
