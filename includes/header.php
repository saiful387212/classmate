<?php
/**
 * ClassMate - Main Header
 * Includes navigation, user profile, notifications, and search
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <script>
        (function () {
            try {
                if (localStorage.getItem('classmate-theme') === 'dark') {
                    document.documentElement.dataset.theme = 'dark';
                }
            } catch (error) {
                console.warn('Unable to read the saved theme preference.', error);
            }
        })();
    </script>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo asset('icons/favicon.ico'); ?>" type="image/x-icon">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    // 1. Define your manifest data
    var manifestData = {
        "name": "ClassMate - Class Representative Management System",
        "short_name": "ClassMate",
        "start_url": "/",
        "display": "standalone",
        "background_color": "#0F766E",
        "theme_color": "#0F766E",
        "icons": [
            {
                "src": "/assets/icons/web-app-manifest-192x192.png",
                "sizes": "192x192",
                "type": "image/png"
            },
            {
                "src": "/assets/icons/web-app-manifest-512x512.png",
                "sizes": "512x512",
                "type": "image/png"
            }
        ]
    };

    // 2. Convert to JSON and create a Blob
    var stringManifest = JSON.stringify(manifestData);
    var blob = new Blob([stringManifest], {type: 'application/json'});
    var manifestURL = URL.createObjectURL(blob);

    // 3. Create and append the link tag
    var link = document.createElement('link');
    link.rel = 'manifest';
    link.href = manifestURL;
    document.head.appendChild(link);
})();
    </script>
    
    <!-- Custom CSS -->
        <link rel="stylesheet" href="<?php echo asset('css/style.css') . '?v=' . APP_VERSION; ?>">
        <link rel="stylesheet" href="<?php echo asset('css/components.css') . '?v=' . APP_VERSION; ?>">
        <link rel="stylesheet" href="<?php echo asset('css/responsive.css') . '?v=' . APP_VERSION; ?>">
    
    <?php 
    // Page specific CSS
    if (isset($pageCss)) {
        if (is_array($pageCss)) {
            foreach ($pageCss as $css) {
                 echo '<link rel="stylesheet" href="' . asset('css/' . $css) . '?v=' . APP_VERSION . '">';
            }
        } else {
              echo '<link rel="stylesheet" href="' . asset('css/' . $pageCss) . '?v=' . APP_VERSION . '">';
        }
    }
    
    // Admin specific CSS
    if (isset($isAdmin) && $isAdmin) {
           echo '<link rel="stylesheet" href="' . asset('css/admin.css') . '?v=' . APP_VERSION . '">';
    }
    
    // Student specific CSS
    if (isset($isStudent) && $isStudent) {
        echo '<link rel="stylesheet" href="' . asset('css/student.css') . '?v=' . APP_VERSION . '">';
    }
    ?>
    <link rel="stylesheet" href="<?php echo asset('css/theme.css') . '?v=' . APP_VERSION; ?>">
    <!-- ===== PWA META TAGS ===== -->
    <!-- Basic PWA Meta -->
   
    <meta name="theme-color" content="#0F766E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ClassMate">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="ClassMate">

    <!-- Favicon & Touch Icons -->
    <link rel="icon" href="<?php echo asset('icons/favicon.ico'); ?>" type="image/x-icon">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo asset('icons/favicon-96x96.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo asset('icons/apple-touch-icon.png'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo asset('icons/apple-touch-icon.png'); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo asset('icons/web-app-manifest-192x192.png'); ?>">
    <link rel="apple-touch-icon" sizes="512x512" href="<?php echo asset('icons/web-app-manifest-512x512.png'); ?>">

    <!-- PWA Install Modal Styles -->
    <style>
    @keyframes pwaFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes pwaScaleUp { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
    .pwa-step-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 12px 14px;
        text-align: left;
    }
    .pwa-step-badge {
        background: #4F46E5;
        color: #ffffff;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .pwa-step-text {
        font-size: 0.875rem;
        color: #374151;
        line-height: 1.45;
        flex: 1;
    }
    .pwa-inline-icon {
        color: #4F46E5;
        margin: 0 3px;
        display: inline-block;
    }
    </style>

    <!-- Service Worker & PWA Logic -->
    <script>
    window.BASE_URL = '<?php echo BASE_URL; ?>';
    window.API_NOTIFICATIONS_URL = '<?php echo url('api/notifications.php'); ?>';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            // Use root-relative path for proper scope on mobile
            var swUrl = '<?php echo url('sw.js'); ?>';
            navigator.serviceWorker.register(swUrl, { scope: '<?php echo url(''); ?>' })
                .then(function(registration) {
                    console.log('ClassMate ServiceWorker registered with scope:', registration.scope);
                })
                .catch(function(error) {
                    console.warn('ClassMate ServiceWorker registration error:', error);
                });
        });
    }

    // ===== Update App Function =====
    function updateApp() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(function(registration) {
                if (registration) {
                    registration.update();
                    alert('Updating ClassMate... Please wait.');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            });
        }
    }

    // ===== PWA Installation Logic =====
    let deferredPrompt = null;
    let installPromptElement = null;

    function isAppInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.matchMedia('(display-mode: fullscreen)').matches ||
               window.navigator.standalone === true;
    }

    function getClientPlatform() {
        const ua = navigator.userAgent || '';
        const isIOS = (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) ||
                      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /Android/i.test(ua);
        const isMobile = isIOS || isAndroid || /webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua) || window.innerWidth <= 768;
        const isChrome = /Chrome|CriOS/i.test(ua) && !/Edge|Edg|OPR|Opera|SamsungBrowser/i.test(ua);
        const isEdge = /Edg|Edge/i.test(ua);
        const isSamsung = /SamsungBrowser/i.test(ua);
        const isFirefox = /Firefox|FxiOS/i.test(ua);
        const isSafari = /^((?!chrome|android).)*safari/i.test(ua) || (isIOS && !isChrome && !isFirefox && !isEdge);

        return { ua, isIOS, isAndroid, isMobile, isChrome, isEdge, isSamsung, isFirefox, isSafari };
    }

    // Capture install prompt event as soon as browser fires it
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        window.deferredPrompt = e;
        console.log('ClassMate PWA: beforeinstallprompt captured');
        
        if (!isAppInstalled() && !sessionStorage.getItem('dismissedInstallPrompt')) {
            createInstallPrompt();
        }
    });

    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
        window.deferredPrompt = null;
        dismissInstall();
        closeInstallModal();
        showToast('ClassMate installed successfully! Open it from your home screen.', 'success');
    });

    // Display banner after short delay if not installed and not dismissed
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (!isAppInstalled() && !sessionStorage.getItem('dismissedInstallPrompt')) {
                createInstallPrompt();
            }
        }, 2500);
    });

    function createInstallPrompt() {
        if (installPromptElement || isAppInstalled()) {
            return;
        }

        installPromptElement = document.createElement('div');
        installPromptElement.id = 'pwaInstallBanner';
        installPromptElement.className = 'install-prompt';
        installPromptElement.innerHTML = `
            <div class="install-content" style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1;">
                    <img src="<?php echo asset('icons/web-app-manifest-192x192.png'); ?>" alt="ClassMate" style="width:36px;height:36px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.15);flex-shrink:0;">
                    <div style="min-width:0;">
                        <div style="font-weight:600;font-size:0.875rem;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Install ClassMate</div>
                        <div style="font-size:0.75rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Quick access on mobile</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                    <button type="button" onclick="installApp()" class="btn btn-sm btn-primary" style="padding:6px 14px;font-size:0.8rem;font-weight:600;border-radius:6px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-download"></i> Install
                    </button>
                    <button type="button" onclick="dismissInstall()" class="btn-outline" style="background:none;border:none;font-size:1.25rem;color:#9ca3af;cursor:pointer;padding:4px 8px;line-height:1;" aria-label="Dismiss">×</button>
                </div>
            </div>
        `;
        document.body.appendChild(installPromptElement);
    }

    function dismissInstall() {
        sessionStorage.setItem('dismissedInstallPrompt', 'true');
        const prompt = document.getElementById('pwaInstallBanner') || document.querySelector('.install-prompt');
        if (prompt) {
            prompt.remove();
            installPromptElement = null;
        }
    }

    function closeInstallModal() {
        const modal = document.getElementById('classmateInstallModal');
        if (modal) modal.remove();
    }

    function installApp() {
        if (isAppInstalled()) {
            showToast('ClassMate is already installed and running!', 'success');
            return;
        }

        // 1. If native deferredPrompt is ready, trigger native browser prompt
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(choiceResult) {
                if (choiceResult.outcome === 'accepted') {
                    showToast('Installing ClassMate...', 'success');
                }
                deferredPrompt = null;
                dismissInstall();
                closeInstallModal();
            });
            return;
        }

        // 2. Check if connection is non-HTTPS on a remote origin
        if (!window.isSecureContext) {
            showInsecureContextModal();
            return;
        }

        // 3. Fallback: Show tailored, visual install instructions for user's platform/browser
        const platform = getClientPlatform();
        showInstallGuide(platform);
    }

    function showInstallGuide(platform) {
        closeInstallModal();
        const appIcon = '<?php echo asset("icons/web-app-manifest-192x192.png"); ?>';

        let title = 'Install ClassMate App';
        let subtitle = 'Add ClassMate to your home screen for quick access and full offline features:';
        let stepsHtml = '';

        if (platform.isIOS) {
            title = 'Install on iPhone & iPad';
            subtitle = 'Follow these 3 simple steps in Safari or Chrome:';
            stepsHtml = `
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">1</div>
                    <div class="pwa-step-text">
                        Tap the <strong>Share</strong> button <i class="fas fa-arrow-up-from-bracket pwa-inline-icon"></i> in your browser bar (bottom in Safari, top-right in Chrome/iPad).
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">2</div>
                    <div class="pwa-step-text">
                        Scroll down and tap <strong><i class="far fa-plus-square pwa-inline-icon"></i> Add to Home Screen</strong>.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">3</div>
                    <div class="pwa-step-text">
                        Tap <strong>Add</strong> in the top-right corner. ClassMate is now installed on your home screen!
                    </div>
                </div>
            `;
        } else if (platform.isSamsung) {
            title = 'Install on Samsung Internet';
            subtitle = 'Add ClassMate in Samsung Internet:';
            stepsHtml = `
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">1</div>
                    <div class="pwa-step-text">
                        Tap the <strong>Menu</strong> <i class="fas fa-bars pwa-inline-icon"></i> button at the bottom-right.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">2</div>
                    <div class="pwa-step-text">
                        Tap <strong><i class="fas fa-plus pwa-inline-icon"></i> Add page to</strong>.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">3</div>
                    <div class="pwa-step-text">
                        Select <strong>App screen</strong> or <strong>Home screen</strong>.
                    </div>
                </div>
            `;
        } else if (platform.isAndroid) {
            title = 'Install on Android';
            subtitle = 'Add ClassMate directly from your browser:';
            stepsHtml = `
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">1</div>
                    <div class="pwa-step-text">
                        Tap the <strong>menu</strong> (three dots <i class="fas fa-ellipsis-vertical pwa-inline-icon"></i>) in the top-right corner of Chrome/browser.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">2</div>
                    <div class="pwa-step-text">
                        Choose <strong><i class="fas fa-download pwa-inline-icon"></i> Install app</strong> or <strong>Add to Home screen</strong>.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">3</div>
                    <div class="pwa-step-text">
                        Tap <strong>Install</strong> to confirm. ClassMate will launch as a full native app!
                    </div>
                </div>
            `;
        } else {
            title = 'Install ClassMate App';
            subtitle = 'Install ClassMate on your device:';
            stepsHtml = `
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">1</div>
                    <div class="pwa-step-text">
                        Click the <strong>Install icon</strong> <i class="fas fa-download pwa-inline-icon"></i> in the browser address bar.
                    </div>
                </div>
                <div class="pwa-step-card">
                    <div class="pwa-step-badge">2</div>
                    <div class="pwa-step-text">
                        Or open the browser menu <i class="fas fa-ellipsis-vertical pwa-inline-icon"></i> and click <strong>Install ClassMate</strong>.
                    </div>
                </div>
            `;
        }

        const modal = document.createElement('div');
        modal.id = 'classmateInstallModal';
        modal.innerHTML = `
            <div style="position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;animation:pwaFadeIn 0.25s ease;">
                <div style="background:#ffffff;border-radius:18px;max-width:440px;width:100%;box-shadow:0 20px 40px -8px rgba(0,0,0,0.3);overflow:hidden;animation:pwaScaleUp 0.25s cubic-bezier(0.16,1,0.3,1);">
                    <div style="background:linear-gradient(135deg,#4F46E5 0%,#3730A3 100%);padding:20px;color:#fff;display:flex;align-items:center;gap:14px;position:relative;">
                        <img src="${appIcon}" alt="ClassMate" style="width:48px;height:48px;border-radius:12px;background:#fff;padding:2px;box-shadow:0 4px 12px rgba(0,0,0,0.25);flex-shrink:0;">
                        <div style="flex:1;min-width:0;">
                            <h3 style="margin:0 0 2px;font-size:1.1rem;font-weight:700;color:#ffffff;line-height:1.2;">${title}</h3>
                            <p style="margin:0;font-size:0.8rem;color:#E0E7FF;line-height:1.3;">Class Representative System</p>
                        </div>
                        <button type="button" onclick="closeInstallModal()" style="position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;" aria-label="Close">×</button>
                    </div>
                    <div style="padding:20px;">
                        <p style="margin:0 0 14px;font-size:0.875rem;color:#4B5563;line-height:1.45;">${subtitle}</p>
                        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;">
                            ${stepsHtml}
                        </div>
                        <div style="background:#EEF2FF;border:1px dashed #6366F1;border-radius:10px;padding:10px 12px;font-size:0.8rem;color:#3730A3;margin-bottom:16px;line-height:1.4;">
                            <i class="fas fa-circle-info"></i> <strong>Already installed on PC?</strong> Chrome suppresses re-installation for apps already installed on your computer. In DevTools, go to <strong>Application → Manifest → click "Install"</strong>.
                        </div>
                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" onclick="closeInstallModal()" class="btn btn-secondary" style="padding:9px 18px;border-radius:8px;font-weight:600;font-size:0.875rem;">Close</button>
                            ${platform.isAndroid ? '<button type="button" onclick="retryInstallPrompt()" class="btn btn-primary" style="padding:9px 18px;border-radius:8px;font-weight:600;font-size:0.875rem;display:flex;align-items:center;gap:6px;"><i class=\"fas fa-arrows-rotate\"></i> Try Direct Install</button>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function retryInstallPrompt() {
        if (deferredPrompt) {
            closeInstallModal();
            deferredPrompt.prompt();
        } else {
            showToast('Please open the browser menu (⋮) and tap "Install app".', 'info');
        }
    }

    function showInsecureContextModal() {
        closeInstallModal();
        const modal = document.createElement('div');
        modal.id = 'classmateInstallModal';
        modal.innerHTML = `
            <div style="position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;">
                <div style="background:#ffffff;border-radius:18px;max-width:440px;width:100%;box-shadow:0 20px 40px -8px rgba(0,0,0,0.3);overflow:hidden;">
                    <div style="background:#EF4444;padding:18px;color:#fff;display:flex;align-items:center;gap:12px;">
                        <i class="fas fa-lock" style="font-size:1.5rem;"></i>
                        <div style="flex:1;">
                            <h3 style="margin:0;font-size:1.05rem;font-weight:700;">HTTPS Required for Mobile App</h3>
                        </div>
                        <button type="button" onclick="closeInstallModal()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;" aria-label="Close">×</button>
                    </div>
                    <div style="padding:20px;">
                        <p style="margin:0 0 12px;font-size:0.875rem;color:#374151;line-height:1.5;">
                            Mobile browsers strictly require a secure <strong>HTTPS connection</strong> to install progressive web apps.
                        </p>
                        <div style="background:#F3F4F6;border-radius:8px;padding:12px;font-size:0.825rem;color:#4B5563;margin-bottom:16px;line-height:1.45;">
                            <strong>To install on your phone:</strong><br>
                            • Open ClassMate on the live HTTPS site: <strong>https://classmate.site.je</strong><br>
                            • Or if testing locally on Wi-Fi, open in Chrome: <code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code> and add your local IP.
                        </div>
                        <div style="text-align:right;">
                            <button type="button" onclick="closeInstallModal()" class="btn btn-primary" style="padding:9px 18px;border-radius:8px;">Got It</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

// ===== Offline Status Detection =====
window.addEventListener('online', function() {
    showToast('Back online!', 'success');
    // Refresh data
    location.reload();
});

window.addEventListener('offline', function() {
    showToast('You are offline. Some features may be unavailable.', 'warning');
});

// ===== Toast for offline status =====
function showToast(message, type) {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
</script>
</head>
<body>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <?php if (Auth::isLoggedIn()): ?>
    <!-- ===== Main Application Container ===== -->
    <div class="app-container">
        
        <!-- ===== Sidebar ===== -->
            <?php if (Authorization::isAdmin()): ?>
            <?php include_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
        <?php else: ?>
            <?php include_once BASE_PATH . '/includes/sidebar-student.php'; ?>
        <?php endif; ?>
        
        <!-- ===== Main Content Wrapper ===== -->
        <div class="main-wrapper">
            
            <!-- ===== Top Header ===== -->
            <header class="app-header" id="appHeader">
                <div class="header-left">
                    <!-- Mobile Menu Toggle -->
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Page Title / Breadcrumb -->
                    <div class="header-title">
                        <?php if (isset($pageTitle)): ?>
                            <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <?php endif; ?>
                        <?php if (isset($breadcrumb)): ?>
                            <nav class="breadcrumb" aria-label="Breadcrumb">
                                <?php foreach ($breadcrumb as $index => $item): ?>
                                    <?php if ($index === count($breadcrumb) - 1): ?>
                                        <span class="current"><?php echo htmlspecialchars($item['label']); ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>">
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </a>
                                        <span class="separator"><i class="fas fa-chevron-right"></i></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="header-right">
                    <!-- ===== Search Bar ===== -->
                    <div class="search-bar">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="headerSearch" placeholder="Search..." 
                               aria-label="Search" autocomplete="off">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                    
                    <!-- ===== Header Actions ===== -->
                    <div class="header-actions">
                        <button type="button" class="action-btn theme-toggle" data-theme-toggle
                                aria-label="Switch to dark mode" title="Switch to dark mode">
                            <i class="fas fa-moon" aria-hidden="true"></i>
                        </button>
                        <!-- ===== Notifications ===== -->
<?php 
$unreadCount = 0;
$headerNotifications = [];
if (Auth::isLoggedIn()) {
    try {
        $db = Database::getInstance();
        $userId = Session::get('user_id');
        $userType = Session::get('user_type');
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");
        $stmt->execute([$userId, $userType]);
        $unreadCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND user_type = ? 
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$userId, $userType]);
        $headerNotifications = $stmt->fetchAll();
    } catch (Throwable $e) {
        $unreadCount = 0;
        $headerNotifications = [];
    }
}
?>
<button type="button"
        class="action-btn notification-btn <?php echo $unreadCount > 0 ? 'has-unread' : ''; ?>"
        id="notificationBtn"
        data-dropdown="notificationDropdown"
        aria-label="Notifications<?php echo $unreadCount > 0 ? ', ' . $unreadCount . ' unread' : ''; ?>"
        aria-expanded="false"
        aria-controls="notificationDropdown">
    <i class="fas fa-bell"></i>
    <span class="badge" id="notificationBadge" <?php echo $unreadCount > 0 ? '' : 'style="display:none;"'; ?>>
        <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
    </span>
</button>

<!-- Notification Dropdown -->
<div class="dropdown-menu notification-dropdown" id="notificationDropdown">
    <div class="dropdown-header">
        <span class="dropdown-title">
            <i class="fas fa-bell"></i> Notifications
        </span>
        <button class="btn btn-sm btn-outline" onclick="markAllNotificationsRead()" id="markAllBtn" 
                <?php echo $unreadCount > 0 ? '' : 'style="display:none;"'; ?>>
            Mark all read
        </button>
    </div>
    <div class="dropdown-body" id="notificationList">
        <?php if (empty($headerNotifications)): ?>
            <div class="empty-notifications">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications yet</p>
                <small>You're all caught up!</small>
            </div>
        <?php else: ?>
            <?php foreach ($headerNotifications as $n): ?>
                <?php
                $nType = $n['notification_type'] ?? 'info';
                $nIcon = ($nType === 'success') ? 'fa-check-circle' : (($nType === 'error' || $nType === 'danger') ? 'fa-exclamation-circle' : (($nType === 'warning') ? 'fa-exclamation-triangle' : 'fa-info-circle'));
                $nColor = ($nType === 'success') ? 'success' : (($nType === 'error' || $nType === 'danger') ? 'danger' : (($nType === 'warning') ? 'warning' : 'info'));
                ?>
                <a href="<?php echo !empty($n['link']) ? htmlspecialchars($n['link']) : 'javascript:void(0)'; ?>" 
                   class="notification-item <?php echo $n['is_read'] ? 'read' : 'unread'; ?>" 
                   data-id="<?php echo $n['id']; ?>"
                   data-link="<?php echo htmlspecialchars($n['link'] ?? ''); ?>"
                   data-read="<?php echo $n['is_read'] ? '1' : '0'; ?>"
                   onclick="if (typeof markNotificationRead === 'function') markNotificationRead(<?php echo $n['id']; ?>);">
                    <div class="notification-icon notification-icon-<?php echo $nColor; ?>">
                        <i class="fas <?php echo $nIcon; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($n['message']); ?></div>
                        <div class="notification-time">
                            <i class="fas fa-clock"></i> <?php echo timeAgo($n['created_at']); ?>
                        </div>
                    </div>
                    <?php if (!$n['is_read']): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="dropdown-footer">
        <a href="<?php echo url('notifications.php'); ?>" class="btn btn-sm btn-block btn-outline">
            <i class="fas fa-list"></i> View All Notifications
        </a>
    </div>
</div>
                        
                        <!-- Quick Actions -->
                        <div class="action-btn" data-dropdown="quickActionsDropdown">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        
                        <!-- Quick Actions Dropdown -->
                        <div class="dropdown-menu dropdown-menu-right" id="quickActionsDropdown">
                            <?php if (Authorization::isAdmin()): ?>
                                <a href="<?php echo url('admin/add-class.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-book"></i> Add Class
                                </a>
                                <a href="<?php echo url('admin/add-assignment.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-tasks"></i> Add Assignment
                                </a>
                                <a href="<?php echo url('admin/add-income.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-coins"></i> Add Income
                                </a>
                                <a href="<?php echo url('admin/add-expenditure.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-money-bill-wave"></i> Add Expenditure
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo url('admin/announcements.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-bullhorn"></i> Post Announcement
                                </a>
                                <a href="<?php echo url('resources_notes.php'); ?>" class="mobile-nav-item">
    <i class="fas fa-folder-open"></i>
    <span>Resources</span>
</a>
                            <?php else: ?>
                                <a href="<?php echo url('student/classes.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-book"></i> View Classes
                                </a>
                                <a href="<?php echo url('student/assignments.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-tasks"></i> View Assignments
                                </a>
                                <a href="<?php echo url('student/class-fund.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-coins"></i> View Class Fund
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ===== User Profile ===== -->
                        <?php if (Auth::isLoggedIn() && isset($currentUser)): ?>
                            <div class="user-profile" data-dropdown="profileDropdown">
                                <button class="profile-trigger" aria-label="User menu">
                                    <div class="avatar">
                                        <?php 
                                        $initial = strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1));
                                        echo $initial;
                                        ?>
                                    </div>
                                    <div class="profile-info">
                                        <span class="name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                                        <span class="role">
                                            <?php 
                                            if (Authorization::isAdmin()) {
                                                echo 'Administrator';
                                            } else {
                                                echo 'Student';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-down profile-arrow"></i>
                                </button>
                            </div>
                            
                            <!-- Profile Dropdown -->
                            <div class="dropdown-menu dropdown-menu-right" id="profileDropdown">
                                <div class="dropdown-user-info">
                                    <div class="avatar-lg">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="user-details">
                                        <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                                        <span class="user-email">
                                            <?php 
                                            if (Authorization::isAdmin()) {
                                                echo htmlspecialchars($currentUser['email'] ?? '');
                                            } else {
                                                echo 'Roll: ' . htmlspecialchars($currentUser['class_roll'] ?? '');
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                
                                <?php if (Authorization::isAdmin()): ?>
                                    <a href="<?php echo url('admin/dashboard.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-chart-pie"></i> Dashboard
                                    </a>
                                    <a href="<?php echo url('admin/settings.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo url('student/dashboard.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-chart-pie"></i> Dashboard
                                    </a>
                                    <a href="<?php echo url('student/profile.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-user"></i> My Profile
                                    </a>
                                    <a href="<?php echo url('student/change-pin.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-key"></i> Change PIN
                                    </a>
                                <?php endif; ?>
                                
                                <div class="dropdown-divider"></div>
                                
                                <a href="<?php echo url('auth/logout.php'); ?>" class="dropdown-item text-danger" 
                                   onclick="return confirm('Are you sure you want to logout?')">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Login Button when not logged in -->
                            <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <!-- ===== Main Content ===== -->
            <main class="main-content" id="mainContent">
                
                <!-- Flash Messages -->
                <?php include_once BASE_PATH . '/includes/flash-messages.php'; ?>

<?php else: ?>
    <!-- ===== Public Layout (No Sidebar) ===== -->
    <div class="app-container public-layout">
        <!-- Public Header (logo, search, auth links) -->
        <header class="app-header public-header" id="appHeader">
            <div class="header-left">
                <a href="<?php echo url(''); ?>" class="brand-link">
                    <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                        <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate" style="height:36px;">
                    <?php else: ?>
                        <span class="brand-text">ClassMate</span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="headerSearch" placeholder="Search..." aria-label="Search" autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                </div>
                <div class="header-actions">
                    <button type="button" class="action-btn theme-toggle" data-theme-toggle
                            aria-label="Switch to dark mode" title="Switch to dark mode">
                        <i class="fas fa-moon" aria-hidden="true"></i>
                    </button>
                    <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-sm btn-primary">Login</a>
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-sm" style="margin-left:8px;">Register</a>
                </div>
            </div>
        </header>
        <main class="main-content public-content">
            <!-- Flash Messages for Public Pages -->
            <?php include_once BASE_PATH . '/includes/flash-messages.php'; ?>
<?php endif; ?>