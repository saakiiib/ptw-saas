<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js"></script>

<script>
    firebase.initializeApp({
        apiKey: "AIzaSyDV3T5-qJy8Djb2o_YRs4LF5bEsKCb07rE",
        authDomain: "ptw-saas.firebaseapp.com",
        projectId: "ptw-saas",
        storageBucket: "ptw-saas.firebasestorage.app",
        messagingSenderId: "97656475516",
        appId: "1:97656475516:web:2d40fd29bf38343e595ef9"
    });

    const messaging = firebase.messaging();

    async function initFCM() {
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            await navigator.serviceWorker.ready;

            const token = await messaging.getToken({
                vapidKey: '{{ config("services.firebase.vapid_key") }}',
                serviceWorkerRegistration: registration
            });

            if (token) {
                await fetch('/admin/fcm-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ token })
                });
                console.log('FCM token saved!');
            }
        } catch (err) {
            console.error('FCM Error:', err);
        }
    }

    function showNotificationBanner() {
        const banner = document.createElement('div');
        banner.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;background:#dc2626;color:white;padding:12px 20px;z-index:999999;display:flex;align-items:center;justify-content:space-between;">
                <span>⚠️ Notification permission is blocked. You won't receive new order alerts!</span>
                <a href="https://support.google.com/chrome/answer/3220216" target="_blank" 
                    style="background:white;color:#dc2626;padding:6px 14px;border-radius:6px;font-weight:600;font-size:13px;text-decoration:none;">
                    How to Enable
                </a>
            </div>
        `;
        document.body.prepend(banner);
    }

    initFCM();

    messaging.onMessage(function(payload) {
        if (payload.data && payload.data.type === 'new_order') {
            handleNewOrderNotification(payload.data.order_id);
        }
    });
</script>