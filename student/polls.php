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

// Show open polls and closed polls the student has already voted in.
$sql = "
    SELECT 
        p.*,
        c.class_code,
        c.class_title,
        CASE WHEN p.status = 'active' AND p.end_date IS NOT NULL AND p.end_date < NOW()
             THEN 'closed' ELSE p.status END AS display_status,
        (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as option_count,
        (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND user_id = ?) as user_voted,
        (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id) as total_votes
    FROM polls p
    LEFT JOIN classes c ON c.id = p.class_id
    WHERE p.batch_id = ?
    AND (p.class_id IS NULL OR p.class_id IN (" . implode(',', $classIds ?: [0]) . "))
    AND (
        (p.status = 'active' AND (p.end_date IS NULL OR p.end_date >= NOW()))
        OR EXISTS (
            SELECT 1 FROM poll_votes pv
            WHERE pv.poll_id = p.id AND pv.user_id = ?
        )
    )
    ORDER BY (p.status = 'active' AND (p.end_date IS NULL OR p.end_date >= NOW())) DESC, p.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$studentId, $studentBatchId, $studentId]);
$polls = $stmt->fetchAll();
$openPollCount = count(array_filter($polls, static function ($poll) {
    return !$poll['user_voted'] && $poll['display_status'] === 'active';
}));
$votedPollCount = count(array_filter($polls, static function ($poll) {
    return (int)$poll['user_voted'] > 0;
}));

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Polls & Voting</h1>
                <p class="text-muted">Cast your vote and follow the results of your polls.</p>
            </div>
        </div>
    </div>

    <div class="poll-overview" aria-label="Poll overview">
        <div class="poll-overview-item">
            <span class="poll-overview-icon"><i class="fas fa-vote-yea"></i></span>
            <span><strong><?php echo $openPollCount; ?></strong><small>Waiting for your vote</small></span>
        </div>
        <div class="poll-overview-item">
            <span class="poll-overview-icon poll-overview-icon-complete"><i class="fas fa-check"></i></span>
            <span><strong><?php echo $votedPollCount; ?></strong><small>Polls you voted in</small></span>
        </div>
    </div>

    <div class="poll-toolbar">
        <label class="poll-search" aria-label="Search polls">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="pollSearch" placeholder="Search polls..." autocomplete="off">
        </label>
        <div class="poll-view-filters" role="group" aria-label="Filter polls">
            <button type="button" class="poll-view-filter active" data-poll-filter="all" aria-pressed="true">All</button>
            <button type="button" class="poll-view-filter" data-poll-filter="open" aria-pressed="false">Needs your vote</button>
            <button type="button" class="poll-view-filter" data-poll-filter="voted" aria-pressed="false">Voted</button>
        </div>
    </div>
    
    <?php if (empty($polls)): ?>
        <div class="empty-state">
            <i class="fas fa-poll"></i>
            <h3>You're all caught up</h3>
            <p>There are no polls waiting for your vote right now. Check back later.</p>
        </div>
    <?php else: ?>
        <div class="polls-grid student-polls">
            <?php foreach ($polls as $poll): 
                $hasVoted = (int)$poll['user_voted'] > 0;
            ?>
                <article class="poll-card student-poll-card <?php echo $hasVoted ? 'voted' : ''; ?>"
                         data-poll-state="<?php echo $hasVoted ? 'voted' : 'open'; ?>"
                         data-poll-search="<?php echo htmlspecialchars($poll['title'] . ' ' . ($poll['description'] ?? '') . ' ' . ($poll['class_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="poll-card-header">
                        <div class="poll-title">
                            <h3><?php echo htmlspecialchars($poll['title']); ?></h3>
                            <?php if ($poll['is_anonymous']): ?>
                                <span class="badge badge-secondary"><i class="fas fa-user-secret"></i> Anonymous</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasVoted): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Voted</span>
                        <?php elseif ($poll['display_status'] !== 'active'): ?>
                            <span class="badge badge-secondary"><i class="fas fa-lock"></i> Closed</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="poll-card-body">
                        <?php if ($poll['description']): ?>
                            <p class="poll-description"><?php echo htmlspecialchars($poll['description']); ?></p>
                        <?php endif; ?>

                        <?php if ($poll['class_id']): ?>
                            <div class="poll-meta">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars(($poll['class_code'] ? $poll['class_code'] . ' - ' : '') . $poll['class_title']); ?>
                            </div>
                        <?php else: ?>
                            <div class="poll-meta">
                                <i class="fas fa-users"></i> Batch-wide poll
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($poll['end_date']): ?>
                            <div class="poll-meta">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo $poll['display_status'] === 'active' ? 'Closes' : 'Closed'; ?>:
                                <?php echo formatDateTime($poll['end_date']); ?>
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
                </article>
            <?php endforeach; ?>
        </div>
        <div class="empty-state poll-filter-empty" id="pollFilterEmpty" hidden>
            <i class="fas fa-search"></i>
            <h3>No matching polls</h3>
            <p>Try a different search or filter.</p>
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
                    ${poll.description ? `<p class="poll-description">${escapePollHtml(poll.description)}</p>` : ''}
                    <div class="poll-options">
                `;
                
                if (hasVoted) {
                    // Show results
                    const totalVotes = options.reduce((sum, opt) => sum + Number(opt.votes || 0), 0);
                    html += `<p class="poll-results-summary"><i class="fas fa-chart-pie"></i> ${totalVotes} total vote${totalVotes === 1 ? '' : 's'}</p>`;
                    options.forEach(opt => {
                        const votes = Number(opt.votes || 0);
                        const percentage = totalVotes > 0 ? (votes / totalVotes) * 100 : 0;
                        const isVoted = votedOptions.some(id => Number(id) === Number(opt.id));
                        html += `
                            <div class="option-result ${isVoted ? 'voted' : ''}">
                                <div class="option-label">
                                    <span>${escapePollHtml(opt.option_text)}</span>
                                    <span>${votes} vote${votes === 1 ? '' : 's'} · ${percentage.toFixed(1)}%</span>
                                </div>
                                <div class="option-bar" role="progressbar" aria-label="${escapePollHtml(opt.option_text)} results"
                                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percentage.toFixed(1)}">
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
                                       onchange="selectOption(${opt.id}, this)">
                                <span>${escapePollHtml(opt.option_text)}</span>
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
                content.innerHTML = `<div class="alert alert-error">${escapePollHtml(data.message || 'Failed to load poll.')}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = `<div class="alert alert-error">${escapePollHtml(error.message || 'An error occurred.')}</div>`;
        });
}

function escapePollHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
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
    
    const csrfToken = <?php echo json_encode(Security::generateCSRFToken()); ?>;
    const submitButton = document.getElementById('submitVoteBtn');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
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
            closeModal('voteModal');
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to submit vote.');
        }
    })
    .catch(error => {
        alert(error.message || 'An error occurred while submitting your vote.');
    })
    .finally(() => {
        submitButton.disabled = selectedOptions.length === 0;
        submitButton.innerHTML = '<i class="fas fa-check"></i> Submit Vote';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('pollSearch');
    const cards = Array.from(document.querySelectorAll('.student-poll-card'));
    const filterButtons = Array.from(document.querySelectorAll('[data-poll-filter]'));
    const emptyState = document.getElementById('pollFilterEmpty');
    if (!search || !emptyState) {
        return;
    }

    let activeFilter = 'all';

    function updatePollCards() {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        cards.forEach(function (card) {
            const matchesSearch = card.dataset.pollSearch.includes(query);
            const matchesFilter = activeFilter === 'all' || card.dataset.pollState === activeFilter;
            card.hidden = !(matchesSearch && matchesFilter);
            if (!card.hidden) visibleCount++;
        });
        emptyState.hidden = visibleCount > 0;
    }

    search.addEventListener('input', updatePollCards);
    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeFilter = button.dataset.pollFilter;
            filterButtons.forEach(function (filterButton) {
                const isActive = filterButton === button;
                filterButton.classList.toggle('active', isActive);
                filterButton.setAttribute('aria-pressed', String(isActive));
            });
            updatePollCards();
        });
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>