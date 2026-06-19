<style>
    .rag-debug-container {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }
    .rag-debug-container h1 {
        color: #333;
        border-bottom: 2px solid #4CAF50;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    .rag-debug-container button {
        background: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-left: 10px;
    }
    .rag-debug-container button:hover {
        background: #45a049;
    }
    .rag-debug-container button:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    .result {
        margin-top: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 4px solid #4CAF50;
    }
    .result.error {
        border-left-color: #f44336;
        background: #ffebee;
    }
    .loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }
    .result pre {
        background: #f4f4f4;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
        max-height: 500px;
        overflow-y: auto;
    }
    .section {
        margin-top: 20px;
        padding: 15px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .section h3 {
        margin-top: 0;
        color: #4CAF50;
    }
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
    }
    .badge.success {
        background: #4CAF50;
        color: white;
    }
    .badge.error {
        background: #f44336;
        color: white;
    }
    .badge.info {
        background: #2196F3;
        color: white;
    }
</style>

<div class="rag-debug-container">
    <h1>🔍 اختبار RAG Debug Route</h1>
    
    <form id="ragDebugForm">
        <div class="form-group">
            <label for="rag_question">السؤال:</label>
            <textarea id="rag_question" name="question" required>ما هي المهارات المطلوبة للتعليم الفعال؟</textarea>
        </div>
        
        <div class="form-group">
            <label for="rag_word_count">عدد الكلمات:</label>
            <input type="number" id="rag_word_count" name="word_count" value="500" min="300" max="2000" required>
        </div>
        
        <div class="form-group">
            <label for="rag_format">نوع التنسيق:</label>
            <select id="rag_format" name="format">
                <option value="essay">مقال أكاديمي</option>
                <option value="structured">إجابة منظمة</option>
            </select>
        </div>
        
        <button type="submit" id="ragSubmitBtn">🚀 إرسال الطلب</button>
        <button type="button" id="ragClearBtn">🗑️ مسح النتائج</button>
    </form>
    
    <div id="ragResult"></div>
</div>

<script>
    (function() {
        const form = document.getElementById('ragDebugForm');
        const resultDiv = document.getElementById('ragResult');
        const submitBtn = document.getElementById('ragSubmitBtn');
        const clearBtn = document.getElementById('ragClearBtn');

        if (!form || !resultDiv || !submitBtn || !clearBtn) {
            console.error('RAG Debug: Missing required elements');
            return;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const question = document.getElementById('rag_question').value.trim();
            const wordCount = parseInt(document.getElementById('rag_word_count').value);
            const format = document.getElementById('rag_format').value;
            
            if (!question) {
                showError('يرجى إدخال السؤال');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ جاري المعالجة...';
            resultDiv.innerHTML = '<div class="loading">⏳ جاري إرسال الطلب...</div>';
            
            try {
                const response = await fetch('/students/ai-tools/debug-rag', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        question: question,
                        word_count: wordCount,
                        format: format
                    })
                });
                
                console.log('RAG Debug Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('RAG Debug HTTP Error:', response.status, errorText);
                    showError(`خطأ HTTP ${response.status}: ${errorText}`);
                    return;
                }
                
                const data = await response.json();
                console.log('RAG Debug Response data:', data);
                
                if (data.ok && data.debug_info) {
                    showSuccess(data);
                } else {
                    showError('استجابة غير متوقعة: ' + JSON.stringify(data));
                }
                
            } catch (error) {
                console.error('RAG Debug Error:', error);
                showError('خطأ في الاتصال: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 إرسال الطلب';
            }
        });
        
        clearBtn.addEventListener('click', () => {
            resultDiv.innerHTML = '';
        });
        
        function showError(message) {
            resultDiv.innerHTML = `
                <div class="result error">
                    <h3>❌ خطأ</h3>
                    <p>${escapeHtml(message)}</p>
                </div>
            `;
        }
        
        function showSuccess(data) {
            const info = data.debug_info;
            const embeddingStatus = info.embedding.created ? 
                '<span class="badge success">✅ تم الإنشاء</span>' : 
                '<span class="badge error">❌ فشل الإنشاء</span>';
            
            const dimensionMatch = info.embedding.dimension === info.embedding.expected_dimension ?
                '<span class="badge success">✅ متطابق</span>' :
                '<span class="badge error">❌ غير متطابق</span>';
            
            resultDiv.innerHTML = `
                <div class="result">
                    <h2>✅ النتائج</h2>
                    
                    <div class="section">
                        <h3>📝 المعلومات الأساسية</h3>
                        <p><strong>السؤال:</strong> ${escapeHtml(info.question)}</p>
                        <p><strong>عدد الكلمات:</strong> ${info.word_count}</p>
                        <p><strong>التنسيق:</strong> ${info.format}</p>
                    </div>
                    
                    <div class="section">
                        <h3>🔢 Embedding</h3>
                        <p>الحالة: ${embeddingStatus}</p>
                        <p>البعد: ${info.embedding.dimension} / ${info.embedding.expected_dimension} ${dimensionMatch}</p>
                        <p>Provider: <span class="badge info">${info.embedding.provider}</span></p>
                        <p>Model: <span class="badge info">${info.embedding.model}</span></p>
                        ${info.embedding.first_10_values ? 
                            `<p><strong>أول 10 قيم:</strong> <code>[${info.embedding.first_10_values.join(', ')}]</code></p>` : 
                            ''}
                    </div>
                    
                    <div class="section">
                        <h3>🔍 نتائج البحث</h3>
                        <p>Semantic Results: <span class="badge info">${info.search.semantic_results_count}</span></p>
                        <p>Keyword Results: <span class="badge info">${info.search.keyword_results_count}</span></p>
                        <p>Hybrid Results: <span class="badge info">${info.search.hybrid_results_count}</span></p>
                        
                        <details>
                            <summary>عرض نتائج Semantic Search</summary>
                            <pre>${JSON.stringify(info.search.semantic_results, null, 2)}</pre>
                        </details>
                        
                        <details>
                            <summary>عرض نتائج Keyword Search</summary>
                            <pre>${JSON.stringify(info.search.keyword_results, null, 2)}</pre>
                        </details>
                        
                        <details>
                            <summary>عرض نتائج Hybrid Search</summary>
                            <pre>${JSON.stringify(info.search.hybrid_results, null, 2)}</pre>
                        </details>
                    </div>
                    
                    <div class="section">
                        <h3>📄 السياق</h3>
                        <p>الطول: <span class="badge info">${info.context.length} حرف</span></p>
                        <details>
                            <summary>معاينة السياق (أول 500 حرف)</summary>
                            <pre>${escapeHtml(info.context.preview)}</pre>
                        </details>
                        <details>
                            <summary>عرض السياق الكامل</summary>
                            <pre>${escapeHtml(info.context.full)}</pre>
                        </details>
                    </div>
                    
                    <div class="section">
                        <h3>📚 الملفات المستخدمة</h3>
                        <ul>
                            ${info.used_files.map(f => `<li>${escapeHtml(f)}</li>`).join('')}
                        </ul>
                    </div>
                    
                    <div class="section">
                        <h3>💬 System Prompt</h3>
                        <pre>${escapeHtml(info.prompts.system_prompt)}</pre>
                    </div>
                    
                    <div class="section">
                        <h3>📋 User Prompt</h3>
                        <p>الطول: <span class="badge info">${info.prompts.user_prompt_length} حرف</span></p>
                        <details>
                            <summary>عرض User Prompt الكامل</summary>
                            <pre>${escapeHtml(info.prompts.user_prompt)}</pre>
                        </details>
                    </div>
                </div>
            `;
        }
        
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
    })();
</script>

