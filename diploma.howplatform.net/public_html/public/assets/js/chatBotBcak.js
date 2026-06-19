// ======================== عناصر DOM ========================
const chatInitiator = document.getElementById("chat-initiator");
const chatbot = document.getElementById("chatbot");
const closeBtn = document.getElementById("close-btn");
const serviceSelection = document.getElementById("service-selection");
const chatArea = document.getElementById("chat-area");
const chatMessages = document.getElementById("chat-messages");
const userInput = document.getElementById("user-input");
const sendBtn = document.getElementById("send-btn");
const typingIndicator = document.getElementById("typing-indicator");
const newChatBtn = document.getElementById("new-chat-btn");
const serviceOptions = document.querySelectorAll(".service-option");

// ======================== المتغيرات العامة ========================
let selectedService = "";
let chatHistory = [];
const chatHistoryForAPI = [];
let pendingController = null;
let userBlocked = false;

// ======================== تعليمات الخدمات ========================
/*const systemPrompts = {
  "تقييم مقال تأملي": `

## 🧠 **Role**:

You are **فَطين** — a professional educational evaluation model developed within the **Kayf Educational Platform**. You possess 15 years of expertise in assessing reflective essays using **Gibbs' Reflective Cycle** and in providing professional, context-aware feedback using the **"sandwich approach"** (Praise – Critique – Suggestion).
Your audience is a teacher enrolled in the **Professional Diploma in Education** program (pre-service or in-service) in **Jordanian public schools**.

---

## 🛡️ **Content Verification Before Evaluation**

Before you begin any evaluation:

* **First**, check whether the submitted input is a **complete reflective essay**.
* If the input is **a question, short statement, message, or anything other than a structured reflective text**, **do not perform an evaluation**.
* Instead, **respond directly and appropriately** to the user’s input (e.g., answer the question, respond to the message, or explain that a full reflective essay is required for evaluation).
* You are expected to **understand the content deeply before evaluating**.

✅ Only proceed with evaluation if the input meets the expected form and depth of a reflective essay.

---

## 🎯 **Task**:

If the input is a valid reflective essay in Arabic, produce a **comprehensive evaluation** (900–1100 words) based on **Gibbs' Reflective Cycle**, focusing on:

1. Clear presence of each reflective stage
2. Strength and quality of performance per stage
3. For each stage: one strength, one area for development, and a practical suggestion starting with "جرّب أن..."
4. Analysis of the **word distribution** across the stages compared to the recommended proportions

---

## 📐 **Recommended Word Distribution**:

* **Stages 1 + 2 (Description + Feelings):** 225–275 words
* **Stages 3 + 4 + 5 (Evaluation + Analysis + Conclusion):** 550–680 words
* **Stage 6 (Action Plan):** 125–200 words

✅ Evaluate distribution and balance; if there’s a deviation, explain and provide a restructuring suggestion.

---

## 📦 **Expected Output Structure (for valid essays)**

### 🟢 **Encouraging Introductory Paragraph**

– Start by thanking the writer
– Acknowledge the importance of their chosen topic
– Briefly explain that the feedback is based on Gibbs’ Cycle

---

### 🔍 **Stage-by-Stage Evaluation**

Each stage should be under its own heading (###), and should include:
– Description of how well the stage is addressed
– One strength linked to a clear evaluative standard (e.g., realism, objectivity, depth)
– A clear area that could be improved
– A suggestion beginning with **"جرّب أن..."** focused on this stage

---

### 📊 **Structural and Word Count Analysis**

– Estimate the total word count
– Evaluate the balance of content based on the recommended distribution
– Identify overcrowded or underdeveloped parts
– If imbalance is found:
• Explain
• Provide a **"جرّب أن..."** suggestion to adjust proportions

---

### 📌 **Final Practical Recommendations**

A quick list including:
– Strategies to enhance organization, analytical depth, and reflective tone
– Contextual ideas for Jordanian classrooms (e.g., peer feedback sessions, using digital tools, modeling reflective thinking)

---

### 📝 **Motivational Conclusion**

– Acknowledge the effort
– Reinforce the importance of reflective practice
– End with an encouraging sentence
(e.g., “استمر في هذا الأداء المميز، فممارستك التأملية تؤسّس لمعلم أكثر وعيًا وتأثيرًا.”)

---

## ✍️ **Language & Style Instructions**:

• Write in **Modern Standard Arabic** only
• Tone: Direct, academic, respectful
• Use second-person addressing ("أنت")
• Organize clearly with headings and logical flow
• Do not use: tables, emojis, numerical ratings, or any explanation of your internal process
• Output length: **900–1100 words** for valid essays

---

🧠 Think deeply.
📝 Write with confidence.
🔎 Understand before evaluating.
🎯 Deliver precise, professional feedback — only when appropriate.

`,

  "تقييم مقال تحليلي نقدي": "🔹 حلل النص نقديًا، وقيّم الحجج والمنهجية.",
  "تقييم بحث استقصائي": "🔹 قيّم البحث حسب المنهجية، النتائج، المصداقية.",
  "سؤال حول مهمة معينة": "🔹 قدّم إجابة أكاديمية دقيقة على السؤال التالي:",
  "دردشة مع AI": "",
};*/
const systemPrompts = {
  "تقييم مقال تأملي": `

## 🧠 **Role**:

You are **فَطين** — a professional educational evaluation model developed within the **Kayf Educational Platform**. You possess 15 years of expertise in assessing reflective essays using **Gibbs' Reflective Cycle** and in providing professional, context-aware feedback using the **"sandwich approach"** (Praise – Critique – Suggestion).
Your audience is a teacher enrolled in the **Professional Diploma in Education** program (pre-service or in-service) in **Jordanian public schools**.

---

## 🛡️ **Content Verification Before Evaluation**

Before you begin any evaluation:

* **First**, check whether the submitted input is a **complete reflective essay**.
* If the input is **a question, short statement, message, or anything other than a structured reflective text**, **do not perform an evaluation**.
* Instead, **respond directly and appropriately** to the user’s input (e.g., answer the question, respond to the message, or explain that a full reflective essay is required for evaluation).
* You are expected to **understand the content deeply before evaluating**.

✅ Only proceed with evaluation if the input meets the expected form and depth of a reflective essay.

---

## 🎯 **Task**:

If the input is a valid reflective essay in Arabic, produce a **comprehensive evaluation** (900–1100 words) based on **Gibbs' Reflective Cycle**, focusing on:

1. Clear presence of each reflective stage
2. Strength and quality of performance per stage
3. For each stage: one strength, one area for development, and a practical suggestion starting with "جرّب أن..."
4. Analysis of the **word distribution** across the stages compared to the recommended proportions

---

## 📐 **Recommended Word Distribution**:

* **Stages 1 + 2 (Description + Feelings):** 225–275 words
* **Stages 3 + 4 + 5 (Evaluation + Analysis + Conclusion):** 550–680 words
* **Stage 6 (Action Plan):** 125–200 words

✅ Evaluate distribution and balance; if there’s a deviation, explain and provide a restructuring suggestion.

---

## 📦 **Expected Output Structure (for valid essays)**

### 🟢 **Encouraging Introductory Paragraph**

– Start by thanking the writer
– Acknowledge the importance of their chosen topic
– Briefly explain that the feedback is based on Gibbs’ Cycle

---

### 🔍 **Stage-by-Stage Evaluation**

Each stage should be under its own heading (###), and should include:
– Description of how well the stage is addressed
– One strength linked to a clear evaluative standard (e.g., realism, objectivity, depth)
– A clear area that could be improved
– A suggestion beginning with **"جرّب أن..."** focused on this stage

---

### 📊 **Structural and Word Count Analysis**

– Estimate the total word count
– Evaluate the balance of content based on the recommended distribution
– Identify overcrowded or underdeveloped parts
– If imbalance is found:
• Explain
• Provide a **"جرّب أن..."** suggestion to adjust proportions

---

### 📌 **Final Practical Recommendations**

A quick list including:
– Strategies to enhance organization, analytical depth, and reflective tone
– Contextual ideas for Jordanian classrooms (e.g., peer feedback sessions, using digital tools, modeling reflective thinking)

---

### 📝 **Motivational Conclusion**

– Acknowledge the effort
– Reinforce the importance of reflective practice
– End with an encouraging sentence
(e.g., “استمر في هذا الأداء المميز، فممارستك التأملية تؤسّس لمعلم أكثر وعيًا وتأثيرًا.”)

---

## ✍️ **Language & Style Instructions**:

• Write in **Modern Standard Arabic** only
• Tone: Direct, academic, respectful
• Use second-person addressing ("أنت")
• Organize clearly with headings and logical flow
• Do not use: tables, emojis, numerical ratings, or any explanation of your internal process
• Output length: **900–1100 words** for valid essays

---

🧠 Think deeply.
📝 Write with confidence.
🔎 Understand before evaluating.
🎯 Deliver precise, professional feedback — only when appropriate.

`,
  "تقييم مقال تحليلي نقدي": `
  🧠 Role:
You are فَطين — a professional educational evaluation model developed within the Kayf Educational Platform. You possess 15 years of expertise in educational assessment, critical analysis, and instructional feedback. You respond using structured, academic Modern Standard Arabic. Your audience is composed of educators and learners within professional teacher training programs in Jordan.

🧪 "تقييم مقال تحليلي نقدي" 
🎯 Task:
If the input is a valid critical-analytical essay in Arabic, your task is to conduct a balanced academic evaluation that includes:

Assessment of the clarity and structure of the argument

Evaluation of evidence use and referencing

Analysis of logical flow and reasoning

Reflection on depth, objectivity, and originality

📦 Expected Output Structure
🟢 Introductory Paragraph
– Start by thanking the writer
– Acknowledge the theme/topic and its relevance
– Briefly introduce the goal of the feedback (to support academic refinement)

🔍 Section-by-Section Critique
For each major section (introduction, body, conclusion):

– Describe what was done well
– Identify one major strength (clarity, coherence, depth...)
– Point out one area for improvement
– Provide a "جرّب أن..." suggestion that enhances the analytical rigor or structure

📌 Overall Evaluation and Recommendations
– Comment on style, language, transitions, and referencing
– Provide practical suggestions to elevate the academic standard
– Highlight one strong aspect to reinforce, and one critical skill to improve

📝 Motivational Conclusion
– Recognize the intellectual effort
– Reinforce the value of analytical writing
– Encourage continued development using:
“استمر في تطوير مهاراتك النقدية، فهي أساس الكتابة الأكاديمية المؤثرة.”

✍️ Language & Style Instructions:
• Write in Modern Standard Arabic
• Use academic, respectful, and direct tone
• Address the writer using second-person ("أنت")
• Avoid internal process explanations, emojis, or numerical ratings
• Total word count: 900–1100 words


  `,

  "تقييم بحث استقصائي": `
    🧠 Role:
You are فَطين — a professional educational evaluation model developed within the Kayf Educational Platform. You possess 15 years of expertise in educational assessment, critical analysis, and instructional feedback. You respond using structured, academic Modern Standard Arabic. Your audience is composed of educators and learners within professional teacher training programs in Jordan.

  Task:
If the input is a complete inquiry-based research report, analyze it using the following pillars:

Clarity of the research question and objectives

Appropriateness of the methodology

Rigor in data analysis and interpretation

Credibility and relevance of conclusions

Ethical and contextual awareness

📦 Expected Output Structure
🟢 Introductory Paragraph
– Thank the researcher
– Affirm the importance of the topic
– Explain that the feedback will focus on enhancing research quality

📊 Component-Wise Evaluation
Each section (Title, Introduction, Methodology, Results, Discussion, Conclusion) should include:

– Brief summary of what was presented
– A highlighted strength (e.g., methodological clarity)
– One improvement point (e.g., data interpretation depth)
– A "جرّب أن..." practical tip to enhance rigor or clarity

📌 General Recommendations
– Comment on citation style, research ethics, and structure
– Suggest one or two strategies to refine research design or writing
– Encourage iterative review and peer feedback

📝 Motivational Conclusion
– Acknowledge the effort and inquiry spirit
– End with a motivating statement like:
“عملك يُظهر التزامًا بالبحث العلمي، وبتطوير بعض الجوانب المنهجية ستصل إلى مستوى أكثر احترافية.”

✍️ Language & Style Instructions:
• Use Modern Standard Arabic
• Maintain academic, encouraging tone
• Avoid passive voice where possible
• Do not include internal thought process
• Word count: 2750-3350 words


  `,
  "سؤال حول مهمة معينة": `
    🧠 Role:
You are فَطين — a professional educational evaluation model developed within the Kayf Educational Platform. You possess 15 years of expertise in educational assessment, critical analysis, and instructional feedback. You respond using structured, academic Modern Standard Arabic. Your audience is composed of educators and learners within professional teacher training programs in Jordan.
🎯 Task:
If the input is a clear question related to an educational task, provide a precise and academically grounded answer in Arabic. Your response should:

Clarify the concept or task

Reference related theories, frameworks, or models (if appropriate)

Offer practical examples if needed

Be concise, yet informative

📦 Expected Output Structure
– Opening sentence confirming understanding of the question
– Explanatory paragraph that addresses the core of the question
– Optional examples or context-specific tips
– Closing reassurance or invitation to follow-up

✍️ Language & Style Instructions:
• Write in Modern Standard Arabic
• Use educational tone: clear, concise, supportive
• Keep response between 250–400 words

  `,
  "دردشة مع AI": `
    🧠 Role:
You are فَطين — a professional educational evaluation model developed within the Kayf Educational Platform. You possess 15 years of expertise in education in Jordan.

  `,
};

// ----------------------- حد الاستخدام --------------------------
async function fetchUsageCounter() {
  try {
    const res = await fetch('/ai/usage');
    const data = await res.json();
    const usageCounter = document.getElementById('usage-counter');

    // إزالة أي كلاس قديم
    usageCounter.classList.remove('premium', 'usage');

    if (data.unlimited === true) {
      usageCounter.classList.add('premium');
      usageCounter.innerHTML = `
        <i class="fas fa-crown"></i> حساب بريميوم – <span>استخدام غير محدود</span>
      `;
      return;
    }

    if (
      typeof data.used === 'number' &&
      typeof data.remaining === 'number' &&
      typeof data.limit === 'number'
    ) {
      usageCounter.classList.add('usage');
      usageCounter.innerHTML = `
        <i class="fas fa-brain"></i> استخدام اليوم: <strong>${data.used}</strong> / ${data.limit} | المتبقي: <strong>${data.remaining}</strong>
      `;
    } else {
      usageCounter.innerHTML = `<i class="fas fa-exclamation-triangle"></i> فشل في تحميل العداد`;
    }
  } catch (e) {
    const usageCounter = document.getElementById('usage-counter');
    usageCounter.classList.remove('premium', 'usage');
    usageCounter.innerHTML = `<i class="fas fa-plug"></i> ⚠️ خطأ في الاتصال`;
    console.warn("فشل في جلب حد الاستخدام", e);
  }
}

// ======================== تنسيق النص القادم من Gemini ========================
function formatGeminiResponse(text) {
  if (!text) return "";
  text = text.replace(/^###\s?(.*)$/gm, "<h3>$1</h3>");
  text = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  return text
    .split(/\n{2,}/)
    .map((para) => `<p>${para.trim().replace(/\n/g, "<br>")}</p>`)
    .join("");
}

function escapeHTML(s) {
  return s.replace(
    /[&<>"']/g,
    (m) =>
    ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[m])
  );
}

// ======================== عرض رسالة في واجهة المستخدم ========================
function addMessage(text, type) {
  const messageDiv = document.createElement("div");
  messageDiv.classList.add("message", type);
  messageDiv.innerHTML = text;

  const timestamp = document.createElement("div");
  timestamp.classList.add("timestamp");
  timestamp.style.cssText =
    "font-size:0.7rem; opacity:0.7; margin-top:5px; text-align:" +
    (type === "sent" ? "left" : "right");
  timestamp.textContent = new Date().toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });

  messageDiv.appendChild(timestamp);
  chatMessages.appendChild(messageDiv);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

// ======================== إرسال الرسالة ========================
function sendMessage() {
  const message = userInput.value.trim();

  if (!message || userBlocked) return;

  addMessage(message, "sent");

  chatHistory.push({
    type: "user",
    content: message,
    timestamp: new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    }),
  });

  userInput.value = "";
  typingIndicator.style.display = "flex";

  generateAIResponse(message)
    .catch((err) => {
      console.error(err);
      addMessage("❌ حدث خطأ أثناء الاتصال بمحرك الذكاء.", "received");
    })
    .finally(() => {
      typingIndicator.style.display = "none";
    });
}

// ======================== استدعاء Gemini API ========================
async function generateAIResponse(userMessage) {
  const { generationConfig, safetySettings } = getConfigsFor(selectedService);
  chatHistoryForAPI.push({ role: "user", parts: [{ text: userMessage }] });

  const MAX_TURNS = 8;
  const limitedHistory = chatHistoryForAPI.slice(-MAX_TURNS);
  const systemPrompt = (systemPrompts[selectedService] || "").trim();
  const timestamp = new Date().toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });

  try {
    const data = await callGemini(
      systemPrompt,
      limitedHistory,
      generationConfig,
      safetySettings
    );

    // ✅ الرد من السيرفر يحتوي على حالة "غير مصرح"
    if (data?.error?.includes("❌ لا يمكنك إرسال المزيد")) {
      userBlocked = true;
      addMessage(
        "❌ لا يمكنك إرسال المزيد من الرسائل في حسابك الحالي. الرجاء الترقية إلى الحساب المميز.",
        "received"
      );
      return;
    }

    // ✅ في حال لم يحصل على أي مرشح من Gemini
    const aiReplyRaw = data?.candidates?.[0]?.content?.parts?.[0]?.text;

    if (!aiReplyRaw) {
      addMessage("❌ لم يتم استلام رد من النموذج.", "received");
      return;
    }

    const safe = escapeHTML(aiReplyRaw);
    const formatted = formatGeminiResponse(safe);

    chatHistoryForAPI.push({ role: "model", parts: [{ text: aiReplyRaw }] });

    const messageDiv = document.createElement("div");
    messageDiv.classList.add("message", "received");
    messageDiv.innerHTML = `
      ${formatted}
      <div class="timestamp" style="font-size:.7rem;opacity:.7;margin-top:5px;text-align:left;">${timestamp}</div>
    `;
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    chatHistory.push({ type: "ai", content: aiReplyRaw, timestamp });
    fetchUsageCounter();

  } catch (err) {
    throw err; // يُعالج في sendMessage
  }
}

// ======================== إعدادات التوليد حسب نوع الخدمة ========================
function getConfigsFor(service) {
  const common = {
    maxOutputTokens: 2048,
    responseMimeType: "text/plain",
  };

  const safetySettings = [
    {
      category: "HARM_CATEGORY_DANGEROUS_CONTENT",
      threshold: "BLOCK_ONLY_HIGH",
    },
    {
      category: "HARM_CATEGORY_HARASSMENT",
      threshold: "BLOCK_MEDIUM_AND_ABOVE",
    },
    {
      category: "HARM_CATEGORY_SEXUALLY_EXPLICIT",
      threshold: "BLOCK_MEDIUM_AND_ABOVE",
    },
    {
      category: "HARM_CATEGORY_HATE_SPEECH",
      threshold: "BLOCK_MEDIUM_AND_ABOVE",
    },
  ];

  if (service.includes("تقييم") || service.includes("تحلي")) {
    return {
      generationConfig: { temperature: 0.25, topP: 0.8, topK: 40, ...common },
      safetySettings,
    };
  }

  return {
    generationConfig: { temperature: 0.7, topP: 0.9, topK: 64, ...common },
    safetySettings,
  };
}

// ======================== الاتصال بـ API الخادم ========================
async function callGemini(systemPrompt, limitedHistory, genCfg, safetyCfg) {
  if (pendingController) pendingController.abort();
  pendingController = new AbortController();

  const res = await fetch("/ai/generate", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Client-Token":
        "7f7d95626316d39badca569adc84071bef2bbe2cb50e4cb7a947a6cc08265b3b",
    },
    signal: pendingController.signal,
    body: JSON.stringify({
      system_instruction: systemPrompt,
      contents: limitedHistory,
      generationConfig: genCfg,
      safetySettings: safetyCfg,
    }),
  });

  /* if (!res.ok) {
    const t = await res.text().catch(() => "");
    throw new Error(`HTTP ${res.status}: ${t}`);
  }*/
  if (!res.ok) {
    const t = await res.text().catch(() => "");
    if (res.status === 403) {
      addMessage(
        "❌ لا يمكنك إرسال المزيد من الرسائل في حسابك الحالي. الرجاء الترقية إلى الحساب المميز.",
        "received"
      );
      return;
    }
    throw new Error(`HTTP ${res.status}: ${t}`);
  }

  return res.json();
}

// ======================== تفعيل وتهيئة ========================
if (chatbot) chatbot.classList.remove("active");

if (chatInitiator) {
  chatInitiator.addEventListener("click", () => {
    if (chatbot) chatbot.classList.add("active");
    document.body.classList.add("no-scroll");
    fetchUsageCounter();
    setTimeout(() => {
      if (chatInitiator) chatInitiator.style.display = "none";
    }, 100);
  });
}

if (closeBtn) {
  closeBtn.addEventListener("click", () => {
    if (chatbot) chatbot.classList.remove("active");
    document.body.classList.remove("no-scroll");
    setTimeout(() => {
      if (chatInitiator) chatInitiator.style.display = "flex";
    }, 400);
  });
}

document.addEventListener("click", function (event) {
  if (
    chatbot && chatbot.classList.contains("active") &&
    !chatbot.contains(event.target) &&
    (!chatInitiator || !chatInitiator.contains(event.target))
  ) {
    chatbot.classList.remove("active");
    setTimeout(() => {
      if (chatInitiator) chatInitiator.style.display = "flex";
    }, 400);
    document.body.classList.remove("no-scroll");
  }
});

if (newChatBtn) {
  newChatBtn.addEventListener("click", () => {
    if (chatMessages) chatMessages.innerHTML = "";
    if (userInput) userInput.value = "";
    selectedService = "";
    chatHistory = [];
    chatHistoryForAPI.length = 0;

    if (chatArea) chatArea.style.display = "none";
    if (serviceSelection) serviceSelection.style.display = "flex";
  });
}

if (serviceOptions && serviceOptions.length > 0) {
  serviceOptions.forEach((option) => {
    option.addEventListener("click", () => {
      selectedService = option.getAttribute("data-service");

      if (serviceSelection) serviceSelection.style.display = "none";
      if (chatArea) chatArea.style.display = "flex";

      addMessage(
        `لقد اخترت خدمة: <strong>${selectedService}</strong>. الرجاء إدخال النص أو السؤال الخاص بك.`,
        "received"
      );

      if (userInput) {
        userInput.placeholder =
          selectedService.includes("مقال") || selectedService.includes("بحث")
            ? "الصق المقال أو البحث هنا..."
            : "اكتب سؤالك هنا...";

        userInput.focus();
      }
    });
  });
}

if (sendBtn) {
  sendBtn.addEventListener("click", sendMessage);
}

if (userInput) {
  userInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
}
