let currentPage = 1;
const itemsPerPage = 10;
let totalPages = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadRegistrations(1);
    initializeSearch();
    initializeFilters();
});

function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(() => applyFilters(), 500));
    }
}

function initializeFilters() {
    const filters = ['provinceFilter', 'districtFilter', 'firstNameFilter', 'lastNameFilter', 'phoneFilter', 'statusFilter'];
    filters.forEach(filterId => {
        const element = document.getElementById(filterId);
        if (element) {
            element.addEventListener('change', debounce(applyFilters, 500));
            if (element.tagName === 'INPUT') {
                element.addEventListener('keyup', debounce(applyFilters, 500));
            }
        }
    });
}

function applyFilters() {
    const filters = {
        province: document.getElementById('provinceFilter')?.value || '',
        district: document.getElementById('districtFilter')?.value || '',
        firstName: document.getElementById('firstNameFilter')?.value || '',
        lastName: document.getElementById('lastNameFilter')?.value || '',
        phone: document.getElementById('phoneFilter')?.value || '',
        status: document.getElementById('statusFilter')?.value || '',
        search: document.getElementById('searchInput')?.value || ''
    };
    loadRegistrations(1, filters);
}

function resetFilters() {
    document.getElementById('provinceFilter').value = '';
    document.getElementById('districtFilter').value = '';
    document.getElementById('districtFilter').disabled = true;
    document.getElementById('firstNameFilter').value = '';
    document.getElementById('lastNameFilter').value = '';
    document.getElementById('phoneFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters();
}

async function loadRegistrations(page, filters = {}) {
    try {
        currentPage = page;
        const queryParams = new URLSearchParams({
            page,
            limit: itemsPerPage,
            ...filters
        }).toString();
        
        const tableBody = document.getElementById('registrationsList');
        tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                </td>
            </tr>
        `;
        
        const response = await fetch(`api/get_registrations.php?${queryParams}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');

        const registrations = result.data.registrations;
        totalPages = Math.ceil(result.data.total / itemsPerPage);
        
        renderRegistrations(registrations);
        renderPagination(totalPages, currentPage);
        
    } catch (error) {
        console.error('Error:', error);
        showError(error.message);
    }
}

function renderRegistrations(registrations) {
    const tableBody = document.getElementById('registrationsList');
    tableBody.innerHTML = registrations.length ? 
        registrations.map(reg => `
            <tr>
                <td>${new Date(reg.created_at).toLocaleDateString('th-TH')}</td>
                <td>${reg.fullname}</td>
                <td>${reg.organization || '-'}</td>
                <td>${reg.phone}</td>
                <td>${reg.email}</td>
                <td>${reg.address ? `${reg.address} ${reg.subdistrict_name} ${reg.district_name} ${reg.province_name} ${reg.zipcode}` : '-'}</td>
                <td>${reg.is_approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}</td>
                <td>${reg.payment_status === 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ'}</td>
                <td>
                    <button class="btn btn-sm btn-primary">ดู</button>
                    <button class="btn btn-sm btn-warning">แก้ไข</button>
                </td>
            </tr>
        `).join('') : 
        '<tr><td colspan="9" class="text-center">ไม่มีข้อมูล</td></tr>';
}

function renderPagination(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    let html = '';
    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadRegistrations(${currentPage - 1})">ก่อนหน้า</a></li>`;
    }
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="loadRegistrations(${i})">${i}</a></li>`;
    }
    if (currentPage < totalPages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadRegistrations(${currentPage + 1})">ถัดไป</a></li>`;
    }
    pagination.innerHTML = html;
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: message,
    });
}

function exportToExcel() {
    const filters = {
        province: document.getElementById('provinceFilter')?.value || '',
        district: document.getElementById('districtFilter')?.value || '',
        firstName: document.getElementById('firstNameFilter')?.value || '',
        lastName: document.getElementById('lastNameFilter')?.value || '',
        phone: document.getElementById('phoneFilter')?.value || '',
        status: document.getElementById('statusFilter')?.value || '',
        search: document.getElementById('searchInput')?.value || ''
    };
    const queryParams = new URLSearchParams(filters).toString();
    window.location.href = `api/export_registrations.php?${queryParams}`;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}