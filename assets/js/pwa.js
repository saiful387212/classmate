/**
 * ClassMate - PWA JavaScript
 * Handles PWA features and offline support
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ===== Splash Screen =====
    const splash = document.getElementById('splashScreen');
    if (splash) {
        setTimeout(function () {
            splash.classList.add('hidden');
            setTimeout(function () {
                splash.remove();
            }, 500);
        }, 1500);
    }

    // ===== Check if App is Installed =====
    function isAppInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;
    }

    // ===== Hide Install Prompt if Already Installed =====
    if (isAppInstalled()) {
        const installPrompt = document.querySelector('.install-prompt');
        if (installPrompt) {
            installPrompt.remove();
        }
    }

    // ===== Register Service Worker with Update Check =====
    if ('serviceWorker' in navigator) {
        let swRegistration;

        const manifestLink = document.querySelector('link[rel="manifest"]');
        const appRoot = manifestLink
            ? new URL('./', manifestLink.href)
            : new URL('./', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1));
        const serviceWorkerUrl = new URL('sw.js', appRoot);
        navigator.serviceWorker.register(serviceWorkerUrl, { scope: appRoot.pathname })
            .then(function (registration) {
                swRegistration = registration;
                console.log('Service Worker registered');

                // Check for updates every 60 seconds
                setInterval(function () {
                    registration.update();
                }, 60000);
            })
            .catch(function (error) {
                console.error('Service Worker registration failed:', error);
            });

        // Check for updates
        navigator.serviceWorker.ready.then(function (registration) {
            registration.update();
        });

        // Handle controller change
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    }

    // ===== Background Sync for Offline Submissions =====
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        navigator.serviceWorker.ready.then(function (registration) {
            registration.sync.register('sync-data')
                .then(function () {
                    console.log('Background sync registered');
                })
                .catch(function (error) {
                    console.error('Background sync registration failed:', error);
                });
        });
    }

    // ===== Store Offline Data in IndexedDB =====
    const dbName = 'ClassMateDB';
    const dbVersion = 1;
    let db;

    function openDB() {
        return new Promise(function (resolve, reject) {
            const request = indexedDB.open(dbName, dbVersion);

            request.onerror = function (event) {
                reject('Database error: ' + event.target.errorCode);
            };

            request.onsuccess = function (event) {
                db = event.target.result;
                resolve(db);
            };

            request.onupgradeneeded = function (event) {
                const db = event.target.result;

                // Create object stores
                if (!db.objectStoreNames.contains('offlineData')) {
                    const store = db.createObjectStore('offlineData', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('type', 'type', { unique: false });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };
        });
    }

    // ===== Save Data for Offline =====
    window.saveOfflineData = function (data) {
        openDB().then(function (db) {
            const transaction = db.transaction(['offlineData'], 'readwrite');
            const store = transaction.objectStore('offlineData');

            data.timestamp = new Date().toISOString();
            data.synced = false;

            const request = store.add(data);

            request.onsuccess = function () {
                console.log('Data saved for offline sync');
                showToast('Saved offline', 'success');
            };

            request.onerror = function () {
                console.error('Failed to save offline data');
            };
        }).catch(function (error) {
            console.error('Database error:', error);
        });
    };

    // ===== Sync Offline Data =====
    window.syncOfflineData = function () {
        if (!navigator.onLine) {
            showToast('You are offline. Data will sync when online.', 'warning');
            return;
        }

        openDB().then(function (db) {
            const transaction = db.transaction(['offlineData'], 'readonly');
            const store = transaction.objectStore('offlineData');
            const index = store.index('synced');
            const request = index.getAll(false);

            request.onsuccess = function () {
                const unsyncedData = request.result;

                if (unsyncedData.length === 0) {
                    showToast('All data synced!', 'success');
                    return;
                }

                // Send each item to server
                unsyncedData.forEach(function (item) {
                    fetch('/api/sync.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(item.data)
                    })
                        .then(function (response) {
                            if (response.ok) {
                                // Mark as synced
                                const updateTransaction = db.transaction(['offlineData'], 'readwrite');
                                const updateStore = updateTransaction.objectStore('offlineData');
                                const updateRequest = updateStore.get(item.id);

                                updateRequest.onsuccess = function () {
                                    const data = updateRequest.result;
                                    data.synced = true;
                                    updateStore.put(data);
                                };
                            }
                        })
                        .catch(function () {
                            console.error('Failed to sync item:', item.id);
                        });
                });

                showToast('Synced ' + unsyncedData.length + ' items', 'success');
            };
        }).catch(function (error) {
            console.error('Sync error:', error);
        });
    };

    // ===== Auto-sync on Online Event =====
    window.addEventListener('online', function () {
        showToast('Online - Syncing data...', 'info');
        setTimeout(function () {
            syncOfflineData();
        }, 2000);
    });
});

// ===== Network Status Check =====
function checkNetworkStatus() {
    if (navigator.onLine) {
        document.body.classList.remove('offline');
    } else {
        document.body.classList.add('offline');
    }
}

// Check network status periodically
setInterval(checkNetworkStatus, 5000);
checkNetworkStatus();