document.addEventListener('DOMContentLoaded', function() {
    loadProvinces(); // โหลดจังหวัดเมื่อหน้าเว็บเปิด
    applyFilters();  // โหลดข้อมูลการลงทะเบียนเริ่มต้น

    // เพิ่ม Event Listener สำหรับปุ่มค้นหา
    document.querySelector('#searchInput + .btn').addEventListener('click', applyFilters);
    // เพิ่ม Event Listener สำหรับ Enter ในช่องค้นหา
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilters();
    });
});

// ฟังก์ชันโหลดข้อมูลจังหวัด
async function loadProvinces() {
    try {
        const response = await fetch('api/get_provinces.php');
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลจังหวัดได้');
        const provinces = await response.json();
        const provinceSelect = document.getElementById('provinceFilter');
        provinceSelect.innerHTML = '<option value="">เลือกจังหวัด</option>' + 
            provinces.map(p => `<option value="${p.id}">${p.name_in_thai}</option>`).join('');
    } catch (error) {
        console.error('Error loading provinces:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถโหลดข้อมูลจังหวัดได้ กรุณาลองใหม่'
        });
    }
}

// ฟังก์ชันโหลดและกรองข้อมูลการลงทะเบียน
async function applyFilters() {
    const province = document.getElementById('provinceFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const url = `api/get_registrations.php?page=1&limit=10&province=${encodeURIComponent(province)}&firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลการลงทะเบียนได้');
        const result = await response.json();
        
        if (result.status === 'success') {
            const tableBody = document.getElementById('registrationsList');
            tableBody.innerHTML = result.data.registrations.length > 0 ? 
                result.data.registrations.map(reg => `
                    <tr>
                        <td>${new Date(reg.created_at).toLocaleDateString('th-TH')}</td>
                        <td>${reg.fullname || '-'}</td>
                        <td>${reg.organization || '-'}</td>
                        <td>${reg.phone || '-'}</td>
                        <td>${reg.email || '-'}</td>
                        <td>${reg.address ? `${reg.address} ${reg.subdistrict_name} ${reg.district_name} ${reg.province_name} ${reg.zipcode}` : '-'}</td>
                        <td><span class="status-badge ${reg.is_approved ? 'bg-success' : 'bg-warning'} text-white">${reg.is_approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}</span></td>
                        <td><span class="status-badge ${reg.payment_status === 'paid' ? 'bg-success' : 'bg-danger'} text-white">${reg.payment_status === 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewRegistration(${reg.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-warning" onclick="editRegistration(${reg.id})"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                `).join('') : 
                '<tr><td colspan="9" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
            renderPagination(result.data.total, 1);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading registrations:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่'
        });
    }
}

// ฟังก์ชันรีเซ็ตฟิลเตอร์
function resetFilters() {
    document.getElementById('provinceFilter').value = '';
    document.getElementById('firstNameFilter').value = '';
    document.getElementById('lastNameFilter').value = '';
    document.getElementById('phoneFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters(); // โหลดข้อมูลใหม่หลังรีเซ็ต
}

// ฟังก์ชันส่งออกเป็น Excel
function exportToExcel() {
    const province = document.getElementById('provinceFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const queryParams = new URLSearchParams({
        province: province,
        firstName: firstName,
        lastName: lastName,
        phone: phone,
        status: status,
        search: search
    }).toString();
    
    window.location.href = `api/export_registrations.php?${queryParams}`;
}

// ฟังก์ชันสร้าง Pagination
function renderPagination(total, currentPage) {
    const pagination = document.getElementById('pagination');
    const totalPages = Math.ceil(total / 10);
    let html = '';
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadPage(${currentPage - 1}); return false;">ก่อนหน้า</a></li>`;
    }
    
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="loadPage(${i}); return false;">${i}</a></li>`;
    }
    
    if (currentPage < totalPages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadPage(${currentPage + 1}); return false;">ถัดไป</a></li>`;
    }
    
    pagination.innerHTML = html;
}

// ฟังก์ชันโหลดข้อมูลหน้าใหม่
async function loadPage(page) {
    const province = document.getElementById('provinceFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const url = `api/get_registrations.php?page=${page}&limit=10&province=${encodeURIComponent(province)}&firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลการลงทะเบียนได้');
        const result = await response.json();
        
        if (result.status === 'success') {
            const tableBody = document.getElementById('registrationsList');
            tableBody.innerHTML = result.data.registrations.length > 0 ? 
                result.data.registrations.map(reg => `
                    <tr>
                        <td>${new Date(reg.created_at).toLocaleDateString('th-TH')}</td>
                        <td>${reg.fullname || '-'}</td>
                        <td>${reg.organization || '-'}</td>
                        <td>${reg.phone || '-'}</td>
                        <td>${reg.email || '-'}</td>
                        <td>${reg.address ? `${reg.address} ${reg.subdistrict_name} ${reg.district_name} ${reg.province_name} ${reg.zipcode}` : '-'}</td>
                        <td><span class="status-badge ${reg.is_approved ? 'bg-success' : 'bg-warning'} text-white">${reg.is_approved ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}</span></td>
                        <td><span class="status-badge ${reg.payment_status === 'paid' ? 'bg-success' : 'bg-danger'} text-white">${reg.payment_status === 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ'}</span></td>
                        <td>
                                                        <button class="btn btn-sm btn-primary me-1" onclick="viewRegistration(${reg.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-warning" onclick="editRegistration(${reg.id})"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                `).join('') : 
                '<tr><td colspan="9" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
            renderPagination(result.data.total, page);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error loading registrations:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่'
        });
    }
}

// ฟังก์ชันดูรายละเอียดการลงทะเบียน
function viewRegistration(id) {
    window.location.href = `registration_detail.php?id=${id}`;
}

// ฟังก์ชันแก้ไขการลงทะเบียน
function editRegistration(id) {
    window.location.href = `edit_registration.php?id=${id}`;
}

// ฟังก์ชัน debounce สำหรับการค้นหา
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

// เพิ่ม Event Listener สำหรับการค้นหาแบบ debounce
document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));