document.addEventListener('DOMContentLoaded', () => {
    // 모바일 메뉴 토글 버튼 추가
    const nav = document.querySelector('.nav-container');
    const mobileMenuBtn = document.createElement('button');
    mobileMenuBtn.className = 'mobile-menu-btn md:hidden';
    mobileMenuBtn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    `;
    nav.appendChild(mobileMenuBtn);

    // 모바일 메뉴 토글 이벤트
    const menu = document.querySelector('.nav-menu');
    mobileMenuBtn.addEventListener('click', () => {
        menu.classList.toggle('show');
        if (menu.classList.contains('show')) {
            menu.style.display = 'flex';
            menu.style.flexDirection = 'column';
            menu.style.position = 'absolute';
            menu.style.top = '100%';
            menu.style.left = '0';
            menu.style.right = '0';
            menu.style.backgroundColor = 'white';
            menu.style.padding = '1rem';
            menu.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        } else {
            menu.style.display = '';
        }
    });

    // 화면 크기 변경 시 모바일 메뉴 상태 초기화
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            menu.classList.remove('show');
            menu.style.display = '';
        }
    });

    // 스크롤 시 헤더 그림자 효과
    let lastScroll = 0;
    const header = document.querySelector('.main-header');
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > lastScroll) {
            // 아래로 스크롤
            header.style.transform = 'translateY(-100%)';
        } else {
            // 위로 스크롤
            header.style.transform = 'translateY(0)';
            header.style.boxShadow = currentScroll > 0 ? '0 2px 4px rgba(0,0,0,0.1)' : 'none';
        }
        
        lastScroll = currentScroll;
    });
});

// Smooth Scroll for Navigation Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            // Close mobile menu if open
            document.querySelector('.nav-menu').classList.remove('active');
            mobileMenuBtn.classList.remove('active');
        }
    });
});

// Stats Animation
const stats = document.querySelectorAll('.stat-number');
const animateStats = () => {
    stats.forEach(stat => {
        const target = parseInt(stat.textContent);
        let current = 0;
        const increment = target / 50; // Adjust speed
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                clearInterval(timer);
                current = target;
            }
            stat.textContent = Math.round(current).toLocaleString();
        }, 20);
    });
};

// Intersection Observer for Stats Animation
const statsSection = document.querySelector('.stats');
if (statsSection) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateStats();
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    observer.observe(statsSection);
}

// Form Validation Helper
const validateForm = (form) => {
    const inputs = form.querySelectorAll('input, textarea, select');
    let isValid = true;
    
    inputs.forEach(input => {
        if (input.hasAttribute('required') && !input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
        
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                isValid = false;
                input.classList.add('error');
            }
        }
    });
    
    return isValid;
};

// Toast Message Helper
const showToast = (message, type = 'info') => {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

// Export Helper Functions
window.howContent = {
    validateForm,
    showToast
}; 