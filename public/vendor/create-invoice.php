<?php
/**
 * Create Invoice Page
 * Allows vendors to create new invoices with purchase orders and items
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

$pageTitle = 'Create Invoice';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .form-section {
        background: white;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .form-section h4 {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 12px;
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

    .po-search-wrapper {
        position: relative;
        margin-bottom: 20px;
    }

    .po-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        max-height: 300px;
        overflow-y: auto;
        display: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .po-search-results button {
        width: 100%;
        text-align: left;
        padding: 12px 15px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .po-search-results button:hover {
        background: #f8f9fa;
    }

    .po-selected-table td {
        vertical-align: middle;
    }

    .po-items-row {
        background: #fafafa;
    }

    .po-chevron {
        display: inline-block;
        transition: transform 0.3s ease;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 30px;
    }

    .form-actions .btn {
        min-width: 140px;
    }

    .breadcrumb-nav {
        margin-bottom: 25px;
    }
</style>

<div class="container" style="margin-top: 30px; margin-bottom: 30px; max-width: 1200px;">
    <div class="breadcrumb-nav">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/vendor/invoices.php">Invoices</a></li>
                <li class="breadcrumb-item active">Create Invoice</li>
            </ol>
        </nav>
    </div>

    <h1 class="h2 mb-4" style="color: var(--primary); font-weight: 700;">
        <i class="fas fa-file-invoice-dollar me-2"></i> Create New Invoice
    </h1>

    <form id="form-create-invoice">
        <!-- Basic Information Section -->
        <div class="form-section">
            <h4><i class="fas fa-info-circle me-2"></i> Invoice Information</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Number *</label>
                    <input type="text" class="form-control" id="invoice-number" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Date *</label>
                    <input type="date" class="form-control" id="invoice-date" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Type *</label>
                    <select class="form-select" id="invoice-type" required>
                        <option value="" selected disabled>Select type</option>
                        <option value="down_payment">Down Payment</option>
                        <option value="regular">Regular Invoice</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" id="due-date-label">Due Date </label>
                    <input type="date" class="form-control" id="invoice-due-date" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Total Amount *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="invoice-amount" step="100" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders Section -->
        <div class="form-section">
            <h4><i class="fas fa-cube me-2"></i> Purchase Orders</h4>
            <div class="mb-3 po-search-wrapper">
                <input type="text" class="form-control" id="po-search" placeholder="Search and add purchase orders...">
                <div class="po-search-results" id="po-search-results"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm po-selected-table">
                    <thead>
                        <tr>
                            <th>PO#</th>
                            <th>PO Value</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="selected-po-table-body">
                        <tr>
                            <td colspan="3" class="text-center text-muted">No purchase orders added</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="form-section">
            <h4><i class="fas fa-file-upload me-2"></i> Documents & Notes</h4>
            <div class="mb-3">
                <label class="form-label">Invoice Document *</label>
                <input type="file" class="form-control" id="invoice-document" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required>
                <small class="text-muted">Supported formats: PDF, JPG, PNG, GIF, WebP (Max 100MB)</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="invoice-description" rows="4" placeholder="Add any additional notes..."></textarea>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="form-actions">
            <a href="<?php echo BASE_PATH; ?>/vendor/invoices.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> Cancel
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="saveInvoice('draft')">
                <i class="fas fa-save me-2"></i> Save as Draft
            </button>
            <button type="button" class="btn btn-primary" onclick="saveInvoice('submit')">
                <i class="fas fa-paper-plane me-2"></i> Submit for Approval
            </button>
        </div>
    </form>
</div>


<script>
    const API_URL = '/api/';
    let availablePurchaseOrders = [];
    const selectedPurchaseOrders = new Map();

    window.addEventListener('DOMContentLoaded', () => {
        loadPurchaseOrdersForInvoice();
        initializeFormHandlers();
        
        const urlParams = new URLSearchParams(window.location.search);
        const poId = urlParams.get('po_id');
        if (poId) {
            setTimeout(() => {
                addPurchaseOrderToInvoice(poId);
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 500);
        }
    });

    function initializeFormHandlers() {
        const invoiceDateInput = document.getElementById('invoice-date');
        if (invoiceDateInput) {
            invoiceDateInput.addEventListener('change', updateDueDate);
        }

        const searchInput = document.getElementById('po-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                renderPurchaseOrderOptions(searchInput.value.trim(), true);
            });
            searchInput.addEventListener('focus', () => {
                renderPurchaseOrderOptions(searchInput.value.trim(), true);
            });
        }

        const resultsContainer = document.getElementById('po-search-results');
        if (resultsContainer) {
            resultsContainer.addEventListener('click', event => {
                const button = event.target.closest('button[data-po]');
                if (!button) {
                    return;
                }
                addPurchaseOrderToInvoice(button.getAttribute('data-po'));
            });
        }

        const selectedTableBody = document.getElementById('selected-po-table-body');
        if (selectedTableBody) {
            selectedTableBody.addEventListener('click', event => {
                const button = event.target.closest('[data-remove-po]');
                if (!button) {
                    return;
                }
                removeSelectedPurchaseOrder(button.getAttribute('data-remove-po'));
            });
        }

        document.addEventListener('click', event => {
            const wrapper = document.querySelector('.po-search-wrapper');
            if (!wrapper) {
                return;
            }
            if (!wrapper.contains(event.target)) {
                const container = document.getElementById('po-search-results');
                if (container) {
                    container.style.display = 'none';
                }
            }
        });
    }

    function updateDueDate() {
        const invoiceDateInput = document.getElementById('invoice-date');
        const dueDateInput = document.getElementById('invoice-due-date');
        const dueDateLabel = document.getElementById('due-date-label');
        
        if (!invoiceDateInput.value) {
            dueDateInput.value = '';
            dueDateLabel.textContent = 'Due Date ';
            return;
        }
        
        fetch(`${API_URL}vendor-profile.php?action=get_term_info`)
            .then(res => res.json())
            .then(data => {
                let dueDays = 30;
                let termLabel = '';
                
                if (data.success && data.data) {
                    if (data.data.invoice_due_days !== null) {
                        dueDays = data.data.invoice_due_days;
                    }
                    if (data.data.term) {
                        termLabel = ` (${data.data.term})`;
                    }
                }
                
                dueDateLabel.textContent = `Due Date ${termLabel}`;
                
                if (dueDays === -1) {
                    dueDateInput.value = '';
                    return;
                }
                
                const invoiceDate = new Date(invoiceDateInput.value);
                const dueDate = new Date(invoiceDate);
                dueDate.setDate(dueDate.getDate() + dueDays);
                
                const year = dueDate.getFullYear();
                const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                const day = String(dueDate.getDate()).padStart(2, '0');
                
                dueDateInput.value = `${year}-${month}-${day}`;
            })
            .catch(err => {
                console.error('Error fetching term info:', err);
                dueDateLabel.textContent = 'Due Date (No Terms)';
                
                const invoiceDate = new Date(invoiceDateInput.value);
                const dueDate = new Date(invoiceDate);
                dueDate.setDate(dueDate.getDate() + 30);
                
                const year = dueDate.getFullYear();
                const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                const day = String(dueDate.getDate()).padStart(2, '0');
                
                dueDateInput.value = `${year}-${month}-${day}`;
            });
    }

    function loadPurchaseOrdersForInvoice() {
        fetch(`${API_URL}purchase-orders.php?for_invoice=1`)
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
                        availablePurchaseOrders = (data.data || []).map(po => ({
                            id: String(po.id),
                            tran_id: po.tran_id || po.po_number || `PO ${po.id}`,
                            total_amount: Number(po.total_amount || 0)
                        }));
                        renderPurchaseOrderOptions('');
                    } else {
                        alert(data.message || 'Failed to load purchase orders');
                    }
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    alert('Error: ' + e.message + '\n\nResponse: ' + text.substring(0, 200));
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alert('Failed to load purchase orders: ' + err.message);
            });
    }

    function renderPurchaseOrderOptions(filter = '', forceShow = false) {
        const container = document.getElementById('po-search-results');
        if (!container) {
            return;
        }
        const term = filter ? filter.toLowerCase() : '';
        const options = availablePurchaseOrders.filter(po => {
            if (selectedPurchaseOrders.has(po.id)) {
                return false;
            }
            if (!term) {
                return true;
            }
            const numberMatch = (po.tran_id || '').toLowerCase().includes(term);
            const valueMatch = Number(po.total_amount || 0).toFixed(2).includes(term);
            return numberMatch || valueMatch;
        });
        if (options.length === 0) {
            container.innerHTML = '<div class="px-3 py-2 text-muted">No purchase orders available</div>';
            container.style.display = forceShow && term ? 'block' : 'none';
            return;
        }
        container.innerHTML = options.map(po => {
            const value = Number(po.total_amount || 0).toFixed(2);
            return `
                <button type="button" data-po="${po.id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>${po.tran_id}</strong></span>
                        <span>$${value}</span>
                    </div>
                </button>
            `;
        }).join('');
        container.style.display = forceShow || term ? 'block' : 'none';
    }

    function addPurchaseOrderToInvoice(poId) {
        const id = String(poId);
        if (selectedPurchaseOrders.has(id)) {
            return;
        }
        const po = availablePurchaseOrders.find(item => item.id === id);
        if (!po) {
            return;
        }
        selectedPurchaseOrders.set(id, {
            id: id,
            tran_id: po.tran_id || `PO ${id}`,
            total_amount: Number(po.total_amount || 0),
            amount_billed: 0
        });
        renderSelectedPurchaseOrders();
        const searchInput = document.getElementById('po-search');
        if (searchInput) {
            searchInput.value = '';
        }
        renderPurchaseOrderOptions('');
        const container = document.getElementById('po-search-results');
        if (container) {
            container.style.display = 'none';
        }
    }

    function renderSelectedPurchaseOrders() {
        const tbody = document.getElementById('selected-po-table-body');
        if (!tbody) {
            return;
        }
        if (selectedPurchaseOrders.size === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No purchase orders added</td></tr>';
            return;
        }
        const rows = Array.from(selectedPurchaseOrders.values()).map(po => {
            const poValue = Number(po.total_amount || 0);
            return `
                <tr data-po-id="${po.id}">
                    <td>
                        <span style="cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-right: 8px;" data-bs-toggle="collapse" data-bs-target="#po-items-${po.id}" role="button">
                            <i class="fas fa-chevron-right po-chevron" style="width: 16px; color: #0d6efd;"></i>
                            <span>${po.tran_id}</span>
                        </span>
                    </td>
                    <td>$${poValue.toFixed(2)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-po="${po.id}">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
                <tr class="po-items-row">
                    <td colspan="3" class="p-0">
                        <div class="collapse" id="po-items-${po.id}" data-po-id="${po.id}">
                            <div class="card card-body p-3">
                                <div class="po-items-table-container" data-po-id="${po.id}">
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-spinner fa-spin me-2"></i> Loading items...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        tbody.innerHTML = rows;
        
        tbody.querySelectorAll('[data-bs-toggle="collapse"]').forEach(trigger => {
            const target = trigger.getAttribute('data-bs-target');
            const collapseElement = document.querySelector(target);
            if (collapseElement) {
                collapseElement.addEventListener('show.bs.collapse', function(e) {
                    if (!this.dataset.loaded) {
                        this.dataset.loaded = 'true';
                        const poId = this.getAttribute('data-po-id');
                        loadPurchaseOrderItems(poId);
                    }
                    const chevron = trigger.querySelector('.po-chevron');
                    if (chevron) {
                        chevron.classList.remove('fa-chevron-right');
                        chevron.classList.add('fa-chevron-down');
                    }
                });
                collapseElement.addEventListener('hide.bs.collapse', function(e) {
                    const chevron = trigger.querySelector('.po-chevron');
                    if (chevron) {
                        chevron.classList.remove('fa-chevron-down');
                        chevron.classList.add('fa-chevron-right');
                    }
                });
            }
        });
    }

    function loadPurchaseOrderItems(poId) {
        const container = document.querySelector(`.po-items-table-container[data-po-id="${poId}"]`);
        if (!container) {
            return;
        }

        fetch(`${API_URL}purchase-orders.php?id=${poId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderPurchaseOrderItems(poId, data.data);
                } else {
                    container.innerHTML = '<div class="alert alert-warning mb-0">Failed to load items</div>';
                }
            })
            .catch(err => {
                console.error('Error loading PO items:', err);
                container.innerHTML = '<div class="alert alert-danger mb-0">Error loading items</div>';
            });
    }

    function renderPurchaseOrderItems(poId, poData) {
        const container = document.querySelector(`.po-items-table-container[data-po-id="${poId}"]`);
        if (!container) {
            return;
        }

        const items = poData.items || [];
        if (items.length === 0) {
            container.innerHTML = '<div class="alert alert-info mb-0">No items in this purchase order</div>';
            return;
        }

        let totalUnbilled = 0;

        const itemsHtml = items.map(item => {
            const quantity = Number(item.quantity || 0);
            const unitPrice = Number(item.rate || 0);
            const lineTotal = quantity * unitPrice;
            const quantityBilled = 0;
            const totalBilledAmount = quantityBilled * unitPrice;
            const unbilledAmount = Math.max(lineTotal - totalBilledAmount, 0);

            totalUnbilled += unbilledAmount;

            return `
                <tr data-item-id="${item.id}">
                    <td style="width: 45%">${item.item_name || '-'}</td>
                    <td>$${unitPrice.toFixed(2)}</td>
                    <td>${quantity}</td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 120px;">
                            <input type="number" class="form-control item-qty-billed" data-po-id="${poId}" data-item-id="${item.id}" min="0" max="${quantity}" step="0.1" value="0" placeholder="0">
                        </div>
                    </td>
                    <td data-total-billed="${item.id}">$0.00</td>
                    <td data-unbilled-amount="${item.id}">$${unbilledAmount.toFixed(2)}</td>
                </tr>
            `;
        }).join('');

        const tableHtml = `
            <table class="table table-sm mb-0 table-bordered">
                <thead>
                    <tr>
                        <th class="w-30">Item</th>
                        <th>Rate</th>
                        <th>Quantity</th>
                        <th>Quantity Billed</th>
                        <th>Total Billed</th>
                        <th>Unbilled Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                    <tr style="font-weight: 600; border-top: 2px solid #ddd;">
                        <td colspan="4" class="text-end">Total:</td>
                        <td data-items-total-billed="${poId}">$0.00</td>
                        <td data-items-total-unbilled="${poId}">$${totalUnbilled.toFixed(2)}</td>
                    </tr>
                </tbody>
            </table>
        `;

        container.innerHTML = tableHtml;

        container.querySelectorAll('.item-qty-billed').forEach(input => {
            input.addEventListener('input', updateItemBilledAmount);
            input.addEventListener('blur', updateItemBilledAmount);
        });
    }

    function updateItemBilledAmount(event) {
        const input = event.target;
        const poId = input.getAttribute('data-po-id');
        const itemId = input.getAttribute('data-item-id');
        const container = document.querySelector(`.po-items-table-container[data-po-id="${poId}"]`);
        if (!container) {
            return;
        }

        const row = input.closest('tr');
        const unitPriceCell = row.querySelector('td:nth-child(2)');
        const unitPrice = parseFloat(unitPriceCell.textContent.replace('$', '')) || 0;

        let quantityBilled = parseFloat(input.value);
        if (!Number.isFinite(quantityBilled) || quantityBilled < 0) {
            quantityBilled = 0;
        }

        const maxQuantity = parseFloat(row.querySelector('td:nth-child(3)').textContent);
        if (quantityBilled > maxQuantity) {
            quantityBilled = maxQuantity;
            input.value = quantityBilled.toFixed(2);
        } else {
            input.value = quantityBilled.toFixed(2);
        }

        const totalBilledAmount = quantityBilled * unitPrice;
        const unbilledAmount = Math.max((maxQuantity * unitPrice) - totalBilledAmount, 0);

        row.querySelector(`[data-total-billed="${itemId}"]`).textContent = '$' + totalBilledAmount.toFixed(2);
        row.querySelector(`[data-unbilled-amount="${itemId}"]`).textContent = '$' + unbilledAmount.toFixed(2);

        updateItemsTableTotals(poId, container);
    }

    function updateItemsTableTotals(poId, container) {
        let grandTotalBilled = 0;
        let grandTotalUnbilled = 0;

        container.querySelectorAll('tbody tr:not(:last-child)').forEach(row => {
            const totalBilledCell = row.querySelector('[data-total-billed]');
            const unbilledCell = row.querySelector('[data-unbilled-amount]');

            if (totalBilledCell && unbilledCell) {
                const billed = parseFloat(totalBilledCell.textContent.replace('$', '')) || 0;
                const unbilled = parseFloat(unbilledCell.textContent.replace('$', '')) || 0;
                grandTotalBilled += billed;
                grandTotalUnbilled += unbilled;
            }
        });

        const totalBilledCell = container.querySelector(`[data-items-total-billed="${poId}"]`);
        const totalUnbilledCell = container.querySelector(`[data-items-total-unbilled="${poId}"]`);

        if (totalBilledCell) {
            totalBilledCell.textContent = '$' + grandTotalBilled.toFixed(2);
        }
        if (totalUnbilledCell) {
            totalUnbilledCell.textContent = '$' + grandTotalUnbilled.toFixed(2);
        }
    }

    function removeSelectedPurchaseOrder(poId) {
        const id = String(poId);
        if (!selectedPurchaseOrders.has(id)) {
            return;
        }
        selectedPurchaseOrders.delete(id);
        renderSelectedPurchaseOrders();
        renderPurchaseOrderOptions('');
    }

    async function checkInvoiceNumberExists(invoiceNumber) {
        try {
            const response = await fetch(`${API_URL}invoices.php?action=check_number&invoice_number=${encodeURIComponent(invoiceNumber)}`);
            const data = await response.json();
            return data.exists === true;
        } catch (err) {
            console.error('Error checking invoice number:', err);
            return false;
        }
    }

    function getPurchaseOrderPayload() {
        return Array.from(selectedPurchaseOrders.values()).map(po => {
            let itemLevelBilled = 0;
            const container = document.querySelector(`.po-items-table-container[data-po-id="${po.id}"]`);
            if (container) {
                const totalBilledCell = container.querySelector(`[data-items-total-billed="${po.id}"]`);
                if (totalBilledCell) {
                    itemLevelBilled = parseFloat(totalBilledCell.textContent.replace('$', '')) || 0;
                }
            }
            return {
                po_id: Number(po.id),
                po_number: po.tran_id,
                po_value: Number(po.total_amount || 0),
                amount_billed: itemLevelBilled
            };
        });
    }

    async function saveInvoice(action) {
        const invoiceNumber = document.getElementById('invoice-number').value.trim();
        const invoiceDate = document.getElementById('invoice-date').value;
        const invoiceType = document.getElementById('invoice-type').value;
        const dueDate = document.getElementById('invoice-due-date').value;
        const invoiceAmountValue = document.getElementById('invoice-amount').value;
        const invoiceTotal = parseFloat(invoiceAmountValue);

        if (!invoiceNumber || !invoiceDate || !invoiceType) {
            alert('Please complete all required invoice fields.');
            return;
        }

        const isDuplicate = await checkInvoiceNumberExists(invoiceNumber);
        if (isDuplicate) {
            alert('Invoice number already exists for your vendor account. Please use a different invoice number.');
            return;
        }

        if (!Number.isFinite(invoiceTotal) || invoiceTotal <= 0) {
            alert('Please enter a valid invoice total amount.');
            return;
        }

        const poLineItems = getPurchaseOrderPayload();

        if (poLineItems.length === 0) {
            alert('Add at least one purchase order before saving.');
            return;
        }

        const totalPOBilled = poLineItems.reduce((sum, item) => sum + item.amount_billed, 0);
        const totalPOValue = poLineItems.reduce((sum, item) => sum + item.po_value, 0);

        if (totalPOBilled > invoiceTotal) {
            alert('Please ensure total amount billed from purchase orders matches the invoice total.');
            return;
        }

        const lineItems = [];
        let lineNumber = 1;

        poLineItems.forEach(po => {
            lineItems.push({
                description: po.po_number ? `PO ${po.po_number}` : `PO ${po.po_id}`,
                quantity: 1,
                unit_price: po.amount_billed,
                amount: po.amount_billed,
                line_number: lineNumber++,
                reference: String(po.po_id),
                po_id: po.po_id,
                po_value: po.po_value,
                po_amount_billed: po.amount_billed
            });
        });

        const payload = {
            invoice_number: invoiceNumber,
            invoice_date: invoiceDate,
            due_date: dueDate || null,
            amount_total: invoiceTotal,
            description: document.getElementById('invoice-description').value,
            invoice_type: invoiceType,
            line_items: lineItems,
            po_line_items: poLineItems
        };

        const documentInput = document.getElementById('invoice-document');
        
        // Validate document upload
        if (!documentInput || documentInput.files.length === 0) {
            alert('Please upload an invoice document (PDF or image file).');
            return;
        }

        const file = documentInput.files[0];
        const allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxFileSize = 100 * 1024 * 1024; // 100MB
        
        // Check file type
        if (!allowedMimes.includes(file.type)) {
            alert('Invalid file type. Please upload a PDF document or image file (JPG, PNG, GIF, or WebP).');
            return;
        }
        
        // Check file size
        if (file.size > maxFileSize) {
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
            alert(`File is too large. Your file is ${fileSizeMB}MB, but the maximum allowed size is 100MB.`);
            return;
        }
        
        // Check file extension matches MIME type
        const fileName = file.name.toLowerCase();
        const allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png', '.gif', '.webp'];
        const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
        
        if (!hasValidExtension) {
            alert('Invalid file extension. Please upload a PDF document or image file (.pdf, .jpg, .png, .gif, or .webp).');
            return;
        }

        const formData = new FormData();
        formData.append('payload', JSON.stringify(payload));
        formData.append('document', file);

        fetch(`${API_URL}invoices.php?action=create`, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // If action is 'submit', submit the invoice after creating it
                if (action === 'submit') {
                    return submitInvoiceAfterCreate(data.invoice_id);
                }
                showSuccessMessage('Invoice saved as draft successfully!');
                setTimeout(() => {
                    window.location.href = '<?php echo BASE_PATH; ?>/vendor/invoices.php';
                }, 1500);
            } else {
                alert('Error: ' + (data.error || data.message || 'Unknown error'));
            }
        })
        .catch(err => alert('Error creating invoice: ' + err.message));
    }

    function submitInvoiceAfterCreate(invoiceId) {
        return fetch(`${API_URL}invoices.php?action=submit&id=${invoiceId}`, {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage('Invoice submitted for approval successfully!');
                setTimeout(() => {
                    window.location.href = '<?php echo BASE_PATH; ?>/vendor/invoices.php';
                }, 1500);
            } else {
                alert('Error submitting invoice: ' + (data.error || data.message || 'Unknown error'));
            }
        })
        .catch(err => alert('Error submitting invoice: ' + err.message));
    }

    function showSuccessMessage(message) {
        // Create a temporary success notification
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
