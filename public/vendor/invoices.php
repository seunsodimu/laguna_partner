<?php
/**
 * Vendor Invoice Management
 * View, manage, and track invoices
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

Auth::requireAuth(['vendor']);

$db = Database::getInstance();
$user = Auth::user();

$pageTitle = 'Invoice Management';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .container-main {
        margin-top: 30px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .stat-card h5 {
        color: #666;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .stat-card .value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0d6efd;
    }

    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 10px 12px;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .status-draft { background-color: #e2e3e5; color: #383d41; }
    .status-submitted { background-color: #fff3cd; color: #664d03; }
    .status-under_review { background-color: #cfe2ff; color: #084298; }
    .status-approved { background-color: #d1e7dd; color: #0f5132; }
    .status-processing { background-color: #d1ecf1; color: #0c5460; }
    .status-paid { background-color: #d1e7dd; color: #0f5132; }

    .invoice-number {
        font-weight: 700;
        color: var(--primary);
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        justify-content: center;
    }

    .action-buttons .btn {
        padding: 4px 8px;
        font-size: 0.85rem;
    }
</style>

<div class="container-main">
    <div class="container">
        <!-- Page Title -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h2" style="color: var(--primary); font-weight: 700;">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Invoice Management
                </h1>
                <p class="text-muted">Manage your invoices, track payments, and view payment status</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Total Invoices</h5>
                    <div class="value" id="stat-total-invoices">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Submitted</h5>
                    <div class="value" id="stat-submitted-invoices" style="color: var(--warning);">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Approved</h5>
                    <div class="value" id="stat-approved-invoices" style="color: var(--success);">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Total Amount</h5>
                    <div class="value" id="stat-total-amount" style="color: var(--info); font-size: 1.5rem;">$0</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-invoices" data-bs-toggle="tab" data-bs-target="#content-invoices" type="button" role="tab">
                    <i class="fas fa-list me-2"></i> My Invoices
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-payments" data-bs-toggle="tab" data-bs-target="#content-payments" type="button" role="tab">
                    <i class="fas fa-money-bill me-2"></i> Payment History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-profile" data-bs-toggle="tab" data-bs-target="#content-profile" type="button" role="tab">
                    <i class="fas fa-user me-2"></i> My Profile
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- INVOICES TAB -->
            <div class="tab-pane fade show active" id="content-invoices" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="invoice-search" placeholder="Search invoice number...">
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="<?php echo BASE_PATH; ?>/vendor/create-invoice.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Create Invoice
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-table-body">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-spinner fa-spin me-2"></i> Loading invoices...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PAYMENTS TAB -->
            <div class="tab-pane fade" id="content-payments" role="tabpanel">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Payment #</th>
                                    <th>Invoice #</th>
                                    <th>Payment Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Expected Arrival</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payments-table-body">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-spinner fa-spin me-2"></i> Loading payments...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PROFILE TAB -->
            <div class="tab-pane fade" id="content-profile" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-edit me-2"></i> Company Information
                            </div>
                            <div class="card-body">
                                <form id="form-profile">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="profile-company-name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tax ID</label>
                                            <input type="text" class="form-control" id="profile-tax-id">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="profile-email" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" id="profile-phone">
                                        </div>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3">Primary Contact</h5>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" id="profile-primary-name">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" id="profile-primary-email">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" id="profile-primary-phone">
                                        </div>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3">Billing Address</h5>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Address 1</label>
                                            <input type="text" class="form-control" id="profile-billing-addr1">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Address 2</label>
                                            <input type="text" class="form-control" id="profile-billing-addr2">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" id="profile-billing-city">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">State</label>
                                            <input type="text" class="form-control" id="profile-billing-state">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">ZIP</label>
                                            <input type="text" class="form-control" id="profile-billing-zip">
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-primary" onclick="saveProfile()">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-file-upload me-2"></i> Documents
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#modalUploadDocument">
                                        <i class="fas fa-plus me-2 text-primary"></i> Upload W-9
                                    </button>
                                    <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#modalUploadDocument">
                                        <i class="fas fa-plus me-2 text-primary"></i> Upload Insurance Certificate
                                    </button>
                                    <button class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#modalUploadDocument">
                                        <i class="fas fa-plus me-2 text-primary"></i> Upload Tax Exemption
                                    </button>
                                </div>
                                <div id="documents-list" class="mt-3"></div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-credit-card me-2"></i> Payment Methods
                            </div>
                            <div class="card-body">
                                <div id="payment-methods-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: View Invoice -->
<div class="modal fade" id="modalViewInvoice" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-invoice-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-invoice-content">
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Upload Document -->
<div class="modal fade" id="modalUploadDocument" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i> Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-upload-document" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Document Type *</label>
                        <select class="form-select" id="document-type" required>
                            <option selected disabled>Select document type...</option>
                            <option value="w9">W-9 Form</option>
                            <option value="w8">W-8 Form</option>
                            <option value="insurance_certificate">Insurance Certificate</option>
                            <option value="tax_exemption">Tax Exemption Certificate</option>
                            <option value="other">Other Document</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">File *</label>
                        <input type="file" class="form-control" id="document-file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" id="document-expiration">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="document-notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="uploadDocument()">
                    <i class="fas fa-upload me-2"></i> Upload
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    const API_URL = '/api/';
    let currentInvoiceId = null;

    window.addEventListener('DOMContentLoaded', () => {
        loadInvoices();
        loadPaymentHistory();
        loadVendorProfile();
        loadInvoiceStatistics();
    });

    function loadInvoices() {
        fetch(`${API_URL}invoices.php?action=list`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        renderInvoicesTable(data.data);
                    } else {
                        console.error('API returned error:', data);
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                }
            })
            .catch(err => console.error('Error loading invoices:', err));
    }

    function renderInvoicesTable(invoices) {
        const tbody = document.getElementById('invoices-table-body');
        if (invoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No invoices found</td></tr>';
            return;
        }

        tbody.innerHTML = invoices.map(inv => `
            <tr>
                <td><span class="invoice-number">${inv.invoice_number}</span></td>
                <td>${new Date(inv.invoice_date).toLocaleDateString()}</td>
                <td>${inv.due_date ? new Date(inv.due_date).toLocaleDateString() : '-'}</td>
                <td>$${parseFloat(inv.amount_total).toFixed(2)}</td>
                <td><span class="badge status-${inv.status}">${inv.status.replace('_', ' ').toUpperCase()}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-info" onclick="viewInvoice(${inv.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${inv.status === 'draft' ? `
                            <button class="btn btn-sm btn-warning" onclick="editInvoice(${inv.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-success" onclick="submitInvoice(${inv.id})">
                                <i class="fas fa-paper-plane"></i> Submit
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function submitInvoice(invoiceId) {
        if (confirm('Are you sure you want to submit this invoice for review?')) {
            fetch(`${API_URL}invoices.php?action=submit&id=${invoiceId}`, {
                method: 'POST'
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('Invoice submitted successfully!');
                        loadInvoices();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                    alert('Error: ' + e.message);
                }
            })
            .catch(err => {
                alert('Error submitting invoice: ' + err.message);
            });
        }
    }

    function viewInvoice(invoiceId) {
        fetch(`${API_URL}invoices.php?action=get&id=${invoiceId}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const inv = data.data;
                        let html = `
                            <div class="invoice-view">
                                <h5>${inv.invoice_number}</h5>
                                <hr>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Invoice Date:</strong> ${new Date(inv.invoice_date).toLocaleDateString()}</p>
                                        <p><strong>Due Date:</strong> ${inv.due_date ? new Date(inv.due_date).toLocaleDateString() : '-'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Status:</strong> <span class="badge status-${inv.status}">${inv.status.toUpperCase()}</span></p>
                                        <p><strong>Total Amount:</strong> $${parseFloat(inv.amount_total).toFixed(2)}</p>
                                    </div>
                                </div>
                                <h6>Line Items</h6>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${inv.line_items.map(item => `
                                            <tr>
                                                <td>${item.description}</td>
                                                <td>${item.quantity}</td>
                                                <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                                                <td>$${parseFloat(item.amount).toFixed(2)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                        document.getElementById('modal-invoice-title').textContent = 'Invoice ' + inv.invoice_number;
                        document.getElementById('modal-invoice-content').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('modalViewInvoice')).show();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to load invoice'));
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                    alert('Error: ' + e.message);
                }
            })
            .catch(err => alert('Error loading invoice: ' + err.message));
    }

    function loadPaymentHistory() {
        fetch(`${API_URL}payments.php?action=history`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        renderPaymentsTable(data.data);
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                }
            })
            .catch(err => console.error('Error loading payments:', err));
    }

    function renderPaymentsTable(payments) {
        const tbody = document.getElementById('payments-table-body');
        if (payments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">No payments found</td></tr>';
            return;
        }

        tbody.innerHTML = payments.map(pay => `
            <tr>
                <td><strong>${pay.payment_number}</strong></td>
                <td>${pay.invoice_number}</td>
                <td>${new Date(pay.payment_date).toLocaleDateString()}</td>
                <td>$${parseFloat(pay.amount_paid).toFixed(2)}</td>
                <td>${pay.payment_method.toUpperCase()}</td>
                <td><span class="badge status-${pay.status}">${pay.status.toUpperCase()}</span></td>
                <td>${pay.expected_arrival_date ? new Date(pay.expected_arrival_date).toLocaleDateString() : '-'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="downloadReceipt(${pay.id})">
                        <i class="fas fa-download"></i> Receipt
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function downloadReceipt(paymentId) {
        window.location.href = `${API_URL}payments.php?action=generate_receipt&id=${paymentId}`;
    }

    function loadVendorProfile() {
        fetch(`${API_URL}vendor-profile.php?action=get`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        populateProfileForm(data.data);
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                }
            })
            .catch(err => console.error('Error loading profile:', err));
    }

    function populateProfileForm(profile) {
        document.getElementById('profile-company-name').value = profile.company_name || '';
        document.getElementById('profile-email').value = profile.email || '';
        document.getElementById('profile-phone').value = profile.phone || '';
        document.getElementById('profile-tax-id').value = profile.tax_id || '';
        document.getElementById('profile-primary-name').value = profile.primary_contact_name || '';
        document.getElementById('profile-primary-email').value = profile.primary_contact_email || '';
        document.getElementById('profile-primary-phone').value = profile.primary_contact_phone || '';
        document.getElementById('profile-billing-addr1').value = profile.billing_address_1 || '';
        document.getElementById('profile-billing-addr2').value = profile.billing_address_2 || '';
        document.getElementById('profile-billing-city').value = profile.billing_city || '';
        document.getElementById('profile-billing-state').value = profile.billing_state || '';
        document.getElementById('profile-billing-zip').value = profile.billing_zip || '';
    }

    function saveProfile() {
        const data = {
            company_name: document.getElementById('profile-company-name').value,
            email: document.getElementById('profile-email').value,
            phone: document.getElementById('profile-phone').value,
            tax_id: document.getElementById('profile-tax-id').value,
            primary_contact_name: document.getElementById('profile-primary-name').value,
            primary_contact_email: document.getElementById('profile-primary-email').value,
            primary_contact_phone: document.getElementById('profile-primary-phone').value,
            billing_address_1: document.getElementById('profile-billing-addr1').value,
            billing_address_2: document.getElementById('profile-billing-addr2').value,
            billing_city: document.getElementById('profile-billing-city').value,
            billing_state: document.getElementById('profile-billing-state').value,
            billing_zip: document.getElementById('profile-billing-zip').value
        };

        fetch(`${API_URL}vendor-profile.php?action=update`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    alert('Profile updated successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Invalid JSON response:', text.substring(0, 300));
                alert('Error: ' + e.message);
            }
        })
        .catch(err => alert('Error updating profile: ' + err.message));
    }

    function uploadDocument() {
        const formData = new FormData();
        formData.append('document_type', document.getElementById('document-type').value);
        formData.append('file', document.getElementById('document-file').files[0]);
        formData.append('expiration_date', document.getElementById('document-expiration').value);
        formData.append('notes', document.getElementById('document-notes').value);

        fetch(`${API_URL}vendor-profile.php?action=upload_document`, {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    alert('Document uploaded successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('modalUploadDocument')).hide();
                    document.getElementById('form-upload-document').reset();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Invalid JSON response:', text.substring(0, 300));
                alert('Error: ' + e.message);
            }
        })
        .catch(err => alert('Error uploading document: ' + err.message));
    }

    function loadInvoiceStatistics() {
        fetch(`${API_URL}invoices.php?action=statistics`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const stats = data.data;
                        document.getElementById('stat-total-invoices').textContent = stats.total_invoices || 0;
                        document.getElementById('stat-submitted-invoices').textContent = stats.submitted_count || 0;
                        document.getElementById('stat-approved-invoices').textContent = stats.approved_count || 0;
                        document.getElementById('stat-total-amount').textContent = '$' + (parseFloat(stats.total_amount || 0).toFixed(2));
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text.substring(0, 300));
                }
            })
            .catch(err => console.error('Error loading statistics:', err));
    }

    document.getElementById('invoice-search').addEventListener('input', (e) => {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('#invoices-table-body tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
