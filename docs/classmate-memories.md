# ClassMate Memories

## Install

1. Back up the database, then import [`20261108_add_classmate_memories.sql`](../migrations/20261108_add_classmate_memories.sql) into the ClassMate database with phpMyAdmin or the MySQL command line.
2. Deploy the changed PHP/CSS/JavaScript files and open **ClassMate Memories** from the sidebar or mobile navigation.
3. Ensure the PHP runtime has **PDO MySQL, GD, and Fileinfo** enabled. Image uploads are re-encoded as optimized JPEGs and thumbnails; video uploads are limited to MP4/WebM.
4. Ensure the PHP process can create and write to `MEMORIES_STORAGE_PATH`. By default this is a `classmate-private/memories` directory two levels above the application root, outside the web document root. Keep it outside public web serving and preserve the generated `.htaccess` deny rule as defense in depth.
5. If your PHP limits are lower than the application limits, set `upload_max_filesize` to at least `100M`, `post_max_size` to at least `105M`, and a suitable `max_execution_time`. The app enforces a 20 MB image and 100 MB video limit independently.

## Roles and privacy

- Students can browse authorized albums, submit to albums that accept submissions, like/comment on approved memories, and report content.
- Admins scoped to a batch (including that batch's CR account) can create/manage that batch's albums and review its submissions, reports, and comments. Super admins can manage global albums and gallery categories.
- Album access and media delivery are checked server-side on every request. Media files are randomized, stored outside the public web root, and served only through `api/memories-media.php`. Album URLs do not bypass authentication or batch/private album checks.
- Student submissions remain pending until an authorized admin approves them. Soft-deleted albums and media remain recorded; moderation and album management write to `memory_audit_logs`.
- Event upload links require an authenticated student and follow the album's scope: batch albums require membership in that batch, while global albums accept students across batches. Copy an album's **Event link** and encode that URL as a QR code using an approved/private QR tool before printing it. The URL grants upload access only; it does not grant viewing access to private albums.

## Memories features

The shared Memories section includes year browsing (Memory Lane), session and event album types, category/tag filters, masonry photo/video cards, lazy-loaded thumbnails, a keyboard/touch-friendly lightbox, YouTube embeds, previous/next navigation, pagination, album links, likes, comments, reports, and moderation queues. YouTube content is embedded only after opening a memory.

## Deployment notes

- Apply the SQL migration before serving the Memories page/API.
- Validate the private storage directory from the PHP process account after deployment. If the application directory layout differs, update `MEMORIES_STORAGE_PATH` in `includes/config.php` to a non-public writable location.
- Verify HTTPS in production; the existing ClassMate session-cookie settings are managed independently by the main configuration.
- Smoke-test with a student account, a batch-scoped admin/CR account, and a super-admin account. Test that an unrelated batch cannot view the album URL or request its media endpoint.
