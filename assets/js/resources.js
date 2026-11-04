/**
 * ClassMate - Resources JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    document.querySelectorAll('.resources-filter-form select').forEach(function(select) {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });

    const searchInput = document.querySelector('.search-input-wrapper input');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const form = this.closest('form');
            timeout = setTimeout(function() {
                form.submit();
            }, 600);
        });
    }

    const resourcePage = document.querySelector('.resources-page[data-resource-api]');
    if (!resourcePage) return;

    const reviewModal = document.getElementById('reviewModal');
    const previewModal = document.getElementById('previewModal');
    const previewFrame = document.getElementById('previewIframe');
    const previewTitle = document.getElementById('previewTitle');
    let currentRating = 0;
    let previewTrigger = null;

    async function postAction(action, resourceId, extraData, keepalive) {
        const body = new URLSearchParams(Object.assign({
            action: action,
            resource_id: String(resourceId),
            csrf_token: resourcePage.dataset.csrfToken
        }, extraData || {}));
        const response = await fetch(resourcePage.dataset.resourceApi, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: Boolean(keepalive)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not complete the resource action.');
        }
        return data;
    }

    function showError(message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'error', 'Resource action failed');
        } else {
            window.alert(message);
        }
    }

    function renderRating(rating) {
        currentRating = rating;
        document.querySelectorAll('.star-rating [data-rating]').forEach(function(starButton) {
            const isSelected = Number(starButton.dataset.rating) <= rating;
            const icon = starButton.querySelector('i');
            icon.className = isSelected ? 'fas fa-star' : 'far fa-star';
            starButton.setAttribute('aria-pressed', String(isSelected));
        });
    }

    function closeReviewModal() {
        reviewModal.style.display = 'none';
        reviewModal.setAttribute('aria-hidden', 'true');
    }

    function getPreviewUrl(resourceUrl) {
        let parsed;
        try {
            parsed = new URL(resourceUrl);
        } catch (error) {
            throw new Error('This resource has an invalid preview link.');
        }

        if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
            throw new Error('This resource has an unsupported preview link.');
        }

        const host = parsed.hostname.toLowerCase();
        if (host !== 'drive.google.com' && host !== 'docs.google.com') {
            return parsed.href;
        }

        const match = parsed.pathname.match(/\/(?:file\/)?d\/([a-zA-Z0-9_-]+)/);
        const fileId = match ? match[1] : parsed.searchParams.get('id');
        if (!fileId || !/^[a-zA-Z0-9_-]+$/.test(fileId)) {
            throw new Error('This resource has an invalid Google Drive file link.');
        }

        const documentMatch = host === 'docs.google.com'
            ? parsed.pathname.match(/^\/(document|spreadsheets|presentation)\/d\//)
            : null;
        if (documentMatch) {
            return 'https://docs.google.com/' + documentMatch[1] + '/d/' + encodeURIComponent(fileId) + '/preview';
        }
        return 'https://drive.google.com/file/d/' + encodeURIComponent(fileId) + '/preview';
    }

    function openPreview(button) {
        try {
            previewFrame.src = getPreviewUrl(button.dataset.previewUrl || '');
            previewTitle.textContent = button.dataset.previewTitle || 'File Preview';
            previewModal.style.display = 'flex';
            previewModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            previewTrigger = button;
            previewModal.querySelector('[data-close-preview]').focus();

            const resourceId = button.dataset.resourceId;
            if (resourceId) {
                postAction('track_view', resourceId).then(function() {
                    const card = button.closest('.resource-card');
                    const counter = card && card.querySelector('[data-resource-count="views"]');
                    if (counter) {
                        const count = Number.parseInt(counter.textContent, 10) || 0;
                        counter.textContent = (count + 1) + ' views';
                    }
                }).catch(showError);
            }
        } catch (error) {
            showError(error.message);
        }
    }

    function closePreview() {
        previewModal.style.display = 'none';
        previewModal.setAttribute('aria-hidden', 'true');
        previewFrame.src = '';
        document.body.style.overflow = '';
        if (previewTrigger) {
            previewTrigger.focus();
            previewTrigger = null;
        }
    }

    window.closePreview = closePreview;
    previewModal.querySelector('[data-close-preview]').addEventListener('click', closePreview);
    previewModal.addEventListener('click', function(event) {
        if (event.target === previewModal) closePreview();
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && previewModal.getAttribute('aria-hidden') === 'false') {
            closePreview();
        }
    });

    document.addEventListener('click', async function(event) {
        const bookmarkButton = event.target.closest('[data-resource-action="toggle_bookmark"]');
        if (bookmarkButton) {
            event.preventDefault();
            if (bookmarkButton.disabled) return;
            bookmarkButton.disabled = true;
            try {
                const data = await postAction('toggle_bookmark', bookmarkButton.dataset.resourceId);
                const bookmarked = data.status === 'added';
                bookmarkButton.classList.toggle('active', bookmarked);
                bookmarkButton.setAttribute('aria-pressed', String(bookmarked));
                bookmarkButton.setAttribute('aria-label', bookmarked ? 'Remove bookmark' : 'Bookmark resource');
                bookmarkButton.innerHTML = '<i class="' + (bookmarked ? 'fas' : 'far') + ' fa-bookmark"></i>';
            } catch (error) {
                showError(error.message);
            } finally {
                bookmarkButton.disabled = false;
            }
            return;
        }

        const previewButton = event.target.closest('[data-open-preview]');
        if (previewButton) {
            event.preventDefault();
            openPreview(previewButton);
            return;
        }

        const tracker = event.target.closest('[data-resource-action]');
        if (tracker) {
            const action = tracker.dataset.resourceAction;
            postAction(action, tracker.dataset.resourceId, null, true).then(function() {
                const card = tracker.closest('.resource-card');
                if (!card) return;
                const counterType = action === 'track_view' ? 'views' : 'downloads';
                const counter = card.querySelector('[data-resource-count="' + counterType + '"]');
                if (counter) {
                    const count = Number.parseInt(counter.textContent, 10) || 0;
                    counter.textContent = (count + 1) + (counterType === 'views' ? ' views' : ' downloads');
                }
            }).catch(function(error) {
                showError(error.message);
            });
            return;
        }

        const reviewButton = event.target.closest('[data-open-review]');
        if (reviewButton) {
            const card = reviewButton.closest('.resource-card');
            document.getElementById('reviewResourceId').value = reviewButton.dataset.resourceId;
            document.getElementById('reviewText').value = card.dataset.myReview || '';
            document.getElementById('reviewMessage').hidden = true;
            document.getElementById('reviewMessage').textContent = '';
            document.getElementById('submitReviewButton').disabled = false;
            renderRating(Number(card.dataset.myRating || 0));
            reviewModal.style.display = 'flex';
            reviewModal.setAttribute('aria-hidden', 'false');
            return;
        }

        if (event.target.closest('[data-close-review]') || event.target === reviewModal) {
            closeReviewModal();
            return;
        }

        const starButton = event.target.closest('.star-rating [data-rating]');
        if (starButton) {
            renderRating(Number(starButton.dataset.rating));
            return;
        }

        if (event.target.closest('#submitReviewButton')) {
            if (currentRating < 1 || currentRating > 5) {
                showError('Choose a rating from 1 to 5 stars.');
                return;
            }

            const submitButton = document.getElementById('submitReviewButton');
            const message = document.getElementById('reviewMessage');
            submitButton.disabled = true;
            try {
                await postAction('submit_review', document.getElementById('reviewResourceId').value, {
                    rating: String(currentRating),
                    review_text: document.getElementById('reviewText').value
                });
                message.textContent = 'Review saved.';
                message.style.color = 'var(--success)';
                message.hidden = false;
                window.setTimeout(function() {
                    window.location.reload();
                }, 500);
            } catch (error) {
                message.textContent = error.message;
                message.style.color = 'var(--danger)';
                message.hidden = false;
                submitButton.disabled = false;
            }
        }
    });
});
