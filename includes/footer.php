<?php
/**
 * ClassMate - Main Footer
 */
?>

        </main>
    </div>
</div>

<!-- ===== Mobile Bottom Navigation ===== -->
<?php if (Auth::isLoggedIn()): ?>
    <?php include_once BASE_PATH . '/includes/mobile-nav.php'; ?>
<?php endif; ?>

<!-- ===== JavaScript ===== -->
<!-- jQuery (optional, for compatibility) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Main JavaScript -->
<script src="<?php echo asset('js/main.js') . '?v=' . APP_VERSION; ?>"></script>
<script src="<?php echo asset('js/notifications.js') . '?v=' . APP_VERSION; ?>"></script>
<script src="<?php echo asset('js/notifications-system.js') . '?v=' . APP_VERSION; ?>"></script>
<script src="<?php echo asset('js/validation.js') . '?v=' . APP_VERSION; ?>"></script>

<?php 
// Page specific JavaScript
if (isset($pageJs)) {
    if (is_array($pageJs)) {
        foreach ($pageJs as $js) {
            echo '<script src="' . asset('js/' . $js) . '?v=' . APP_VERSION . '"></script>';
        }
    } else {
        echo '<script src="' . asset('js/' . $pageJs) . '"></script>';
    }
}

// Admin specific JavaScript
if (isset($isAdmin) && $isAdmin) {
    echo '<script src="' . asset('js/admin.js') . '?v=' . APP_VERSION . '"></script>';
}

// Student specific JavaScript
if (isset($isStudent) && $isStudent) {
    echo '<script src="' . asset('js/student.js') . '?v=' . APP_VERSION . '"></script>';
}
?>

<!-- Toast Notifications from PHP Flash Messages -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for flash messages and display as toasts
    <?php 
    $flashes = Session::getAllFlashes();
    foreach ($flashes as $key => $flash): 
    ?>
        showToast(
            '<?php echo addslashes($flash['message']); ?>', 
            '<?php echo $flash['type']; ?>',
            '<?php echo ucfirst($flash['type']); ?>'
        );
    <?php endforeach; ?>
});
</script>
<script src="<?php echo asset('js/notifications-system.js'); ?>?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>