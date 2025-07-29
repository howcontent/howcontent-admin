// DOM Elements
const userMenuBtn = document.getElementById('userMenuBtn');
const userMenuDropdown = document.getElementById('userMenuDropdown');
const notificationBtn = document.getElementById('notificationBtn');
const notificationModal = document.getElementById('notificationModal');
const notificationList = document.getElementById('notificationList');
const modalCloseBtn = document.querySelector('.modal-close');
const logoutBtn = document.getElementById('logoutBtn');
const dateRange = document.getElementById('dateRange');
const searchInput = document.getElementById('searchInput');

// 사용자 메뉴 토글
userMenuBtn.addEventListener('click', () => {
    userMenuDropdown.classList.toggle('active');
});

// 알림 모달 토글
notificationBtn.addEventListener('click', () => {
    notificationModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    loadNotifications();
});

modalCloseBtn.addEventListener('click', () => {
    notificationModal.style.display = 'none';
    document.body.style.overflow = '';
});

window.addEventListener('click', (e) => {
    if (e.target === notificationModal) {
        notificationModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    if (!userMenuBtn.contains(e.target) && !userMenuDropdown.contains(e.target)) {
        userMenuDropdown.classList.remove('active');
    }
});

// 로그아웃
logoutBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    
    try {
        const response = await fetch('/includes/process-logout.php', {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = result.redirect;
        } else {
            window.howContent.showToast(result.message || '로그아웃 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// 날짜 범위 변경
dateRange.addEventListener('change', () => {
    loadDashboardData();
});

// 검색
let searchTimeout;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const query = searchInput.value.trim();
        if (query) {
            searchData(query);
        }
    }, 300);
});

// 대시보드 데이터 로드
async function loadDashboardData() {
    try {
        const response = await fetch(`/includes/get-dashboard-data.php?range=${dateRange.value}`);
        const data = await response.json();
        
        if (data.success) {
            updateDashboard(data);
        } else {
            window.howContent.showToast('데이터 로딩 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 알림 로드
async function loadNotifications() {
    try {
        const response = await fetch('/includes/get-notifications.php');
        const data = await response.json();
        
        if (data.success) {
            updateNotifications(data.notifications);
        } else {
            window.howContent.showToast('알림 로딩 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 검색 실행
async function searchData(query) {
    try {
        const response = await fetch(`/includes/search.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            updateSearchResults(data.results);
        } else {
            window.howContent.showToast('검색 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 대시보드 업데이트
function updateDashboard(data) {
    // 요약 정보 업데이트
    document.getElementById('newEstimateCount').textContent = data.newEstimates;
    document.getElementById('activeTaskCount').textContent = data.activeTasks;
    document.getElementById('newClientCount').textContent = data.newClients;
    document.getElementById('totalRevenue').textContent = formatPrice(data.revenue);
    
    // 견적 뱃지 업데이트
    document.getElementById('estimateCount').textContent = data.pendingEstimates;
    document.getElementById('taskCount').textContent = data.pendingTasks;
    
    // 최근 견적 테이블 업데이트
    const estimatesTable = document.getElementById('recentEstimatesTable').querySelector('tbody');
    estimatesTable.innerHTML = data.recentEstimates.map(estimate => `
        <tr>
            <td>${estimate.code}</td>
            <td>${estimate.customerName}</td>
            <td>${getWorkTypeName(estimate.workType)}</td>
            <td>${formatDate(estimate.requestDate)}</td>
            <td><span class="status-badge ${estimate.status}">${getStatusName(estimate.status)}</span></td>
        </tr>
    `).join('');
    
    // 진행중인 작업 테이블 업데이트
    const tasksTable = document.getElementById('activeTasksTable').querySelector('tbody');
    tasksTable.innerHTML = data.activeTasks.map(task => `
        <tr>
            <td>${task.title}</td>
            <td>${task.assignedTo}</td>
            <td>${formatDate(task.startDate)}</td>
            <td>${formatDate(task.endDate)}</td>
            <td><span class="status-badge ${task.status}">${getStatusName(task.status)}</span></td>
        </tr>
    `).join('');
}

// 알림 목록 업데이트
function updateNotifications(notifications) {
    notificationList.innerHTML = notifications.map(notification => `
        <div class="notification-item ${notification.isRead ? '' : 'unread'}">
            <div class="notification-content">
                <h3>${notification.title}</h3>
                <p>${notification.content}</p>
                <span class="notification-time">${formatDate(notification.createdAt)}</span>
            </div>
        </div>
    `).join('');
    
    // 읽지 않은 알림 수 업데이트
    const unreadCount = notifications.filter(n => !n.isRead).length;
    document.getElementById('notificationCount').textContent = unreadCount;
}

// 검색 결과 업데이트
function updateSearchResults(results) {
    // 검색 결과 표시 로직 구현
}

// 작업 종류 이름 변환
function getWorkTypeName(type) {
    const types = {
        'product_registration': '상품 등록',
        'detail_page': '상세 페이지 제작',
        'marketing': '마케팅 운영',
        'other': '기타'
    };
    return types[type] || type;
}

// 상태 이름 변환
function getStatusName(status) {
    const statuses = {
        'requested': '요청됨',
        'in_progress': '진행중',
        'feedback': '피드백',
        'completed': '완료',
        'pending': '대기중',
        'review': '검토중'
    };
    return statuses[status] || status;
}

// 날짜 포맷
function formatDate(date) {
    return new Date(date).toLocaleDateString('ko-KR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// 가격 포맷
function formatPrice(price) {
    return new Intl.NumberFormat('ko-KR', {
        style: 'currency',
        currency: 'KRW'
    }).format(price);
}

// 초기 데이터 로드
loadDashboardData(); 