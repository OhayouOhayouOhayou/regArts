/**
 * Enhanced Seminar Registration System
 * Professional-grade implementation with multi-registrant support
 * © 2025 Registration System
 */

// ======================= UTILITY FUNCTIONS =======================

/**
 * Format date in Thai locale
 * @param {string} dateString - Date string to format
 * @returns {string} Formatted date
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleString('th-TH', options);
}

/**
 * Get document type display name
 * @param {string} type - Document type code
 * @returns {string} Human-readable document type
 */
function getDocumentTypeName(type) {
    const typeMap = {
        'identification': 'บัตรประชาชน/บัตรข้าราชการ',
        'certificate': 'วุฒิบัตร/ประกาศนียบัตร',
        'professional': 'เอกสารรับรองทางวิชาชีพ',
        'general': 'เอกสารทั่วไป',
        'other': 'เอกสารอื่นๆ'
    };
    return typeMap[type] || 'เอกสารอื่นๆ';
}

/**
 * Get appropriate file icon based on file type
 * @param {string} filePath - Path to the file
 * @returns {string} HTML icon element
 */
function getFileIcon(filePath) {
    if (filePath.endsWith('.pdf')) {
        return '<i class="far fa-file-pdf text-danger me-1"></i>';
    } else if (filePath.match(/\.(jpg|jpeg|png|gif)$/i)) {
        return '<i class="far fa-file-image text-primary me-1"></i>';
    } else {
        return '<i class="far fa-file text-secondary me-1"></i>';
    }
}

/**
 * Show loading overlay
 * @param {string} message - Message to display during loading
 */
function showLoading(message = 'กรุณารอสักครู่...') {
    Swal.fire({
        title: 'กำลังประมวลผล',
        text: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

/**
 * Show error message
 * @param {string} message - Error message to display
 */
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: message,
        confirmButtonText: 'ตกลง'
    });
}

/**
 * Download a file
 * @param {string} filePath - Path to file
 * @param {string} fileName - Optional filename
 */
function downloadFile(filePath, fileName) {
    const link = document.createElement('a');
    link.href = filePath;
    link.download = fileName || filePath.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {boolean} Whether email is valid
 */
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Validate phone number format (Thai)
 * @param {string} phone - Phone number to validate
 * @returns {boolean} Whether phone number is valid
 */
function validatePhone(phone) {
    // Thai phone number validation (9-10 digits)
    return /^[0-9]{9,10}$/.test(phone.replace(/[- ]/g, ''));
}

// ======================= REGISTRATION STATUS FUNCTIONS =======================

/**
 * Get timeline steps based on registration status
 * @param {string} paymentStatus - Payment status
 * @param {boolean} isApproved - Approval status
 * @returns {Array} Array of timeline step objects
 */
function getTimelineSteps(paymentStatus, isApproved) {
    const timelineSteps = [
        {
            title: 'ลงทะเบียน',
            description: 'ดำเนินการลงทะเบียนเรียบร้อยแล้ว',
            icon: 'fas fa-user-check',
            status: 'completed'
        },
        {
            title: 'ชำระเงิน',
            description: 'อัพโหลดหลักฐานการชำระเงิน',
            icon: 'fas fa-money-bill-wave',
            status: 'waiting'
        },
        {
            title: 'ตรวจสอบ',
            description: 'รอการตรวจสอบจากเจ้าหน้าที่',
            icon: 'fas fa-clipboard-check',
            status: 'waiting'
        },
        {
            title: 'เสร็จสมบูรณ์',
            description: 'การลงทะเบียนเสร็จสมบูรณ์',
            icon: 'fas fa-check-circle',
            status: 'waiting'
        }
    ];
    
    // Update status based on payment status
    if (paymentStatus === 'paid') {
        timelineSteps[1].status = 'completed';
        
        if (isApproved) {
            timelineSteps[2].status = 'completed';
            timelineSteps[3].status = 'completed';
        } else {
            timelineSteps[2].status = 'current';
        }
    }
    
    return timelineSteps;
}

/**
 * Create HTML for timeline
 * @param {Array} timelineSteps - Array of timeline step objects
 * @returns {string} HTML for timeline
 */
function createTimelineHTML(timelineSteps) {
    return `
        <div class="timeline-container p-4">
            <div class="timeline">
                ${timelineSteps.map((step, index) => `
                    <div class="timeline-item ${step.status}">
                        <div class="timeline-icon">
                            <i class="${step.icon}"></i>
                        </div>
                        <div class="timeline-content">
                            <h5>${step.title}</h5>
                            <p>${step.description}</p>
                            ${step.status === 'current' ? `
                                <div class="timeline-status">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">กำลังดำเนินการ</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

/**
 * Create payment upload form
 * @param {Object} registration - Registration data
 * @returns {string} HTML for payment form
 */
function createPaymentUploadForm(registration) {
    return `
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-upload me-2"></i>อัพโหลดหลักฐานการชำระเงิน</h5>
            </div>
            <div class="card-body">
                <div class="payment-info mb-4">
                    <h5 class="mb-3">รายละเอียดการโอนเงิน</h5>
                    
                    <div class="text-center mb-4">
                        <img src="QR.jpg" alt="QR Code สำหรับชำระเงิน" style="max-width: 600px;" class="img-fluid border p-2">
                        <p class="text-muted mt-2">สแกน QR Code เพื่อชำระเงิน</p>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>กรุณาชำระเงินค่าลงทะเบียน ก่อนวันเข้ารับการฝึกอบรม 7 วัน</strong>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body bg-light">
                            <p class="mb-2">
                                <i class="fas fa-university me-2"></i>
                                <strong>โอนเงินเข้าบัญชี:</strong> ธนาคารกรุงไทย สาขาโรจนะ
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-file-invoice me-2"></i>
                                <strong>ชื่อบัญชี:</strong> "มทร.สุวรรณภูมิ เงินรายได้"
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-money-check me-2"></i>
                                <strong>เลขที่บัญชี:</strong> 128-028939-2
                            </p>
                            <p class="mb-0 text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>*ไม่รับชำระด้วยเช็ค*</strong>
                            </p>
                        </div>
                    </div>
                </div>
                
                <form id="paymentForm" class="border p-4 rounded bg-light">
                    <div class="mb-3">
                        <label class="form-label required">วันที่และเวลาที่ชำระเงิน</label>
                        <input type="datetime-local" class="form-control" name="payment_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">หลักฐานการชำระเงิน</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-upload"></i>
                            </span>
                            <input type="file" class="form-control" name="payment_slip" accept="image/*,.pdf" required>
                        </div>
                        <small class="text-muted">รองรับไฟล์ภาพ (JPG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB</small>
                    </div>
                    <input type="hidden" name="registration_id" value="${registration.id}">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-upload me-2"></i>อัพโหลดหลักฐาน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

// ======================= ADDRESS FUNCTIONS =======================

/**
 * Prepare HTML for address information
 * @param {Array} addresses - Array of address objects
 * @returns {string} HTML for address display
 */
function prepareAddressHTML(addresses) {
    if (!addresses || addresses.length === 0) {
        return '<div class="alert alert-info">ไม่พบข้อมูลที่อยู่</div>';
    }
    
    // Find invoice address (as per requirement, we only display this one)
    const invoiceAddress = addresses.find(addr => addr.address_type === 'invoice');
    
    if (!invoiceAddress) {
        return '<div class="alert alert-info">ไม่พบข้อมูลที่อยู่สำหรับออกใบเสร็จ</div>';
    }
    
    return `
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>ที่อยู่สำหรับออกใบเสร็จ</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <p class="mb-2">${invoiceAddress.address}</p>
                        <p class="mb-0">
                            ตำบล/แขวง <strong>${invoiceAddress.subdistrict_name}</strong> 
                            อำเภอ/เขต <strong>${invoiceAddress.district_name}</strong> 
                            จังหวัด <strong>${invoiceAddress.province_name}</strong> 
                            <strong>${invoiceAddress.zipcode}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ======================= DOCUMENT FUNCTIONS =======================

/**
 * Prepare HTML for documents display
 * @param {Array} documents - Array of document objects
 * @returns {string} HTML for documents section
 */
function prepareDocumentsHTML(documents) {
    if (!documents || documents.length === 0) {
        return '';
    }
    
    const documentItems = documents.map(doc => {
        let docType = getDocumentTypeName(doc.document_type);
        let fileIcon = getFileIcon(doc.file_path);
        
        return `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${fileIcon} ${docType}</h6>
                        <small class="text-muted">${doc.description || 'ไม่มีคำอธิบาย'}</small>
                    </div>
                    <div>
                        <a href="${doc.file_path}" target="_blank" class="btn btn-sm btn-outline-primary me-1">
                            <i class="fas fa-eye me-1"></i>ดูเอกสาร
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadFile('${doc.file_path}', '${doc.file_name}')">
                            <i class="fas fa-download me-1"></i>ดาวน์โหลด
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    return `
        <div class="card mb-4 mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>เอกสารที่อัพโหลด (${documents.length} รายการ)</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    ${documentItems}
                </div>
            </div>
        </div>
    `;
}

// ======================= MULTI-REGISTRANT FUNCTIONS =======================

/**
 * Create HTML for registrant tabs
 * @param {Array} registrants - Array of registrant data
 * @returns {string} HTML for registrant tabs
 */
function createRegistrantTabsHTML(registrants) {
    if (!registrants || registrants.length === 0) {
        return '';
    }
    
    const tabNavs = [];
    const tabContents = [];
    
    registrants.forEach((reg, index) => {
        const isActive = index === 0;
        const tabId = `registrant-${reg.id}`;
        
        // Create tab navigation item
        tabNavs.push(`
            <li class="nav-item" role="presentation">
                <button class="nav-link ${isActive ? 'active' : ''}" 
                        id="${tabId}-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#${tabId}" 
                        type="button" 
                        role="tab" 
                        aria-controls="${tabId}" 
                        aria-selected="${isActive ? 'true' : 'false'}">
                    ${index + 1}. ${reg.title} ${reg.fullname}
                </button>
            </li>
        `);
        
        // Create tab content
        tabContents.push(`
            <div class="tab-pane fade ${isActive ? 'show active' : ''}" 
                 id="${tabId}" 
                 role="tabpanel" 
                 aria-labelledby="${tabId}-tab">
                <div class="card border-0">
                    <div class="card-body bg-light rounded">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>ชื่อ-นามสกุล:</strong></p>
                                <p>${reg.title === 'other' ? reg.title_other : reg.title} ${reg.fullname}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>หน่วยงาน:</strong></p>
                                <p>${reg.organization}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>ตำแหน่ง:</strong></p>
                                <p>${reg.position}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>เบอร์โทรศัพท์:</strong></p>
                                <p>${reg.phone}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>อีเมล:</strong></p>
                                <p>${reg.email}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <p class="mb-1"><strong>LINE ID:</strong></p>
                                <p>${reg.line_id || '-'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
    
    return `
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>ข้อมูลผู้ลงทะเบียน (${registrants.length} คน)</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="registrantTabs" role="tablist">
                    ${tabNavs.join('')}
                </ul>
                <div class="tab-content mt-3" id="registrantTabsContent">
                    ${tabContents.join('')}
                </div>
            </div>
        </div>
    `;
}

// ======================= FORM VALIDATION FUNCTIONS =======================

/**
 * Set up enhanced form validation
 */
function setupEnhancedValidation() {
    const requiredFields = document.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        // Add validation styling
        field.addEventListener('blur', function() {
            validateField(this);
        });
    });
    
    // Email validation
    const emailFields = document.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value && !validateEmail(this.value)) {
                setInvalidField(this, 'กรุณากรอกอีเมลให้ถูกต้อง');
            }
        });
    });
    
    // Phone validation
    const phoneFields = document.querySelectorAll('input[name="phone"]');
    phoneFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value && !validatePhone(this.value)) {
                setInvalidField(this, 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง');
            }
        });
    });
}

/**
 * Validate a form field
 * @param {HTMLElement} field - Field to validate
 */
function validateField(field) {
    // Required field validation
    if (field.hasAttribute('required') && !field.value) {
        setInvalidField(field, 'กรุณากรอกข้อมูลในช่องนี้');
    } else {
        setValidField(field);
    }
    
    // Email validation
    if (field.type === 'email' && field.value && !validateEmail(field.value)) {
        setInvalidField(field, 'กรุณากรอกอีเมลให้ถูกต้อง');
    }
    
    // Phone validation
    if (field.name === 'phone' && field.value && !validatePhone(field.value)) {
        setInvalidField(field, 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง');
    }
}

/**
 * Mark a field as invalid
 * @param {HTMLElement} field - Field to mark
 * @param {string} message - Validation message
 */
function setInvalidField(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    
    // Add validation message if not exists
    let nextEl = field.nextElementSibling;
    if (!nextEl || !nextEl.classList.contains('invalid-feedback')) {
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.innerText = message;
        field.parentNode.insertBefore(feedback, field.nextSibling);
    } else if (nextEl.classList.contains('invalid-feedback')) {
        nextEl.innerText = message;
    }
}

/**
 * Mark a field as valid
 * @param {HTMLElement} field - Field to mark
 */
function setValidField(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    
    // Remove validation message if exists
    let nextEl = field.nextElementSibling;
    if (nextEl && nextEl.classList.contains('invalid-feedback')) {
        nextEl.remove();
    }
}

// ======================= REGISTRATION DISPLAY FUNCTIONS =======================

/**
 * Fetch registration details from the server
 * @param {string|number} registrationId - Registration ID
 */
function fetchRegistrationDetails(registrationId) {
    showLoading('กำลังโหลดข้อมูล');
    
    fetch('get_registration_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ registration_id: registrationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.close();
            displayRegistrationDetails(data);
        } else {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลได้');
        }
    })
    .catch(error => {
        console.error('Error loading registration details:', error);
        showError(error.message || 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง');
    });
}

/**
 * Display registration details
 * @param {Object} data - Registration data
 */
function displayRegistrationDetails(data) {
    const registration = data.registration;
    const addresses = data.addresses;
    const documents = data.documents;
    const groupRegistrations = data.group_registrations || [];
    
    // Hide phone check section
    document.getElementById('phoneCheck').classList.remove('active');
    
    // Create registration info container
    const registrantInfoDiv = document.createElement('div');
    registrantInfoDiv.id = 'registrantInfo';
    registrantInfoDiv.className = 'form-section active';
    
    // Determine status display
    let statusText = '';
    let statusClass = '';
    let actionHtml = '';
    
    switch(registration.payment_status) {
        case 'not_paid':
            statusText = 'รอชำระเงิน';
            statusClass = 'text-warning';
            actionHtml = createPaymentUploadForm(registration);
            break;
        case 'paid':
            if (registration.is_approved) {
                statusText = 'ลงทะเบียนเสร็จสมบูรณ์';
                statusClass = 'text-success';
            } else {
                statusText = 'ชำระเงิน (อัพโหลดแล้วรอการตรวจสอบ)';
                statusClass = 'text-primary';
            }
            break;
    }
    
    // Prepare main container content
    registrantInfoDiv.innerHTML = `
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-user-check me-2"></i>ข้อมูลการลงทะเบียน</h4>
                    <div>
                        <button class="btn btn-sm btn-light" onclick="printRegistrationDetails()">
                            <i class="fas fa-print me-1"></i> พิมพ์
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Registration Status -->
                <div class="card mb-4 border-0 bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">สถานะการลงทะเบียน: <span class="${statusClass} fw-bold">${statusText}</span></h5>
                                <p class="mb-2"><strong>รหัสการลงทะเบียน:</strong> ${registration.id}</p>
                                <p class="mb-2"><strong>วันที่ลงทะเบียน:</strong> ${formatDate(registration.created_at)}</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="alert alert-${registration.payment_status === 'paid' ? 'success' : 'warning'} mb-0 d-inline-block">
                                    <i class="fas fa-${registration.payment_status === 'paid' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                                    <strong>จำนวนผู้ลงทะเบียนในกลุ่ม:</strong> ${groupRegistrations.length ? groupRegistrations.length : '1'} คน
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div id="registrationTimeline" class="mb-4">
                    ${createTimelineHTML(getTimelineSteps(registration.payment_status, registration.is_approved))}
                </div>
                
                <!-- Registrant Details -->
                ${createRegistrantTabsHTML(groupRegistrations.length > 0 ? groupRegistrations : [registration])}
                
                <!-- Address Information -->
                <div class="address-info mt-4">
                    <h5 class="mb-3">ข้อมูลที่อยู่</h5>
                    ${prepareAddressHTML(addresses)}
                </div>
                
                <!-- Documents -->
                ${prepareDocumentsHTML(documents)}
            </div>
        </div>
        
        <!-- Payment Form (if needed) -->
        ${actionHtml}
    `;
    
    // Add to page
    document.querySelector('.container.py-4').appendChild(registrantInfoDiv);
    
    // Initialize tabs
    initializeRegistrantTabs();
    
    // Setup payment form listener
    setupPaymentFormListener();
}

/**
 * Initialize registrant tabs
 */
function initializeRegistrantTabs() {
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    if (tabs.length === 0) return;
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get target
            const target = document.querySelector(this.getAttribute('data-bs-target'));
            
            // Deactivate all tabs
            document.querySelectorAll('.nav-link').forEach(navLink => {
                navLink.classList.remove('active');
                navLink.setAttribute('aria-selected', 'false');
            });
            
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // Activate selected tab
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            // Show selected pane
            target.classList.add('show', 'active');
        });
    });
}

/**
 * Print registration details
 */
function printRegistrationDetails() {
    const content = document.getElementById('registrantInfo').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>รายละเอียดการลงทะเบียน</title>
            <meta charset="UTF-8">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: 'Sarabun', sans-serif;
                    padding: 20px;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding: 15px;
                    border-bottom: 2px solid #2C3E50;
                }
                .print-logo {
                    max-height: 60px;
                    margin-bottom: 10px;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .card {
                        border: 1px solid #ddd !important;
                        break-inside: avoid;
                    }
                    .card-header {
                        background-color: #f8f9fa !important;
                        color: #000 !important;
                        border-bottom: 1px solid #ddd !important;
                    }
                    button {
                        display: none !important;
                    }
                }
                .tab-pane {
                    display: block !important;
                    opacity: 1 !important;
                }
                .nav-tabs {
                    display: none !important;
                }
                .registrant-separator {
                    margin-top: 20px;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-top: 1px solid #ddd;
                    border-bottom: 1px solid #ddd;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <img src="https://arts.rmutsb.ac.th/image/logo_art_2019.png" alt="Logo" class="print-logo">
                <h2>รายละเอียดการลงทะเบียน</h2>
                <p class="lead">การสัมมนาเพิ่มศักยภาพท้องถิ่นเพื่อการขับเคลื่อนอนาคตไทยอย่างยั่งยืน</p>
                <p>วันที่ 13-15 พฤษภาคม พ.ศ. 2568</p>
            </div>
            <div class="container">
                ${content}
            </div>
            <div class="text-center mt-4 no-print">
                <button class="btn btn-primary" onclick="window.print()">พิมพ์เอกสาร</button>
                <button class="btn btn-secondary" onclick="window.close()">ปิด</button>
            </div>
            <script>
                // Hide elements not needed for print
                document.addEventListener('DOMContentLoaded', function() {
                    // Add print headers to each section
                    const tabPanes = document.querySelectorAll('.tab-pane');
                    tabPanes.forEach((pane, index) => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'registrant-separator';
                        wrapper.innerHTML = '<h4>ผู้ลงทะเบียนคนที่ ' + (index + 1) + '</h4>';
                        pane.insertBefore(wrapper, pane.firstChild);
                    });
                });
            </script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// ======================= PAYMENT FUNCTIONS =======================

/**
 * Setup payment form listener
 */
function setupPaymentFormListener() {
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadPaymentWithDate(new FormData(this));
        });
    }
}

/**
 * Upload payment with date
 * @param {FormData} formData - Form data
 */
function uploadPaymentWithDate(formData) {
    // Show loading
    showLoading('กำลังอัพโหลด');
    
    // Validate inputs
    const paymentDate = formData.get('payment_date');
    const paymentFile = formData.get('payment_slip');
    
    if (!paymentDate) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณาระบุวันที่และเวลาที่ชำระเงิน',
            text: 'โปรดกรอกวันที่และเวลาที่คุณชำระเงิน'
        });
        return;
    }
    
    if (!paymentFile || paymentFile.size === 0) {
        Swal.fire({
            icon: 'error',
            title: 'กรุณาเลือกไฟล์',
            text: 'คุณยังไม่ได้เลือกไฟล์หลักฐานการชำระเงิน'
        });
        return;
    }
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const fileType = paymentFile.type;
    
    if (!allowedTypes.includes(fileType)) {
        Swal.fire({
            icon: 'error',
            title: 'ประเภทไฟล์ไม่ถูกต้อง',
            text: 'กรุณาอัพโหลดไฟล์ภาพ (JPG, PNG, GIF) หรือ PDF เท่านั้น'
        });
        return;
    }
    
    // Validate file size
    if (paymentFile.size > 5 * 1024 * 1024) { // 5MB
        Swal.fire({
            icon: 'error',
            title: 'ขนาดไฟล์ใหญ่เกินไป',
            text: 'กรุณาอัพโหลดไฟล์ขนาดไม่เกิน 5MB'
        });
        return;
    }
    
    // Send data to server
    fetch('upload_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('ข้อมูลตอบกลับไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ');
        }
    })
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'อัพโหลดสำเร็จ',
                text: 'หลักฐานการชำระเงินถูกอัพโหลดเรียบร้อยแล้ว กรุณารอการตรวจสอบจากเจ้าหน้าที่',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                // Refresh page
                window.location.reload();
            });
        } else {
            throw new Error(result.message || 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์');
        }
    })
    .catch(error => {
        console.error('Error uploading file:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถอัพโหลดไฟล์ได้ กรุณาลองใหม่อีกครั้ง',
            confirmButtonText: 'ตกลง'
        });
    });
}

// ======================= FORM FUNCTIONS =======================

/**
 * Check registration status
 */
async function checkRegistration() {
    console.log('Starting registration check...');
    const phone = document.getElementById('checkPhone').value;
    console.log('Phone number:', phone);
    
    if (!phone) {
        console.log('No phone number provided');
        Swal.fire({
            icon: 'error',
            title: 'กรุณากรอกเบอร์โทรศัพท์',
            text: 'โปรดกรอกเบอร์โทรศัพท์เพื่อตรวจสอบสถานะ'
        });
        return;
    }

    try {
        showLoading('กำลังตรวจสอบข้อมูล');
        console.log('Sending request to server...');
        
        const response = await fetch('check_registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ phone: phone })
        });

        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        try {
            const result = JSON.parse(responseText);
            console.log('Parsed response:', result);
            
            if (!result.success) {
                throw new Error(result.message);
            }

            Swal.close();
            handleRegistrationStatus(result);
            
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.log('Invalid JSON response:', responseText);
            throw new Error('ข้อมูลที่ได้รับจากเซิร์ฟเวอร์ไม่ถูกต้อง');
        }

    } catch (error) {
        console.error('Error details:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถตรวจสอบสถานะได้ กรุณาลองใหม่อีกครั้ง'
        });
    }
}

/**
 * Handle registration status
 * @param {Object} data - Registration status data
 */
function handleRegistrationStatus(data) {
    const { status, message } = data;
    
    // Log data
    console.log("ข้อมูลสถานะการลงทะเบียน:", data);
    
    // Handle not registered case
    if (status === 'not_registered') {
        Swal.fire({
            title: 'ยังไม่ได้ลงทะเบียน',
            text: 'เบอร์โทรศัพท์นี้ยังไม่เคยลงทะเบียน คุณสามารถลงทะเบียนใหม่ได้ทันที',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'ลงทะเบียนใหม่',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // Save phone number for the form
                const phone = document.getElementById('checkPhone').value;
                
                // Show registration form
                document.getElementById('phoneCheck').classList.remove('active');
                document.getElementById('registrationForm').classList.add('active');
                
                // Load address fields and provinces
                loadAddressFields().then(() => {
                    // Auto-fill phone number
                    document.querySelector('input[name="phone"]').value = phone;
                });
                
                // Update progress stepper
                updateProgress(1);
            }
        });
        return;
    }
    
    // Fetch registration details
    fetchRegistrationDetails(data.data?.registration_id || data.registration_id);
}

// ======================= ADDRESS FUNCTIONS FOR FORM =======================

/**
 * Load address fields
 */
async function loadAddressFields() {
    const addressSections = ['invoiceAddress'];
    
    for (const section of addressSections) {
        document.getElementById(section).innerHTML = `
            <div class="mb-3">
                <label class="form-label required">ที่อยู่</label>
                <textarea class="form-control" name="${section}_address" 
                        rows="3" required placeholder="กรุณากรอกที่อยู่"></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label required">จังหวัด</label>
                    <select class="form-select" name="${section}_province" required 
                            onchange="loadDistricts(this.value, '${section}')">
                        <option value="">เลือกจังหวัด</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">อำเภอ/เขต</label>
                    <select class="form-select" name="${section}_district" required 
                            onchange="loadSubdistricts(this.value, '${section}')" disabled>
                        <option value="">เลือกอำเภอ/เขต</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">ตำบล/แขวง</label>
                    <select class="form-select" name="${section}_subdistrict" required 
                            onchange="updateZipcode(this.value, '${section}')" disabled>
                        <option value="">เลือกตำบล/แขวง</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label required">รหัสไปรษณีย์</label>
                    <input type="text" class="form-control" name="${section}_zipcode" 
                           required readonly>
                </div>
            </div>
        `;
    }
    
    await loadProvinces();
    
    // Set up form validation
    setupEnhancedValidation();
    
    updateProgress(1);
}

/**
 * Load provinces
 */
async function loadProvinces() {
    try {
        const response = await fetch('api/get_provinces.php');
        
        if (!response.ok) {
            throw new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('รูปแบบข้อมูลไม่ถูกต้อง');
        }

        const text = await response.text();
        const provinces = JSON.parse(text.trim());
        
        if (!Array.isArray(provinces)) {
            throw new Error('ข้อมูลจังหวัดไม่อยู่ในรูปแบบที่ถูกต้อง');
        }

        const provinceSelects = document.querySelectorAll('select[name$="_province"]');
        provinceSelects.forEach(select => {
            // Clear existing options
            select.innerHTML = '<option value="">เลือกจังหวัด</option>';
            
            // Add new options
            provinces.forEach(province => {
                const option = new Option(province.name_in_thai, province.id);
                select.add(option);
            });
        });

    } catch (error) {
        console.error('Error loading provinces:', error);
        showError('ไม่สามารถโหลดข้อมูลจังหวัดได้ กรุณาลองใหม่อีกครั้ง');
        throw error;
    }
}

/**
 * Load districts for selected province
 * @param {string|number} provinceId - Province ID
 * @param {string} section - Address section ID
 */
async function loadDistricts(provinceId, section) {
    try {
        const response = await fetch(`api/get_districts.php?province_id=${provinceId}`);
        const districts = await response.json();
        
        const districtSelect = document.querySelector(`select[name="${section}_district"]`);
        const subdistrictSelect = document.querySelector(`select[name="${section}_subdistrict"]`);
        
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ/เขต</option>';
        districtSelect.disabled = false;
        
        districts.forEach(district => {
            const option = new Option(district.name_in_thai, district.id);
            districtSelect.add(option);
        });
        
        // Reset subdistricts and zipcode
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        subdistrictSelect.disabled = true;
        document.querySelector(`input[name="${section}_zipcode"]`).value = '';
        
    } catch (error) {
        console.error('Error loading districts:', error);
        showError('ไม่สามารถโหลดข้อมูลอำเภอได้');
    }
}

/**
 * Load subdistricts for selected district
 * @param {string|number} districtId - District ID
 * @param {string} section - Address section ID
 */
async function loadSubdistricts(districtId, section) {
    try {
        const response = await fetch(`api/get_subdistricts.php?district_id=${districtId}`);
        const subdistricts = await response.json();
        
        const subdistrictSelect = document.querySelector(`select[name="${section}_subdistrict"]`);
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        subdistrictSelect.disabled = false;
        
        subdistricts.forEach(subdistrict => {
            const option = new Option(subdistrict.name_in_thai, subdistrict.id);
            subdistrictSelect.add(option);
        });
        
        // Reset zipcode
        document.querySelector(`input[name="${section}_zipcode"]`).value = '';
        
    } catch (error) {
        console.error('Error loading subdistricts:', error);
    }
}

/**
 * Update zipcode for selected subdistrict
 * @param {string|number} subdistrictId - Subdistrict ID
 * @param {string} section - Address section ID
 */
async function updateZipcode(subdistrictId, section) {
    try {
        const response = await fetch(`api/get_zipcode.php?subdistrict_id=${subdistrictId}`);
        
        if (!response.ok) {
            throw new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'ไม่สามารถโหลดข้อมูลรหัสไปรษณีย์ได้');
        }

        const zipcodeInput = document.querySelector(`input[name="${section}_zipcode"]`);
        zipcodeInput.value = data.zip_code;

    } catch (error) {
        console.error('Error loading zipcode:', error);
        const zipcodeInput = document.querySelector(`input[name="${section}_zipcode"]`);
        zipcodeInput.value = '';
    }
}

/**
 * Copy address from one section to another
 * @param {string} fromSection - Source section ID
 * @param {string} toSection - Target section ID
 */
function copyAddress(fromSection, toSection) {
    // Copy address text
    document.querySelector(`textarea[name="${toSection}_address"]`).value = 
        document.querySelector(`textarea[name="${fromSection}_address"]`).value;
    
    // Copy province and load districts
    const provinceSelect = document.querySelector(`select[name="${fromSection}_province"]`);
    const toProvinceSelect = document.querySelector(`select[name="${toSection}_province"]`);
    
    if (provinceSelect && toProvinceSelect) {
        toProvinceSelect.value = provinceSelect.value;
        
        loadDistricts(provinceSelect.value, toSection).then(() => {
            // Copy district and load subdistricts
            const districtSelect = document.querySelector(`select[name="${fromSection}_district"]`);
            const toDistrictSelect = document.querySelector(`select[name="${toSection}_district"]`);
            
            if (districtSelect && toDistrictSelect) {
                toDistrictSelect.value = districtSelect.value;
                
                loadSubdistricts(districtSelect.value, toSection).then(() => {
                    // Copy subdistrict and update zipcode
                    const subdistrictSelect = document.querySelector(`select[name="${fromSection}_subdistrict"]`);
                    const toSubdistrictSelect = document.querySelector(`select[name="${toSection}_subdistrict"]`);
                    
                    if (subdistrictSelect && toSubdistrictSelect) {
                        toSubdistrictSelect.value = subdistrictSelect.value;
                        updateZipcode(subdistrictSelect.value, toSection);
                    }
                });
            }
        });
    }
}

// ======================= REGISTRANT MANAGEMENT =======================

/**
 * Add new registrant form
 */
function addRegistrant() {
    const registrantsContainer = document.getElementById('registrants-container');
    const registrantCount = registrantsContainer.querySelectorAll('.registrant-item').length;
    
    if (registrantCount >= 15) { 
        Swal.fire({
            icon: 'info',
            title: 'ข้อจำกัดการลงทะเบียน',
            text: 'ท่านสามารถลงทะเบียนได้สูงสุด 15 คนต่อเบอร์โทรศัพท์' 
        });
        return;
    }
    
    const registrantItem = document.createElement('div');
    registrantItem.className = 'registrant-item';
    
    // Create header and delete button
    const headerDiv = document.createElement('div');
    headerDiv.className = 'd-flex justify-content-between align-items-center mb-3';
    headerDiv.innerHTML = `
        <h5 class="mb-0">ผู้สมัครคนที่ ${registrantCount + 1}</h5>
        <button type="button" class="btn btn-sm btn-outline-danger remove-registrant-btn">
            <i class="fas fa-times me-1"></i> ลบ
        </button>
    `;
    
    // Create form fields
    const formHTML = `
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label required">คำนำหน้าชื่อ</label>
                <select class="form-select" name="title_${registrantCount}" required>
                    <option value="">เลือกคำนำหน้าชื่อ</option>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="other">อื่นๆ</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label required">ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" name="fullname_${registrantCount}" required>
            </div>
            <div class="col-md-3 mb-3 title-other-container-${registrantCount} d-none">
                <label class="form-label required">ระบุคำนำหน้าชื่อ</label>
                <input type="text" class="form-control" name="title_other_${registrantCount}">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label required">หน่วยงาน</label>
                <input type="text" class="form-control" name="organization_${registrantCount}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label required">ตำแหน่ง</label>
                <input type="text" class="form-control" name="position_${registrantCount}" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label required">อีเมล <span style="color:red">(ใช้สำหรับรับวุฒิบัตร)</span></label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" name="email_${registrantCount}" required>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">LINE ID</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fab fa-line"></i>
                    </span>
                    <input type="text" class="form-control" name="line_id_${registrantCount}">
                </div>
            </div>
        </div>
    `;
    
    registrantItem.appendChild(headerDiv);
    registrantItem.insertAdjacentHTML('beforeend', formHTML);
    
    // Add to container
    registrantsContainer.appendChild(registrantItem);
    
    // Set up title selection event listener
    registrantItem.querySelector(`select[name="title_${registrantCount}"]`).addEventListener('change', function() {
        const titleOtherContainer = registrantItem.querySelector(`.title-other-container-${registrantCount}`);
        const titleOtherInput = registrantItem.querySelector(`input[name="title_other_${registrantCount}"]`);
        
        if (this.value === 'other') {
            titleOtherContainer.classList.remove('d-none');
            titleOtherInput.required = true;
        } else {
            titleOtherContainer.classList.add('d-none');
            titleOtherInput.required = false;
            titleOtherInput.value = '';
        }
    });
    
    // Set up delete button event listener
    registrantItem.querySelector('.remove-registrant-btn').addEventListener('click', function() {
        registrantsContainer.removeChild(registrantItem);
        
        // Update numbers
        updateRegistrantNumbers();
    });
    
    // Set up validation for new fields
    const newFields = registrantItem.querySelectorAll('[required]');
    newFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
    });
    
    // Animate new registrant
    requestAnimationFrame(() => {
        registrantItem.style.opacity = '0';
        registrantItem.style.transform = 'translateY(20px)';
        registrantItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        requestAnimationFrame(() => {
            registrantItem.style.opacity = '1';
            registrantItem.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Update registrant numbers
 */
function updateRegistrantNumbers() {
    const registrantItems = document.querySelectorAll('.registrant-item');
    registrantItems.forEach((item, index) => {
        item.querySelector('h5').textContent = `ผู้สมัครคนที่ ${index + 1}`;
    });
}

/**
 * Add document field
 */
function addDocumentField() {
    const documentsContainer = document.getElementById('documents-container');
    const documentCount = documentsContainer.querySelectorAll('.document-item').length;
    
    // Limit to 5 documents
    if (documentCount >= 5) {
        Swal.fire({
            icon: 'info',
            title: 'ข้อจำกัดการอัพโหลด',
            text: 'ท่านสามารถอัพโหลดเอกสารได้สูงสุด 5 รายการ'
        });
        return;
    }
    
    const documentItem = document.createElement('div');
    documentItem.className = 'document-item mb-3 p-3 border rounded';
    documentItem.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">เอกสารที่ ${documentCount + 1}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger remove-document-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">ประเภทเอกสาร</label>
                <select class="form-select" name="document_type[]">
                    <option value="identification">บัตรประชาชน/บัตรข้าราชการ</option>
                    <option value="certificate">วุฒิบัตร/ประกาศนียบัตร</option>
                    <option value="professional">เอกสารรับรองทางวิชาชีพ</option>
                    <option value="other">อื่นๆ</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">รายละเอียด</label>
                <input type="text" class="form-control" name="document_description[]" placeholder="อธิบายเอกสาร (ถ้ามี)">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">เอกสาร</label>
                <input type="file" class="form-control" name="documents[]" accept="image/*,.pdf">
                <small class="text-muted">รองรับไฟล์ภาพ (JPG, PNG, GIF) และ PDF ขนาดไม่เกิน 5MB</small>
            </div>
        </div>
    `;
    
    // Add to container
    documentsContainer.appendChild(documentItem);
    
    // Set up delete button
    documentItem.querySelector('.remove-document-btn').addEventListener('click', function() {
        documentsContainer.removeChild(documentItem);
    });
    
    // Animate new document
    requestAnimationFrame(() => {
        documentItem.style.opacity = '0';
        documentItem.style.transform = 'translateY(20px)';
        documentItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        requestAnimationFrame(() => {
            documentItem.style.opacity = '1';
            documentItem.style.transform = 'translateY(0)';
        });
    });
}

// ======================= PROGRESS FUNCTIONS =======================

/**
 * Update progress stepper
 * @param {number} step - Active step index
 */
function updateProgress(step) {
    const steps = document.querySelectorAll('.step');
    steps.forEach((stepEl, index) => {
        if (index < step) {
            stepEl.classList.add('completed');
            stepEl.classList.remove('active');
        } else if (index === step) {
            stepEl.classList.add('active');
            stepEl.classList.remove('completed');
        } else {
            stepEl.classList.remove('active', 'completed');
        }
    });
}

/**
 * Show help dialog
 */
function showHelp() {
    Swal.fire({
        title: 'ช่วยเหลือ',
        html: `
            <div class="text-start">
                <h5>ขั้นตอนการลงทะเบียน</h5>
                <p>1. กรอกเบอร์โทรศัพท์เพื่อตรวจสอบสถานะ</p>
                <p>2. กรอกข้อมูลส่วนตัวและที่อยู่</p>
                <p>3. อัพโหลดหลักฐานการชำระเงิน</p>
                <p>4. รอการตรวจสอบและยืนยัน</p>
                
                <h5 class="mt-4">การชำระเงิน</h5>
                <p>- โอนเงินเข้าบัญชีที่ระบุ</p>
                <p>- เก็บหลักฐานการโอนเงิน</p>
                <p>- อัพโหลดหลักฐานในระบบ</p>
                
                <h5 class="mt-4">ติดต่อสอบถาม</h5>
                <p>คุณชนิดาภา บุญเตี้ย (คุณนาว): 095-5439933</p>
                <p class="mt-3">
                    <a href="https://line.me/ti/g2/MVMMDL4KM05ML2EUDiuipEJaH6LtU6_6x-pfKw?utm_source=invitation&utm_medium=QR_code&utm_campaign=default" 
                       target="_blank" class="btn btn-success">
                        <i class="fab fa-line me-2"></i>เข้าร่วมกลุ่ม Line Open Chat
                    </a>
                </p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'เข้าใจแล้ว',
        customClass: {
            container: 'help-dialog',
            popup: 'help-dialog-popup'
        },
        width: '600px'
    });
}

// ======================= SUBMISSION PROCESSING =======================

/**
 * Process form submission
 * @param {FormData} formData - Form data
 */
async function processFormSubmission(formData) {
    showLoading('กำลังประมวลผลการลงทะเบียน');
    
    try {
        const response = await fetch('process_registration.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errorText = await response.text();
            
            let errorMessage = 'ไม่สามารถลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง';
            try {
                const errorData = JSON.parse(errorText);
                if (errorData.message) {
                    errorMessage = errorData.message;
                }
            } catch (jsonError) {
                console.error('ไม่สามารถแปลงข้อความ error เป็น JSON ได้', jsonError);
            }
            
            throw new Error(errorMessage);
        }

        const responseText = await response.text();
        const result = JSON.parse(responseText);

        if (result.success) {
            let successMessage = 'ลงทะเบียนสำเร็จ';
            
            // Show message based on payment status
            if (result.payment_uploaded) {
                successMessage += ' และอัพโหลดหลักฐานการชำระเงินเรียบร้อยแล้ว กรุณารอการตรวจสอบจากเจ้าหน้าที่';
            } else {
                successMessage += ' กรุณาอัพโหลดหลักฐานการชำระเงินเมื่อท่านชำระเงินเรียบร้อยแล้ว';
            }
            
            // Show registration count
            if (result.registration_count > 1) {
                successMessage += `\n\nมีผู้ลงทะเบียนทั้งหมด ${result.registration_count} คน`;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'ลงทะเบียนสำเร็จ',
                text: successMessage
            }).then(() => {
                window.location.reload();
            });
        } else {
            throw new Error(result.message || 'เกิดข้อผิดพลาดในการลงทะเบียน');
        }
    } catch (error) {
        console.error('เกิดข้อผิดพลาดในการส่งฟอร์ม:', error);
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: error.message || 'ไม่สามารถลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง'
        });
    }
}

// ======================= EVENT LISTENERS =======================

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Title selection event
    const titleSelect = document.querySelector('select[name="title"]');
    if (titleSelect) {
        titleSelect.addEventListener('change', function() {
            const titleOtherContainer = document.querySelector('.title-other-container');
            const titleOtherInput = document.querySelector('input[name="title_other"]');
            
            if (this.value === 'other') {
                titleOtherContainer.classList.remove('d-none');
                titleOtherInput.required = true;
            } else {
                titleOtherContainer.classList.add('d-none');
                titleOtherInput.required = false;
                titleOtherInput.value = '';
            }
        });
    }
    
    // Add document button
    const addDocumentBtn = document.getElementById('add-document-btn');
    if (addDocumentBtn) {
        addDocumentBtn.addEventListener('click', addDocumentField);
    }
    
    // Add registrant button
    const addRegistrantBtn = document.getElementById('add-registrant-btn');
    if (addRegistrantBtn) {
        addRegistrantBtn.addEventListener('click', addRegistrant);
    }
    
    // Form submission
    const seminarRegistration = document.getElementById('seminarRegistration');
    if (seminarRegistration) {
        seminarRegistration.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Client-side validation 
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                validateField(field);
                if (field.classList.contains('is-invalid')) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณากรอกข้อมูลให้ครบถ้วน',
                    text: 'โปรดตรวจสอบข้อมูลที่กรอกและแก้ไขข้อมูลที่ไม่ถูกต้อง'
                });
                
                // Scroll to first invalid field
                const firstInvalid = this.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return;
            }
            
            // If validation passed, process form
            processFormSubmission(new FormData(this));
        });
    }
    
    // Set up form validation
    setupEnhancedValidation();
});