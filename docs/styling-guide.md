# راهنمای کامل ویرایش استایل‌های فرانت‌اند با Tailwind CSS

## معماری استایل در این افزونه

فایل CSS فرانت‌اند: `assets/front/front.css`

این فایل **از قبل کامپایل شده** و مستقیماً در سایت لود می‌شود. برای تغییر استایل‌ها دو راه دارید:

---

## روش ۱: تغییر مستقیم کلاس‌های Tailwind در PHP (ساده‌ترین)

فایل HTML در `src/Front/View.php` رندر می‌شود. تمام استایل‌ها با کلاس‌های Tailwind درون خود HTML نوشته شده‌اند.

**مثال:** فرض کنید می‌خواهید رنگ پس‌زمینه کامنت کاربر را تغییر دهید:

```php
// قبل
<div class="flex-1 bg-gray-50 p-4 md:p-6 rounded-2xl border border-gray-200/80">

// بعد (تغییر bg-gray-50 به bg-blue-50)
<div class="flex-1 bg-blue-50 p-4 md:p-6 rounded-2xl border border-gray-200/80">
```

**کلاس‌های کاربردی رایج:**
- **رنگ پس‌زمینه:** `bg-white` / `bg-gray-50` / `bg-gray-100` / `bg-teal/10` / `bg-gold/10`
- **حاشیه:** `border-2` / `border-border` / `border-teal` / `rounded-xl` / `rounded-full`
- **فاصله:** `gap-3` / `gap-4` / `gap-6` / `p-4` / `p-6` / `m-0`
- **اندازه فونت:** `text-sm` / `text-base` / `text-lg` / `text-2xl`
- **رنگ متن:** `text-primary` / `text-gold` / `text-teal` / `text-neutral` / `text-gray-800`
- **نمایش:** `flex` / `hidden` / `block`

**رنگ‌های اختصاصی تعریف شده در این افزونه:**
| کلاس | مقدار هگز | کاربرد |
|------|-----------|--------|
| `text-primary` / `bg-primary` | `#0c2d28` | رنگ اصلی (سبز تیره) |
| `text-gold` / `bg-gold` | `#c39854` | رنگ طلایی (ستاره‌ها، دکمه ارسال) |
| `text-teal` / `bg-teal` | `#009c8f` | رنگ فیروزه‌ای (ادمین، پاسخ) |
| `text-neutral` | `#606060` | رنگ خنثی (توضیحات فرعی) |
| `border-border` | `#d9d9d9` | رنگ حاشیه پیش‌فرض |
| `border-subtle` | `#e0e0e0` | رنگ حاشیه ملایم |

---

## روش ۲: بازسازی فایل CSS با Tailwind CLI (برای پیشرفته)

اگر کلاس جدیدی اضافه کنید که در `front.css` وجود نداشته باشد، باید Tailwind را دوباره بیلد کنید.

### مرحله ۱: نصب وابستگی‌ها
```bash
cd C:\Users\hosein\Local Sites\sherkati\app\public\wp-content\plugins\MD-Comment
npm install
```

### مرحله ۲: ویرایش فایل سورس Tailwind
فایل `src/input.css` را باز کنید و کلاس‌های سفارشی اضافه کنید:

```css
@import "tailwindcss";

/* رنگ‌های اختصاصی افزونه */
@theme {
  --color-primary: #0c2d28;
  --color-gold: #c39854;
  --color-teal: #009c8f;
  --color-neutral: #606060;
  --color-border: #d9d9d9;
  --color-subtle: #e0e0e0;
}
```

### مرحله ۳: بیلد کردن CSS جدید
```bash
npm run build
```
این دستور `front.css` را در `assets/front/` بازسازی می‌کند.

### حالت توسعه (واچ خودکار):
```bash
npm run dev
```
این دستور فایل‌ها را زیر نظر می‌گیرد و به محض ذخیره تغییرات، CSS را دوباره می‌سازد.

---

## روش ۳: اضافه کردن استایل دستی (بدون نیاز به بیلد)

اگر نمی‌خواهید Tailwind را بیلد کنید، می‌توانید مستقیم در `assets/front/front.css` استایل بنویسید:

```css
/* انتهای فایل front.css اضافه کنید */
.my-custom-class {
  background-color: #f0f0f0;
  padding: 20px;
}
```

---

## راهنمای سریع کلاس‌های Tailwind

**فاصله‌ها (Spacing):**
| کلاس | مقدار |
|------|-------|
| `p-4` | padding: 16px |
| `px-12` | padding-left/right: 48px |
| `gap-3` | gap: 12px |
| `gap-4` | gap: 16px |
| `mb-6` | margin-bottom: 24px |
| `pb-2` | padding-bottom: 8px |

**حالت ریسپانسیو (موبایل/دسکتاپ):**
```php
<div class="w-12 h-12 md:w-20 md:h-20">
<!-- در موبایل: 48px، در دسکتاپ: 80px -->
```

**ترکیب رنگ با اوپاسیتی:**
```php
bg-gold/10   /* 10% کدورت از رنگ gold */
bg-teal/5    /* 5% کدورت */
border-gray-200/80  /* 80% کدورت */
```

---

## ویرایش‌های متداول (quick reference)

| تغییر مورد نظر | کلاس فعلی | کلاس جدید |
|---------------|-----------|-----------|
| گردی بیشتر گوشه‌ها | `rounded-xl` | `rounded-2xl` |
| حذف حاشیه | `border-b border-subtle` | حذف شود |
| افزایش فاصله بین عناصر | `gap-4` | `gap-6` یا `gap-8` |
| کاهش فاصله | `gap-4` | `gap-2` یا `gap-1` |
| متن پررنگ‌تر | `font-semibold` | `font-bold` یا `font-black` |
| سایه به المان | ندارد | `shadow-md` یا `shadow-lg` |
| تراز وسط | ندارد | `text-center` یا `items-center` |
| حاشیه دور | `border` | `border-2 border-border` |
