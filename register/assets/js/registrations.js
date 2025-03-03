/**
 * Registration Management System - JavaScript
 * This file handles all client-side functionality for the registration management system
 * including loading, displaying, searching, and managing registrations.
 */

// Global variables for pagination
let currentPage = 1;
const itemsPerPage = 10;
let totalPages = 0;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    loadRegistrations(1);
    initializeSearch();
    
    // Add event listener for Excel export button
    const exportButton = document.querySelector('.btn-success[onclick="exportToExcel()"]');
    if (exportButton) {
        exportButton.addEventListener('click', function(e) {
            e.preventDefault();
            exportToExcel();
        });
    }
});

/**
 * Initialize search functionality with debouncing
 */
function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(() => {
            loadRegistrations(1);
        }, 500));
    }
}

/**
 * Display error message using SweetAlert
 * @param {string} message - Error message to display
 */
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: message
    });
}

/**
 * Load registrations data from the server
 * @param {number} page - Page number to load
 */
async function loadRegistrations(page) {
    try {
        currentPage = page;
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput ? searchInput.value : '';
        
        // Show loading spinner
        const tableBody = document.getElementById('registrationsList');
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                </td>
            </tr>
        `;
        
        // Fetch registrations data
        const response = await fetch(`api/get_registrations.php?page=${page}&limit=${itemsPerPage}&search=${encodeURIComponent(searchTerm)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.status !== 'success') {
            throw new Error(result.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
        }

        // Extract data from response
        const registrations = result.data.registrations;
        totalPages = Math.ceil(result.data.total / itemsPerPage);
        
        // Render data
        renderRegistrations(registrations);
        renderPagination(totalPages, currentPage);
        
    } catch (error) {
        console.error('Error:', error);
        const tableBody = document.getElementById('registrationsList');
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    เกิดข้อผิดพลาดในการโหลดข้อมูล: ${error.message}
                </td>
            </tr>
        `;
    }
}

/**
 * Render registrations table
 * @param {Array} registrations - Array of registration objects
 */
function renderRegistrations(registrations) {
    const tableBody = document.getElementById('registrationsList');
    
    if (!registrations || registrations.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-info-circle me-2"></i>
                    ไม่พบข้อมูลการลงทะเบียน
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = registrations.map(reg => {
        // Prepare status badges
        const approvalStatus = reg.is_approved 
            ? `<span class="badge bg-success status-badge">อนุมัติแล้ว</span>` 
            : `<span class="badge bg-warning text-dark status-badge">รอการอนุมัติ</span>`;
        
        const paymentStatus = reg.payment_status === 'paid' 
            ? `<span class="badge bg-success status-badge">ชำระแล้ว</span>` 
            : `<span class="badge bg-danger status-badge">ยังไม่ชำระ</span>`;
            
        // Return table row
        return `
            <tr>
                <td>${reg.formatted_date || formatDate(reg.created_at)}</td>
                <td>${escapeHtml(reg.fullname)}</td>
                <td>${escapeHtml(reg.organization)}</td>
                <td>${escapeHtml(reg.phone)}</td>
                <td>${escapeHtml(reg.email)}</td>
                <td>${approvalStatus}</td>
                <td>${paymentStatus}</td>
                <td>
                    <div class="btn-group">
                        ${!reg.is_approved ? `
                            <button class="btn btn-success btn-sm" onclick="approveRegistration(${reg.id})" title="อนุมัติ">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        <a href="registration_detail.php?id=${reg.id}" class="btn btn-primary btn-sm" title="ดูรายละเอียด">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-danger btn-sm" onclick="deleteRegistration(${reg.id})" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} unsafe - Unsafe string that might contain HTML
 * @returns {string} Escaped HTML string
 */
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Render pagination controls
 * @param {number} totalPages - Total number of pages
 * @param {number} currentPage - Current active page
 */
function renderPagination(totalPages, currentPage) {
    const pagination = document.getElementById('pagination');
    
    if (!pagination) return;
    
    // Clear previous pagination
    pagination.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous page button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    
    const prevLink = document.createElement('a');
    prevLink.className = 'page-link';
    prevLink.href = '#';
    prevLink.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevLink.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage > 1) loadRegistrations(currentPage - 1);
    });
    
    prevLi.appendChild(prevLink);
    pagination.appendChild(prevLi);
    
    // Determine which page numbers to show
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // First page button if not visible
    if (startPage > 1) {
        const firstLi = document.createElement('li');
        firstLi.className = 'page-item';
        
        const firstLink = document.createElement('a');
        firstLink.className = 'page-link';
        firstLink.href = '#';
        firstLink.textContent = '1';
        firstLink.addEventListener('click', function(e) {
            e.preventDefault();
            loadRegistrations(1);
        });
        
        firstLi.appendChild(firstLink);
        pagination.appendChild(firstLi);
        
        // Ellipsis if needed
        if (startPage > 2) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            
            const ellipsisSpan = document.createElement('a');
            ellipsisSpan.className = 'page-link';
            ellipsisSpan.innerHTML = '&hellip;';
            
            ellipsisLi.appendChild(ellipsisSpan);
            pagination.appendChild(ellipsisLi);
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        const pageLi = document.createElement('li');
        pageLi.className = `page-item ${currentPage === i ? 'active' : ''}`;
        
        const pageLink = document.createElement('a');
        pageLink.className = 'page-link';
        pageLink.href = '#';
        pageLink.textContent = i;
        pageLink.addEventListener('click', function(e) {
            e.preventDefault();
            loadRegistrations(i);
        });
        
        pageLi.appendChild(pageLink);
        pagination.appendChild(pageLi);
    }
    
    // Ellipsis and last page if needed
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const ellipsisLi = document.createElement('li');
            ellipsisLi.className = 'page-item disabled';
            
            const ellipsisSpan = document.createElement('a');
            ellipsisSpan.className = 'page-link';
            ellipsisSpan.innerHTML = '&hellip;';
            
            ellipsisLi.appendChild(ellipsisSpan);
            pagination.appendChild(ellipsisLi);
        }
        
        const lastLi = document.createElement('li');
        lastLi.className = 'page-item';
        
        const lastLink = document.createElement('a');
        lastLink.className = 'page-link';
        lastLink.href = '#';
        lastLink.textContent = totalPages;
        lastLink.addEventListener('click', function(e) {
            e.preventDefault();
            loadRegistrations(totalPages);
        });
        
        lastLi.appendChild(lastLink);
        pagination.appendChild(lastLi);
    }
    
    // Next page button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    
    const nextLink = document.createElement('a');
    nextLink.className = 'page-link';
    nextLink.href = '#';
    nextLink.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextLink.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage < totalPages) loadRegistrations(currentPage + 1);
    });
    
    nextLi.appendChild(nextLink);
    pagination.appendChild(nextLi);
}

/**
 * Approve a registration
 * @param {number} id - Registration ID to approve
 */
function approveRegistration(id) {
    Swal.fire({
        title: 'ยืนยันการอนุมัติ',
        text: "คุณต้องการอนุมัติการลงทะเบียนนี้ใช่หรือไม่?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, อนุมัติ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading indicator
            Swal.fire({
                title: 'กำลังดำเนินการ',
                text: 'กรุณารอสักครู่...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send approval request
            fetch('api/approve_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `registration_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'อนุมัติเรียบร้อย',
                        text: 'การลงทะเบียนได้รับการอนุมัติแล้ว'
                    }).then(() => {
                        loadRegistrations(currentPage);
                    });
                } else {
                    throw new Error(data.message || 'เกิดข้อผิดพลาดในการอนุมัติ');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: error.message || 'ไม่สามารถอนุมัติการลงทะเบียนได้'
                });
            });
        }
    });
}

/**
 * Delete a registration
 * @param {number} id - Registration ID to delete
 */
function deleteRegistration(id) {
    Swal.fire({
        title: 'ยืนยันการลบ',
        text: 'คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบรายการ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading indicator
            Swal.fire({
                title: 'กำลังดำเนินการ',
                text: 'กรุณารอสักครู่...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Send delete request
            fetch('api/delete_registration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `registration_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบเรียบร้อย',
                        text: 'ลบรายการลงทะเบียนเรียบร้อยแล้ว'
                    }).then(() => {
                        loadRegistrations(currentPage);
                    });
                } else {
                    throw new Error(data.message || 'เกิดข้อผิดพลาดในการลบรายการ');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: error.message || 'ไม่สามารถลบรายการลงทะเบียนได้'
                });
            });
        }
    });
}

/**
 * Format date to Thai locale
 * @param {string} dateString - Date string to format
 * @returns {string} Formatted date string
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    try {
        return new Date(dateString).toLocaleDateString('th-TH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        console.error('Date formatting error:', e);
        return dateString;
    }
}

/**
 * Create debounce function to limit API calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Milliseconds to wait
 * @returns {Function} Debounced function
 */
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

/**
 * Export registrations to Excel
 */
function exportToExcel() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput ? searchInput.value : '';
    
    // Redirect to export endpoint with search parameters
    window.location.href = `api/export_registrations.php?search=${encodeURIComponent(searchTerm)}`;
}