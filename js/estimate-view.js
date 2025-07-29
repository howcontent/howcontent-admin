// DOM Elements
const estimateCode = document.getElementById('estimateCode');
const requestDate = document.getElementById('requestDate');
const customerName = document.getElementById('customerName');
const companyName = document.getElementById('companyName');
const workType = document.getElementById('workType');
const budget = document.getElementById('budget');
const deadline = document.getElementById('deadline');
const requirements = document.getElementById('requirements');
const basePrice = document.getElementById('basePrice');
const designPrice = document.getElementById('designPrice');
const optionPrice = document.getElementById('optionPrice');
const totalPrice = document.getElementById('totalPrice');
const notes = document.getElementById('notes');

const downloadPdfBtn = document.getElementById('downloadPdf');
const requestModificationBtn = document.getElementById('requestModification');
const modificationModal = document.getElementById('modificationModal');
const modificationForm = document.getElementById('modificationForm');
const modalCloseBtn = document.querySelector('.modal-close');

// Get estimate code from URL
const urlParams = new URLSearchParams(window.location.search);
const code = urlParams.get('code');

if (!code) {
    window.location.href = '/';
} else {
    loadEstimate(code);
}

// Load estimate data
async function loadEstimate(code) {
    try {
        const response = await fetch(`/includes/get-estimate.php?code=${code}`);
        const data = await response.json();
        
        if (data.success) {
            populateEstimate(data.estimate);
        } else {
            window.howContent.showToast('견적서를 찾을 수 없습니다.', 'error');
            setTimeout(() => {
                window.location.href = '/';
            }, 2000);
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('견적서 로딩 중 오류가 발생했습니다.', 'error');
    }
}

// Populate estimate data
function populateEstimate(data) {
    estimateCode.textContent = data.code;
    requestDate.textContent = new Date(data.requestDate).toLocaleDateString();
    customerName.textContent = data.customerName;
    companyName.textContent = data.companyName || '-';
    workType.textContent = getWorkTypeName(data.workType);
    budget.textContent = formatPrice(data.budget);
    deadline.textContent = new Date(data.deadline).toLocaleDateString();
    requirements.textContent = data.requirements || '-';
    basePrice.textContent = formatPrice(data.basePrice);
    designPrice.textContent = formatPrice(data.designPrice);
    optionPrice.textContent = formatPrice(data.optionPrice);
    totalPrice.textContent = formatPrice(data.totalPrice);
    notes.textContent = data.notes || '-';
}

// Work type mapping
function getWorkTypeName(type) {
    const types = {
        'product_registration': '상품 등록',
        'detail_page': '상세 페이지 제작',
        'marketing': '마케팅 운영',
        'other': '기타'
    };
    return types[type] || type;
}

// Price formatting
function formatPrice(price) {
    return new Intl.NumberFormat('ko-KR', {
        style: 'currency',
        currency: 'KRW'
    }).format(price);
}

// PDF Download
downloadPdfBtn.addEventListener('click', async () => {
    const { jsPDF } = window.jspdf;
    const content = document.getElementById('estimateContent');
    
    try {
        // Create PDF
        const pdf = new jsPDF('p', 'mm', 'a4');
        const canvas = await html2canvas(content, {
            scale: 2,
            useCORS: true,
            logging: false
        });
        
        const imgData = canvas.toDataURL('image/jpeg', 1.0);
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
        
        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
        pdf.save(`견적서_${estimateCode.textContent}.pdf`);
        
        window.howContent.showToast('PDF 다운로드가 완료되었습니다.', 'success');
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('PDF 생성 중 오류가 발생했습니다.', 'error');
    }
});

// Modification request modal
requestModificationBtn.addEventListener('click', () => {
    modificationModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
});

modalCloseBtn.addEventListener('click', () => {
    modificationModal.style.display = 'none';
    document.body.style.overflow = '';
});

window.addEventListener('click', (e) => {
    if (e.target === modificationModal) {
        modificationModal.style.display = 'none';
        document.body.style.overflow = '';
    }
});

// Submit modification request
modificationForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(modificationForm);
    formData.append('estimateCode', code);
    
    try {
        const response = await fetch('/includes/request-modification.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.howContent.showToast('수정 요청이 접수되었습니다.', 'success');
            modificationModal.style.display = 'none';
            document.body.style.overflow = '';
            modificationForm.reset();
        } else {
            window.howContent.showToast(result.message || '수정 요청 중 오류가 발생했습니다.', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        window.howContent.showToast('서버와의 통신 중 오류가 발생했습니다.', 'error');
    }
}); 