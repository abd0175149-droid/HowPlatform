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
const maximizeBtn = document.getElementById("maximize-btn");
const minimizeBtn = document.getElementById("minimize-btn");
const historyBtn = document.getElementById("history-btn");
const historyNewBtn = document.getElementById("history-new-btn");
const historyList = document.getElementById("fateen-history-list");
const serviceOptions = document.querySelectorAll(".service-option");

// ======================== المتغيرات العامة ========================
let selectedService = "";
let chatHistory = [];
let chatHistoryForAPI = [];
let pendingController = null;
let userBlocked = false;

// ======================== سجل المحادثات (محادثات متعددة) ========================
const FATEEN_STORE_KEY = "fateen_chats_v1";
let activeConvId = null;

function loadStore() {
  try {
    const s = JSON.parse(localStorage.getItem(FATEEN_STORE_KEY) || "null");
    if (s && Array.isArray(s.list)) return s;
  } catch (e) {}
  return { activeId: null, maximized: false, list: [] };
}

function persistStore(s) {
  try { localStorage.setItem(FATEEN_STORE_KEY, JSON.stringify(s)); } catch (e) {}
}

function genConvId() {
  return "c" + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}

function deriveTitle() {
  const u = (chatHistoryForAPI || []).find((m) => m.role === "user");
  let t = u && u.parts && u.parts[0] ? u.parts[0].text : "";
  t = (t || "").replace(/\s+/g, " ").trim();
  if (t) return t.length > 34 ? t.slice(0, 34) + "…" : t;
  return selectedService || "محادثة جديدة";
}

function fmtConvTime(ts) {
  if (!ts) return "";
  try {
    const d = new Date(ts);
    return d.toLocaleDateString("ar", { day: "2-digit", month: "2-digit" }) +
      " " + d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  } catch (e) { return ""; }
}

// لقطة للمحادثة النشطة الحالية → التخزين
function snapshotActive() {
  const inChat =
    chatArea && chatArea.style.display !== "none" &&
    chatMessages && chatMessages.children.length > 0;
  if (!inChat) return;

  const store = loadStore();
  if (!activeConvId) activeConvId = genConvId();
  let conv = store.list.find((c) => c.id === activeConvId);
  if (!conv) {
    conv = { id: activeConvId };
    store.list.unshift(conv);
  }
  conv.service = selectedService;
  conv.title = deriveTitle();
  conv.messagesHTML = chatMessages.innerHTML;
  conv.historyForAPI = chatHistoryForAPI;
  conv.updatedAt = Date.now();

  store.activeId = activeConvId;
  store.maximized = chatbot.classList.contains("maximized");
  persistStore(store);
  renderHistoryList();
}

function renderHistoryList() {
  if (!historyList) return;
  const store = loadStore();
  if (!store.list.length) {
    historyList.innerHTML =
      '<div class="fh-empty">لا توجد محادثات محفوظة بعد</div>';
    return;
  }
  const sorted = store.list
    .slice()
    .sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
  historyList.innerHTML = sorted
    .map(
      (c) => `
      <div class="fh-item${c.id === activeConvId ? " active" : ""}" data-id="${c.id}">
        <div class="fh-item-main">
          <span class="fh-title">${escapeHTML(c.title || c.service || "محادثة")}</span>
          <span class="fh-meta">${escapeHTML(c.service || "")} · ${fmtConvTime(c.updatedAt)}</span>
        </div>
        <button class="fh-del" data-id="${c.id}" title="حذف المحادثة">
          <i class="fas fa-trash"></i>
        </button>
      </div>`
    )
    .join("");
}

function openConversation(id) {
  const store = loadStore();
  const conv = store.list.find((c) => c.id === id);
  if (!conv) return;
  activeConvId = id;
  selectedService = conv.service || "";
  chatHistoryForAPI = Array.isArray(conv.historyForAPI) ? conv.historyForAPI : [];
  chatMessages.innerHTML = conv.messagesHTML || "";
  serviceSelection.style.display = "none";
  chatArea.style.display = "flex";
  chatMessages.scrollTop = chatMessages.scrollHeight;
  userBlocked = false;
  store.activeId = id;
  persistStore(store);
  chatbot.classList.remove("show-history");
  renderHistoryList();
}

function newConversation() {
  snapshotActive(); // احفظ الحالية أولاً
  activeConvId = genConvId();
  selectedService = "";
  chatHistory = [];
  chatHistoryForAPI = [];
  userBlocked = false;
  chatMessages.innerHTML = "";
  if (userInput) userInput.value = "";
  chatArea.style.display = "none";
  serviceSelection.style.display = "flex";
  const store = loadStore();
  store.activeId = activeConvId;
  persistStore(store);
  chatbot.classList.remove("show-history");
  renderHistoryList();
}

function deleteConversation(id) {
  const store = loadStore();
  store.list = store.list.filter((c) => c.id !== id);
  persistStore(store);
  if (id === activeConvId) {
    activeConvId = genConvId();
    selectedService = "";
    chatHistoryForAPI = [];
    chatMessages.innerHTML = "";
    chatArea.style.display = "none";
    serviceSelection.style.display = "flex";
  }
  renderHistoryList();
}

function syncMaximizeIcon() {
  if (!maximizeBtn) return;
  const on = chatbot.classList.contains("maximized");
  maximizeBtn.innerHTML = on
    ? '<i class="fas fa-compress"></i>'
    : '<i class="fas fa-expand"></i>';
  maximizeBtn.title = on ? "تصغير المحادثة" : "تكبير المحادثة";
}

function restoreChatState() {
  const store = loadStore();
  if (store.activeId) {
    const conv = store.list.find((c) => c.id === store.activeId);
    if (conv && conv.messagesHTML) {
      openConversation(conv.id);
    } else {
      activeConvId = store.activeId;
    }
  }
  renderHistoryList();
  syncMaximizeIcon();
}

// توافق مع النداءات القديمة
function saveChatState() { snapshotActive(); }

// ======================== تعليمات الخدمات ========================
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
    const res = await fetch('/FateenAi/usage');
    const data = await res.json();
    const usageCounter = document.getElementById('usage-counter');

    // إزالة أي كلاس قديم
    usageCounter.classList.remove('premium', 'usage');

    if (data.unlimited === true) {
      // حساب بريميوم بلا حدود
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
        <i class="fas fa-brain"></i> ${data.plan || 'Free'} – استخدام اليوم:
        <strong>${data.used}</strong> / ${data.limit}
        | المتبقي: <strong>${data.remaining}</strong>
      `;
    } else {
      usageCounter.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i> فشل في تحميل العداد
      `;
    }
  } catch (e) {
    const usageCounter = document.getElementById('usage-counter');
    usageCounter.classList.remove('premium', 'usage');
    usageCounter.innerHTML = `
      <i class="fas fa-plug"></i> ⚠️ خطأ في الاتصال
    `;
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
  saveChatState();
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

    if (data?.error?.includes("❌ لا يمكنك إرسال المزيد")) {
      userBlocked = true;
      addMessage(
        "❌ لا يمكنك إرسال المزيد من الرسائل في حسابك الحالي. الرجاء الترقية إلى الحساب المميز.",
        "received"
      );
      return;
    }

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

    const msgContent = document.createElement("div");
    msgContent.innerHTML = `
      ${formatted}
      <div class="timestamp" style="font-size:.7rem;opacity:.7;margin-top:5px;text-align:left;">
        ${timestamp}
      </div>
    `;

    // --- زر النسخ مع انيميشن ---
    const copyBtn = document.createElement("button");
    copyBtn.classList.add("copy-btn");
    copyBtn.innerHTML = `<i class="fas fa-copy"></i>`;

    copyBtn.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(aiReplyRaw);

        // تأثير تبديل الأيقونة مع انيميشن
        copyBtn.classList.add("copied");
        copyBtn.innerHTML = `<i class="fas fa-check"></i>`;

        setTimeout(() => {
          copyBtn.classList.remove("copied");
          copyBtn.innerHTML = `<i class="fas fa-copy"></i>`;
        }, 1000);
      } catch (err) {
        console.error("فشل النسخ:", err);
      }
    });

    messageDiv.appendChild(msgContent);
    messageDiv.appendChild(copyBtn);

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    chatHistory.push({ type: "ai", content: aiReplyRaw, timestamp });
    saveChatState();
    fetchUsageCounter();

  } catch (err) {
    throw err;
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

  const res = await fetch("/FateenAi/generate", {
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

  if (!res.ok) {
    const t = await res.text().catch(() => "");
    if (res.status === 403) {
      addMessage(
        "لا يمكنك إرسال المزيد من الرسائل في حسابك الحالي. الرجاء الترقية إلى خطة أعلى",
        "received"
      );
      return;
    }
    throw new Error(`HTTP ${res.status}: ${t}`);
  }

  return res.json();
}

// ======================== تفعيل وتهيئة ========================
chatbot.classList.remove("active");

chatInitiator.addEventListener("click", () => {
  chatbot.classList.add("active");
  document.body.classList.add("no-scroll");
    fetchUsageCounter();
   setTimeout(() => {
  chatInitiator.style.display = "none";
  }, 100);
});

closeBtn.addEventListener("click", () => {
  chatbot.classList.remove("active");
  document.body.classList.remove("no-scroll");
   setTimeout(() => {
    chatInitiator.style.display = "flex";
  }, 400);
});

document.addEventListener("click", function (event) {
  if (
    chatbot.classList.contains("active") &&
    !chatbot.classList.contains("in-section") &&
    !chatbot.contains(event.target) &&
    !chatInitiator.contains(event.target)
  ) {
    chatbot.classList.remove("active");
    setTimeout(() => {
      chatInitiator.style.display = "flex";
    }, 400);
    document.body.classList.remove("no-scroll");
  }
});

newChatBtn.addEventListener("click", newConversation);

if (historyNewBtn) historyNewBtn.addEventListener("click", newConversation);

if (maximizeBtn) {
  maximizeBtn.addEventListener("click", () => {
    snapshotActive();
    // التكبير = فتح قسم فَطين الكامل (صفحة SPA كاملة مثل بقية الموقع)
    if (window.StudentRouter && typeof window.StudentRouter.navigate === "function") {
      // تذكّر القسم الحالي للعودة إليه عند التصغير
      try {
        const cur = localStorage.getItem("student_active_section") || "dashboard";
        window.__fateenReturnSection = cur !== "fateen-chat" ? cur : "dashboard";
      } catch (e) { window.__fateenReturnSection = "dashboard"; }
      chatbot.classList.remove("active", "show-history");
      document.body.classList.remove("no-scroll");
      window.StudentRouter.navigate("fateen-chat");
      return;
    }
    // احتياطي: لو الراوتر غير متوفر، استخدم وضع الطبقة الكاملة
    chatbot.classList.toggle("maximized");
    syncMaximizeIcon();
    renderHistoryList();
  });
}

// إتاحة تحديث قائمة السجل لقسم فَطين الكامل
window.__fateenRenderHistory = renderHistoryList;

if (historyBtn) {
  historyBtn.addEventListener("click", () => {
    chatbot.classList.toggle("show-history");
    if (chatbot.classList.contains("show-history")) renderHistoryList();
  });
}

// التصغير: العودة من القسم الكامل إلى النافذة العائمة
if (minimizeBtn) {
  minimizeBtn.addEventListener("click", () => {
    snapshotActive();
    const back = window.__fateenReturnSection || "dashboard";
    // اطلب من unmount القسم أن يفتح النافذة العائمة بعد إعادة العقدة إلى body
    window.__fateenWantFloating = true;
    if (window.StudentRouter && typeof window.StudentRouter.navigate === "function") {
      window.StudentRouter.navigate(back);
    } else {
      // احتياطي: لو الراوتر غير متاح
      chatbot.classList.remove("in-section");
      document.body.appendChild(chatbot);
      chatbot.classList.add("active");
      window.__fateenWantFloating = false;
    }
  });
}

if (historyList) {
  historyList.addEventListener("click", (e) => {
    const del = e.target.closest(".fh-del");
    if (del) {
      e.stopPropagation();
      deleteConversation(del.getAttribute("data-id"));
      return;
    }
    const item = e.target.closest(".fh-item");
    if (item) openConversation(item.getAttribute("data-id"));
  });
}

// استرجاع المحادثات وآخر محادثة نشطة عند تحميل الصفحة
restoreChatState();

serviceOptions.forEach((option) => {
  option.addEventListener("click", () => {
    selectedService = option.getAttribute("data-service");

    serviceSelection.style.display = "none";
    chatArea.style.display = "flex";

    addMessage(
      `لقد اخترت خدمة: <strong>${selectedService}</strong>. الرجاء إدخال النص أو السؤال الخاص بك.`,
      "received"
    );

    userInput.placeholder =
      selectedService.includes("مقال") || selectedService.includes("بحث")
        ? "الصق المقال أو البحث هنا..."
        : "اكتب سؤالك هنا...";

    userInput.focus();
    saveChatState();
  });
});

sendBtn.addEventListener("click", sendMessage);

userInput.addEventListener("keydown", (e) => {
  if (e.key === "Enter" && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});
