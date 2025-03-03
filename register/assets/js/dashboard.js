document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadRecentRegistrations();
});

async function loadDashboardStats() {
    try {
        const response = await fetch('get_dashboard_stats.php');
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }

        const stats = result.data;
        document.querySelector('.stat-card.bg-primary .card-title').textContent = stats.total;
        document.querySelector('.stat-card.bg-warning .card-title').textContent = stats.pending;
        document.querySelector('.stat-card.bg-success .card-title').textContent = stats.approved;
        document.querySelector('.stat-card.bg-danger .card-title').textContent = stats.unpaid;

    } catch (error) {
        console.error('Error loading dashboard stats:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถโหลดข้อมูลสถิติได้'
        });
    }
}
async function loadRecentRegistrations() {
    try {
        const response = await fetch('get_recent_registrations.php');
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        const registrations = result.data;
        const tbody = document.getElementById('recentRegistrations');
        
        if (!registrations || registrations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">ไม่พบข้อมูลการลงทะเบียน</td></tr>';
            return;
        }

        tbody.innerHTML = registrations.map(reg => `
            <tr>
                <td>${formatDate(reg.created_at)}</td>
                <td>${reg.fullname}</td>
                <td>${reg.organization}</td>
                <td>${getStatusBadge(reg.status)}</td>
                <td>${getPaymentBadge(reg.payment_status)}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewDetails(${reg.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (error) {
        console.error('Error loading recent registrations:', error);
        const tbody = document.getElementById('recentRegistrations');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
    }
}


async function viewDetails(id) {
    // Show loading indicator
    Swal.fire({
        title: 'กำลังโหลดข้อมูล',
        text: 'กรุณารอสักครู่...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Redirect to detail page
    window.location.href = `registration_detail.php?id=${id}`;
}

async function approveRegistration(registrationId) {
    try {
        const result = await Swal.fire({
            title: 'ยืนยันการอนุมัติ',
            text: 'คุณต้องการอนุมัติการลงทะเบียนนี้ใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'อนุมัติ',
            cancelButtonText: 'ยกเลิก'
        });

        if (result.isConfirmed) {
            const response = await fetch('../admin/approve_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ registration_id: registrationId })
            });

            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'อนุมัติสำเร็จ',
                    text: 'การลงทะเบียนได้รับการอนุมัติแล้ว'
                });
                loadDashboardStats();
                loadRecentRegistrations();
            } else {
                throw new Error(data.message);
            }
        }
    } catch (error) {
        console.error('Error approving registration:', error);
        showError('ไม่สามารถอนุมัติการลงทะเบียน');
    }
}

// Utility functions
function updateStatCard(id, value) {
    document.getElementById(id).textContent = value;
}

function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('th-TH', options);
}

function getStatusBadge(status) {
    const statusMap = {
        'pending': '<span class="badge bg-warning">รอการตรวจสอบ</span>',
        'approved': '<span class="badge bg-success">อนุมัติแล้ว</span>',
        'rejected': '<span class="badge bg-danger">ไม่อนุมัติ</span>'
    };
    return statusMap[status] || status;
}

function getPaymentBadge(status) {
    const statusMap = {
        'not_paid': '<span class="badge bg-danger">ยังไม่ชำระเงิน</span>',
        'paid': '<span class="badge bg-success">ชำระเงินแล้ว</span>'
    };
    return statusMap[status] || status;
}

function formatAddress(address) {
    return `${address.address} ${address.subdistrict} ${address.district} ${address.province} ${address.zipcode}`;
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: message
    });
}