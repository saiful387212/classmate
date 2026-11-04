<?php
/**
 * Flash Messages Display
 */

$flashes = Session::getAllFlashes();
if (!empty($flashes)): ?>
    <div class="flash-messages-container">
        <?php foreach ($flashes as $key => $flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php 
                    echo $flash['type'] === 'success' ? 'check-circle' : 
                        ($flash['type'] === 'error' ? 'exclamation-circle' : 
                        ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
                ?>"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>