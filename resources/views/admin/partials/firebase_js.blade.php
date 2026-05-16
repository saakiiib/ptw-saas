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
            const token = await messaging.getToken({
                vapidKey: '{{ config('services.firebase.vapid_key') }}'
            });

            if (token) {
                await fetch('/admin/fcm-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        token
                    })
                });
            }
        } catch (err) {
            console.error('FCM Error:', err);
        }
    }

    initFCM();

    messaging.onMessage(function(payload) {
        if (payload.data && payload.data.type === 'new_order') {
            handleNewOrderNotification(payload.data.order_id);
        }
    });
</script>