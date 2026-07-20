---
name: financial-performance-backend
description: Use for every Laravel backend task in the Financial Performance Platform, including architecture, authentication, tenancy, imports, publication, reporting, alerts, queues, PDF, mail, tests, and API contracts.
---


# Financial Performance Platform — Project Agent Rules

هذه التعليمات خاصة بمنصة متابعة الأداء المالي والتشغيلي للمطاعم والكافيهات.
تُطبق بجانب تعليمات الأدوات أو Laravel Boost الموجودة بالفعل، ولا تُلغيها.

## ترتيب أولوية المراجع

عند التعارض اتبع الترتيب التالي:

1. قرار بشري صريح داخل المحادثة أو `DECISIONS_LOG.md`.
2. `CURRENT_SLICE.md` المعتمد.
3. المواصفات الموجودة داخل `../project-docs/specifications/`.
4. ملف Skill الخاص بالـRepository.
5. `AGENTS.md` وتعليمات الأدوات العامة.
6. الافتراضات الشخصية للـAgent، وهي أقل أولوية ولا تُستخدم عند وجود غموض جوهري.

## قاعدة العمل

لا تبدأ تنفيذًا واسعًا مباشرة. ابدأ بخطة محدودة، واذكر:
- الهدف.
- الملفات المتوقعة.
- التغييرات على قاعدة البيانات أو الـAPI.
- الاختبارات.
- المخاطر.
- ما هو خارج النطاق.

لا تنفذ إلا بعد اعتماد الخطة عندما تكون المهمة مصنفة كـPlan First.


## متى تستخدم هذه Skill؟

استخدمها في أي مهمة داخل `financial-performance-backend`.

## هوية المشروع

- Laravel Modular Monolith.
- قاعدة البيانات MySQL.
- Redis للـQueue والـCache والـLocks.
- Horizon لمراقبة الـWorkers.
- Sanctum Cookies للويب.
- Sanctum Bearer Tokens للموبايل.
- Scramble لتوليد OpenAPI.
- Pest للاختبارات.
- Laravel Excel للاستيراد.
- Spatie Laravel PDF/Browsershot للتصدير.

## حدود الـModules

الوحدات الأساسية المتوقعة:

```text
Identity
Clients
Branches
ClientUsers
DataIntake
Imports
ReportingPeriods
Publications
Metrics
FinancialReports
Alerts
Notifications
Exports
Audit
SystemSettings
```

لا تتصل Controller بجداول Module آخر مباشرة. استخدم Application Service أو Interface واضح عند وجود حدود حقيقية.

لا تبالغ في الطبقات أو Interfaces؛ البساطة مقدمة على الشكل النظري.

## قواعد Multi-Tenancy

- كل جدول أعمال مملوك لعميل يحمل `client_id` ما لم تكن هناك وثيقة تنص على غير ذلك.
- استخرج العميل من هوية المستخدم أو سياق إداري موثق.
- لا تثق في `client_id` القادم من الموبايل.
- تحقق من تبعية الفرع للعميل.
- أضف اختبارات منع الوصول بين العملاء.
- اجعل Unique Constraints وIndexes واعية بالـTenant.

## المال والحسابات

- ممنوع استخدام `float` للحسابات المالية.
- استخدم DECIMAL في قاعدة البيانات.
- استخدم Decimal/Money Value Objects أو BCMath.
- لا تقرب في خطوات وسيطة بلا نص.
- النسب الإجمالية تحسب من المجاميع، وليس متوسط نسب الفروع.
- `0` لا يساوي Missing.
- لا قسمة على صفر.
- كل معادلة مالية يجب أن ترتبط بإصدار Formula واضح عند الحاجة.

## الاستيراد

التدفق:

```text
Private temporary upload
→ file/template validation
→ sheet/header validation
→ row and cross-sheet validation
→ import batch
→ draft data
→ review
→ publish
→ delete original Excel file
```

- لا ينشر الاستيراد تلقائيًا.
- احذف الملف في كل حالة نهائية.
- أضف Cleanup للملفات اليتيمة.
- لا تخزن محتوى الملف في Logs.
- اجعل الاستيراد Idempotent.
- ملف Excel الحالي ما زال تحت اعتماد العميل؛ لا تثبت Mapping نهائيًا قبل Gate الاعتماد.

## النشر

الحالات:

```text
DRAFT
UNDER_REVIEW
PUBLISHED
SUPERSEDED
REJECTED
```

- النشر داخل Transaction.
- النسخة المنشورة السابقة تظل ظاهرة حتى نجاح الجديدة.
- شغّل Jobs بعد Commit.
- لا تنشر مركزًا ماليًا غير متوازن.
- سجّل Audit لكل انتقال.

## التقارير

المصدر الرسمي للمعادلات:

```text
../project-docs/specifications/REPORTS_DATA_AND_CALCULATIONS_SPECIFICATION_V0.1.md
```

لا تخترع معادلة أو تصنيفًا ماليًا.

## التنبيهات

- التقييم على الفرع وعلى إجمالي العميل.
- لا تنبيه عند `NOT_AVAILABLE`.
- لا تكرر Push أثناء استمرار نفس الحالة.
- الحالة الجديدة بعد الخروج والعودة تنتج حدثًا جديدًا.
- استخدم Idempotency/Deduplication keys.

## API

- Prefix مثل `/api/v1`.
- Form Requests للتحقق.
- API Resources للاستجابات.
- تنسيق أخطاء موحد.
- Pagination وFilters موثقة.
- Idempotency في العمليات الحساسة.
- حدّث Scramble/OpenAPI عند كل تغيير عقد.
- لا تكسر الواجهات دون خطة Compatibility.

## Queues

تدخل Queue عادة:

- Excel imports.
- metric calculation.
- alert evaluation.
- push notifications.
- mail.
- PDF عندما يكون ثقيلًا.
- cleanup.

استخدم `afterCommit` أو ما يعادله عندما تعتمد المهمة على بيانات تم Commit لها.

## الاختبارات الإلزامية حسب المهمة

- Tenant isolation.
- Authorization.
- State transitions.
- Financial formulas.
- Decimal precision.
- Import validation.
- Publication versioning.
- Alert deduplication.
- API contract behavior.

## ممنوعات

- Microservices.
- Full CQRS/Event Sourcing.
- نظام محاسبي كامل.
- تكاملات POS أو منصات في الـMVP.
- تخزين Excel/PDF دائمًا.
- وضع Business Logic في Controllers.
- Queries غير معزولة بالعميل.
- إصلاحات واسعة خارج Slice.
