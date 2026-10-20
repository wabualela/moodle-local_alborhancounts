<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic strings for local_alborhancounts.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['blocknames'] = 'أنواع الكتل';
$string['blocknames_desc'] = 'أنواع الكتل التي يُبحث في محتواها عن علامات الأعداد، مفصولة بفواصل. العلامة وسم span مثل <code>&lt;span dir="ltr" data-alborhan-count="199"&gt;+4,700&lt;/span&gt;</code>، والرقم 199 هو رقم المقرر.';
$string['cachedef_cardcountsrequest'] = 'أعداد طلاب بطاقات المقررات، محفوظة طوال الطلب الواحد';
$string['cachedef_studentcounts'] = 'أعداد طلاب بطاقات المقررات، مشتركة بين الطلبات (فقط عندما تكون مدة التخزين أكبر من 0)';
$string['cardcachettl'] = 'مدة التخزين المشترك (بالثواني)';
$string['cardcachettl_desc'] = 'عندما تكون أكبر من 0، يُحفظ عدد طلاب المقرر الذي لا يختلف بين الزوار ويُستخدم في الطلبات التالية لهذه المدة، ويُحذف فوراً عند تغيّر التسجيلات أو الأدوار. القيمة 0 (الافتراضية) تعدّ في كل طلب، فيكون العدد دقيقاً دائماً.';
$string['cardcounts'] = 'عدّ الطلاب لبطاقات المقررات';
$string['cardcounts_desc'] = 'تستخدمه ملفات theme_remui وlocal_edwiserpagebuilder المعدّلة (انظر vendor-patches/ في مستودع الإضافة): تحصل بطاقات المقررات على عدد الطلاب باستعلام عدّ واحد بدل تحميل كل الطلاب. ألغِ التحديد لتعود البطاقات إلى العدّ بكود المورّد دون تغيير أي ملف.';
$string['cardsheading'] = 'أعداد بطاقات المقررات (تعديل كود المورّد)';
$string['cardsheading_desc'] = 'إعدادات بطاقات مقررات Edwiser المعدّلة لتستدعي هذه الإضافة.';
$string['checkvendorpatch'] = 'تعديل بطاقات مقررات Edwiser';
$string['checkvendorpatch_changed'] = '{$a->label}: تغيّر كود المورّد ({$a->path}). راجع التعديل لهذا الإصدار.';
$string['checkvendorpatch_helperfails'] = 'دالة العدّ تفشل، فتعود البطاقات إلى كود المورّد البطيء: {$a}';
$string['checkvendorpatch_missing'] = 'أحد تعديلات بطاقات مقررات Edwiser مفقود: عادت البطاقات تعدّ الطلاب بالطريقة البطيئة.';
$string['checkvendorpatch_novendor'] = 'لا توجد إضافة Edwiser معدّلة مثبتة.';
$string['checkvendorpatch_off'] = 'فحص التعديل معطّل في إعدادات الإضافة.';
$string['checkvendorpatch_ok'] = 'تعديلات بطاقات مقررات Edwiser موجودة.';
$string['checkvendorpatch_patched'] = '{$a->label}: معدّل ({$a->path}).';
$string['checkvendorpatch_switchedoff'] = 'عدّ البطاقات معطّل في إعدادات الإضافة: كود المورّد هو الذي يعدّ.';
$string['checkvendorpatch_unpatched'] = '{$a->label}: غير معدّل ({$a->path}). الأرجح أن تحديثاً للمورّد استبدله: أعد تطبيق التعديل.';
$string['enabled'] = 'تحديث الأعداد';
$string['enabled_desc'] = 'عند التفعيل، تكتب المهمة المجدولة في كل علامة عدد الطلاب المسجلين في مقررها بعد التقريب.';
$string['pluginname'] = 'أعداد مقررات البرهان';
$string['privacy:metadata'] = 'لا تخزّن إضافة أعداد مقررات البرهان أي بيانات شخصية. تكتب إجمالي كل مقرر في محتوى الكتل، وتخزّن إجمالي كل مقرر مؤقتاً، وتحتفظ بنسخ من إعدادات الكتل.';
$string['taskproblems'] = 'انتهى تحديث الأعداد مع مشكلات: {$a}.';
$string['taskupdatecounts'] = 'تحديث أعداد التسجيل الثابتة';
$string['taskvendorpatchmissing'] = 'أحد تعديلات بطاقات مقررات Edwiser مفقود أو دالة العدّ تفشل؛ انظر فحص «تعديل بطاقات مقررات Edwiser» في تقرير حالة النظام.';
$string['taskverifyvendorpatch'] = 'التحقق من تعديل بطاقات مقررات Edwiser';
$string['vendorpatchcheck'] = 'فحص تعديلات المورّد';
$string['vendorpatchcheck_desc'] = 'يُبلغ في صفحة حالة النظام، وتفشل المهمة التي تعمل كل ساعة، عندما يتوقف ملف Edwiser معدّل عن استدعاء هذه الإضافة (مثلاً بعد تحديث الثيم).';
$string['vendorpatches'] = 'تعديلات المورّد المتوقعة';
$string['vendorpatches_desc'] = 'أسماء التعديلات التي يجب أن تكون مطبّقة، مفصولة بفواصل: remui-counts (أعداد بطاقات المقررات) وremui-enrolpage-limits (صفحة الانضمام تبني البطاقات المعروضة فقط).';
