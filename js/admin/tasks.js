// DOM Elements
const createTaskBtn = document.getElementById('createTaskBtn');
const taskModal = document.getElementById('taskModal');
const taskForm = document.getElementById('taskForm');
const modalTitle = document.getElementById('modalTitle');
const modalCloseBtn = document.querySelector('#taskModal .modal-close');
const generateAiBtn = document.getElementById('generateAiBtn');
const statusFilter = document.getElementById('statusFilter');
const assigneeFilter = document.getElementById('assigneeFilter');
const dateFilter = document.getElementById('dateFilter');
const searchInput = document.getElementById('searchInput');
const kanbanBoard = document.getElementById('kanbanBoard');

const taskDetailModal = document.getElementById('taskDetailModal');
const detailModalCloseBtn = document.querySelector('#taskDetailModal .modal-close');
const commentForm = document.getElementById('commentForm');

let currentTaskId = null;

// 모달 컨트롤
createTaskBtn.addEventListener('click', () => {
    modalTitle.textContent = '작업 생성';
    taskForm.reset();
    taskModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // 최소 시작일 설정
    const today = new Date();
    document.getElementById('startDate').min = today.toISOString().split('T')[0];
});

modalCloseBtn.addEventListener('click', () => {
    taskModal.style.display = 'none';
    document.body.style.overflow = '';
});

detailModalCloseBtn.addEventListener('click', () => {
    taskDetailModal.style.display = 'none';
    document.body.style.overflow = '';
});

window.addEventListener('click', (e) => {
    if (e.target === taskModal) {
        taskModal.style.display = 'none';
        document.body.style.overflow = '';
    }
    if (e.target === taskDetailModal) {
        taskDetailModal.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// 필터 변경 이벤트
statusFilter.addEventListener('change', loadTasks);
assigneeFilter.addEventListener('change', loadTasks);
dateFilter.addEventListener('change', loadTasks);

// 검색
let searchTimeout;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadTasks, 300);
});

// 작업 목록 로드
async function loadTasks() {
    try {
        const params = new URLSearchParams({
            status: statusFilter.value,
            assignee: assigneeFilter.value,
            dateRange: dateFilter.value,
            search: searchInput.value.trim()
        });

        const response = await fetch(`/includes/get-tasks.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            updateKanbanBoard(data.tasks);
            updateTaskCounts(data.counts);
            document.getElementById('taskCount').textContent = data.pendingCount;
        } else {
            window.howContent.showToast('작업 목록을 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 칸반 보드 업데이트
function updateKanbanBoard(tasks) {
    const columns = document.querySelectorAll('.kanban-column');
    
    columns.forEach(column => {
        const status = column.dataset.status;
        const taskList = column.querySelector('.task-list');
        const statusTasks = tasks.filter(task => task.status === status);
        
        taskList.innerHTML = statusTasks.map(task => `
            <div class="task-card" draggable="true" data-task-id="${task.id}" onclick="viewTask(${task.id}, event)">
                <h3>${task.title}</h3>
                <div class="task-meta">
                    <div class="task-assignee">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                        </svg>
                        ${task.assignedTo}
                    </div>
                    <div class="task-due ${isOverdue(task.endDate) ? 'overdue' : ''}">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        ${formatDate(task.endDate)}
                    </div>
                </div>
            </div>
        `).join('');
    });

    // 드래그 앤 드롭 이벤트 설정
    setupDragAndDrop();
}

// 작업 수 업데이트
function updateTaskCounts(counts) {
    const columns = document.querySelectorAll('.kanban-column');
    
    columns.forEach(column => {
        const status = column.dataset.status;
        const countElement = column.querySelector('.task-count');
        countElement.textContent = counts[status] || 0;
    });
}

// 작업 상세 조회
async function viewTask(taskId, event) {
    if (event) {
        event.stopPropagation();
    }
    
    try {
        const response = await fetch(`/includes/get-task.php?id=${taskId}`);
        const data = await response.json();
        
        if (data.success) {
            currentTaskId = taskId;
            updateTaskDetail(data.task);
            loadComments(taskId);
            loadTaskHistory(taskId);
            taskDetailModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            window.howContent.showToast('작업 정보를 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 작업 상세 정보 업데이트
function updateTaskDetail(task) {
    document.getElementById('detailTaskTitle').textContent = task.title;
    document.getElementById('detailAssignedTo').textContent = task.assignedTo;
    document.getElementById('detailStartDate').textContent = formatDate(task.startDate);
    document.getElementById('detailEndDate').textContent = formatDate(task.endDate);
    document.getElementById('detailStatus').textContent = getStatusName(task.status);
    document.getElementById('detailDescription').textContent = task.description || '-';
}

// 댓글 목록 로드
async function loadComments(taskId) {
    try {
        const response = await fetch(`/includes/get-comments.php?taskId=${taskId}`);
        const data = await response.json();
        
        if (data.success) {
            updateComments(data.comments);
        } else {
            window.howContent.showToast('댓글을 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 댓글 목록 업데이트
function updateComments(comments) {
    const commentList = document.getElementById('commentList');
    commentList.innerHTML = comments.map(comment => `
        <div class="comment-item">
            <div class="comment-header">
                <span class="comment-author">${comment.userName}</span>
                <span class="comment-time">${formatDate(comment.createdAt)}</span>
            </div>
            <div class="comment-content">${comment.content}</div>
        </div>
    `).join('');
}

// 작업 이력 로드
async function loadTaskHistory(taskId) {
    try {
        const response = await fetch(`/includes/get-task-history.php?taskId=${taskId}`);
        const data = await response.json();
        
        if (data.success) {
            updateTaskHistory(data.history);
        } else {
            window.howContent.showToast('작업 이력을 불러오는 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}

// 작업 이력 업데이트
function updateTaskHistory(history) {
    const historyList = document.getElementById('historyList');
    historyList.innerHTML = history.map(item => `
        <div class="history-item">
            <div class="history-time">${formatDate(item.createdAt)}</div>
            <div class="history-content">${item.description}</div>
        </div>
    `).join('');
}

// 댓글 작성
commentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!currentTaskId) return;
    
    const formData = new FormData(commentForm);
    formData.append('taskId', currentTaskId);
    
    try {
        const response = await fetch('/includes/add-comment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.howContent.showToast('댓글이 작성되었습니다.', 'success');
            commentForm.reset();
            loadComments(currentTaskId);
        } else {
            window.howContent.showToast(result.message || '댓글 작성 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// AI 요약 생성
generateAiBtn.addEventListener('click', async () => {
    const description = document.getElementById('description').value;
    
    if (!description) {
        window.howContent.showToast('작업 설명을 먼저 입력해주세요.', 'error');
        return;
    }
    
    try {
        const response = await fetch('/includes/generate-ai-summary.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ description })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('aiSummary').value = data.summary;
        } else {
            window.howContent.showToast('AI 요약 생성 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// 작업 폼 제출
taskForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!window.howContent.validateForm(taskForm)) {
        window.howContent.showToast('필수 항목을 모두 입력해주세요.', 'error');
        return;
    }
    
    const formData = new FormData(taskForm);
    const isEdit = modalTitle.textContent === '작업 수정';
    
    try {
        const response = await fetch('/includes/process-task.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.howContent.showToast(
                isEdit ? '작업이 수정되었습니다.' : '작업이 생성되었습니다.',
                'success'
            );
            taskModal.style.display = 'none';
            document.body.style.overflow = '';
            loadTasks();
        } else {
            window.howContent.showToast(result.message || '작업 처리 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
});

// 드래그 앤 드롭 설정
function setupDragAndDrop() {
    const taskCards = document.querySelectorAll('.task-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    taskCards.forEach(card => {
        card.addEventListener('dragstart', () => {
            card.classList.add('dragging');
        });
        
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', e => {
            e.preventDefault();
            column.classList.add('drag-over');
        });
        
        column.addEventListener('dragleave', () => {
            column.classList.remove('drag-over');
        });
        
        column.addEventListener('drop', async e => {
            e.preventDefault();
            column.classList.remove('drag-over');
            
            const card = document.querySelector('.task-card.dragging');
            if (!card) return;
            
            const taskId = card.dataset.taskId;
            const newStatus = column.dataset.status;
            
            try {
                const response = await fetch('/includes/update-task-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        taskId,
                        status: newStatus
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadTasks();
                } else {
                    window.howContent.showToast('작업 상태 변경 중 오류가 발생했습니다.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
            }
        });
    });
}

// 마감일 초과 체크
function isOverdue(date) {
    return new Date(date) < new Date();
}

// 상태 이름 변환
function getStatusName(status) {
    const statuses = {
        'pending': '대기중',
        'in_progress': '진행중',
        'review': '검토중',
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

// 초기 데이터 로드
loadTasks(); 