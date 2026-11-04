<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

Auth::requireStudent();

function failResourceDownload(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

function isGoogleDownloadHost(string $host): bool
{
    $host = strtolower($host);
    $googleusercontentDomain = 'googleusercontent.com';
    return $host === 'google.com'
        || substr($host, -11) === '.google.com'
        || $host === $googleusercontentDomain
        || (strlen($host) > strlen($googleusercontentDomain)
            && substr($host, -strlen($googleusercontentDomain)) === $googleusercontentDomain
            && $host[strlen($host) - strlen($googleusercontentDomain) - 1] === '.');
}

function resolveGoogleRedirect(string $baseUrl, string $location): ?string
{
    if (preg_match('~^https://~i', $location)) {
        return $location;
    }

    $base = parse_url($baseUrl);
    if (!$base || empty($base['host'])) {
        return null;
    }

    if (strpos($location, '//') === 0) {
        return 'https:' . $location;
    }

    $origin = 'https://' . $base['host'];
    if (strpos($location, '/') === 0) {
        return $origin . $location;
    }

    if (strpos($location, '?') === 0) {
        return $origin . ($base['path'] ?? '/') . $location;
    }

    $path = $base['path'] ?? '/';
    return $origin . substr($path, 0, strrpos($path, '/') + 1) . $location;
}

$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$studentId = (int)Session::get('user_id');
$batchId = (int)(Authorization::getStudentBatchId() ?? 0);
if (!$resourceId || $resourceId < 1 || !$studentId || !$batchId) {
    failResourceDownload(404, 'Resource not found.');
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT id, title, drive_link
    FROM resources
    WHERE id = ? AND batch_id = ? AND status = 'published'
    LIMIT 1
");
$stmt->execute([$resourceId, $batchId]);
$resource = $stmt->fetch();
if (!$resource) {
    failResourceDownload(404, 'Resource not found.');
}

$driveUrl = trim((string)$resource['drive_link']);
$urlParts = parse_url($driveUrl);
$host = strtolower((string)($urlParts['host'] ?? ''));
$path = (string)($urlParts['path'] ?? '');
$fileId = null;
$documentType = null;

if (!in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
    failResourceDownload(422, 'This resource does not have a supported Google Drive download link.');
}

if (preg_match('~/(?:file/)?d/([a-zA-Z0-9_-]+)~', $path, $matches)) {
    $fileId = $matches[1];
} else {
    parse_str((string)($urlParts['query'] ?? ''), $query);
    $fileId = $query['id'] ?? null;
}

if ($host === 'docs.google.com' && preg_match('~^/(document|spreadsheets|presentation)/d/~', $path, $matches)) {
    $documentType = $matches[1];
}

if (!is_string($fileId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
    failResourceDownload(422, 'The resource has an invalid Google Drive file link.');
}

$downloadUrl = $documentType
    ? 'https://docs.google.com/' . $documentType . '/d/' . rawurlencode($fileId) . '/export?format=pdf'
    : 'https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId);

if (!function_exists('curl_init')) {
    error_log('Resource download failed: PHP cURL extension is unavailable.');
    failResourceDownload(503, 'Downloads are temporarily unavailable. Please contact the administrator.');
}

$maxBytes = 100 * 1024 * 1024;
$downloadStream = null;
$contentType = 'application/octet-stream';
$downloadSize = 0;
$lastError = '';

for ($redirects = 0; $redirects <= 5; $redirects++) {
    $currentParts = parse_url($downloadUrl);
    $currentHost = strtolower((string)($currentParts['host'] ?? ''));
    if (($currentParts['scheme'] ?? '') !== 'https' || !isGoogleDownloadHost($currentHost)) {
        failResourceDownload(502, 'Google Drive returned an unsupported download location.');
    }

    $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
    if (!$stream) {
        error_log('Resource download failed: unable to open temporary stream.');
        failResourceDownload(500, 'Could not prepare this download. Please try again.');
    }

    $responseHeaders = ['location' => null, 'content_type' => null];
    $receivedBytes = 0;
    $exceededSizeLimit = false;
    $curl = curl_init($downloadUrl);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'ClassMate Resource Download',
        CURLOPT_HTTPHEADER => ['Accept: */*'],
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$responseHeaders): int {
            $length = strlen($header);
            if (stripos($header, 'Location:') === 0) {
                $responseHeaders['location'] = trim(substr($header, 9));
            } elseif (stripos($header, 'Content-Type:') === 0) {
                $responseHeaders['content_type'] = trim(explode(';', substr($header, 13), 2)[0]);
            }
            return $length;
        },
        CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use ($stream, &$receivedBytes, &$exceededSizeLimit, $maxBytes): int {
            $length = strlen($data);
            if ($receivedBytes + $length > $maxBytes) {
                $exceededSizeLimit = true;
                return 0;
            }
            $written = fwrite($stream, $data);
            if ($written === false) {
                return 0;
            }
            $receivedBytes += $written;
            return $written;
        }
    ]);

    $success = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $lastError = curl_error($curl);
    curl_close($curl);

    if ($success === false) {
        fclose($stream);
        error_log('Resource download request failed: ' . $lastError);
        if ($exceededSizeLimit) {
            failResourceDownload(413, 'This file is larger than the 100 MB download limit.');
        }
        failResourceDownload(502, 'Google Drive could not provide this file. Check that the resource is shared for viewing.');
    }

    if ($status >= 300 && $status < 400 && !empty($responseHeaders['location'])) {
        fclose($stream);
        if ($redirects === 5) {
            failResourceDownload(502, 'Google Drive redirected too many times to provide this file.');
        }
        $nextUrl = resolveGoogleRedirect($downloadUrl, $responseHeaders['location']);
        $nextParts = $nextUrl ? parse_url($nextUrl) : false;
        if (!$nextParts || ($nextParts['scheme'] ?? '') !== 'https' || !isGoogleDownloadHost((string)($nextParts['host'] ?? ''))) {
            failResourceDownload(502, 'Google Drive returned an unsupported download location.');
        }
        $downloadUrl = $nextUrl;
        continue;
    }

    if ($status < 200 || $status >= 300) {
        fclose($stream);
        error_log('Resource download received HTTP status ' . $status);
        failResourceDownload(502, 'Google Drive could not provide this file. Check that the resource is shared for viewing.');
    }

    $contentType = strtolower((string)($responseHeaders['content_type'] ?? 'application/octet-stream'));
    if (!preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~', $contentType)) {
        $contentType = 'application/octet-stream';
    }
    if (strpos($contentType, 'text/html') === 0 || strpos($contentType, 'application/json') === 0) {
        fclose($stream);
        failResourceDownload(502, 'Google Drive returned a preview or permission page instead of the file. Check its sharing permissions.');
    }

    $downloadStream = $stream;
    $downloadSize = $receivedBytes;
    break;
}

if (!$downloadStream) {
    error_log('Resource download failed after redirects: ' . $lastError);
    failResourceDownload(502, 'Could not download this resource. Please try again.');
}

$filenameBase = trim((string)$resource['title']);
$filenameBase = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $filenameBase);
$filenameBase = preg_replace('/\s+/u', ' ', $filenameBase);
$filenameBase = trim($filenameBase, " .\t\n\r\0\x0B");
if ($filenameBase === '') {
    $filenameBase = 'classmate-resource';
}
if ($documentType && !preg_match('/\.pdf$/i', $filenameBase)) {
    $filenameBase .= '.pdf';
}
$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filenameBase);
$asciiFilename = str_replace(['"', '\\'], '_', $asciiFilename);

$stmt = $db->prepare("
    UPDATE resources SET download_count = download_count + 1
    WHERE id = ? AND batch_id = ? AND status = 'published'
");
$stmt->execute([$resourceId, $batchId]);

rewind($downloadStream);
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $asciiFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filenameBase));
header('Content-Length: ' . $downloadSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
fpassthru($downloadStream);
fclose($downloadStream);
