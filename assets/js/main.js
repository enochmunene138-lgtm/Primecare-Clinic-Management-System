// ============================================================
// PrimeCare Clinic Management System — Main JavaScript
// ============================================================

// ── Live Clock ───────────────────────────────────────────────
function updateClock() {
    const el = document.getElementById('clockTime');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
    }
}
setInterval(updateClock, 1000);

// ── Sidebar Toggle ───────────────────────────────────────────
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main    = document.getElementById('mainContent');
    sidebar.classList.toggle('open');
    if (window.innerWidth > 900) {
        if (sidebar.style.width === '0px' || sidebar.style.width === '') {
            sidebar.style.width = 'var(--sidebar-w)';
            main.style.marginLeft = 'var(--sidebar-w)';
        } else {
            sidebar.style.width = '0';
            main.style.marginLeft = '0';
        }
    }
}

// ── Auto-dismiss flash messages ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashAlert');
    if (flash) {
        setTimeout(() => flash.style.opacity = '0', 4000);
        setTimeout(() => flash.remove(), 4500);
    }
});

// ── Confirm dialog ────────────────────────────────────────────
function confirmAction(msg, url) {
    if (confirm(msg || 'Are you sure?')) {
        window.location.href = url;
    }
}

// ── Real-time search table filter ─────────────────────────────
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('keyup', () => {
        const filter = input.value.toLowerCase();
        const rows   = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// ── Bill item calculator ──────────────────────────────────────
function calcBillTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty   = parseFloat(row.querySelector('.item-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
        const total = qty * price;
        const totalEl = row.querySelector('.item-total');
        if (totalEl) totalEl.textContent = 'KSh ' + total.toFixed(2);
        subtotal += total;
    });
    const discount   = parseFloat(document.getElementById('discount')?.value || 0);
    const grandTotal = Math.max(0, subtotal - discount);
    if (document.getElementById('subtotalDisplay'))
        document.getElementById('subtotalDisplay').textContent = 'KSh ' + subtotal.toFixed(2);
    if (document.getElementById('totalDisplay'))
        document.getElementById('totalDisplay').textContent = 'KSh ' + grandTotal.toFixed(2);
    if (document.getElementById('total_amount'))
        document.getElementById('total_amount').value = grandTotal.toFixed(2);
    if (document.getElementById('subtotal'))
        document.getElementById('subtotal').value = subtotal.toFixed(2);
}

// ── Add bill item row ─────────────────────────────────────────
let itemCount = 0;
function addItemRow(services) {
    itemCount++;
    const container = document.getElementById('billItems');
    const row = document.createElement('div');
    row.className = 'item-row d-flex gap-2 mb-2 align-items-center';
    row.innerHTML = `
        <select class="form-control form-select item-service" style="flex:2;"
            onchange="fillItemPrice(this, ${JSON.stringify(services)})">
            <option value="">-- Select service --</option>
            ${services.map(s =>
                `<option value="${s.service_id}"
                data-price="${s.price}">${s.service_name}</option>`
            ).join('')}
        </select>
        <input type="text" class="form-control item-desc"
            placeholder="Description" style="flex:2;" name="desc[]">
        <input type="number" class="form-control item-qty"
            value="1" min="1" style="width:70px;" name="qty[]"
            onchange="calcBillTotal()">
        <input type="number" class="form-control item-price"
            placeholder="Price" style="width:110px;" name="price[]"
            onchange="calcBillTotal()">
        <span class="item-total"
            style="min-width:90px;font-weight:600;color:var(--primary);">
            KSh 0.00
        </span>
        <button type="button" class="btn btn-sm btn-danger btn-icon"
            onclick="this.closest('.item-row').remove(); calcBillTotal()">
            <i class="bi bi-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

// ── Fill item price from service ──────────────────────────────
function fillItemPrice(select, services) {
    const row   = select.closest('.item-row');
    const opt   = select.options[select.selectedIndex];
    const price = opt?.dataset?.price || 0;
    const name  = opt?.text || '';
    row.querySelector('.item-price').value = price;
    row.querySelector('.item-desc').value  = name;
     calcBillTotal();
}