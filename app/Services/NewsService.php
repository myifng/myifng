<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Str;
use App\Models\News;
use App\Models\NewsRemark;
use App\Models\NewsRevision;

/**
 * ख़बर का कारोबारी तर्क: पहुँच (कौन देखे/बदले), सेव + रिश्ते (टॉपिक, टैग, संबंधित, गैलरी),
 * वर्ज़न हिस्ट्री, स्थिति बदलना (NewsWorkflow के नियमों से), शेड्यूलर, असाइनमेंट से तालमेल।
 */
final class NewsService
{
    public const MAX_TAGS = 15;
    public const MAX_RELATED = 6;
    public const MAX_GALLERY = 30;

    // ---------- पहुँच ----------

    public static function isOwner(array $news): bool
    {
        $uid = (int) auth()->id();
        return $uid > 0 && ((int) $news['reporter_id'] === $uid || (int) $news['created_by'] === $uid);
    }

    /** डेस्क (सभी ख़बरें) या सिर्फ़ अपनी */
    public static function seesAll(): bool
    {
        return can('news.approve') || can('news.manage');
    }

    public static function canView(array $news): bool
    {
        return self::seesAll() || self::isOwner($news);
    }

    public static function canEdit(array $news): bool
    {
        if ($news['deleted_at'] !== null || !can('news.edit')) {
            return false;
        }
        if (can('news.manage')) {
            return true;
        }
        if (can('news.approve')) {
            return $news['status'] !== 'archived';
        }
        return self::isOwner($news) && in_array($news['status'], NewsWorkflow::OWNER_EDITABLE, true);
    }

    /** SQL शर्त: यूज़र कौन-सी ख़बरें देख सकता है */
    public static function scope(string $alias = 'n'): array
    {
        if (self::seesAll()) {
            return ['1=1', []];
        }
        $uid = (int) auth()->id();
        return ["($alias.reporter_id = ? OR $alias.created_by = ?)", [$uid, $uid]];
    }

    /** जिन्हें ख़बर का रिपोर्टर बनाया जा सकता है (चालू यूज़र, जिनके रोल में news.create है या Admin/Super Admin) */
    public static function reporters(): array
    {
        return db()->all(
            "SELECT DISTINCT u.id, u.name, r.name AS role FROM {p}users u JOIN {p}roles r ON r.id = u.role_id
             LEFT JOIN {p}role_permissions rp ON rp.role_id = r.id LEFT JOIN {p}permissions p ON p.id = rp.permission_id
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND (p.name = 'news.create' OR r.slug IN ('super-admin','admin'))
             ORDER BY u.name"
        );
    }

    // ---------- सेव ----------

    /**
     * नई या मौजूदा ख़बर सेव; रिश्ते और वर्ज़न एक ही ट्रांज़ैक्शन में
     * $rel = [topics => int[], tags => string[], related => int[], gallery => int[]]
     */
    public static function save(?array $news, array $data, array $rel, string $reason = '', bool $correction = false): int
    {
        return db()->transaction(static function () use ($news, $data, $rel, $reason, $correction): int {
            $data['word_count'] = self::wordCount((string) ($data['content'] ?? ''));
            $data['updated_by'] = auth()->id();
            if ($news) {
                $id = (int) $news['id'];
                if ($correction && $news['status'] === 'published') {
                    $data['correction_note'] = mb_substr($reason, 0, 500);
                    $data['corrected_at'] = now();
                }
                News::update($id, $data);
            } else {
                $data['created_by'] = auth()->id();
                $data['status'] = 'draft';
                $id = News::create($data);
            }
            self::syncRelations($id, $rel);
            self::addRevision($id, $reason !== '' ? $reason : ($news ? 'बदलाव' : 'पहला ड्राफ़्ट'), $correction && $news && $news['status'] === 'published');
            return $id;
        });
    }

    public static function syncRelations(int $id, array $rel): void
    {
        $db = db();
        if (array_key_exists('topics', $rel)) {
            $db->query('DELETE FROM {p}news_topics WHERE news_id = ?', [$id]);
            foreach (array_unique($rel['topics']) as $t) {
                $db->query('INSERT INTO {p}news_topics (news_id, topic_id) VALUES (?, ?)', [$id, $t]);
            }
        }
        if (array_key_exists('tags', $rel)) {
            $old = array_map('intval', array_column($db->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$id]), 'tag_id'));
            $new = self::tagIds($rel['tags']);
            $db->query('DELETE FROM {p}news_tags WHERE news_id = ?', [$id]);
            foreach ($new as $t) {
                $db->query('INSERT INTO {p}news_tags (news_id, tag_id) VALUES (?, ?)', [$id, $t]);
            }
            self::recountTags(array_unique([...$old, ...$new]));
        }
        if (array_key_exists('related', $rel)) {
            $db->query('DELETE FROM {p}news_related WHERE news_id = ?', [$id]);
            foreach (array_values(array_unique(array_diff($rel['related'], [$id]))) as $i => $r) {
                $db->query('INSERT INTO {p}news_related (news_id, related_id, sort_order) VALUES (?, ?, ?)', [$id, $r, $i]);
            }
        }
        if (array_key_exists('gallery', $rel)) {
            $db->query('DELETE FROM {p}news_gallery WHERE news_id = ?', [$id]);
            foreach (array_values(array_unique($rel['gallery'])) as $i => $m) {
                $db->query('INSERT INTO {p}news_gallery (news_id, media_id, sort_order) VALUES (?, ?, ?)', [$id, $m, $i]);
            }
        }
    }

    /** टैग नाम → ID (न हो तो बनता है) */
    public static function tagIds(array $names): array
    {
        $ids = [];
        foreach (array_slice($names, 0, self::MAX_TAGS) as $name) {
            $name = trim(mb_substr(strip_tags((string) $name), 0, 100));
            if ($name === '') {
                continue;
            }
            $id = (int) db()->value('SELECT id FROM {p}tags WHERE name = ?', [$name]);
            if (!$id) {
                $id = db()->insert('tags', ['name' => $name, 'slug' => TaxonomyService::slug('tags', '', $name, 0, 'tag'), 'created_at' => now(), 'updated_at' => now()]);
            }
            $ids[$id] = $id;
        }
        return array_values($ids);
    }

    /** टैग की गिनती: सिर्फ़ प्रकाशित ख़बरें */
    public static function recountTags(array $tagIds): void
    {
        if (!$tagIds) {
            return;
        }
        db()->query(
            "UPDATE {p}tags t SET usage_count = (SELECT COUNT(*) FROM {p}news_tags nt JOIN {p}news n ON n.id = nt.news_id
             WHERE nt.tag_id = t.id AND n.status = 'published' AND n.deleted_at IS NULL) WHERE t.id IN (" . Database::in($tagIds) . ')',
            array_values($tagIds)
        );
    }

    public static function relations(int $id): array
    {
        return [
            'topics' => array_map('intval', array_column(db()->all('SELECT topic_id FROM {p}news_topics WHERE news_id = ?', [$id]), 'topic_id')),
            'tags' => array_column(db()->all('SELECT t.name FROM {p}news_tags nt JOIN {p}tags t ON t.id = nt.tag_id WHERE nt.news_id = ? ORDER BY t.name', [$id]), 'name'),
            'related' => db()->all('SELECT n.id, n.title, n.status FROM {p}news_related r JOIN {p}news n ON n.id = r.related_id WHERE r.news_id = ? ORDER BY r.sort_order', [$id]),
            'gallery' => db()->all('SELECT m.* FROM {p}news_gallery g JOIN {p}media m ON m.id = g.media_id WHERE g.news_id = ? ORDER BY g.sort_order', [$id]),
        ];
    }

    public static function uniqueSlug(string $text, int $exceptId = 0): string
    {
        $slug = Str::slug($text, 90) ?: 'news';
        return TaxonomyService::unique('news', $slug, $exceptId);
    }

    public static function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
        return $text === '' ? 0 : count(explode(' ', $text));
    }

    /** पढ़ने का समय (मिनट), 200 शब्द/मिनट */
    public static function readingTime(int $words): int
    {
        return max(1, (int) ceil($words / 200));
    }

    // ---------- वर्ज़न ----------

    /** मौजूदा स्थिति का पूरा स्नैपशॉट (सामग्री + बाकी खाने + रिश्ते) */
    public static function addRevision(int $id, string $reason, bool $correction = false): int
    {
        $n = News::find($id, true);
        $rel = self::relations($id);
        $data = array_diff_key($n, array_flip(['id', 'title', 'summary', 'content', 'views', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by']));
        $data['topics'] = $rel['topics'];
        $data['tags'] = $rel['tags'];
        $data['related'] = array_map('intval', array_column($rel['related'], 'id'));
        $data['gallery'] = array_map('intval', array_column($rel['gallery'], 'id'));
        $version = (int) db()->value('SELECT COALESCE(MAX(version), 0) + 1 FROM {p}news_revisions WHERE news_id = ?', [$id]);
        return NewsRevision::create([
            'news_id' => $id, 'version' => $version, 'title' => $n['title'], 'summary' => $n['summary'], 'content' => $n['content'],
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'status' => $n['status'], 'reason' => mb_substr($reason, 0, 500),
            'is_correction' => $correction ? 1 : 0, 'changed_by' => auth()->id(), 'created_at' => now(),
        ]);
    }

    /** पुराना वर्ज़न वापस: सामग्री और खाने (स्थिति और प्रकाशन समय नहीं बदलते) */
    public static function restoreRevision(array $news, array $rev): void
    {
        $data = (array) json_decode((string) $rev['data'], true);
        $keep = ['subtitle', 'featured_image', 'image_caption', 'image_credit', 'video_url', 'audio_file', 'category_id', 'location_id', 'source', 'news_credit',
            'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots', ...array_keys(News::FLAGS)];
        $fields = array_intersect_key($data, array_flip($keep));
        $fields += ['title' => $rev['title'], 'summary' => $rev['summary'], 'content' => $rev['content']];
        // बीच में हटाई गई श्रेणी/लोकेशन/मीडिया का ID न लौटे
        foreach (['category_id' => 'categories', 'location_id' => 'locations'] as $col => $t) {
            if (!empty($fields[$col]) && !db()->value("SELECT id FROM {p}$t WHERE id = ?", [$fields[$col]])) {
                $fields[$col] = null;
            }
        }
        $exists = static function (string $table, mixed $ids): array {
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if (!$ids) {
                return [];
            }
            $found = array_map('intval', array_column(db()->all("SELECT id FROM {p}$table WHERE id IN (" . Database::in($ids) . ')', $ids), 'id'));
            return array_values(array_intersect($ids, $found)); // पुराना क्रम बना रहे
        };
        $rel = ['topics' => $exists('topics', $data['topics'] ?? []), 'tags' => (array) ($data['tags'] ?? []),
            'related' => $exists('news', $data['related'] ?? []), 'gallery' => $exists('media', $data['gallery'] ?? [])];
        self::save($news, $fields, $rel, 'वर्ज़न ' . $rev['version'] . ' वापस लाया गया', false);
    }

    // ---------- स्थिति ----------

    /**
     * वर्कफ़्लो का काम लागू करें। पहले NewsWorkflow::check() पास होना चाहिए।
     * @return string|null त्रुटि
     */
    public static function transition(array $news, string $action, string $remark = '', ?string $scheduleAt = null): ?string
    {
        if ($err = NewsWorkflow::check($news, $action)) {
            return $err;
        }
        if (NewsWorkflow::needsRemark($action) && trim($remark) === '') {
            return 'इस काम के लिए कारण/टिप्पणी लिखना ज़रूरी है।';
        }
        $to = NewsWorkflow::target($action);
        $data = ['status' => $to, 'updated_by' => auth()->id()];
        if (in_array($action, ['publish', 'schedule'], true)) {
            if (trim((string) $news['title']) === '' || self::wordCount((string) $news['content']) < 20) {
                return 'प्रकाशन से पहले शीर्षक और कम से कम 20 शब्दों का लेख ज़रूरी है।';
            }
            if (!$news['category_id']) {
                return 'प्रकाशन से पहले श्रेणी चुनें।';
            }
        }
        if ($action === 'schedule') {
            $ts = $scheduleAt ? strtotime($scheduleAt) : false;
            if (!$ts || $ts <= time() + 60) {
                return 'शेड्यूल का समय कम से कम 2 मिनट आगे का होना चाहिए।';
            }
            $data['scheduled_at'] = date('Y-m-d H:i:s', $ts);
            $data['editor_id'] = auth()->id();
        }
        if ($action === 'publish') {
            $data['published_at'] = $news['published_at'] ?: now();
            $data['scheduled_at'] = null;
            $data['editor_id'] = auth()->id();
        }
        if (in_array($action, ['approve', 'reject'], true)) {
            $data['editor_id'] = auth()->id();
        }
        if ($action === 'unschedule') {
            $data['scheduled_at'] = null;
        }
        db()->transaction(static function () use ($news, $data, $action, $remark, $to) {
            News::update((int) $news['id'], $data);
            self::remark((int) $news['id'], 'status', $remark, $news['status'], $to);
            self::syncAssignment($news, $to);
            if (in_array($to, ['published', 'disabled', 'archived'], true) || $news['status'] === 'published') {
                self::recountTags(array_map('intval', array_column(db()->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$news['id']]), 'tag_id')));
            }
        });
        AuditService::log($action, 'news', $news['id'], NewsWorkflow::label($news['status']) . ' → ' . NewsWorkflow::label($to) . ': ' . $news['title'] . ($remark !== '' ? ' (' . mb_substr($remark, 0, 120) . ')' : ''));
        return null;
    }

    public static function remark(int $newsId, string $type, string $message, ?string $from = null, ?string $to = null): int
    {
        return NewsRemark::create([
            'news_id' => $newsId, 'user_id' => auth()->id(), 'type' => $type, 'message' => trim(mb_substr($message, 0, 3000)) ?: null,
            'from_status' => $from, 'to_status' => $to, 'created_at' => now(),
        ]);
    }

    /** ख़बर की स्थिति से जुड़े असाइनमेंट की स्थिति */
    private static function syncAssignment(array $news, string $to): void
    {
        if (!$news['assignment_id']) {
            return;
        }
        $map = ['submitted' => 'submitted', 'published' => 'completed', 'scheduled' => 'completed', 'rejected' => 'in_progress', 'draft' => 'in_progress'];
        if (isset($map[$to])) {
            db()->query("UPDATE {p}assignments SET status = ?, updated_at = NOW() WHERE id = ? AND status <> 'cancelled'", [$map[$to], $news['assignment_id']]);
        }
    }

    /**
     * शेड्यूल की गई ख़बरें जिनका समय आ गया: प्रकाशित करें (cron की ज़रूरत नहीं; SchedulerMiddleware से)
     * @return int कितनी प्रकाशित हुईं
     */
    public static function publishDue(): int
    {
        $due = db()->all("SELECT * FROM {p}news WHERE status = 'scheduled' AND scheduled_at <= NOW() AND deleted_at IS NULL LIMIT 50");
        foreach ($due as $n) {
            db()->transaction(static function () use ($n) {
                db()->query("UPDATE {p}news SET status = 'published', published_at = COALESCE(published_at, scheduled_at), scheduled_at = NULL WHERE id = ? AND status = 'scheduled'", [$n['id']]);
                NewsRemark::create(['news_id' => $n['id'], 'user_id' => null, 'type' => 'status', 'message' => 'तय समय पर अपने आप प्रकाशित', 'from_status' => 'scheduled', 'to_status' => 'published', 'created_at' => now()]);
                self::syncAssignment($n, 'published');
                self::recountTags(array_map('intval', array_column(db()->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$n['id']]), 'tag_id')));
            });
        }
        return count($due);
    }

    /** वेबसाइट पर ख़बर का पता (Phase 5 का article पेज इसी से) */
    public static function url(array $news): string
    {
        return url('news/' . $news['slug']);
    }
}
