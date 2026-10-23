# Rahdana — API Backend

**راهدانا** یک پلتفرم یادگیری و برنامه‌ریزی آموزشی است که Backend آن بر پایهٔ **PayaCore** (PHP + MVC + REST API) آغاز شده است.

> وضعیت فعلی: هسته و نام‌گذاری اولیه آماده است؛ هیچ ماژول اختصاصی آموزشی یا اشتراک هنوز پیاده‌سازی نشده است.

## فناوری

- PHP 8.3+ و Composer (PSR-4)
- MVC سبک با JSON REST API
- چهار کلاس Core با Namespace `Paya\Core`
- Controllerهای محصول با Namespace `Rahdana\Controllers`
- PDO برای لایه دیتابیس

## اجرای محلی

```bash
git clone git@github.com:MH-Memarain/rahdana.git
cd rahdana
composer install
composer test
php -S 127.0.0.1:8000 -t public public/index.php
curl -i http://127.0.0.1:8000/api/v1/health
```

مسیر موجود: `GET /api/v1/health` (بدون نیاز به اتصال دیتابیس). برای کار با MySQL افزونهٔ `pdo_mysql` لازم است.

## ساختار

```text
public/                   نقطه ورود عمومی و Apache Rewrite
bootstrap/                راه‌اندازی Router
config/                   تنظیمات برنامه و Database
routes/api.php            مسیرهای API
src/Core/                 Router, Request, Response, Database
src/Controllers/          کنترلرهای مخصوص راهدانا
src/Models/               مدل‌های مخصوص راهدانا
src/Views/                رزرو برای استفاده‌های بعدی
src/Middleware/           Middlewareهای پروژه
database/migrations/      Migrationهای دیتابیس
storage/logs/             فایل‌های خصوصی و لاگ
tests/                    تست‌ها
docs/                     مستندات
```

## نکات مهم

- فقط `public/` باید Document Root وب‌سرور باشد.
- `.env.example` صرفاً نمونه است و بارگذاری خودکار فایل `.env` وجود ندارد؛ متغیرهای محیطی باید در محیط اجرا تنظیم شوند.
- احراز هویت، مجوزها، CORS و محدودسازی نرخ درخواست هنوز پیاده‌سازی نشده‌اند.
- اطلاعات منشأ کپی و Baseline در [docs/ORIGIN.md](docs/ORIGIN.md) و قراردادها در [docs/architecture.md](docs/architecture.md) آمده است.

**توجه:** در زمان ثبت این نسخه، مخزن در GitHub عمومی (Public) بود. در صورت نیاز به مخزن خصوصی، Visibility را از تنظیمات GitHub تغییر بده.
