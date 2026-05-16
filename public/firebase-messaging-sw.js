importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyDV3T5-qJy8Djb2o_YRs4LF5bEsKCb07rE",
    authDomain: "ptw-saas.firebaseapp.com",
    projectId: "ptw-saas",
    storageBucket: "ptw-saas.firebasestorage.app",
    messagingSenderId: "97656475516",
    appId: "1:97656475516:web:2d40fd29bf38343e595ef9"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
    // no popup notification
});