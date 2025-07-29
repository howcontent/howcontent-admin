// DOM Elements
const form = document.getElementById('estimateForm');
const modal = document.getElementById('privacyModal');
const privacyDetailBtn = document.querySelector('.privacy-detail-btn');
const modalCloseBtn = document.querySelector('.modal-close');
const phoneInput = document.getElementById('phone');
const deadlineInput = document.getElementById('deadline');

// Set minimum date for deadline
const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(tomorrow.getDate() + 1);
deadlineInput.min = tomorrow.toISOString().split('T')[0];

// Modal Controls
privacyDetailBtn.addEventListener('click', () => {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
});

modalCloseBtn.addEventListener('click', () => {
    modal.style.display = 'none';
    document.body.style.overflow = '';
});

window.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Phone number formatting
phoneInput.addEventListener('input', (e) => {
    let value = e.target.value.replace(/[^0-9]/g, '');
    
    if (value.length > 11) {
        value = value.slice(0, 11);
    }
    
    if (value.length > 3 && value.length <= 7) {
        value = value.slice(0, 3) + '-' + value.slice(3);
    } else if (value.length > 7) {
        value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7);
    }
    
    e.target.value = value;
});

// Form submission
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!window.howContent.validateForm(form)) {
        window.howContent.showToast('필수 항목을 모두 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/includes/process-estimate.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.howContent.showToast('견적 요청이 성공적으로 접수되었습니다.', 'success');
            form.reset();
            
            // 견적서 확인 페이지로 리다이렉트
            if (result.estimateCode) {
                setTimeout(() => {
                    window.location.href = `/estimate/view.html?code=${result.estimateCode}`;
                }, 2000);
            }
        } else {
            window.howContent.showToast(result.message || '견적 요청 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// Field validation
const workTypeSelect = document.getElementById('workType');
const budgetInput = document.getElementById('budget');
const emailInput = document.getElementById('email');

workTypeSelect.addEventListener('change', () => {
    if (!workTypeSelect.value) {
        workTypeSelect.classList.add('error');
    } else {
        workTypeSelect.classList.remove('error');
    }
});

budgetInput.addEventListener('input', () => {
    const value = parseInt(budgetInput.value);
    if (isNaN(value) || value <= 0) {
        budgetInput.classList.add('error');
    } else {
        budgetInput.classList.remove('error');
    }
});

emailInput.addEventListener('input', () => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailInput.value)) {
        emailInput.classList.add('error');
    } else {
        emailInput.classList.remove('error');
    }
}); 