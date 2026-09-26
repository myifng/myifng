<?php
/** पॉडकास्ट RSS (iTunes नेमस्पेस)। XML में सब कुछ escape (ENT_XML1) */
$x = static fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$link = route('podcast', ['slug' => $s['slug']]);
$img = $s['cover'] ? media_url($s['cover'], 'original') : (setting('logo') ? upload_url((string) setting('logo')) : '');
$lang = (string) setting('language', 'hi');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
  <title><?= $x($s['title']) ?></title>
  <link><?= $x($link) ?></link>
  <atom:link href="<?= $x(route('podcast.feed', ['slug' => $s['slug']])) ?>" rel="self" type="application/rss+xml"/>
  <description><?= $x($s['description'] ?: $s['title']) ?></description>
  <language><?= $x($lang) ?></language>
  <copyright><?= $x('© ' . date('Y') . ' ' . setting('site_name')) ?></copyright>
  <itunes:author><?= $x($s['author'] ?: setting('site_name')) ?></itunes:author>
  <itunes:summary><?= $x($s['description'] ?: $s['title']) ?></itunes:summary>
  <itunes:explicit>false</itunes:explicit>
  <itunes:category text="News"/>
  <?php if ($img): ?><itunes:image href="<?= $x($img) ?>"/>
  <image><url><?= $x($img) ?></url><title><?= $x($s['title']) ?></title><link><?= $x($link) ?></link></image><?php endif; ?>
<?php foreach ($eps as $e):
    $src = $e['file'] ? upload_url($e['file']) : \App\Services\EmbedService::safeLink($e['external_url']);
    if (!$src) continue;
    $abs = $e['file'] ? BASE_PATH . '/public/uploads/' . $e['file'] : null;
    $len = $abs && is_file($abs) ? filesize($abs) : 0;
    $mime = preg_match('/\.m4a$/i', $src) ? 'audio/mp4' : (preg_match('/\.ogg$/i', $src) ? 'audio/ogg' : (preg_match('/\.wav$/i', $src) ? 'audio/wav' : 'audio/mpeg')); ?>
  <item>
    <title><?= $x($e['title']) ?></title>
    <link><?= $x(\App\Services\MultimediaService::url('audio', $e)) ?></link>
    <guid isPermaLink="false"><?= $x('audio-' . $e['id']) ?></guid>
    <pubDate><?= $x(date(DATE_RSS, strtotime($e['published_at']))) ?></pubDate>
    <description><?= $x($e['description'] ?: $e['title']) ?></description>
    <enclosure url="<?= $x($src) ?>" length="<?= (int) $len ?>" type="<?= $x($mime) ?>"/>
    <?php if ($e['duration']): ?><itunes:duration><?= (int) $e['duration'] ?></itunes:duration><?php endif; ?>
    <?php if ($e['episode_no']): ?><itunes:episode><?= (int) $e['episode_no'] ?></itunes:episode><?php endif; ?>
    <?php if ($e['cover']): ?><itunes:image href="<?= $x(media_url($e['cover'], 'original')) ?>"/><?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>
