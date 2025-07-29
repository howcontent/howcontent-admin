// 전역 변수
let clients = [];
let currentClientId = null;

// 페이지 로드 시 실행
document.addEventListener('DOMContentLoaded', () => {
    loadClients();
    setupEventListeners();
});

// 이벤트 리스너 설정
function setupEventListeners() {
    // 검색 입력 이벤트
    document.getElementById('searchInput').addEventListener('input', filterClients);
    
    // 상태 필터 변경 이벤트
    document.getElementById('statusFilter').addEventListener('change', filterClients);
    
    // 클라이언트 폼 제출 이벤트
    document.getElementById('clientForm').addEventListener('submit', handleFormSubmit);
}

// 클라이언트 목록 로드
async function loadClients() {
    try {
        const response = await fetch('../api/clients.php');
        if (!response.ok) throw new Error('클라이언트 데이터를 불러오는데 실패했습니다.');
        
        clients = await response.json();
        renderClients(clients);
    } catch (error) {
        showError('데이터 로드 실패', error.message);
    }
}

// 클라이언트 목록 렌더링
function renderClients(clientsToRender) {
    const tbody = document.getElementById('clientTableBody');
    tbody.innerHTML = '';

    clientsToRender.forEach(client => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${client.company_name}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${client.contact_name}</div>
                <div class="text-sm text-gray-500">${client.email}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${client.phone}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                    ${client.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                    ${client.status === 'active' ? '활성' : '비활성'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="editClient(${client.id})" 
                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                    수정
                </button>
                <button onclick="deleteClient(${client.id})" 
                        class="text-red-600 hover:text-red-900">
                    삭제
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// 클라이언트 필터링
function filterClients() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;

    const filteredClients = clients.filter(client => {
        const matchesSearch = 
            client.company_name.toLowerCase().includes(searchTerm) ||
            client.contact_name.toLowerCase().includes(searchTerm) ||
            client.email.toLowerCase().includes(searchTerm);
            
        const matchesStatus = !statusFilter || client.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    renderClients(filteredClients);
}

// 클라이언트 추가 모달 열기
function openAddClientModal() {
    currentClientId = null;
    document.getElementById('modalTitle').textContent = '새 클라이언트 추가';
    document.getElementById('clientForm').reset();
    document.getElementById('clientModal').classList.remove('hidden');
}

// 클라이언트 수정 모달 열기
async function editClient(clientId) {
    try {
        const response = await fetch(`../api/clients.php?id=${clientId}`);
        if (!response.ok) throw new Error('클라이언트 정보를 불러오는데 실패했습니다.');
        
        const client = await response.json();
        currentClientId = clientId;
        
        document.getElementById('modalTitle').textContent = '클라이언트 정보 수정';
        document.getElementById('companyName').value = client.company_name;
        document.getElementById('contactName').value = client.contact_name;
        document.getElementById('email').value = client.email;
        document.getElementById('phone').value = client.phone;
        document.getElementById('address').value = client.address || '';
        document.getElementById('notes').value = client.notes || '';
        
        document.getElementById('clientModal').classList.remove('hidden');
    } catch (error) {
        showError('데이터 로드 실패', error.message);
    }
}

// 모달 닫기
function closeClientModal() {
    document.getElementById('clientModal').classList.add('hidden');
    document.getElementById('clientForm').reset();
    currentClientId = null;
}

// 폼 제출 처리
async function handleFormSubmit(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const clientData = {
        company_name: formData.get('companyName'),
        contact_name: formData.get('contactName'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        address: formData.get('address'),
        notes: formData.get('notes')
    };

    try {
        const url = '../api/clients.php';
        const method = currentClientId ? 'PUT' : 'POST';
        const body = currentClientId 
            ? JSON.stringify({ ...clientData, id: currentClientId })
            : JSON.stringify(clientData);

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: body
        });

        if (!response.ok) throw new Error('클라이언트 정보 저장에 실패했습니다.');

        await loadClients();
        closeClientModal();
        showSuccess('저장 완료', '클라이언트 정보가 성공적으로 저장되었습니다.');
    } catch (error) {
        showError('저장 실패', error.message);
    }
}

// 클라이언트 삭제
async function deleteClient(clientId) {
    try {
        const result = await Swal.fire({
            title: '클라이언트 삭제',
            text: '정말로 이 클라이언트를 삭제하시겠습니까?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '삭제',
            cancelButtonText: '취소'
        });

        if (result.isConfirmed) {
            const response = await fetch(`../api/clients.php?id=${clientId}`, {
                method: 'DELETE'
            });

            if (!response.ok) throw new Error('클라이언트 삭제에 실패했습니다.');

            await loadClients();
            showSuccess('삭제 완료', '클라이언트가 성공적으로 삭제되었습니다.');
        }
    } catch (error) {
        showError('삭제 실패', error.message);
    }
}

// 성공 메시지 표시
function showSuccess(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
    });
}

// 에러 메시지 표시
function showError(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'error'
    });
} 