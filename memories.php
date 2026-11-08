<?php
require_once __DIR__ . '/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireLogin();
$memories = new MemoriesManager();
$submissionToken = trim($_GET['submit'] ?? '');
$submissionAlbum = null;
$submissionAllowed = false;
if ($submissionToken !== '') {
    $submissionAlbum = $memories->albumFromSubmissionToken($submissionToken);
    $submissionAllowed = $submissionAlbum && $memories->canSubmitToAlbum($submissionAlbum, $submissionToken);
    if (!$submissionAllowed) {
        $submissionAlbum = null;
    }
}

$pageTitle = 'ClassMate Memories';
$pageCss = ['memories.css'];
$pageJs = ['memories.js'];
$pageAssetVersion = 'memories-20261108-2';
$isAdmin = Authorization::isAdmin();
$isStudent = Authorization::isStudent();
$memoriesConfig = [
    'apiUrl' => url('api/memories.php'),
    'mediaUrl' => url('api/memories-media.php'),
    'pageUrl' => url('memories.php'),
    'csrfToken' => Security::generateCSRFToken(),
    'isAdmin' => $isAdmin,
    'isSuperAdmin' => Authorization::isSuperAdmin(),
    'submissionToken' => $submissionAllowed ? $submissionToken : '',
    'submissionAlbumId' => $submissionAllowed ? (int)$submissionAlbum['id'] : null,
    'submissionAlbumTitle' => $submissionAllowed ? $submissionAlbum['title'] : '',
    'reviewOpen' => isset($_GET['review']),
];

include_once BASE_PATH . '/includes/header.php';
?>
<section class="memories-page" id="memoriesPage">
    <header class="memories-hero">
        <div class="memories-hero-copy">
            <span class="memories-eyebrow"><i class="fa-solid fa-sparkles"></i> OUR SHARED STORY</span>
            <h2>ClassMate <span>Memories</span></h2>
            <p>A little corner for the people, places, and moments that made this class yours.</p>
        </div>
        <div class="memories-hero-actions">
            <?php if ($isAdmin): ?>
                <button class="memories-button memories-button-primary" type="button" data-open-modal="albumModal">
                    <i class="fa-solid fa-plus"></i> Create album
                </button>
            <?php endif; ?>
            <button class="memories-button memories-button-quiet" type="button" data-open-modal="uploadModal">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> Add a memory
            </button>
        </div>
        <div class="memories-orb memories-orb-one"></div>
        <div class="memories-orb memories-orb-two"></div>
    </header>

    <?php if ($submissionAllowed): ?>
        <div class="memories-submit-notice">
            <i class="fa-solid fa-qrcode"></i>
            <div><strong>Event upload link</strong><span>You’re adding a memory to “<?php echo htmlspecialchars($submissionAlbum['title'], ENT_QUOTES, 'UTF-8'); ?>”.</span></div>
            <button type="button" class="memories-text-button" data-copy-submission>Copy event link</button>
        </div>
    <?php endif; ?>

    <div class="memories-toolbar">
        <label class="memories-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="memoriesSearch" type="search" maxlength="80" placeholder="Search memories, albums, or tags" autocomplete="off">
        </label>
        <label class="memories-select-wrap">
            <span class="sr-only">Category</span>
            <select id="memoriesCategory"><option value="">All categories</option></select>
        </label>
        <label class="memories-select-wrap">
            <span class="sr-only">Year</span>
            <select id="memoriesYear"><option value="">Every year</option></select>
        </label>
        <button class="memories-filter-reset" id="memoriesReset" type="button">Reset</button>
    </div>

    <section class="memories-section" aria-labelledby="albumsHeading">
        <div class="memories-section-heading">
            <div><span class="memories-eyebrow">COLLECTED MOMENTS</span><h3 id="albumsHeading">Albums & sessions</h3></div>
            <span class="memories-section-count" id="albumCount"></span>
        </div>
        <div class="memories-albums" id="memoriesAlbums" aria-live="polite"></div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="memories-review" id="memoriesReview" hidden>
            <div class="memories-section-heading">
                <div><span class="memories-eyebrow">KEEPING IT KIND</span><h3>Review submissions</h3></div>
                <?php if (Authorization::isSuperAdmin()): ?>
                    <form id="categoryForm" class="memories-category-form">
                        <input type="text" name="name" maxlength="80" placeholder="New category" aria-label="New category name" required>
                        <button class="memories-button memories-button-small" type="submit">Add</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="memories-queue" id="memoriesQueue"></div>
            <div class="memories-reports" id="memoriesReports"></div>
            <div class="memories-reports" id="memoriesComments"></div>
        </section>
    <?php endif; ?>

    <section class="memories-section memories-feed-section" aria-labelledby="feedHeading">
        <div class="memories-section-heading">
            <div><span class="memories-eyebrow">FROM THE ARCHIVE</span><h3 id="feedHeading">The memory wall</h3></div>
            <span class="memories-section-count" id="memoryCount"></span>
        </div>
        <div class="memories-feed" id="memoriesFeed" aria-live="polite"></div>
        <button class="memories-load-more" id="memoriesLoadMore" type="button" hidden>Load more memories</button>
    </section>
</section>

<dialog class="memories-dialog" id="uploadModal" aria-labelledby="uploadTitle">
    <form class="memories-dialog-card" id="uploadForm" enctype="multipart/form-data">
        <button type="button" class="memories-dialog-close" data-close-modal aria-label="Close upload form"><i class="fa-solid fa-xmark"></i></button>
        <span class="memories-eyebrow">ADD TO THE STORY</span>
        <h3 id="uploadTitle">Share a memory</h3>
        <p class="memories-dialog-note" id="uploadPolicy">Student uploads are reviewed before they appear on the memory wall.</p>
        <input type="hidden" name="action" value="submit_media">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($memoriesConfig['csrfToken'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($submissionAllowed): ?>
            <input type="hidden" name="submission_token" value="<?php echo htmlspecialchars($submissionToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="memories-field"><label>Event album</label><div class="memories-locked-album"><?php echo htmlspecialchars($submissionAlbum['title'], ENT_QUOTES, 'UTF-8'); ?></div></div>
        <?php else: ?>
            <div class="memories-field"><label for="uploadAlbum">Album</label><select id="uploadAlbum" name="album_id" required><option value="">Choose an album</option></select></div>
        <?php endif; ?>
        <div class="memories-field"><label for="mediaFile">Photo or approved video</label><input id="mediaFile" type="file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></div>
        <div class="memories-upload-or"><span>or add a YouTube link</span></div>
        <div class="memories-field"><label for="youtubeUrl">YouTube URL</label><input id="youtubeUrl" type="url" name="youtube_url" maxlength="500" placeholder="https://youtu.be/..."></div>
        <div class="memories-field"><label for="memoryCaption">Caption</label><textarea id="memoryCaption" name="caption" maxlength="500" rows="2" placeholder="What should everyone remember?"></textarea></div>
        <div class="memories-field"><label for="memoryTags">Tags <span>(comma separated)</span></label><input id="memoryTags" name="tags" maxlength="250" placeholder="field trip, friends, 2026"></div>
        <div class="memories-dialog-actions"><button type="button" class="memories-button memories-button-quiet" data-close-modal>Cancel</button><button type="submit" class="memories-button memories-button-primary">Submit memory</button></div>
        <div class="memories-form-message" id="uploadMessage" role="status"></div>
    </form>
</dialog>

<?php if ($isAdmin): ?>
<dialog class="memories-dialog" id="albumModal" aria-labelledby="albumTitle">
    <form class="memories-dialog-card" id="albumForm">
        <button type="button" class="memories-dialog-close" data-close-modal aria-label="Close album form"><i class="fa-solid fa-xmark"></i></button>
        <span class="memories-eyebrow">MAKE ROOM FOR A MOMENT</span><h3 id="albumTitle">Create an album</h3>
        <input type="hidden" name="action" value="create_album">
        <input type="hidden" name="album_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($memoriesConfig['csrfToken'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="memories-field"><label for="albumName">Album name</label><input id="albumName" name="title" maxlength="160" required placeholder="The day we went to..."></div>
        <div class="memories-field"><label for="albumDescription">Description</label><textarea id="albumDescription" name="description" rows="2" maxlength="2000" placeholder="A short note about this collection"></textarea></div>
        <div class="memories-form-grid">
            <div class="memories-field"><label for="albumType">Collection type</label><select id="albumType" name="album_type"><option value="event">Event</option><option value="session">Batch session</option><option value="department">Department</option></select></div>
            <div class="memories-field"><label for="albumDate">Event date</label><input id="albumDate" type="date" name="event_date"></div>
        </div>
        <div class="memories-form-grid">
            <div class="memories-field"><label for="albumSession">Session / batch label</label><input id="albumSession" name="session_label" maxlength="100" placeholder="Session 2024–25"></div>
            <div class="memories-field"><label for="albumCategory">Category</label><select id="albumCategory" name="category_id"><option value="">No category</option></select></div>
        </div>
        <?php if (Authorization::isSuperAdmin()): ?>
            <div class="memories-field"><label for="albumVisibility">Who can view?</label><select id="albumVisibility" name="visibility"><option value="all">All active batches</option><option value="batch">Selected batch only</option><option value="private">Private (owner & admins)</option></select></div>
            <div class="memories-field" id="batchField" hidden><label for="albumBatch">Batch ID</label><input id="albumBatch" type="number" min="1" name="batch_id" placeholder="Enter an existing batch ID"></div>
        <?php endif; ?>
        <label class="memories-check"><input type="checkbox" name="allow_submissions" value="1" checked><span>Allow students to submit memories to this album</span></label>
        <p class="memories-dialog-note">A private, batch-bound event submission link is included in the album card for QR sharing.</p>
        <div class="memories-dialog-actions"><button type="button" class="memories-button memories-button-quiet" data-close-modal>Cancel</button><button type="submit" class="memories-button memories-button-primary">Create album</button></div>
        <div class="memories-form-message" id="albumMessage" role="status"></div>
    </form>
</dialog>
<?php endif; ?>

<dialog class="memories-lightbox" id="memoryViewer" aria-label="Memory viewer">
    <button class="memories-viewer-close" type="button" data-close-viewer aria-label="Close viewer"><i class="fa-solid fa-xmark"></i></button>
    <button class="memories-viewer-nav memories-viewer-prev" type="button" data-viewer-prev aria-label="Previous memory"><i class="fa-solid fa-chevron-left"></i></button>
    <div class="memories-viewer-layout">
        <div class="memories-viewer-media" id="viewerMedia"></div>
        <aside class="memories-viewer-info">
            <div class="memories-viewer-meta" id="viewerMeta"></div>
            <h3 id="viewerCaption"></h3>
            <div class="memories-viewer-actions">
                <button class="memories-button memories-button-quiet" type="button" id="viewerLike"><i class="fa-regular fa-heart"></i> <span>Like</span> <b></b></button>
                <button class="memories-text-button" type="button" id="viewerShare"><i class="fa-solid fa-share-nodes"></i> Share album</button>
                <button class="memories-text-button memories-report-action" type="button" id="viewerReport"><i class="fa-regular fa-flag"></i> Report</button>
            </div>
            <div class="memories-comments" id="viewerComments"></div>
            <form class="memories-comment-form" id="commentForm"><input type="text" name="body" maxlength="1000" placeholder="Leave a kind comment..." required><button type="submit" aria-label="Post comment"><i class="fa-solid fa-paper-plane"></i></button></form>
        </aside>
    </div>
    <button class="memories-viewer-nav memories-viewer-next" type="button" data-viewer-next aria-label="Next memory"><i class="fa-solid fa-chevron-right"></i></button>
</dialog>
<script>window.CLASSMATE_MEMORIES = <?php echo json_encode($memoriesConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php include_once BASE_PATH . '/includes/footer.php'; ?>
