<?php
/**
 * Transactions Table Partial
 */
?>
<div class="rewards-transactions" data-ref="transactionsContainer">
    <div class="rewards-loading" style="display: none;">
        <i class="fas fa-spinner fa-spin"></i> جاري التحميل...
    </div>
    <div class="rewards-transactions-list" id="transactionsList">
        <!-- سيتم ملؤها عبر JavaScript -->
    </div>
</div>

<style>
.rewards-transactions {
    min-height: 200px;
}

.rewards-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.rewards-transactions-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rewards-transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border-right: 4px solid #4caf50;
}

.rewards-transaction-item.negative {
    border-right-color: #f44336;
}

.rewards-transaction-details {
    flex: 1;
}

.rewards-transaction-type {
    font-weight: bold;
    margin-bottom: 5px;
}

.rewards-transaction-description {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.rewards-transaction-date {
    font-size: 12px;
    color: #999;
}

.rewards-transaction-points {
    font-size: 20px;
    font-weight: bold;
    color: #4caf50;
}

.rewards-transaction-item.negative .rewards-transaction-points {
    color: #f44336;
}

.rewards-transactions-empty {
    text-align: center;
    padding: 40px;
    color: #999;
}
</style>

<script>
// تحميل المعاملات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadTransactions();
});

function loadTransactions() {
    const container = document.querySelector('[data-ref="transactionsContainer"]');
    const loading = container.querySelector('.rewards-loading');
    const list = document.getElementById('transactionsList');

    loading.style.display = 'block';
    list.innerHTML = '';

    fetch('/rewards/transactions?limit=50')
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            
            if (data.success && data.transactions && data.transactions.length > 0) {
                data.transactions.forEach(transaction => {
                    const item = createTransactionItem(transaction);
                    list.appendChild(item);
                });
            } else {
                list.innerHTML = '<div class="rewards-transactions-empty">لا توجد معاملات حتى الآن</div>';
            }
        })
        .catch(error => {
            loading.style.display = 'none';
            list.innerHTML = '<div class="rewards-transactions-empty">حدث خطأ أثناء تحميل المعاملات</div>';
            console.error('Error loading transactions:', error);
        });
}

function createTransactionItem(transaction) {
    const item = document.createElement('div');
    item.className = 'rewards-transaction-item';
    if (transaction.points < 0) {
        item.classList.add('negative');
    }

    const typeLabels = {
        'friend_invite': 'دعوة صديق',
        'consecutive_days': 'أيام متتالية',
    };

    const typeLabel = typeLabels[transaction.reward_type] || transaction.reward_type;
    const date = new Date(transaction.created_at).toLocaleDateString('ar-SA');

    item.innerHTML = `
        <div class="rewards-transaction-details">
            <div class="rewards-transaction-type">${typeLabel}</div>
            ${transaction.description ? `<div class="rewards-transaction-description">${transaction.description}</div>` : ''}
            <div class="rewards-transaction-date">${date}</div>
        </div>
        <div class="rewards-transaction-points">
            ${transaction.points > 0 ? '+' : ''}${transaction.points}
        </div>
    `;

    return item;
}
</script>

