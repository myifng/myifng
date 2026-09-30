<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\BackupService as BS;

/** बैकअप: बनाएँ, इतिहास, डाउनलोड, हटाएँ */
final class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all('SELECT b.*, u.name by_name FROM {p}backups b LEFT JOIN {p}users u ON u.id = b.created_by ORDER BY b.created_at DESC, b.id DESC LIMIT 100');
        $dir = BS::dir();
        $dbSize = (int) db()->value('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [str_replace('_', '\\_', db()->prefix()) . '%']);
        $uploads = 0;
        if (is_dir(BASE_PATH . '/public/uploads')) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . '/public/uploads', \FilesystemIterator::SKIP_DOTS)) as $f) {
                $uploads += $f->isFile() ? $f->getSize() : 0;
            }
        }
        return $this->view('admin/backups/index', ['items' => $items, 'free' => @disk_free_space($dir) ?: 0, 'dbSize' => $dbSize, 'uploads' => $uploads,
            'zip' => class_exists(\ZipArchive::class), 'total' => array_sum(array_map(static fn($b) => (int) $b['size'], $items))]);
    }

    public function store(Request $request): Response
    {
        $type = (string) $request->input('type', 'db');
        $r = BS::create($type, auth()->id(), 'manual', (string) $request->input('note', ''));
        AuditService::log('backup', 'backups', $r['id'] ?: null, $r['message']);
        return $this->toRoute('admin.backups.index')->with($r['ok'] ? 'success' : 'danger', $r['message']);
    }

    public function download(Request $request, int $id): Response
    {
        $b = db()->first("SELECT * FROM {p}backups WHERE id = ? AND status = 'done'", [$id]) ?? throw new HttpException(404);
        $path = $b['file'] ? BS::path($b['file']) : null;
        if (!$path || !is_file($path)) {
            return $this->back()->with('danger', 'बैकअप फ़ाइल सर्वर पर नहीं मिली।');
        }
        AuditService::log('download', 'backups', $id, 'बैकअप डाउनलोड: ' . $b['file']);
        return Response::download($path, $b['file'], str_ends_with($b['file'], '.zip') ? 'application/zip' : 'application/gzip');
    }

    public function destroy(Request $request, int $id): Response
    {
        $b = db()->first('SELECT * FROM {p}backups WHERE id = ?', [$id]) ?? throw new HttpException(404);
        BS::remove($id);
        AuditService::log('delete', 'backups', $id, 'बैकअप हटाया: ' . ($b['file'] ?? '#' . $id));
        return $this->toRoute('admin.backups.index')->with('success', 'बैकअप हट गया।');
    }
}
