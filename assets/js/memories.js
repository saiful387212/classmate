(function () {
    'use strict';

    const config = window.CLASSMATE_MEMORIES;
    const page = document.getElementById('memoriesPage');
    if (!config || !page) return;

    const state = {
        albums: [],
        categories: [],
        media: [],
        pageNumber: 1,
        canLoadMore: false,
        activeMediaIndex: -1,
        csrfToken: config.csrfToken
    };
    const elements = {
        feed: document.getElementById('memoriesFeed'),
        albums: document.getElementById('memoriesAlbums'),
        search: document.getElementById('memoriesSearch'),
        category: document.getElementById('memoriesCategory'),
        year: document.getElementById('memoriesYear'),
        loadMore: document.getElementById('memoriesLoadMore'),
        uploadForm: document.getElementById('uploadForm'),
        albumForm: document.getElementById('albumForm'),
        viewer: document.getElementById('memoryViewer')
    };
    let initialAlbum = new URLSearchParams(window.location.search).get('album') || '';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    function showMessage(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type || 'info');
        } else {
            window.alert(message);
        }
    }

    async function request(url, options) {
        const response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (error) {
            if (response.status === 422) {
                throw new Error('The upload was rejected. Check the file type and size; photo uploads also require PHP GD.');
            }
            if (response.status === 413) {
                throw new Error('The upload is larger than the server allows. Increase PHP post_max_size and upload_max_filesize or choose a smaller file.');
            }
            const status = response.status ? ' (HTTP ' + response.status + ')' : '';
            throw new Error('The server returned an invalid response' + status + '. Check the PHP error log for details.');
        }
        if (!response.ok || data.error) throw new Error(data.error || 'The request could not be completed.');
        return data;
    }

    function mediaUrl(id, thumb) {
        const url = new URL(config.mediaUrl, window.location.href);
        url.searchParams.set('id', id);
        if (thumb) url.searchParams.set('thumb', '1');
        return url.toString();
    }

    function albumUrl(id) {
        const url = new URL(config.pageUrl, window.location.href);
        url.searchParams.set('album', id);
        return url.toString();
    }

    function submissionUrl(token) {
        const url = new URL(config.pageUrl, window.location.href);
        url.searchParams.set('submit', token);
        return url.toString();
    }

    function renderCategories() {
        const current = elements.category.value;
        const options = state.categories.map(function (category) {
            return '<option value="' + Number(category.id) + '">' + escapeHtml(category.name) + '</option>';
        }).join('');
        elements.category.innerHTML = '<option value="">All categories</option>' + options;
        if (state.categories.some(function (category) { return String(category.id) === current; })) {
            elements.category.value = current;
        }
        const albumCategory = document.getElementById('albumCategory');
        if (albumCategory) albumCategory.innerHTML = '<option value="">No category</option>' + options;
    }

    function renderYears() {
        const years = Array.from(new Set(state.albums.map(function (album) {
            return album.event_date ? album.event_date.slice(0, 4) : '';
        }).filter(Boolean))).sort().reverse();
        const current = elements.year.value;
        elements.year.innerHTML = '<option value="">Every year</option>' + years.map(function (year) {
            return '<option value="' + escapeHtml(year) + '">' + escapeHtml(year) + '</option>';
        }).join('');
        if (years.includes(current)) elements.year.value = current;
    }

    function renderAlbumCard(album) {
        const cover = album.cover_id
            ? '<img class="memories-album-cover" src="' + escapeHtml(mediaUrl(album.cover_id, true)) + '" alt="" loading="lazy">'
            : '';
        const featured = Number(album.is_featured) === 1
            ? '<span class="memories-featured-pill"><i class="fa-solid fa-star"></i> Featured</span>'
            : (album.category_name ? '<span class="memories-category-pill">' + escapeHtml(album.category_name) + '</span>' : '<span>' + escapeHtml(album.album_type) + '</span>');
        const date = album.event_date ? new Date(album.event_date + 'T12:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short' }) : '';
        let actions = '<button type="button" data-album-open="' + Number(album.id) + '"><i class="fa-regular fa-images"></i> ' + Number(album.media_count) + ' memories</button>';
        actions += '<button type="button" data-album-share="' + Number(album.id) + '"><i class="fa-solid fa-share-nodes"></i> Share</button>';
        if (album.allow_submissions && album.submission_token) {
            actions += '<button type="button" data-album-submit="' + escapeHtml(album.submission_token) + '"><i class="fa-solid fa-qrcode"></i> Event link</button>';
        }
        if (config.isAdmin) {
            actions += '<button type="button" data-album-feature="' + Number(album.id) + '" aria-label="Toggle featured"><i class="fa-solid fa-star"></i></button>';
            actions += '<button type="button" data-album-edit="' + Number(album.id) + '"><i class="fa-solid fa-pen"></i> Edit</button>';
            actions += '<button type="button" data-album-delete="' + Number(album.id) + '" aria-label="Archive album"><i class="fa-solid fa-box-archive"></i></button>';
        }
        const session = [album.session_label, date].filter(Boolean).join(' · ');
        return '<article class="memories-album-card">' + cover +
            '<div class="memories-album-copy"><div class="memories-album-topline">' + featured +
            '<span>' + Number(album.media_count) + ' items</span></div><h4>' + escapeHtml(album.title) +
            '</h4><p>' + escapeHtml(session || album.album_type) + '</p><div class="memories-album-actions">' + actions +
            '</div></div></article>';
    }

    function renderAlbums() {
        const albums = elements.year.value ? state.albums.filter(function (album) {
            return album.event_date && album.event_date.slice(0, 4) === elements.year.value;
        }) : state.albums;
        document.getElementById('albumCount').textContent = albums.length ? albums.length + ' collections' : '';
        elements.albums.innerHTML = albums.length
            ? albums.map(renderAlbumCard).join('')
            : '<div class="memories-empty">No albums yet. The best stories start with one small moment.</div>';
        renderYears();
        const albumSelect = document.getElementById('uploadAlbum');
        if (albumSelect) {
            const available = state.albums.filter(function (album) { return Number(album.allow_submissions) === 1; });
            albumSelect.innerHTML = '<option value="">Choose an album</option>' + available.map(function (album) {
                return '<option value="' + Number(album.id) + '">' + escapeHtml(album.title) + '</option>';
            }).join('');
        }
    }

    function renderMediaCard(media, index) {
        const caption = media.caption || media.album_title;
        let cover;
        if (media.media_type === 'image') {
            cover = '<img src="' + escapeHtml(mediaUrl(media.id, true)) + '" alt="' + escapeHtml(caption) + '" loading="lazy">';
        } else {
            const icon = media.media_type === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-play';
            const label = media.media_type === 'youtube' ? 'YouTube memory' : 'Video memory';
            cover = '<div class="memories-video-cover"><i class="' + icon + '" aria-label="' + label + '"></i></div>';
        }
        const tags = String(media.tags || '').split(',').filter(Boolean).map(function (tag) {
            return '<span class="memories-tag">#' + escapeHtml(tag) + '</span>';
        }).join('');
        const status = media.status === 'pending' ? '<span class="memories-tag">Pending review</span>' : '';
        const approved = media.status === 'approved';
        const adminDelete = config.isAdmin
            ? '<button type="button" data-media-delete="' + Number(media.id) + '" aria-label="Remove memory"><i class="fa-solid fa-trash-can"></i></button>'
            : '';
        return '<article class="memories-card" data-media-card="' + Number(media.id) + '">' +
            '<button type="button" class="memories-card-media" data-view-media="' + index + '" aria-label="Open memory"' + (approved ? '' : ' disabled') + '>' + cover + '</button>' +
            '<div class="memories-card-caption"><strong>' + escapeHtml(caption || 'A moment worth keeping') + '</strong>' +
            '<span>' + escapeHtml(media.album_title) + (media.event_date ? ' · ' + escapeHtml(media.event_date.slice(0, 4)) : '') + '</span>' + status + '</div>' +
            (tags ? '<div class="memories-tags">' + tags + '</div>' : '') +
            '<div class="memories-card-actions"><button type="button" data-like-media="' + Number(media.id) + '" class="' + (Number(media.liked) ? 'is-liked' : '') + '"' + (approved ? '' : ' disabled') + '><i class="' + (Number(media.liked) ? 'fa-solid' : 'fa-regular') + ' fa-heart"></i> ' + Number(media.like_count) + '</button>' +
            '<button type="button" data-view-media="' + index + '"' + (approved ? '' : ' disabled') + '><i class="fa-regular fa-comment"></i> ' + Number(media.comment_count) + '</button>' +
            '<button type="button" class="memories-open-button" data-view-media="' + index + '"' + (approved ? '' : ' disabled') + '>' + (approved ? 'Open' : 'In review') + ' <i class="fa-solid fa-arrow-up-right-from-square"></i></button>' + adminDelete + '</div></article>';
    }

    function renderFeed(append) {
        if (!append) elements.feed.innerHTML = '';
        const offset = append ? state.media.length - state.lastPageLength : 0;
        const current = append ? state.media.slice(offset) : state.media;
        const html = current.map(function (media, index) {
            return renderMediaCard(media, append ? offset + index : index);
        }).join('');
        if (append) elements.feed.insertAdjacentHTML('beforeend', html);
        else elements.feed.innerHTML = html || '<div class="memories-empty">Nothing in this collection matches those filters. Try another year or search.</div>';
        document.getElementById('memoryCount').textContent = state.media.length ? state.media.length + (state.canLoadMore ? '+' : '') + ' memories' : '';
        elements.loadMore.hidden = !state.canLoadMore;
    }

    function renderModeration(data) {
        const review = document.getElementById('memoriesReview');
        if (!review) return;
        const requested = config.reviewOpen || data.queue.length || data.reports.length;
        review.hidden = !requested;
        const queue = document.getElementById('memoriesQueue');
        queue.innerHTML = data.queue.length ? data.queue.map(function (item) {
            let thumb;
            if (item.media_type === 'image') {
                thumb = '<img class="memories-queue-thumb" src="' + escapeHtml(mediaUrl(item.id, true)) + '" alt="" loading="lazy">';
            } else if (item.media_type === 'video') {
                thumb = '<video class="memories-queue-thumb" src="' + escapeHtml(mediaUrl(item.id, false)) + '" controls preload="none" aria-label="Review uploaded video"></video>';
            } else {
                thumb = '<a class="memories-queue-thumb memories-video-cover" href="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(item.youtube_id) + '" target="_blank" rel="noopener noreferrer" aria-label="Preview submitted YouTube video"><i class="fa-brands fa-youtube"></i></a>';
            }
            return '<article class="memories-queue-card">' + thumb + '<div class="memories-queue-copy"><strong>' + escapeHtml(item.caption || item.album_title) + '</strong><span>' + escapeHtml(item.uploader_name || 'Student') + ' · ' + escapeHtml(item.album_title) + '</span></div><div class="memories-queue-actions"><button type="button" data-moderate-media="' + Number(item.id) + '" data-status="approved">Approve</button><button type="button" data-moderate-media="' + Number(item.id) + '" data-status="rejected">Reject</button></div></article>';
        }).join('') : '<div class="memories-empty">No submissions are waiting for review.</div>';
        const reports = document.getElementById('memoriesReports');
        reports.innerHTML = data.reports.length ? data.reports.map(function (report) {
            return '<article class="memories-report-card"><div class="memories-queue-copy"><strong>' + escapeHtml(report.album_title) + '</strong><span>' + escapeHtml(report.reason) + '</span></div><div class="memories-report-actions"><button type="button" data-resolve-report="' + Number(report.id) + '" data-status="reviewed">Review</button><button type="button" data-resolve-report="' + Number(report.id) + '" data-status="dismissed">Dismiss</button></div></article>';
        }).join('') : '';
        const comments = document.getElementById('memoriesComments');
        comments.innerHTML = data.comments.length ? data.comments.map(function (comment) {
            return '<article class="memories-report-card"><div class="memories-queue-copy"><strong>' + escapeHtml(comment.author || 'ClassMate') + ' · ' + escapeHtml(comment.album_title) + '</strong><span>' + escapeHtml(comment.body) + '</span></div><div class="memories-report-actions"><button type="button" data-moderate-comment="' + Number(comment.id) + '">Hide comment</button></div></article>';
        }).join('') : '';
    }

    async function loadFeed(options) {
        const append = !!(options && options.append);
        if (!append) {
            state.pageNumber = 1;
            state.media = [];
        }
        const url = new URL(config.apiUrl, window.location.href);
        url.searchParams.set('action', 'feed');
        url.searchParams.set('page', state.pageNumber);
        if (elements.search.value.trim()) url.searchParams.set('q', elements.search.value.trim());
        if (elements.category.value) url.searchParams.set('category_id', elements.category.value);
        if (elements.year.value) url.searchParams.set('year', elements.year.value);
        if (initialAlbum) url.searchParams.set('album_id', initialAlbum);
        elements.feed.setAttribute('aria-busy', 'true');
        try {
            const data = await request(url.toString());
            state.csrfToken = data.csrf_token || state.csrfToken;
            state.albums = data.albums || [];
            state.categories = data.categories || [];
            renderCategories();
            if (!append) renderAlbums();
            state.lastPageLength = data.media.length;
            state.media = append ? state.media.concat(data.media) : data.media;
            state.canLoadMore = !!data.has_more;
            renderFeed(append);
            if (config.isAdmin) renderModeration(data);
        } catch (error) {
            elements.feed.innerHTML = '<div class="memories-empty">' + escapeHtml(error.message) + '</div>';
            showMessage(error.message, 'error');
        } finally {
            elements.feed.setAttribute('aria-busy', 'false');
        }
    }

    async function postAction(fields) {
        const body = fields instanceof FormData ? fields : new FormData();
        if (!(fields instanceof FormData)) {
            Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
        }
        body.set('csrf_token', state.csrfToken);
        return request(config.apiUrl, { method: 'POST', body: body });
    }

    function openModal(id) {
        const dialog = document.getElementById(id);
        if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    }

    function closeDialog(dialog) {
        if (dialog && dialog.open) dialog.close();
    }

    function renderViewerComments(comments) {
        const container = document.getElementById('viewerComments');
        container.innerHTML = comments.length ? comments.map(function (comment) {
            return '<div class="memories-comment"><strong>' + escapeHtml(comment.author || 'ClassMate') + '</strong>' + escapeHtml(comment.body) + '</div>';
        }).join('') : '<div class="memories-empty">Start the conversation with a kind comment.</div>';
        container.scrollTop = container.scrollHeight;
    }

    async function openViewer(index) {
        const media = state.media[index];
        if (!media || media.status !== 'approved') return;
        state.activeMediaIndex = index;
        const mediaContainer = document.getElementById('viewerMedia');
        const like = document.getElementById('viewerLike');
        document.getElementById('viewerCaption').textContent = media.caption || 'A moment worth keeping';
        document.getElementById('viewerMeta').textContent = media.album_title + (media.event_date ? ' · ' + media.event_date : '');
        if (media.media_type === 'youtube') {
            const embed = document.createElement('iframe');
            embed.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(media.youtube_id) + '?rel=0';
            embed.title = media.caption || 'ClassMate memory';
            embed.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            embed.allowFullscreen = true;
            embed.referrerPolicy = 'no-referrer';
            mediaContainer.replaceChildren(embed);
        } else if (media.media_type === 'video') {
            const video = document.createElement('video');
            video.controls = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.src = mediaUrl(media.id, false);
            mediaContainer.replaceChildren(video);
        } else {
            const image = new Image();
            image.alt = media.caption || 'ClassMate memory';
            image.src = mediaUrl(media.id, false);
            mediaContainer.replaceChildren(image);
        }
        like.classList.toggle('is-liked', Number(media.liked) === 1);
        like.querySelector('i').className = (Number(media.liked) ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        like.querySelector('b').textContent = Number(media.like_count);
        document.querySelector('[data-viewer-prev]').disabled = index <= 0;
        document.querySelector('[data-viewer-next]').disabled = index >= state.media.length - 1;
        document.getElementById('viewerReport').hidden = config.isAdmin;
        renderViewerComments([]);
        if (!elements.viewer.open) elements.viewer.showModal();
        try {
            const url = new URL(config.apiUrl, window.location.href);
            url.searchParams.set('action', 'detail');
            url.searchParams.set('id', media.id);
            const detail = await request(url.toString());
            renderViewerComments(detail.comments || []);
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    async function share(url, title) {
        try {
            if (navigator.share) await navigator.share({ title: title, url: url });
            else if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
                showMessage('Link copied to clipboard.', 'success');
            } else {
                window.prompt('Copy this link:', url);
            }
        } catch (error) {
            if (error.name !== 'AbortError') showMessage('Could not share this link.', 'error');
        }
    }

    let searchTimer;
    elements.search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadFeed(); }, 280);
    });
    elements.category.addEventListener('change', function () { loadFeed(); });
    elements.year.addEventListener('change', function () { loadFeed(); });
    document.getElementById('memoriesReset').addEventListener('click', function () {
        elements.search.value = '';
        elements.category.value = '';
        elements.year.value = '';
        initialAlbum = '';
        window.history.replaceState({}, '', config.pageUrl);
        loadFeed();
    });
    elements.loadMore.addEventListener('click', function () {
        state.pageNumber += 1;
        loadFeed({ append: true });
    });

    document.addEventListener('click', async function (event) {
        const trigger = event.target.closest('[data-open-modal], [data-close-modal], [data-view-media], [data-like-media], [data-media-delete], [data-album-open], [data-album-share], [data-album-submit], [data-album-feature], [data-album-edit], [data-album-delete], [data-moderate-media], [data-moderate-comment], [data-resolve-report], [data-close-viewer], [data-viewer-prev], [data-viewer-next]');
        if (!trigger) return;
        if (trigger.hasAttribute('data-open-modal')) return openModal(trigger.dataset.openModal);
        if (trigger.hasAttribute('data-close-modal')) return closeDialog(trigger.closest('dialog'));
        if (trigger.hasAttribute('data-view-media')) return openViewer(Number(trigger.dataset.viewMedia));
        if (trigger.hasAttribute('data-close-viewer')) return closeDialog(elements.viewer);
        if (trigger.hasAttribute('data-viewer-prev')) return openViewer(Math.max(0, state.activeMediaIndex - 1));
        if (trigger.hasAttribute('data-viewer-next')) return openViewer(Math.min(state.media.length - 1, state.activeMediaIndex + 1));
        try {
            if (trigger.hasAttribute('data-like-media')) {
                const id = Number(trigger.dataset.likeMedia);
                const result = await postAction({ action: 'toggle_like', media_id: id });
                const media = state.media.find(function (item) { return Number(item.id) === id; });
                if (media) {
                    media.liked = result.liked ? 1 : 0;
                    media.like_count = result.count;
                }
                trigger.classList.toggle('is-liked', result.liked);
                trigger.innerHTML = '<i class="' + (result.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart"></i> ' + result.count;
                if (state.activeMediaIndex >= 0 && Number(state.media[state.activeMediaIndex].id) === id) {
                    document.getElementById('viewerLike').classList.toggle('is-liked', result.liked);
                    document.querySelector('#viewerLike i').className = (result.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
                    document.querySelector('#viewerLike b').textContent = result.count;
                }
            } else if (trigger.hasAttribute('data-media-delete')) {
                if (window.confirm('Remove this memory from the gallery? This is a soft delete.')) {
                    await postAction({ action: 'moderate_media', media_id: trigger.dataset.mediaDelete, status: 'deleted' });
                    await loadFeed();
                }
            } else if (trigger.hasAttribute('data-album-open')) {
                const url = new URL(config.pageUrl, window.location.href);
                url.searchParams.set('album', trigger.dataset.albumOpen);
                window.location.href = url.toString();
            } else if (trigger.hasAttribute('data-album-share')) {
                const album = state.albums.find(function (item) { return Number(item.id) === Number(trigger.dataset.albumShare); });
                await share(albumUrl(trigger.dataset.albumShare), album ? album.title : 'ClassMate Memories');
            } else if (trigger.hasAttribute('data-album-submit')) {
                await share(submissionUrl(trigger.dataset.albumSubmit), 'Event photo upload');
            } else if (trigger.hasAttribute('data-album-feature')) {
                await postAction({ action: 'toggle_featured', album_id: trigger.dataset.albumFeature });
                await loadFeed();
            } else if (trigger.hasAttribute('data-album-edit')) {
                const album = state.albums.find(function (item) { return Number(item.id) === Number(trigger.dataset.albumEdit); });
                if (!album || !elements.albumForm) return;
                elements.albumForm.reset();
                elements.albumForm.querySelector('[name="action"]').value = 'update_album';
                elements.albumForm.querySelector('[name="album_id"]').value = album.id;
                elements.albumForm.elements.namedItem('title').value = album.title || '';
                elements.albumForm.elements.namedItem('description').value = album.description || '';
                elements.albumForm.elements.namedItem('album_type').value = album.album_type || 'event';
                elements.albumForm.elements.namedItem('event_date').value = album.event_date || '';
                elements.albumForm.elements.namedItem('session_label').value = album.session_label || '';
                elements.albumForm.elements.namedItem('category_id').value = album.category_id || '';
                if (elements.albumForm.elements.namedItem('visibility')) elements.albumForm.elements.namedItem('visibility').value = album.visibility;
                elements.albumForm.querySelector('[name="allow_submissions"]').checked = Number(album.allow_submissions) === 1;
                document.getElementById('albumTitle').textContent = 'Edit album';
                openModal('albumModal');
            } else if (trigger.hasAttribute('data-album-delete')) {
                if (window.confirm('Archive this album? Its media will no longer be shown.')) {
                    await postAction({ action: 'delete_album', album_id: trigger.dataset.albumDelete });
                    await loadFeed();
                }
            } else if (trigger.hasAttribute('data-moderate-media')) {
                await postAction({ action: 'moderate_media', media_id: trigger.dataset.moderateMedia, status: trigger.dataset.status });
                await loadFeed();
                showMessage('Submission ' + trigger.dataset.status + '.', 'success');
            } else if (trigger.hasAttribute('data-resolve-report')) {
                await postAction({ action: 'resolve_report', report_id: trigger.dataset.resolveReport, status: trigger.dataset.status });
                await loadFeed();
            } else if (trigger.hasAttribute('data-moderate-comment')) {
                await postAction({ action: 'moderate_comment', comment_id: trigger.dataset.moderateComment });
                await loadFeed();
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    document.getElementById('viewerLike').addEventListener('click', async function () {
        const media = state.media[state.activeMediaIndex];
        if (!media) return;
        try {
            const result = await postAction({ action: 'toggle_like', media_id: media.id });
            media.liked = result.liked ? 1 : 0;
            media.like_count = result.count;
            this.classList.toggle('is-liked', result.liked);
            this.querySelector('i').className = (result.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
            this.querySelector('b').textContent = result.count;
            const cardButton = document.querySelector('[data-like-media="' + media.id + '"]');
            if (cardButton) {
                cardButton.classList.toggle('is-liked', result.liked);
                cardButton.innerHTML = '<i class="' + (result.liked ? 'fa-solid' : 'fa-regular') + ' fa-heart"></i> ' + result.count;
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    document.getElementById('viewerShare').addEventListener('click', function () {
        const media = state.media[state.activeMediaIndex];
        if (media) share(albumUrl(media.album_id), media.album_title);
    });

    document.getElementById('viewerReport').addEventListener('click', async function () {
        const media = state.media[state.activeMediaIndex];
        if (!media) return;
        const reason = window.prompt('What is the concern? (up to 500 characters)');
        if (!reason || !reason.trim()) return;
        try {
            await postAction({ action: 'report', media_id: media.id, reason: reason.trim().slice(0, 500) });
            showMessage('Thanks. The report was sent to the gallery moderators.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    elements.uploadForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const message = document.getElementById('uploadMessage');
        const file = document.getElementById('mediaFile').files[0];
        const videoUrl = document.getElementById('youtubeUrl').value.trim();
        if (!file && !videoUrl) {
            message.textContent = 'Choose a photo/video file or add a YouTube link.';
            return;
        }
        if (file && videoUrl) {
            message.textContent = 'Choose a file or a YouTube link, not both.';
            return;
        }
        message.textContent = 'Uploading securely…';
        try {
            const form = new FormData(this);
            form.set('csrf_token', state.csrfToken);
            const result = await request(config.apiUrl, { method: 'POST', body: form, credentials: 'same-origin' });
            message.textContent = result.message;
            this.reset();
            await loadFeed();
            window.setTimeout(function () { closeDialog(document.getElementById('uploadModal')); }, 900);
        } catch (error) {
            message.textContent = error.message;
        }
    });

    if (elements.albumForm) {
        elements.albumForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const message = document.getElementById('albumMessage');
            message.textContent = 'Creating album…';
            try {
                const result = await postAction(new FormData(this));
                const actionInput = this.querySelector('[name="action"]');
                const wasUpdate = actionInput.value === 'update_album';
                message.textContent = wasUpdate ? 'Album updated.' : 'Album created.';
                this.reset();
                this.querySelector('[name="action"]').value = 'create_album';
                this.querySelector('[name="album_id"]').value = '';
                document.getElementById('albumTitle').textContent = 'Create an album';
                await loadFeed();
                if (result.submission_token) {
                    await share(submissionUrl(result.submission_token), 'Event photo upload link');
                }
                window.setTimeout(function () { closeDialog(document.getElementById('albumModal')); }, 1000);
            } catch (error) {
                message.textContent = error.message;
            }
        });
    }

    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const data = new FormData(this);
            try {
                const result = await postAction({ action: 'add_category', name: data.get('name') });
                state.categories = result.categories;
                renderCategories();
                this.reset();
                showMessage('Category added.', 'success');
            } catch (error) {
                showMessage(error.message, 'error');
            }
        });
    }

    document.getElementById('commentForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const media = state.media[state.activeMediaIndex];
        const input = this.elements.body;
        if (!media || !input.value.trim()) return;
        try {
            const result = await postAction({ action: 'comment', media_id: media.id, body: input.value.trim() });
            input.value = '';
            renderViewerComments(result.comments || []);
            media.comment_count = Number(media.comment_count) + 1;
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    document.getElementById('memoriesPage').addEventListener('click', function (event) {
        if (event.target.closest('[data-copy-submission]') && config.submissionToken) {
            share(submissionUrl(config.submissionToken), 'Event photo upload link');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!elements.viewer.open) return;
        if (event.key === 'ArrowLeft') openViewer(Math.max(0, state.activeMediaIndex - 1));
        if (event.key === 'ArrowRight') openViewer(Math.min(state.media.length - 1, state.activeMediaIndex + 1));
    });

    const visibility = document.getElementById('albumVisibility');
    if (visibility) {
        visibility.addEventListener('change', function () {
            document.getElementById('batchField').hidden = visibility.value !== 'batch';
        });
    }

    loadFeed();
    if (config.submissionToken) openModal('uploadModal');
})();
