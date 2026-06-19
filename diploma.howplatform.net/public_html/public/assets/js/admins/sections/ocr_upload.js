export function init() {
  const waitForDom = setInterval(() => {
    const dropZone = document.getElementById("dropZone");
    if (!dropZone) return;
    clearInterval(waitForDom);

    const fileInput = document.getElementById("fileInput");
    const filePreview = document.getElementById("filePreview");
    const fileName = document.getElementById("fileName");
    const fileIcon = document.getElementById("fileIcon");
    const removeFile = document.getElementById("removeFile");
    const analyzeBtn = document.getElementById("analyzeBtn");
    const progressAlert = document.getElementById("progressAlert");
    const resultCard = document.getElementById("resultCard");
    const resultName = document.getElementById("resultName");
    const downloadBtn = document.getElementById("downloadBtn");
    const settingsBtn = document.getElementById("settingsBtn");
    const settingsPanel = document.getElementById("settingsPanel");
    const saveApiKey = document.getElementById("saveApiKey");
    const apiKeyInput = document.getElementById("apiKeyInput");

    // 🔧 إعدادات المفتاح
    let userApiKey = "";
    settingsBtn.addEventListener("click", () => {
      settingsPanel.classList.toggle("hidden");
    });
    saveApiKey.addEventListener("click", () => {
      userApiKey = apiKeyInput.value.trim();
      settingsPanel.classList.add("hidden");
      Swal.fire("تم الحفظ", "تم تحديث مفتاح الـ API بنجاح", "success");
    });

    // 🧾 اختيار الملف أو السحب
    dropZone.addEventListener("click", () => fileInput.click());
    dropZone.addEventListener("dragover", e => {
      e.preventDefault();
      dropZone.classList.add("dragging");
    });
    dropZone.addEventListener("dragleave", () => dropZone.classList.remove("dragging"));
    dropZone.addEventListener("drop", e => {
      e.preventDefault();
      fileInput.files = e.dataTransfer.files;
      handleFileSelect();
    });
    fileInput.addEventListener("change", handleFileSelect);

    function handleFileSelect() {
      const file = fileInput.files[0];
      if (!file) return;
      filePreview.classList.remove("hidden");
      fileName.textContent = file.name;
      const ext = file.name.split(".").pop().toLowerCase();
      const iconMap = { pdf: "fa-file-pdf text-danger", docx: "fa-file-word text-primary", jpg: "fa-file-image text-warning", png: "fa-file-image text-warning" };
      fileIcon.className = "fa-solid " + (iconMap[ext] || "fa-file text-secondary");
    }

    removeFile.addEventListener("click", () => {
      fileInput.value = "";
      filePreview.classList.add("hidden");
    });

    // 🚀 التحليل
    analyzeBtn.addEventListener("click", async () => {
      const file = fileInput.files[0];
      if (!file) return Swal.fire("تنبيه", "الرجاء اختيار ملف أولاً", "warning");

      progressAlert.classList.remove("hidden");
      resultCard.classList.add("hidden");
      analyzeBtn.disabled = true;

      const formData = new FormData();
      formData.append("file", file);
      if (userApiKey) formData.append("api_key", userApiKey);

      try {
        const res = await fetch("https://mistral-ocr-api-g8j6.onrender.com/ocr", { method: "POST", body: formData });
        const data = await res.json();
        if (data.status !== "ok") throw new Error(data.detail || "فشل التحليل");

        resultName.textContent = data.docx_file;
        downloadBtn.href = "https://mistral-ocr-api-g8j6.onrender.com" + data.download_url;
        progressAlert.classList.add("hidden");
        resultCard.classList.remove("hidden");

        Swal.fire("تم بنجاح", "تم إنشاء الملف ويمكنك تحميله الآن.", "success");
      } catch (err) {
        progressAlert.classList.add("hidden");
        Swal.fire("خطأ", err.message, "error");
      } finally {
        analyzeBtn.disabled = false;
      }
    });
  }, 250);
}
