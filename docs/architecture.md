# قرارداد معماری PayaCore v1

هدف: مرجع ثابت، ساده، قابل تست و قابل کلون برای پروژه‌های PHP با REST API و MVC.

## تصمیم‌های ثابت

- PHP 8.3+ و Composer با PSR-4 در namespace Paya.
- چهار کلاس زیرساخت مستقل: Paya\Core\Router، Request، Response و Database.
- هیچ ویژگی تجاری، ORM، Laravel یا DI Container در Core وجود ندارد.
- فقط پوشهٔ public باید Web Document Root باشد.
- منطق کسب‌وکار در Controller/Model و در صورت لزوم Service اختیاری پروژه قرار می‌گیرد.
- APIهای خود پروژه در routes/api.php ثبت می‌شوند؛ پیشوند پیشنهادی /api/v1 است.

## چرخه درخواست

    HTTP Client
       ↓
    public/index.php
       ↓
    Request::fromGlobals()
       ↓
    bootstrap/app.php → routes/api.php → Router
       ↓
    Middleware → Controller(Request)
                     ↓
              Model → Database
                     ↓
                Response
       ↓
    Response::send() → HTTP Status + JSON

## Router

- add(string method, string path, array handler, array middleware = []) و dispatch(Request): Response
- Handler می‌تواند [ControllerClass::class, 'method'] برای کلاس بدون سازندهٔ وابسته، یا [$controllerObject, 'method'] برای وابستگی تزریق‌شده باشد.
- Middleware می‌تواند کلاس بدون آرگومان با متد handle(Request, callable): Response، یک نمونه object، یا callable باشد.
- پارامتر مانند /tasks/{id} یک segment را می‌گیرد و با route('id') قابل خواندن است.
- Static route نسبت به Dynamic اولویت دارد؛ 404 برای مسیر ناشناخته، 405 و Allow برای متد اشتباه.

نمونه Route برنامهٔ جدید:

    $router->add('GET', '/api/v1/tasks/{id}', [new TaskController($db), 'show']);

## Request

- fromGlobals() درخواست HTTP را می‌خواند؛ new Request('GET', '/path') برای تست.
- method، path، header، query، input، all، bearerToken، route و attribute را ارائه می‌کند.
- withRouteParams و withAttribute کپی جدید درخواست برمی‌گردانند.
- query از URL و input/all از بدنه دریافت می‌شود؛ با یکدیگر خودکار ادغام نمی‌شوند.
- Content-Type: application/json باید یک JSON object داشته باشد؛ خطای بدنه 400 و بالاتر از ۱ MiB پاسخ 413 می‌گیرد.
- bearerToken فقط توکن را استخراج می‌کند؛ احراز هویت با Middleware برنامه است.

## Response

- Response::json(['data' => $value], 200)
- Response::error('VALIDATION_ERROR', 'Invalid input.', 422, ['name' => ['Required']])
- Response::noContent() برای 204 بدون بدنه.
- withHeader، statusCode، headers و body برای تست و تنظیم پاسخ.
- فقط public/index.php باید send() را فراخوانی کند؛ Controller نباید echo/exit داشته باشد.
- خطای پیش‌بینی‌نشده 500 عمومی برمی‌گرداند؛ جزئیات فقط در لاگ سرور است.

## Database

- new Database($config)؛ MySQL/MariaDB هدف اصلی و SQLite برای توسعه یا تست اختیاری.
- fetchOne، fetchAll، execute، lastInsertId، transaction و pdo.
- PDO با ERRMODE_EXCEPTION، FETCH_ASSOC؛ در MySQL از utf8mb4 و EMULATE_PREPARES=false استفاده می‌شود.
- Queryها باید با Prepared Statements و bind پارامتر اجرا شوند. نام جدول یا ستون از ورودی کاربر نباید ساخته شود.
- تراکنش با Commit و Rollback خودکار انجام می‌شود؛ تراکنش تو در تو پشتیبانی نمی‌شود.
- اتصال دیتابیس فقط هنگام نیاز ساخته و به Model/Controller تزریق می‌شود.

## محدودیت‌ها و کنترل‌های امنیتی

- این Core به‌تنهایی برای API عمومیِ حساس آماده نیست؛ در پروژه‌ Auth، Authorization، Rate Limit، CORS و نظارت عملیاتی را اضافه کن.
- Endpoint سلامت GET /api/v1/health فقط برای بررسی اتصال HTTP است و به دیتابیس وصل نمی‌شود.
- بارگذاری فایل، Queue، Session، ORM و Migration Runner تعمداً در نسخهٔ اولیه اضافه نشده‌اند.
- درخواست‌های JSON Object و Form URL Encoded پشتیبانی می‌شوند؛ آپلود فایل پیاده نشده است.
- HEAD و OPTIONS و CORS خودکار وجود ندارند؛ در پروژهٔ نهایی تصمیم‌گیری شوند.
- .env به‌طور خودکار خوانده نمی‌شود؛ محیط وب‌سرور/سیستم عامل منبع متغیرهای پیکربندی است.
- در پروژهٔ جدید خروجی JSON و محدودیت اندازه و حق دسترسی کاربران باید متناسب با نیاز تنظیم شوند.

## آزمون

    composer test

این دستور unit/smoke tests را اجرا می‌کند. تست واقعی PDO فقط هنگام وجود pdo_sqlite اجرا می‌شود و تست اتصال MySQL باید در محیط دارای pdo_mysql و دیتابیس آزمایشی انجام شود.
