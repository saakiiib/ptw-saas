<audio id="orderAlertSound" src="/sounds/order-alert.mp3" preload="auto"></audio>

<div style="position:fixed;top:140px;right:16px;z-index:9998;">
    <button id="orderQueueBtn" onclick="toggleOrderQueuePanel()"
        style="display:none;position:relative;background:#dc2626;border:none;cursor:pointer;padding:14px;border-radius:16px;box-shadow:0 4px 16px rgba(220,38,38,0.4);">
        <i class="ri-notification-3-fill" style="font-size:28px;color:white;"></i>
        <span id="orderQueueBadge"
            style="display:none;position:absolute;top:-6px;right:-6px;background:#f59e0b;color:white;font-size:11px;font-weight:700;border-radius:10px;padding:2px 7px;min-width:20px;text-align:center;line-height:18px;">
            0
        </span>
    </button>
</div>

<div id="orderQueuePanel"
    style="position:fixed;top:0;right:-420px;width:400px;height:100vh;background:white;box-shadow:-4px 0 20px rgba(0,0,0,0.15);z-index:9999;transition:right 0.3s ease;overflow-y:auto;">
    <div
        style="padding:16px 20px;background:#dc2626;color:white;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;">
        <strong><i class="ri-notification-3-line"></i> New Orders</strong>
        <button onclick="toggleOrderQueuePanel()"
            style="background:none;border:none;color:white;font-size:20px;cursor:pointer;">×</button>
    </div>
    <div id="orderQueueList" style="padding:12px;">
        <p style="text-align:center;color:#999;margin-top:20px;">No pending orders</p>
    </div>
</div>

<div id="orderModalOverlay"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
    <div
        style="background:#fff;border-radius:12px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        <div
            style="padding:20px 24px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                <strong>Website Order</strong>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span id="modalStatusBadge"
                    style="background:#22c55e;color:white;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;">NEW</span>
                <button onclick="closeOrderModal()"
                    style="border:none;background:none;font-size:20px;cursor:pointer;color:#999;">×</button>
            </div>
        </div>

        <div style="display:flex;">
            <div style="flex:1;padding:24px;border-right:1px solid #eee;">
                <div
                    style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:24px;" id="modalDeliveryIcon">🛍️</span>
                        <strong id="modalDeliveryType" style="font-size:16px;">Collection</strong>
                    </div>
                    <div>
                        <span style="font-size:12px;color:#888;">Time</span>
                        <strong id="modalTime" style="margin-left:6px;">ASAP</strong>
                    </div>
                </div>

                <div id="modalItems" style="margin-bottom:20px;"></div>

                <div
                    style="display:flex;justify-content:space-between;padding:12px 0;border-top:2px solid #eee;font-weight:700;font-size:16px;">
                    <span>TOTAL</span>
                    <span id="modalTotal">£0.00</span>
                </div>

                <div style="margin-top:12px;">
                    <span style="color:#888;font-size:13px;">PAYMENT</span><br>
                    <span id="modalPaymentMethod" style="color:#555;">Cash on Delivery</span>
                </div>

                <div id="modalNotesSection"
                    style="display:none;margin-top:16px;padding:12px;background:#fffbeb;border-radius:8px;">
                    <small style="color:#888;display:block;margin-bottom:4px;">Notes</small>
                    <span id="modalNotes"></span>
                </div>

                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #eee;font-size:12px;color:#aaa;">
                    <span id="modalOrderNumber"></span>
                </div>
            </div>

            <div style="width:220px;padding:24px;display:flex;flex-direction:column;gap:10px;">
                <div id="modalActionButtons" style="display:flex;flex-direction:column;gap:10px;"></div>

                <button onclick="printOrder()"
                    style="background:white;color:#333;border:1px solid #ddd;border-radius:8px;padding:12px;font-size:14px;cursor:pointer;margin-top:8px;">
                    🖨️ PRINT
                </button>

                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #eee;">
                    <strong id="modalCustomerName" style="display:block;margin-bottom:8px;font-size:15px;"></strong>
                    <div style="font-size:13px;color:#555;margin-bottom:6px;">📞 <span id="modalPhone"></span></div>
                    <div style="font-size:13px;color:#2563eb;">✉️ <span id="modalEmail"></span></div>
                    <div id="modalAddressSection" style="display:none;margin-top:8px;font-size:13px;color:#555;">
                        📍 <span id="modalAddress"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes pulse {

        0%,
        100% {
            transform: scale(1)
        }

        50% {
            transform: scale(1.1)
        }
    }

    .btn-pulse {
        animation: pulse 1.2s infinite;
    }
</style>

<script>
    let orderQueue = [];
    let currentOrder = null;
    let queuePanelOpen = false;

    document.addEventListener('DOMContentLoaded', loadPendingOrders);

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            loadPendingOrders();
        }
    });

    let seenOrderIds = new Set();

    async function loadPendingOrders() {
        try {
            const res = await fetch('/admin/orders/pending-queue', {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const data = await res.json();
            orderQueue = data.orders || [];
            updateQueueUI();

            // Only auto-open modal for orders not seen before
            const firstNew = orderQueue.find(o => o.status === 'new' && !seenOrderIds.has(o.id));
            if (firstNew) {
                seenOrderIds.add(firstNew.id);
                showOrderModal(firstNew);
            }
        } catch(e) { console.error(e); }
    }

    function updateQueueUI() {
        const btn = document.getElementById('orderQueueBtn');
        const badge = document.getElementById('orderQueueBadge');
        const list = document.getElementById('orderQueueList');

        btn.style.display = 'flex';

        if (orderQueue.length > 0) {
            badge.style.display = 'flex';
            badge.textContent = orderQueue.length;
            btn.classList.add('btn-pulse');

            const statusConfig = {
                new: {
                    color: '#f59e0b',
                    bg: '#fffbeb',
                    border: '#f59e0b',
                    label: 'NEW'
                },
                accepted: {
                    color: '#2563eb',
                    bg: '#eff6ff',
                    border: '#2563eb',
                    label: 'ACCEPTED'
                },
                preparing: {
                    color: '#0891b2',
                    bg: '#ecfeff',
                    border: '#0891b2',
                    label: 'PREPARING'
                },
                ready: {
                    color: '#6b7280',
                    bg: '#f9fafb',
                    border: '#6b7280',
                    label: 'READY'
                },
                delivered: {
                    color: '#16a34a',
                    bg: '#f0fdf4',
                    border: '#16a34a',
                    label: 'DELIVERED'
                },
                rejected: {
                    color: '#dc2626',
                    bg: '#fef2f2',
                    border: '#dc2626',
                    label: 'REJECTED'
                },
                delivery_failed: {
                    color: '#1f2937',
                    bg: '#f1f5f9',
                    border: '#1f2937',
                    label: 'DELIVERY FAILED'
                },
            };

            list.innerHTML = orderQueue.map((order, i) => {
                const s = statusConfig[order.status] || statusConfig['new'];
                return `
                <div onclick="showOrderByIndex(${i})"
                    style="padding:14px;border-bottom:1px solid #eee;cursor:pointer;border-left:4px solid ${s.border};background:${s.bg};">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <strong style="font-size:14px;">${order.order_number}</strong>
                        <span style="background:${s.color};color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">${s.label}</span>
                    </div>
                    <div style="font-size:13px;color:#555;margin-bottom:4px;">${order.first_name} ${order.last_name}</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                        <span style="font-weight:600;color:${s.color};">£${parseFloat(order.total).toFixed(2)}</span>
                        <span style="color:#999;">${order.delivery_type === 'delivery' ? '🚚' : '🛍️'} ${order.time || 'ASAP'}</span>
                    </div>
                </div>
            `;
            }).join('');
        } else {
            badge.style.display = 'none';
            btn.classList.remove('btn-pulse');
            list.innerHTML = '<p style="text-align:center;color:#999;margin-top:20px;">No pending orders</p>';
        }
    }

    function toggleOrderQueuePanel() {
        const panel = document.getElementById('orderQueuePanel');
        queuePanelOpen = !queuePanelOpen;
        panel.style.right = queuePanelOpen ? '0' : '-420px';
    }

    function showOrderByIndex(i) {
        if (orderQueue[i]) {
            showOrderModal(orderQueue[i]);
            if (queuePanelOpen) toggleOrderQueuePanel();
        }
    }

    function showOrderModal(order) {
        currentOrder = order;
        const isDelivery = order.delivery_type === 'delivery';

        document.getElementById('modalDeliveryIcon').textContent = isDelivery ? '🚚' : '🛍️';
        document.getElementById('modalDeliveryType').textContent = isDelivery ? 'Delivery' : 'Collection';
        document.getElementById('modalTime').textContent = order.time || 'ASAP';
        document.getElementById('modalTotal').textContent = `£${parseFloat(order.total).toFixed(2)}`;
        document.getElementById('modalOrderNumber').textContent = order.order_number;
        document.getElementById('modalCustomerName').textContent = `${order.first_name} ${order.last_name}`;
        document.getElementById('modalPhone').textContent = order.phone;
        document.getElementById('modalEmail').textContent = order.email;
        document.getElementById('modalPaymentMethod').textContent =
            order.payment_method === 'cash' ? 'Cash on Delivery' :
            order.payment_method === 'stripe' ? 'Stripe' : 'PayPal';

        let itemsHtml = '';
        (order.items || []).forEach(item => {
            itemsHtml += `<div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-weight:600;">
                <span>${item.quantity}x ${item.product_name}</span>
                <span>£${parseFloat(item.total).toFixed(2)}</span>
            </div>`;
            if (item.options && item.options.length) {
                itemsHtml +=
                    `<div style="font-size:13px;color:#666;margin-top:3px;">· ${item.options.map(o => o.option_name).join(', ')}</div>`;
            }
            itemsHtml += `</div>`;
        });
        document.getElementById('modalItems').innerHTML = itemsHtml;

        const notesSection = document.getElementById('modalNotesSection');
        if (order.notes) {
            notesSection.style.display = 'block';
            document.getElementById('modalNotes').textContent = order.notes;
        } else {
            notesSection.style.display = 'none';
        }

        const addrSection = document.getElementById('modalAddressSection');
        if (isDelivery) {
            addrSection.style.display = 'block';
            document.getElementById('modalAddress').textContent = [order.address_1, order.address_2, order.city, order
                .postcode
            ].filter(Boolean).join(', ');
        } else {
            addrSection.style.display = 'none';
        }

        renderActionButtons(order);
        document.getElementById('orderModalOverlay').style.display = 'flex';
    }

    function renderActionButtons(order) {
        const badge = document.getElementById('modalStatusBadge');
        const container = document.getElementById('modalActionButtons');

        const statusColors = {
            new: '#22c55e',
            accepted: '#2563eb',
            preparing: '#d97706',
            ready: '#8b5cf6',
            delivered: '#22c55e',
            rejected: '#dc2626',
            delivery_failed: '#dc2626'
        };

        badge.textContent = order.status.replace(/_/g, ' ').toUpperCase();
        badge.style.background = statusColors[order.status] || '#999';

        const btn = (label, status, bg) =>
            `<button onclick="handleOrderAction('${status}')"
            style="background:${bg};color:white;border:none;border-radius:8px;padding:13px;font-size:14px;font-weight:600;cursor:pointer;width:100%;margin-bottom:6px;">
            ${label}
        </button>`;

        let html = '';

        if (order.status === 'new') {
            html = btn('✓ ACCEPT', 'accepted', '#2563eb') +
                btn('✗ REJECT', 'rejected', '#dc2626');

        } else if (order.status === 'accepted') {
            html = btn('🍳 PREPARING', 'preparing', '#d97706');

        } else if (order.status === 'preparing') {
            html = btn('✅ READY', 'ready', '#8b5cf6');

        } else if (order.status === 'ready') {
            html = btn('🚚 DELIVERED', 'delivered', '#22c55e') +
                btn('✗ DELIVERY FAILED', 'delivery_failed', '#dc2626');

        } else {
            html = `<p style="text-align:center;color:#999;font-size:13px;">No further actions</p>`;
        }

        container.innerHTML = html;
    }

    async function handleOrderAction(newStatus) {
        if (!currentOrder) return;
        try {
            const res = await fetch(`/admin/orders/${currentOrder.id}/update-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    status: newStatus
                })
            });
            const data = await res.json();
            if (data.success) {
                currentOrder.status = newStatus;
                const terminal = ['rejected', 'delivered', 'delivery_failed'];
                if (terminal.includes(newStatus)) {
                    orderQueue = orderQueue.filter(o => o.id !== currentOrder.id);
                    updateQueueUI();
                    closeOrderModal();
                    showToast(
                        newStatus === 'delivered' ? '✅ Order Delivered!' :
                        newStatus === 'rejected' ? '❌ Order Rejected!' :
                        '⚠️ Delivery Failed!'
                    );
                    setTimeout(() => {
                        if (orderQueue.length > 0) showOrderModal(orderQueue[0]);
                    }, 600);
                } else {
                    renderActionButtons(currentOrder);
                    updateQueueUI();
                    showToast('✅ Status Updated!');
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    function closeOrderModal() {
        document.getElementById('orderModalOverlay').style.display = 'none';
        currentOrder = null;
    }

    function printOrder() {
        if (!currentOrder) return;
        window.open(`/admin/orders/${currentOrder.id}/print`, '_blank');
    }

    function showToast(message) {
        const t = document.createElement('div');
        t.textContent = message;
        t.style.cssText =
            'position:fixed;bottom:24px;right:24px;background:#333;color:white;padding:12px 24px;border-radius:8px;font-size:15px;z-index:9999999;';
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    function playSound() {
        const a = document.getElementById('orderAlertSound');
        if (a) {
            a.currentTime = 0;
            a.play().catch(() => {});
        }
    }

    async function handleNewOrderNotification(orderId) {
        playSound();
        try {
            const res = await fetch(`/admin/orders/${orderId}/popup`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const order = await res.json();
            if (!orderQueue.find(o => o.id === order.id)) {
                orderQueue.push(order);
                updateQueueUI();
                if (!currentOrder) showOrderModal(order);
            }
        } catch (e) {
            console.error(e);
        }
    }
</script>