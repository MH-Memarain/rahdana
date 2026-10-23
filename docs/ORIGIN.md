# منشأ فنی پروژه Rahdana

راهدانا یک پروژهٔ مستقل است که از هستهٔ PayaCore کپی شده است.

- Repository مرجع: `MH-Memarain/PayaCore` (Private)
- Branch: `main`
- Baseline Commit: `0d86e42a6a8385d86615442f01ebb0e9d2809c40`
- Repository راهدانا: `MH-Memarain/rahdana`
- روش: کپی مستقل از فایل‌های Starter؛ **نه Fork و نه Submodule**
- کلاس‌های اصلی `src/Core/*` از `Paya\Core` استفاده می‌کنند.
- Controllerهای اختصاصی از `Rahdana\Controllers` استفاده می‌کنند.
- به‌روزرسانی‌های PayaCore به‌طور خودکار در Rahdana وارد نمی‌شوند.
- پیش از همگام‌سازی دستی Core، تغییرات و تست‌های مرتبط بررسی شوند.

## دریافت و اجرا

```bash
git clone git@github.com:MH-Memarain/rahdana.git
cd rahdana
composer install
composer test
php -S 127.0.0.1:8000 -t public public/index.php
```

نکته امنیتی: این Repository هنگام ایجاد Public بوده است. کد و داده حساس را داخل آن Commit نکنید.
