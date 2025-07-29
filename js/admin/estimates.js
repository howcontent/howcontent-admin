// DOM Elements
const createEstimateBtn = document.getElementById('createEstimateBtn');
const estimateModal = document.getElementById('estimateModal');
const estimateForm = document.getElementById('estimateForm');
const modalTitle = document.getElementById('modalTitle');
const modalCloseBtn = document.querySelector('#estimateModal .modal-close');
const generateAiBtn = document.getElementById('generateAiBtn');
const statusFilter = document.getElementById('statusFilter');
const workTypeFilter = document.getElementById('workTypeFilter');
const dateFilter = document.getElementById('dateFilter');
const searchInput = document.getElementById('searchInput');

// 가격 입력 필드들
const basePriceInput = document.getElementById('basePrice');
const designPriceInput = document.getElementById('designPrice');
const optionPriceInput = document.getElementById('optionPrice');
const totalPriceInput = document.getElementById('totalPrice');

// 현재 페이지와 페이지당 항목 수
let currentPage = 1;
const itemsPerPage = 10;

// 모달 컨트롤
createEstimateBtn.addEventListener('click', () => {
    modalTitle.textContent = '견적 작성';
    estimateForm.reset();
    estimateModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // 최소 마감일 설정
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('deadline').min = tomorrow.toISOString().split('T')[0];
});

modalCloseBtn.addEventListener('click', () => {
    estimateModal.style.display = 'none';
    document.body.style.overflow = '';
});

window.addEventListener('click', (e) => {
    if (e.target === estimateModal) {
        estimateModal.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// 필터 변경 이벤트
statusFilter.addEventListener('change', loadEstimates);
workTypeFilter.addEventListener('change', loadEstimates);
dateFilter.addEventListener('change', loadEstimates);

// 검색
let searchTimeout;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        loadEstimates();
    }, 300);
});

// 견적 목록 로드
async function loadEstimates() {
    try {
        const params = new URLSearchParams({
            page: currentPage,
            limit: itemsPerPage,
            status: statusFilter.value,
            workType: workTypeFilter.value,
            dateRange: dateFilter.value,
            search: searchInput.value.trim()
        });

        const response = await fetch(`/includes/get-estimates.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            updateEstimatesTable(data.estimates);
            updatePagination(data.total);
            document.getElementById('estimateCount').textContent = data.pendingCount;
        } else {
            window.howContent.showToast('견적 목록을 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 견적 테이블 업데이트
function updateEstimatesTable(estimates) {
    const tbody = document.querySelector('#estimatesTable tbody');
    tbody.innerHTML = estimates.map(estimate => `
        <tr>
            <td>${estimate.code}</td>
            <td>${estimate.customerName}</td>
            <td>${getWorkTypeName(estimate.workType)}</td>
            <td>${formatPrice(estimate.budget)}</td>
            <td>${formatDate(estimate.deadline)}</td>
            <td><span class="status-badge ${estimate.status}">${getStatusName(estimate.status)}</span></td>
            <td>${formatDate(estimate.createdAt)}</td>
            <td>
                <div class="table-actions">
                    <button class="action-button" onclick="viewEstimate('${estimate.code}')">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                    <button class="action-button" onclick="editEstimate('${estimate.code}')">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                    </button>
                    <button class="action-button delete" onclick="deleteEstimate('${estimate.code}')">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// 페이지네이션 업데이트
function updatePagination(total) {
    const totalPages = Math.ceil(total / itemsPerPage);
    const pagination = document.getElementById('estimatesPagination');
    
    let html = '';
    
    if (totalPages > 1) {
        html += `
            <button class="page-button" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(1)">
                <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                    <path fill-rule="evenodd" d="M15.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 010 1.414zm-6 0a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 1.414L5.414 10l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        `;
        
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            html += `
                <button class="page-button ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                    ${i}
                </button>
            `;
        }
        
        html += `
            <button class="page-button" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${totalPages})">
                <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                    <path fill-rule="evenodd" d="M4.293 15.707a1 1 0 001.414 0l5-5a1 1 0 000-1.414l-5-5a1 1 0 00-1.414 1.414L8.586 10 4.293 14.293a1 1 0 000 1.414zm6 0a1 1 0 001.414 0l5-5a1 1 0 000-1.414l-5-5a1 1 0 00-1.414 1.414L14.586 10l-4.293 4.293a1 1 0 000 1.414z" clip-rule="evenodd"></path>
                </svg>
            </button>
        `;
    }
    
    pagination.innerHTML = html;
}

// 페이지 변경
function changePage(page) {
    currentPage = page;
    loadEstimates();
}

// 견적서 조회
async function viewEstimate(code) {
    try {
        const response = await fetch(`/includes/get-estimate.php?code=${code}`);
        const data = await response.json();
        
        if (data.success) {
            window.location.href = `/estimate/view.html?code=${code}`;
        } else {
            window.howContent.showToast('견적서를 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 견적서 수정
async function editEstimate(code) {
    try {
        const response = await fetch(`/includes/get-estimate.php?code=${code}`);
        const data = await response.json();
        
        if (data.success) {
            modalTitle.textContent = '견적 수정';
            fillEstimateForm(data.estimate);
            estimateModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            window.howContent.showToast('견적서를 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 견적서 삭제
async function deleteEstimate(code) {
    if (!confirm('정말 이 견적서를 삭제하시겠습니까?')) {
        return;
    }
    
    try {
        const response = await fetch('/includes/delete-estimate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ code })
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.howContent.showToast('견적서가 삭제되었습니다.', 'success');
            loadEstimates();
        } else {
            window.howContent.showToast(data.message || '견적서 삭제 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 견적 폼 채우기
function fillEstimateForm(estimate) {
    for (const [key, value] of Object.entries(estimate)) {
        const input = document.getElementById(key);
        if (input) {
            if (input.type === 'radio') {
                const radio = document.querySelector(`input[name="${key}"][value="${value}"]`);
                if (radio) radio.checked = true;
            } else {
                input.value = value;
            }
        }
    }
}

// AI 문장 생성
generateAiBtn.addEventListener('click', async () => {
    const workType = document.getElementById('workType').value;
    const budget = document.getElementById('budget').value;
    const needDesign = document.querySelector('input[name="needDesign"]:checked')?.value;
    
    if (!workType || !budget || !needDesign) {
        window.howContent.showToast('작업 종류, 예산, 디자인 필요 여부를 먼저 입력해주세요.', 'error');
        return;
    }
    
    try {
        const response = await fetch('/includes/generate-ai-suggestion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                workType,
                budget,
                needDesign
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('aiSuggestion').value = data.suggestion;
        } else {
            window.howContent.showToast('AI 문장 생성 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// 견적 폼 제출
estimateForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!window.howContent.validateForm(estimateForm)) {
        window.howContent.showToast('필수 항목을 모두 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData(estimateForm);
    const isEdit = modalTitle.textContent === '견적 수정';
    
    try {
        const response = await fetch('/includes/process-estimate.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.howContent.showToast(
                isEdit ? '견적서가 수정되었습니다.' : '견적서가 작성되었습니다.',
                'success'
            );
            estimateModal.style.display = 'none';
            document.body.style.overflow = '';
            loadEstimates();
        } else {
            window.howContent.showToast(result.message || '견적서 처리 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// 가격 자동 계산
[basePriceInput, designPriceInput, optionPriceInput].forEach(input => {
    input.addEventListener('input', calculateTotal);
});

function calculateTotal() {
    const basePrice = parseInt(basePriceInput.value) || 0;
    const designPrice = parseInt(designPriceInput.value) || 0;
    const optionPrice = parseInt(optionPriceInput.value) || 0;
    totalPriceInput.value = basePrice + designPrice + optionPrice;
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
        'completed': '완료'
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
loadEstimates(); 