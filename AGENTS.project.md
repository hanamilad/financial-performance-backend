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


## Repository

- الاسم: `financial-performance-backend`
- المسار داخل الـWorkspace: `backend/`
- المرجع التفصيلي: `.claude/skills/financial-performance-backend/SKILL.md`

## اقرأ قبل أي مهمة

- `../project-docs/workflow/CURRENT_SLICE.md`
- `../project-docs/workflow/DECISIONS_LOG.md`
- المواصفات المرتبطة بالمهمة.

## قواعد مختصرة

- Laravel Modular Monolith.
- Tenant isolation إلزامي.
- DECIMAL وليس Float.
- API عبر Form Requests وResources وOpenAPI.
- Jobs بعد Commit.
- لا Mapping مالي نهائي قبل اعتماد Excel.
- لا تعديل Repository آخر دون تصريح.
- لا تنفيذ قبل خطة معتمدة عندما يكون الـSlice Plan First.
