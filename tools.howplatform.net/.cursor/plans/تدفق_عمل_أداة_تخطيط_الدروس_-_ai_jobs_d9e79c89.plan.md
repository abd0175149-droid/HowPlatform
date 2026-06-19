---
name: تدفق عمل أداة تخطيط الدروس - AI Jobs
overview: توثيق تدفق معلومات الأداة واتصالها بوكيل الذكاء الاصطناعي وكيفية تصميم ملف DOCX
todos: []
---

# تدفق عمل أداة تخطيط الدروس (AI Jobs)

## نظرة عامة

هذا التوثيق يشرح **تدفق المعلومات** فقط: كيف تنتقل البيانات من المستخدم إلى AI وإلى ملف DOCX النهائي.

---

## تدفق المعلومات الكامل

### المرحلة 1: إرسال الطلب من الواجهة

```
المستخدم يملأ النموذج (teacher_name, grade_level, subject_name, إلخ)
    ↓
JS يجمع البيانات في payload JSON
    ↓
POST /students/ai-tools/request
    Body: {
        type_key: "lesson_plan_v1",
        task_id: 123,
        teacher_name: "...",
        grade_level: "...",
        subject_name: "...",
        ... (جميع الحقول)
    }
    ↓
Backend (AiToolsController::request):
 - يتحقق من الصلاحيات والنقاط
 - ينشئ سجل في ai_jobs:
  * user_id
  * job_type_id (من ai_job_types حسب type_key)
  * task_id
  * payload_json = JSON.stringify(payload)
  * status = 'pending'
    ↓
يعيد: { ok: true, status: 'accepted', job_id: 456 }
```

---

### المرحلة 2: معالجة الطلب (Cron Job)

**الملف:** `app/Controllers/Cron/AiJobsRunner.php`

#### الخطوة 1: جلب الطلب

```php
// Cron يعمل كل دقيقة
GET /cron/ai-jobs/run?key=SECRET

// يجلب طلب pending واحد
SELECT * FROM ai_jobs 
WHERE status IN ('pending', 'queued')
ORDER BY created_at ASC
LIMIT 1

// يغيره إلى 'processing'
UPDATE ai_jobs SET status = 'processing' WHERE id = 456
```

#### الخطوة 2: تحضير البيانات

```php
// 1. جلب نوع الأداة من ai_job_types
$jobType = ai_job_types WHERE id = job_type_id
// يحتوي على:
//   - system_prompt (التوجيهات الأساسية)
//   - provider (gemini/openai)
//   - model_name (gemini-2.0-flash)
//   - prompt_maker_class (LessonPlanPromptMaker)
//   - template_path (templates/lesson_plan_template.docx)

// 2. فك تشفير payload_json
$payload = json_decode($job['payload_json'], true)
// = {
//     type_key: "lesson_plan_v1",
//     teacher_name: "...",
//     grade_level: "...",
//     ...
//   }
```

#### الخطوة 3: بناء التوجيهات (Prompts)

**الملف:** `app/Libraries/PromptMaker/LessonPlanPromptMaker.php`

```php
// 1. إنشاء PromptMaker المناسب
$promptMaker = PromptMakerFactory::create('lesson_plan_v1')
// = new LessonPlanPromptMaker()

// 2. بناء System Prompt
$systemPrompt = $promptMaker->buildSystemPrompt(
    $jobType['system_prompt'],  // من قاعدة البيانات
    $payload
)
// النتيجة: نص طويل يحتوي على:
//   "Act like معلّم خبير في تصميم خطط الدروس..."
//   + أي تعديلات إضافية حسب payload

// 3. بناء User Prompt
$userPrompt = $promptMaker->buildUserPrompt($payload)
// النتيجة: نص يحتوي على:
//   "الهدف: إعداد خطة حصة واحدة متكاملة..."
//   + جميع بيانات المستخدم من payload
//   + التعليمات التفصيلية

// 4. دمج الرسائل
$messages = [
    'system' => $systemPrompt,
    'user' => $userPrompt
]
```

**مثال على User Prompt النهائي:**

```
IMPORTANT: Output MUST be JSON only with no extra text outside JSON.

الهدف: إعداد خطة حصة واحدة متكاملة قابلة للتنفيذ لحصة مدتها 40 دقيقة...

بيانات الدرس المدمجة:
- المعلم: أ. فاطمة المصري
- المدرسة: مدرسة الأمل
- الصف/المرحلة: التاسع الأساسي
- المبحث: الرياضيات
- الوحدة: الجبر
- عنوان الدرس: المعادلات الخطية
- ملخص الموضوع: [وصف طويل...]
- عدد النتاجات المطلوبة: 2
...

المهام خطوة بخطوة:
1) تحقّق من اكتمال المدخلات...
2) ابحث حصراً في المنهاج الأردني...
3) صُغ نواتج تعلم SMART...
...
```

---

### المرحلة 3: الاتصال بوكيل الذكاء الاصطناعي

**الملف:** `app/Libraries/AiClient.php`

#### الخطوة 1: اختيار المزود

```php
$provider = $jobType['provider']  // 'gemini' أو 'openai'
$model = $jobType['model_name']   // 'gemini-2.0-flash' أو 'gpt-4o-mini'

$aiClient = new AiClient()
$aiClient->withProviderModel($provider, $model)
```

#### الخطوة 2: بناء طلب API

**لـ Gemini:**

```php
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={API_KEY}"

$requestBody = [
    'system_instruction' => [
        'role' => 'system',
        'parts' => [['text' => $systemPrompt]]
    ],
    'contents' => [[
        'role' => 'user',
        'parts' => [[
            'text' => "IMPORTANT: Output MUST be JSON only...\n\n" . $userPrompt
        ]]
    ]],
    'generationConfig' => [
        'temperature' => 0.2,
        'response_mime_type' => 'application/json'  // فرض JSON
    ],
    'safetySettings' => [...]
]

// إرسال HTTP POST
$response = curl_exec($ch)
```

**لـ OpenAI:**

```php
$url = "https://api.openai.com/v1/chat/completions"

$requestBody = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'system',
            'content' => $systemPrompt . "\n\nIMPORTANT: Output MUST be JSON only..."
        ],
        [
            'role' => 'user',
            'content' => $userPrompt
        ]
    ],
    'temperature' => 0.2,
    'response_format' => ['type' => 'json_object']  // فرض JSON
]

// إرسال HTTP POST مع Authorization: Bearer {API_KEY}
$response = curl_exec($ch)
```

#### الخطوة 3: استخراج JSON من الرد

**لـ Gemini:**

```php
// استخراج النص من candidates[0].content.parts[0].text
$jsonText = $response['candidates'][0]['content']['parts'][0]['text']

// التحقق من صحة JSON
json_decode($jsonText, true)  // يجب أن يعيد array بدون أخطاء
```

**لـ OpenAI:**

```php
// استخراج النص من choices[0].message.content
$jsonText = $response['choices'][0]['message']['content']

// التحقق من صحة JSON
json_decode($jsonText, true)
```

#### الخطوة 4: معالجة الأخطاء

```php
// إذا كان JSON غير صالح، محاولة استخراجه من النص
$jsonText = extractFirstJsonObject($rawResponse)

// إذا فشل، Fallback: إزالة response_mime_type وإعادة المحاولة
```

---

### المرحلة 4: تحويل JSON إلى بيانات القالب

**الملف:** `app/Controllers/Cron/AiJobsRunner.php::makeTemplateDataForDocx()`

```php
// JSON من AI
$jsonObj = {
    "teacher_name": "أ. فاطمة",
    "school_class": "التاسع الأساسي",
    "general_title": "المعادلات الخطية",
    "o_statement": ["ناتج 1", "ناتج 2"],
    "o_assess_strategy": ["استراتيجية 1", "استراتيجية 2"],
    "o_dur": [15, 20],
    "warmup_teacher": "...",
    "warmup_student": "...",
    "w_min": 3,
    ...
}

// تحويل إلى بيانات قالب
$tplData = makeTemplateDataForDocx($jsonObj, 'lesson_plan_v1')

// النتيجة: نفس البيانات لكن:
//   - المصفوفات تُحوّل إلى نصوص (إذا لزم)
//   - الحقول تُنظف من محارف خاصة
//   - البيانات تُهيأ للـ TemplateProcessor
```

**مثال التحويل:**

```php
// قبل:
"o_statement": ["ناتج 1", "ناتج 2"]

// بعد (جاهز للقالب):
"o_statement": ["ناتج 1", "ناتج 2"]  // يبقى مصفوفة للـ cloneRow
```

---

### المرحلة 5: تصميم ملف DOCX

**الملف:** `app/Libraries/DocxService.php`

#### الخطوة 1: فتح القالب

```php
$templatePath = 'templates/lesson_plan_template.docx'
$tp = new TemplateProcessor($templatePath)

// قراءة المتغيرات الموجودة في القالب
$templateVariables = $tp->getVariables()
// = ['teacher_name', 'school_name', 'o_statement', 'o_assess_strategy', ...]
```

#### الخطوة 2: ملء الحقول الفردية

```php
// لكل حقل في $tplData:
foreach ($singleKeys as $key) {
    if (in_array($key, $templateVariables)) {
        $tp->setValue($key, $tplData[$key])
    }
}

// أمثلة:
$tp->setValue('teacher_name', 'أ. فاطمة المصري')
$tp->setValue('school_name', 'مدرسة الأمل')
$tp->setValue('general_title', 'المعادلات الخطية')
$tp->setValue('w_min', '3')
$tp->setValue('i_min', '5')
```

#### الخطوة 3: معالجة الحلقات (Loops) - النتاجات

```php
// النتاجات (o_statement, o_assess_strategy, إلخ) هي مصفوفات
// القالب يحتوي على صف واحد يحتوي ${o_statement} ${o_assess_strategy} ...

// بناء مصفوفة الصفوف
$rows = [
    [
        'o_statement' => 'ناتج 1',
        'o_assess_strategy' => 'استراتيجية 1',
        'o_assess_tool' => 'أداة 1',
        'o_teach_strategy' => 'استراتيجية تدريس 1',
        'o_teacher' => 'دور المعلم 1',
        'o_student' => 'دور الطالب 1',
        'o_dur' => '15'
    ],
    [
        'o_statement' => 'ناتج 2',
        'o_assess_strategy' => 'استراتيجية 2',
        ...
        'o_dur' => '20'
    ]
]

// نسخ الصف في القالب بعدد العناصر
$tp->cloneRowAndSetValues('o_statement', $rows)

// النتيجة: القالب يحتوي الآن على صفين بدلاً من صف واحد
```

#### الخطوة 4: الحفظ

```php
$targetPath = 'downloads/lesson_plans/lesson_123_456_20250123_143022.docx'
$tp->saveAs($targetPath)

// الملف جاهز الآن
```

---

## مخطط التدفق الكامل

```
┌─────────────────────────────────────────────────────────────┐
│ 1. الواجهة (Frontend)                                        │
│    - المستخدم يملأ النموذج                                   │
│    - JS يجمع payload JSON                                    │
│    - POST /students/ai-tools/request                        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Backend - إنشاء Job                                       │
│    - AiToolsController::request()                            │
│    - التحقق من الصلاحيات والنقاط                             │
│    - INSERT INTO ai_jobs (status='pending')                  │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Cron Job Runner (كل دقيقة)                                │
│    - AiJobsRunner::run()                                     │
│    - SELECT pending job                                      │
│    - UPDATE status='processing'                              │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. بناء التوجيهات (Prompts)                                  │
│    - PromptMakerFactory::create('lesson_plan_v1')            │
│    - LessonPlanPromptMaker::buildSystemPrompt()              │
│    - LessonPlanPromptMaker::buildUserPrompt()               │
│    - النتيجة: { system: "...", user: "..." }                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. الاتصال بوكيل AI                                          │
│    - AiClient::generateJson()                                 │
│    - اختيار المزود (Gemini/OpenAI)                           │
│    - بناء HTTP Request                                        │
│    - POST إلى API Provider                                    │
│    - استقبال JSON Response                                   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. معالجة النتيجة                                            │
│    - json_decode($jsonText)                                  │
│    - التحقق من صحة JSON                                      │
│    - makeTemplateDataForDocx($jsonObj)                       │
│    - تحويل البيانات لصيغة القالب                            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. تصميم ملف DOCX                                            │
│    - DocxService::buildFromTemplate()                       │
│    - فتح القالب (TemplateProcessor)                          │
│    - setValue() للحقول الفردية                               │
│    - cloneRowAndSetValues() للنتاجات                         │
│    - saveAs()                                                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. حفظ النتيجة                                               │
│    - UPDATE ai_jobs SET status='done', result_json=...,     │
│      file_id=...                                             │
│    - INSERT INTO files                                       │
│    - الملف جاهز للتنزيل                                      │
└─────────────────────────────────────────────────────────────┘
```

---

## تفاصيل الاتصال بوكيل AI

### Gemini API

**URL:**

```
https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent?key={API_KEY}
```

**Request Body:**

```json
{
  "system_instruction": {
    "role": "system",
    "parts": [{"text": "System prompt هنا..."}]
  },
  "contents": [{
    "role": "user",
    "parts": [{
      "text": "IMPORTANT: Output MUST be JSON only...\n\nUser prompt هنا..."
    }]
  }],
  "generationConfig": {
    "temperature": 0.2,
    "response_mime_type": "application/json"
  },
  "safetySettings": [...]
}
```

**Response:**

```json
{
  "candidates": [{
    "content": {
      "parts": [{
        "text": "{\"teacher_name\":\"...\",\"school_name\":\"...\",...}"
      }]
    },
    "finishReason": "STOP"
  }]
}
```

**استخراج JSON:**

```php
$jsonText = $response['candidates'][0]['content']['parts'][0]['text']
```

---

### OpenAI API

**URL:**

```
https://api.openai.com/v1/chat/completions
```

**Headers:**

```
Authorization: Bearer {API_KEY}
Content-Type: application/json
```

**Request Body:**

```json
{
  "model": "gpt-4o-mini",
  "messages": [
    {
      "role": "system",
      "content": "System prompt هنا...\n\nIMPORTANT: Output MUST be JSON only..."
    },
    {
      "role": "user",
      "content": "User prompt هنا..."
    }
  ],
  "temperature": 0.2,
  "response_format": {"type": "json_object"}
}
```

**Response:**

```json
{
  "choices": [{
    "message": {
      "content": "{\"teacher_name\":\"...\",\"school_name\":\"...\",...}"
    },
    "finish_reason": "stop"
  }]
}
```

**استخراج JSON:**

```php
$jsonText = $response['choices'][0]['message']['content']
```

---

## تصميم ملف DOCX - التفاصيل

### 1. هيكل القالب (Template)

القالب DOCX يحتوي على **placeholders** مثل:

```
${teacher_name}
${school_name}
${general_title}
${o_statement}
${o_assess_strategy}
${o_dur}
```

### 2. معالجة الحقول الفردية

```php
// لكل حقل في $tplData:
$tp->setValue('teacher_name', 'أ. فاطمة المصري')
$tp->setValue('school_name', 'مدرسة الأمل')
$tp->setValue('w_min', '3')
```

**النتيجة في DOCX:**

- `${teacher_name}` → `أ. فاطمة المصري`
- `${school_name}` → `مدرسة الأمل`
- `${w_min}` → `3`

### 3. معالجة الحلقات (Loops) - النتاجات

**القالب يحتوي على:**

```
| ${o_statement} | ${o_assess_strategy} | ${o_teach_strategy} | ${o_dur} |
```

**البيانات من AI:**

```json
{
  "o_statement": ["ناتج 1", "ناتج 2"],
  "o_assess_strategy": ["استراتيجية 1", "استراتيجية 2"],
  "o_teach_strategy": ["تدريس 1", "تدريس 2"],
  "o_dur": [15, 20]
}
```

**التحويل:**

```php
$rows = [
    [
        'o_statement' => 'ناتج 1',
        'o_assess_strategy' => 'استراتيجية 1',
        'o_teach_strategy' => 'تدريس 1',
        'o_dur' => '15'
    ],
    [
        'o_statement' => 'ناتج 2',
        'o_assess_strategy' => 'استراتيجية 2',
        'o_teach_strategy' => 'تدريس 2',
        'o_dur' => '20'
    ]
]

$tp->cloneRowAndSetValues('o_statement', $rows)
```

**النتيجة في DOCX:**

```
| ناتج 1 | استراتيجية 1 | تدريس 1 | 15 |
| ناتج 2 | استراتيجية 2 | تدريس 2 | 20 |
```

### 4. تنظيف المتغيرات غير المستبدلة

بعد الحفظ، يتم تنظيف أي `${variable}` لم يتم استبدالها:

```php
// فتح DOCX كـ ZIP
$zip->open($docxPath)
$documentXml = $zip->getFromName('word/document.xml')

// إزالة ${...} غير مستبدلة
$documentXml = preg_replace('/\$\{([^}]+)\}/u', '', $documentXml)

// حفظ XML المعدل
$zip->deleteName('word/document.xml')
$zip->addFromString('word/document.xml', $documentXml)
```

---

## ملخص التدفق

1. **المستخدم → Backend:**

                        - Payload JSON من النموذج
                        - إنشاء job في `ai_jobs` (status='pending')

2. **Cron → AI:**

                        - جلب job pending
                        - بناء Prompts (System + User)
                        - استدعاء AI API (Gemini/OpenAI)
                        - استقبال JSON

3. **JSON → DOCX:**

                        - تحويل JSON إلى بيانات قالب
                        - فتح قالب DOCX
                        - ملء الحقول الفردية
                        - نسخ صفوف النتاجات
                        - حفظ الملف

4. **حفظ النتيجة:**

                        - UPDATE ai_jobs (status='done', result_json, file_id)
                        - INSERT INTO files
                        - الملف جاهز للتنزيل

---

## الملفات الرئيسية

- **AiToolsController.php** - استقبال الطلبات
- **AiJobsRunner.php** - معالجة الطلبات (Cron)
- **AiClient.php** - الاتصال بوكيل AI
- **PromptMakerFactory.php** - إنشاء PromptMaker المناسب
- **LessonPlanPromptMaker.php** - بناء Prompts لخطة الحصة
- **DocxService.php** - تصميم ملف DOCX
- **TemplateProcessor** (PhpOffice) - معالجة القالب