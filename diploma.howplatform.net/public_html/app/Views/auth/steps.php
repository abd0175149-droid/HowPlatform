<!-- Step 1 -->
<div class="survey-step active" data-step="1">
    <div class="form-group">
        <h3><i class="fas fa-question-circle"></i> اختر الطريقة التي عرفت بها عن منصتنا:</h3>

        <div class="radio-group">
            <label><input type="radio" name="how_known" value="friend" required> <span>عن طريق صديق</span></label>
        </div>
        <div class="radio-group">
            <label><input type="radio" name="how_known" value="social"> <span>من وسائل التواصل الاجتماعي</span></label>
        </div>
        <div class="radio-group">
            <label><input type="radio" name="how_known" value="ad"> <span>إعلان مدفوع (فيسبوك، إنستغرام...)</span></label>
        </div>
        <div class="radio-group">
            <label><input type="radio" name="how_known" value="search"> <span>من محرك بحث (Google)</span></label>
        </div>
        <div class="radio-group">
            <label><input type="radio" name="how_known" value="other" id="otherOption"> <span>أخرى</span></label>
        </div>

        <div class="other-option" id="otherText">
            <input type="text" class="form-input" name="other_source" placeholder="يرجى تحديد المصدر الآخر...">
        </div>
    </div>
</div>

<!-- Step 2 -->
<div class="survey-step" data-step="2">
    <div class="form-group">
        <h3><i class="fas fa-check-circle"></i> اختر الأسباب التي دفعتك للتسجيل (يمكن اختيار أكثر من سبب):</h3>

        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="content"><span>جودة المحتوى التعليمي</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="design"><span>سهولة الاستخدام</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="ratings"><span>التقييمات الإيجابية</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="price"><span>السعر أو المجانية</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="services"><span>الخدمات المقدمة</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="reasons[]" value="other_reason" id="otherReason"><span>أخرى</span></label></div>

        <div class="other-option" id="otherReasonText">
            <input type="text" class="form-input" name="other_reason_text" placeholder="يرجى تحديد السبب الآخر...">
        </div>
    </div>
</div>

<!-- Step 3 -->
<div class="survey-step" data-step="3">
    <div class="form-group">
        <h3><i class="fas fa-cogs"></i> ما نوع الخدمات التي تنوي استخدامها؟</h3>

        <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="follow_tasks"><span>متابعة المهام</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="evaluate_tasks"><span>تقييم المهام</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="exams"><span>الاختبارات</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="view_models"><span>الاطلاع على نماذج</span></label></div>
        <div class="checkbox-group"><label><input type="checkbox" name="services[]" value="request_designs"><span>طلب تصاميم</span></label></div>
    </div>
</div>

<!-- Step 4 -->
<div class="survey-step" data-step="4">
    <div class="form-group">
        <h3><i class="fas fa-mobile-alt"></i> ما الجهاز الذي تفضله لاستخدام المنصة؟</h3>

        <div class="radio-group"><label><input type="radio" name="device" value="phone" required><span>هاتف محمول</span></label></div>
        <div class="radio-group"><label><input type="radio" name="device" value="computer"><span>جهاز كمبيوتر</span></label></div>
        <div class="radio-group"><label><input type="radio" name="device" value="tablet"><span>جهاز لوحي</span></label></div>
        <div class="radio-group"><label><input type="radio" name="device" value="all"><span>جميع ما سبق</span></label></div>
    </div>
</div>

<!-- Step 5 -->
<div class="survey-step" data-step="5">
    <div class="form-group">
        <h3><i class="fas fa-star-half-alt"></i> ما مدى رضاك عن تجربة التسجيل؟</h3>

        <div class="rating-container">
            <div class="rating-option"><div class="rating-circle" data-value="1">1</div><div class="rating-label">غير راضٍ</div></div>
            <div class="rating-option"><div class="rating-circle" data-value="2">2</div><div class="rating-label">قليل الرضا</div></div>
            <div class="rating-option"><div class="rating-circle" data-value="3">3</div><div class="rating-label">متوسط</div></div>
            <div class="rating-option"><div class="rating-circle" data-value="4">4</div><div class="rating-label">راضٍ</div></div>
            <div class="rating-option"><div class="rating-circle" data-value="5">5</div><div class="rating-label">راضٍ جداً</div></div>
        </div>

        <input type="hidden" name="satisfaction" id="satisfactionRating" required>
    </div>
</div>

<div class="form-actions">
    <button type="button" class="nav-btn prev-btn" id="prevBtn" disabled><i class="fas fa-arrow-right"></i> السابق</button>
    <button type="button" class="nav-btn next-btn" id="nextBtn">التالي <i class="fas fa-arrow-left"></i></button>
</div>
