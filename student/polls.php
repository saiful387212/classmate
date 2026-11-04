<?php
/**
 * Student - Polls & Voting
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Polls & Voting';
$pageCss = ['student.css', 'polls.css'];
$isStudent = true;

$db = Database::getInstance();
$studentId = Session::get('user_id');
$studentBatchId = (int)(Authorization::getStudentBatchId() ?? 0);

// Get student's class enrollments
$stmt = $db->prepare("
    SELECT class_id FROM class_enrollments 
    WHERE student_id = ? AND status = 'active'
");
$stmt->execute([$studentId]);
$classIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get active polls (all or class-specific)
$sql = "
    SELECT 
        p.*,
        (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as option_count,
        (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND user_id = ?) as user_voted,
        (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id) as total_votes
    FROM polls p
    WHERE p.status = 'active'
    AND p.batch_id = ?
    AND (p.class_id IS NULL OR p.class_id IN (" . implode(',', $classIds ?: [0]) . "))
    ORDER BY p.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$studentId, $studentBatchId]);
$polls = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Polls & Voting</h1>
                <p class="text-muted">Participate in class polls and vote</p>
            </div>
        </div>
    </div>
    
    <?php if (empty($polls)): ?>
        <div class="empty-state">
            <i class="fas fa-poll"></i>
            <h3>No Active Polls</h3>
            <p>There are no active polls available for you to vote on right now.</p>
        </div>
    <?php else: ?>
        <div class="polls-grid student-polls">
            <?php foreach ($polls as $poll): 
                $hasVoted = $poll['user_voted'] > 0;
            ?>
                <div class="poll-card <?php echo $hasVoted ? 'voted' : ''; ?>">
                    <div class="poll-card-header">
                        <div class="poll-title">
                            <h3><?php echo htmlspecialchars($poll['title']); ?></h3>
                            <?php if ($poll['is_anonymous']): ?>
                                <span class="badge badge-secondary"><i class="fas fa-user-secret"></i> Anonymous</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasVoted): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Voted</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="poll-card-body">
                        <?php if ($poll['description']): ?>
                            <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($poll['end_date']): ?>
                            <div class="poll-meta">
                                <i class="fas fa-calendar-alt"></i>
                                Ends: <?php echo formatDateTime($poll['end_date']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="poll-meta">
                            <i class="fas fa-users"></i>
                            <?php echo $poll['total_votes']; ?> total votes
                        </div>
                    </div>
                    
                    <div class="poll-card-footer">
                        <button class="btn btn-<?php echo $hasVoted ? 'secondary' : 'primary'; ?>" 
                                onclick="openPoll(<?php echo $poll['id']; ?>)">
                            <?php if ($hasVoted): ?>
                                <i class="fas fa-eye"></i> View Results
                            <?php else: ?>
                                <i class="fas fa-vote-yea"></i> Vote Now
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Vote Modal ===== -->
<div class="modal-overlay" id="voteModal">
    <div class="modal modal-md">
        <div class="modal-header">
            <h3 id="voteModalTitle">Vote</h3>
            <button class="modal-close" onclick="closeModal('voteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="voteContent">
            <div class="loading-spinner"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('voteModal')">Close</button>
            <button class="btn btn-primary" id="submitVoteBtn" onclick="submitVote()">
                <i class="fas fa-check"></i> Submit Vote
            </button>
        </div>
        <?php echo Security::csrfField(); ?>
    </div>
</div>

<script>
let currentPollId = null;
let selectedOptions = [];

function openPoll(pollId) {
    currentPollId = pollId;
    const modal = document.getElementById('voteModal');
    const content = document.getElementById('voteContent');
    const title = document.getElementById('voteModalTitle');
    
    content.innerHTML = '<div class="loading-spinner"></div>';
    openModal('voteModal');
    
    fetch('<?php echo url('api/poll-api.php'); ?>?action=get_poll&id=' + pollId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const poll = data.poll;
                const options = data.options;
                const hasVoted = data.has_voted;
                const votedOptions = data.voted_options || [];
                
                title.textContent = poll.title;
                
                let html = `
                    <p class="poll-description">${poll.description || ''}</p>
                    <div class="poll-options">
                `;
                
                if (hasVoted) {
                    // Show results
                    const totalVotes = options.reduce((sum, opt) => sum + opt.votes, 0);
                    options.forEach(opt => {
                        const percentage = totalVotes > 0 ? (opt.votes / totalVotes) * 100 : 0;
                        const isVoted = votedOptions.includes(opt.id);
                        html += `
                            <div class="option-result ${isVoted ? 'voted' : ''}">
                                <div class="option-label">
                                    <span>${opt.option_text}</span>
                                    <span>${opt.votes} votes (${percentage.toFixed(1)}%)</span>
                                </div>
                                <div class="option-bar">
                                    <div class="option-bar-fill" style="width: ${percentage}%;"></div>
                                </div>
                                ${isVoted ? '<span class="voted-badge"><i class="fas fa-check"></i> Your Vote</span>' : ''}
                            </div>
                        `;
                    });
                    
                    document.getElementById('submitVoteBtn').style.display = 'none';
                } else {
                    // Show voting options
                    const allowMultiple = poll.allow_multiple == 1;
                    html += `<p class="vote-instruction">${allowMultiple ? 'Select one or more options' : 'Select one option'}</p>`;
                    
                    options.forEach(opt => {
                        html += `
                            <label class="option-label-radio ${allowMultiple ? 'checkbox' : ''}">
                                <input type="${allowMultiple ? 'checkbox' : 'radio'}" 
                                       name="poll_option" 
                                       value="${opt.id}"
                                       onclick="selectOption(${opt.id}, this)">
                                <span>${opt.option_text}</span>
                            </label>
                        `;
                    });
                    
                    document.getElementById('submitVoteBtn').style.display = 'block';
                    document.getElementById('submitVoteBtn').disabled = true;
                    selectedOptions = [];
                }
                
                html += `</div>`;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-error">Failed to load poll.</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-error">An error occurred.</div>';
        });
}

function selectOption(optionId, input) {
    if (input.type === 'radio') {
        // Radio - single selection
        selectedOptions = [optionId];
        document.getElementById('submitVoteBtn').disabled = false;
    } else {
        // Checkbox - multiple selection
        if (input.checked) {
            if (!selectedOptions.includes(optionId)) {
                selectedOptions.push(optionId);
            }
        } else {
            selectedOptions = selectedOptions.filter(id => id !== optionId);
        }
        document.getElementById('submitVoteBtn').disabled = selectedOptions.length === 0;
    }
}

function submitVote() {
    if (selectedOptions.length === 0) {
        alert('Please select at least one option.');
        return;
    }
    
    if (!confirm('Submit your vote?')) return;
    
    const csrfToken = <?php echo json_encode(Security::generateCSRFToken()); ?>;
    
    fetch('<?php echo url('api/poll-api.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'vote',
            poll_id: currentPollId,
            option_ids: selectedOptions,
            csrf_token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'The voting request failed.');
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            alert('Vote submitted successfully!');
            closeModal('voteModal');
            location.reload();
        } else {
            alert(data.message || 'Failed to submit vote.');
        }
    })
    .catch(error => {
        alert(error.message || 'An error occurred while submitting your vote.');
    });
}
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>