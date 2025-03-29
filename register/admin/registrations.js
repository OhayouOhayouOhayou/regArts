document.addEventListener('DOMContentLoaded', function() {
    loadProvinces(); // โหลดจังหวัดเมื่อหน้าเว็บเปิด
    applyFilters();  // โหลดข้อมูลการลงทะเบียนเริ่มต้น

    // เพิ่ม Event Listener สำหรับปุ่มค้นหา
    document.querySelector('#searchInput + .btn').addEventListener('click', applyFilters);
    // เพิ่ม Event Listener สำหรับ Enter ในช่องค้นหา
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilters();
    });

    // เพิ่ม Event Listener สำหรับปุ่ม filter
    document.getElementById('provinceFilter').addEventListener('change', loadDistricts);
});

// ฟังก์ชันโหลดข้อมูลจังหวัด
async function loadProvinces() {
    try {
        const response = await fetch('api/get_provinces.php');
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลจังหวัดได้');
        const provinces = await response.json();
        
        // ตรวจสอบว่าข้อมูลมีรูปแบบที่ถูกต้อง
        if (!Array.isArray(provinces) && provinces.data && Array.isArray(provinces.data)) {
            // กรณีข้อมูลอยู่ใน property data
            populateProvinces(provinces.data);
        } else if (Array.isArray(provinces)) {
            // กรณีข้อมูลเป็น array โดยตรง
            populateProvinces(provinces);
        } else {
            console.error('รูปแบบข้อมูลจังหวัดไม่ถูกต้อง:', provinces);
            throw new Error('ข้อมูลจังหวัดมีรูปแบบไม่ถูกต้อง');
        }
    } catch (error) {
        console.error('Error loading provinces:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถโหลดข้อมูลจังหวัดได้ กรุณาลองใหม่'
        });
    }
}

// ฟังก์ชันสำหรับเติมข้อมูลจังหวัดลงใน dropdown
function populateProvinces(provinces) {
    const provinceSelect = document.getElementById('provinceFilter');
    provinceSelect.innerHTML = '<option value="">เลือกจังหวัด</option>';
    
    provinces.forEach(p => {
        provinceSelect.innerHTML += `<option value="${p.id}">${p.name_in_thai}</option>`;
    });
}

// ฟังก์ชันโหลดข้อมูลอำเภอตามจังหวัดที่เลือก
async function loadDistricts() {
    const provinceId = document.getElementById('provinceFilter').value;
    const districtSelect = document.getElementById('districtFilter');
    
    districtSelect.innerHTML = '<option value="">เลือกอำเภอ</option>';
    districtSelect.disabled = true;
    
    if (!provinceId) return;
    
    try {
        const response = await fetch(`api/get_districts_in.php?province_id=${provinceId}`);
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลอำเภอได้');
        const districts = await response.json();
        
        if (districts.data && Array.isArray(districts.data)) {
            districts.data.forEach(d => {
                districtSelect.innerHTML += `<option value="${d.id}">${d.name_in_thai}</option>`;
            });
        } else if (Array.isArray(districts)) {
            districts.forEach(d => {
                districtSelect.innerHTML += `<option value="${d.id}">${d.name_in_thai}</option>`;
            });
        }
        
        districtSelect.disabled = false;
    } catch (error) {
        console.error('Error loading districts:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถโหลดข้อมูลอำเภอได้ กรุณาลองใหม่'
        });
    }
}

// ฟังก์ชันโหลดและกรองข้อมูลการลงทะเบียน
async function applyFilters() {
    const province = document.getElementById('provinceFilter').value;
    const district = document.getElementById('districtFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const url = `api/get_registrations.php?page=1&limit=10&province=${encodeURIComponent(province)}&district=${encodeURIComponent(district)}&firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลการลงทะเบียนได้');
        
        // ดึงข้อความดิบสำหรับการตรวจสอบข้อผิดพลาด
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('ไม่สามารถแปลงข้อมูลเป็น JSON ได้:', text);
            throw new Error('ข้อมูลที่ส่งกลับมาไม่ใช่ JSON ที่ถูกต้อง');
        }
        
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
                        <td>${formatAddress(reg)}</td>
                        <td><span class="status-badge ${reg.is_approved == 1 ? 'bg-success' : 'bg-warning'} text-white">${reg.is_approved == 1 ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}</span></td>
                        <td><span class="status-badge ${reg.payment_status === 'paid' ? 'bg-success' : 'bg-danger'} text-white">${reg.payment_status === 'paid' ? 'ชำระแล้ว' : 'ยังไม่ชำระ'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary me-1" onclick="viewRegistration(${reg.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteRegistration(${reg.id})" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </button>
                        </td>
                    </tr>
                `).join('') : 
                '<tr><td colspan="9" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
            renderPagination(result.data.total, 1);
        } else {
            throw new Error(result.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
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


// ฟังก์ชันลบข้อมูลการลงทะเบียน
function deleteRegistration(id) {
    // แสดงกล่องยืนยันการลบ
    Swal.fire({
        title: 'ยืนยันการลบ',
        text: 'คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลการลงทะเบียนนี้? การกระทำนี้ไม่สามารถเปลี่ยนกลับได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ใช่, ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#d33',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // ยืนยันอีกครั้ง
            Swal.fire({
                title: 'ยืนยันการลบอีกครั้ง',
                text: 'ข้อมูลทั้งหมดรวมถึงเอกสารที่อัปโหลดจะถูกลบถาวร',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบถาวร',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#d33',
                reverseButtons: true
            }).then((innerResult) => {
                if (innerResult.isConfirmed) {
                    // ส่งคำขอไปยัง API เพื่อลบข้อมูล
                    fetch(`api/delete_registration.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id=${id}`
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ลบข้อมูลสำเร็จ',
                                text: 'ระบบได้ลบข้อมูลการลงทะเบียนเรียบร้อยแล้ว'
                            }).then(() => {
                                // โหลดข้อมูลใหม่
                                applyFilters();
                            });
                        } else {
                            throw new Error(data.message || 'เกิดข้อผิดพลาดในการลบข้อมูล');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: error.message || 'ไม่สามารถลบข้อมูลได้ กรุณาลองใหม่อีกครั้ง'
                        });
                    });
                }
            });
        }
    });
}


// ฟังก์ชันสำหรับจัดรูปแบบที่อยู่
function formatAddress(reg) {
    const parts = [];
    if (reg.address) parts.push(reg.address);
    if (reg.subdistrict_name) parts.push(reg.subdistrict_name);
    if (reg.district_name) parts.push(reg.district_name);
    if (reg.province_name) parts.push(reg.province_name);
    if (reg.zipcode) parts.push(reg.zipcode);
    
    return parts.length > 0 ? parts.join(' ') : '-';
}

// ฟังก์ชันรีเซ็ตฟิลเตอร์
function resetFilters() {
    document.getElementById('provinceFilter').value = '';
    document.getElementById('districtFilter').value = '';
    document.getElementById('districtFilter').disabled = true;
    document.getElementById('firstNameFilter').value = '';
    document.getElementById('lastNameFilter').value = '';
    document.getElementById('phoneFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters(); // โหลดข้อมูลใหม่หลังรีเซ็ต
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
    const district = document.getElementById('districtFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const url = `api/get_registrations.php?page=${page}&limit=10&province=${encodeURIComponent(province)}&district=${encodeURIComponent(district)}&firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    
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
                        <td>${formatAddress(reg)}</td>
                        <td><span class="status-badge ${reg.is_approved == 1 ? 'bg-success' : 'bg-warning'} text-white">${reg.is_approved == 1 ? 'อนุมัติแล้ว' : 'รอการอนุมัติ'}</span></td>
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

// ฟังก์ชันส่งออก Excel
function exportToExcel() {
    const province = document.getElementById('provinceFilter').value;
    const district = document.getElementById('districtFilter').value;
    const firstName = document.getElementById('firstNameFilter').value.trim();
    const lastName = document.getElementById('lastNameFilter').value.trim();
    const phone = document.getElementById('phoneFilter').value.trim();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();

    const url = `api/export_registrations.php?province=${encodeURIComponent(province)}&district=${encodeURIComponent(district)}&firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}&status=${encodeURIComponent(status)}&search=${encodeURIComponent(search)}`;
    
    window.location.href = url;
}

// ฟังก์ชันดูรายละเอียดการลงทะเบียน
function viewRegistration(id) {
    window.location.href = `registration_detail.php?id=${id}`;
}

// ฟังก์ชันแก้ไขการลงทะเบียน
function editRegistration(id) {
    window.location.href = `edit_registration.php?id=${id}`;
}