<?php
/**
 * app/Views/admins/pages/plans.php
 * إدارة الخطط + المزايا (Admin) - Tailwind Redesign
 */
?>
<div class="p-4 sm:p-6 lg:p-8 w-full max-w-7xl mx-auto space-y-6" dir="rtl" data-component="admin-plans">
  
  <!-- Header & Action Bar -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 bg-cyan-50 rounded-xl flex items-center justify-center text-cyan-600 shadow-inner">
        <i class="fas fa-layer-group text-xl"></i>
      </div>
      <div>
        <h2 class="text-xl font-black text-slate-800 tracking-tight">إدارة الخطط والميزات</h2>
        <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-cyan-600 bg-cyan-50 px-2 py-0.5 rounded-md mt-1 inline-block">Admin Interface</span>
      </div>
    </div>
    
    <div>
      <button id="btnNewPlan" class="px-5 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-bold rounded-xl shadow-sm transition-colors flex items-center gap-2 outline-none">
        <i class="fas fa-plus-circle"></i>
        <span>إضافة خطة جديدة</span>
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3 w-full sm:w-auto">
        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center">
             <i class="fas fa-filter text-slate-400"></i>
        </div>
        <h3 class="font-bold text-slate-700">تصفية الخطط:</h3>
        
        <label class="relative inline-flex items-center cursor-pointer mr-2">
          <input type="checkbox" id="chkShowInactive" class="sr-only peer">
          <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
          <span class="mr-3 text-sm font-semibold text-slate-600">إظهار الخطط غير النشطة</span>
        </label>
      </div>

      <div class="relative w-full sm:w-72">
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
          <i class="fas fa-search text-slate-400"></i>
        </div>
        <input type="search" id="txtSearch" class="block w-full outline-none bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-xl focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 block pr-10 p-2.5 transition-shadow" placeholder="ابحث باسم الخطة...">
      </div>
    </div>
  </div>

  <!-- Plans Grid Container -->
  <div>
    <!-- Rendered dynamically by JS -->
    <div id="plansGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" aria-live="polite">
       <div class="text-slate-500 py-8 px-4 text-center col-span-full"><i class="fas fa-circle-notch fa-spin text-2xl mb-2 text-cyan-500"></i><br>جاري تحميل الخطط...</div>
    </div>
  </div>

  <!-- ========== Plan Modal ========== -->
  <div id="planModal" class="fixed inset-0 z-[9999] hidden flex-col items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="planModalTitle">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" data-close="#planModal"></div>
    
    <!-- Dialog -->
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col w-full max-w-3xl max-h-[90vh] overflow-hidden transform transition-all">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
        <h4 id="planModalTitle" class="text-lg font-black text-slate-800 flex items-center gap-2">
          <i class="fas fa-pen text-cyan-500"></i> إعدادات الخطة
        </h4>
        <button class="text-slate-400 hover:text-red-500 hover:bg-red-50 w-8 h-8 rounded-full flex items-center justify-center transition-colors outline-none" data-close="#planModal" aria-label="إغلاق">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-5 overflow-y-auto flex-1 text-slate-700 bg-white space-y-6">
        <input type="hidden" id="planId">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="space-y-1.5">
            <label class="text-sm font-bold text-slate-700">اسم الخطة</label>
            <input id="planName" class="w-full bg-slate-50 outline-none border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all font-semibold text-slate-800" maxlength="64" placeholder="مثال: Premium Plan">
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-bold text-slate-700">المستوى (Level)</label>
            <input id="planLevel" type="number" min="1" step="1" placeholder="1" class="w-full bg-slate-50 outline-none border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all font-semibold text-slate-800">
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-bold text-slate-700">المدة الافتراضية (أيام)</label>
            <input id="planDuration" type="number" min="1" step="1" placeholder="30" class="w-full bg-slate-50 outline-none border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all font-semibold text-slate-800">
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-bold text-slate-700">السعر (JOD)</label>
            <input id="planPrice" type="number" min="0" step="0.01" placeholder="0.00" class="w-full bg-slate-50 outline-none border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 transition-all font-semibold text-slate-800" dir="ltr">
          </div>
        </div>

        <div class="pt-2">
          <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" id="planActive" class="w-4 h-4 text-cyan-600 bg-slate-100 border-slate-300 rounded focus:ring-cyan-500 focus:ring-2 cursor-pointer">
            <span class="mr-2 text-sm font-bold text-slate-700">الخطة مفعلة ومرئية</span>
          </label>
        </div>

        <!-- Create Features Repeater (Visible only on create) -->
        <div id="createFeaturesBlock" class="hidden mt-6 pt-6 border-t border-slate-100 space-y-4">
          <h4 class="text-md font-bold text-slate-800">ميزات الخطة (اختياري)</h4>
          <div class="bg-amber-50 text-amber-800 p-3 rounded-xl border border-amber-200/50 text-xs font-semibold flex items-start gap-2">
            <i class="fas fa-info-circle mt-0.5"></i>
            <p>أدخل اسم الميزة (feature_key) و حدود الاستخدام بتنسيق JSON صحيح (limits_json).</p>
          </div>
          
          <div id="createFeaturesRows" class="space-y-3">
             <!-- Rows are added here -->
          </div>
          
          <div>
            <button id="btnAddCreateFeature" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition-colors border border-slate-200 outline-none">
              <i class="fas fa-plus"></i> إضافة ميزة أخرى
            </button>
          </div>
        </div>

        <!-- Edit Features Viewer (Visible only on edit) -->
        <div id="editFeaturesBlock" class="hidden mt-6 pt-6 border-t border-slate-100 space-y-4">
          <h4 class="text-md font-bold text-slate-800 flex items-center justify-between">
            <span>مزايا الخطة الحالية</span>
          </h4>
          <div class="bg-slate-50 text-slate-600 p-3 rounded-xl border border-slate-200/50 text-xs font-semibold flex items-center gap-2">
            <i class="fas fa-info-circle"></i> يمكنك إضافة أو تعديل ميزات جديدة من خلال الأزرار المخصصة في بطاقة الخطة بالخارج.
          </div>
          <div id="editFeaturesList" class="pt-2">
            <!-- Rendered by JS -->
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
        <button data-close="#planModal" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold rounded-xl shadow-sm transition-colors outline-none">إلغاء</button>
        <button id="btnSavePlan" class="px-6 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-bold rounded-xl shadow-sm transition-colors flex items-center gap-2 outline-none">
          <i class="fas fa-save"></i> حفظ الخطة
        </button>
      </div>
    </div>
  </div>


  <!-- ========== Features Modal ========== -->
  <div id="featureModal" class="fixed inset-0 z-[9999] hidden flex-col items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="featureModalTitle">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" data-close="#featureModal"></div>
    
    <!-- Dialog -->
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 flex flex-col w-full max-w-3xl max-h-[90vh] overflow-hidden transform transition-all">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
        <h4 id="featureModalTitle" class="text-lg font-black text-slate-800 flex items-center gap-2">
          <i class="fas fa-list-check text-emerald-500"></i> ميزات الخطة
        </h4>
        <button class="text-slate-400 hover:text-red-500 hover:bg-red-50 w-8 h-8 rounded-full flex items-center justify-center transition-colors outline-none" data-close="#featureModal" aria-label="إغلاق">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-5 overflow-y-auto flex-1 text-slate-700 bg-white space-y-5">
        <input type="hidden" id="featureId">
        <input type="hidden" id="featurePlanId">
        
        <div class="bg-emerald-50 text-emerald-800 p-3 rounded-xl border border-emerald-200/50 text-xs font-semibold flex items-start gap-2">
          <i class="fas fa-info-circle mt-0.5"></i>
          <p>يمكنك إضافة ميزة واحدة أو أكثر قبل الحفظ. عند تعديل ميزة فردية سيُتجاهل تكرار الصفوف الإضافية.</p>
        </div>

        <div id="featureRows" class="space-y-3">
          <!-- Rows -->
        </div>

        <div>
          <button id="btnAddFeatureRow" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition-colors border border-slate-200 mt-2 outline-none">
            <i class="fas fa-plus"></i> إضافة صف آخر
          </button>
        </div>

      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
        <button data-close="#featureModal" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold rounded-xl shadow-sm transition-colors outline-none">إلغاء</button>
        <button id="btnSaveFeature" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold rounded-xl shadow-sm transition-colors flex items-center gap-2 outline-none">
          <i class="fas fa-save"></i> حفظ الميزات
        </button>
      </div>
    </div>
  </div>

</div>
