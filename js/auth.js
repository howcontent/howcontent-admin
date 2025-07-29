// DOM Elements
const form = document.querySelector('form');
const togglePasswordBtns = document.querySelectorAll('.toggle-password');
const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('passwordConfirm');
const emailInput = document.getElementById('email');
const phoneInput = document.getElementById('phone');
const businessNumberInput = document.getElementById('businessNumber');
const termsDetailBtn = document.querySelector('.terms-detail-btn');
const termsModal = document.getElementById('termsModal');
const modalCloseBtn = document.querySelector('.modal-close');

// Toggle password visibility
togglePasswordBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.previousElementSibling;
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        // Update icon
        const path = btn.querySelector('path');
        const circle = btn.querySelector('circle');
        
        if (type === 'text') {
            path.setAttribute('d', 'M23.5 12c0 5.5-4.5 10-10 10S3.5 17.5 3.5 12 8 2 13.5 2s10 4.5 10 10zm-10 6a6 6 0 1 0 0-12 6 6 0 0 0 0 12z');
            circle.setAttribute('r', '2');
        } else {
            path.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            circle.setAttribute('r', '3');
        }
    });
});

// Form validation
if (emailInput) {
    emailInput.addEventListener('input', () => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            emailInput.classList.add('error');
            showError(emailInput, '유효한 이메일 주소를 입력해주세요.');
        } else {
            emailInput.classList.remove('error');
            clearError(emailInput);
        }
    });
}

if (passwordInput) {
    passwordInput.addEventListener('input', () => {
        if (passwordInput.value.length < 8) {
            passwordInput.classList.add('error');
            showError(passwordInput, '비밀번호는 8자 이상이어야 합니다.');
        } else {
            passwordInput.classList.remove('error');
            clearError(passwordInput);
        }
    });
}

if (passwordConfirmInput) {
    passwordConfirmInput.addEventListener('input', () => {
        if (passwordConfirmInput.value !== passwordInput.value) {
            passwordConfirmInput.classList.add('error');
            showError(passwordConfirmInput, '비밀번호가 일치하지 않습니다.');
        } else {
            passwordConfirmInput.classList.remove('error');
            clearError(passwordConfirmInput);
        }
    });
}

// Phone number formatting
if (phoneInput) {
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
}

// Business number formatting
if (businessNumberInput) {
    businessNumberInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/[^0-9]/g, '');
        
        if (value.length > 10) {
            value = value.slice(0, 10);
        }
        
        if (value.length > 3 && value.length <= 5) {
            value = value.slice(0, 3) + '-' + value.slice(3);
        } else if (value.length > 5) {
            value = value.slice(0, 3) + '-' + value.slice(3, 5) + '-' + value.slice(5);
        }
        
        e.target.value = value;
    });
}

// Show error message
function showError(input, message) {
    const formGroup = input.closest('.form-group');
    let errorMessage = formGroup.querySelector('.error-message');
    
    if (!errorMessage) {
        errorMessage = document.createElement('div');
        errorMessage.className = 'error-message';
        formGroup.appendChild(errorMessage);
    }
    
    errorMessage.textContent = message;
}

// Clear error message
function clearError(input) {
    const formGroup = input.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Terms modal
if (termsDetailBtn && termsModal) {
    termsDetailBtn.addEventListener('click', () => {
        termsModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });

    modalCloseBtn.addEventListener('click', () => {
        termsModal.style.display = 'none';
        document.body.style.overflow = '';
    });

    window.addEventListener('click', (e) => {
        if (e.target === termsModal) {
            termsModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
}

// Form submission
if (form) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!window.howContent.validateForm(form)) {
            window.howContent.showToast('필수 항목을 모두 입력해주세요.', 'error');
            return;
        }
        
        // 회원가입 폼인 경우 추가 검증
        if (form.id === 'signupForm') {
            if (passwordInput.value !== passwordConfirmInput.value) {
                window.howContent.showToast('비밀번호가 일치하지 않습니다.', 'error');
                return;
            }
        }
        
        const formData = new FormData(form);
        const action = form.getAttribute('action');
        
        try {
            const response = await fetch(action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.howContent.showToast(
                    form.id === 'loginForm' ? '로그인되었습니다.' : '회원가입이 완료되었습니다.',
                    'success'
                );
                
                // 리다이렉트
                setTimeout(() => {
                    window.location.href = result.redirect || (form.id === 'loginForm' ? '/admin/' : '/login.html');
                }, 1000);
            } else {
                window.howContent.showToast(result.message || '처리 중 오류가 발생했습니다.', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
        }
    });
} 