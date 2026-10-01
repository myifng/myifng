<?php
/**
 * "ख़बर सुनें": हर ख़बर पर चालू/बंद, और बने हुए ऑडियो (Google TTS) का कैश
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $cols = array_column($db->all('SHOW COLUMNS FROM {p}news'), 'Field');
        if (!in_array('allow_listen', $cols, true)) {
            $db->query('ALTER TABLE {p}news ADD `allow_listen` TINYINT(1) NOT NULL DEFAULT 1 AFTER `allow_comments`');
        }
        if (!in_array('tts_file', $cols, true)) {
            $db->query('ALTER TABLE {p}news ADD `tts_file` VARCHAR(190) NULL AFTER `allow_listen`, ADD `tts_hash` CHAR(40) NULL AFTER `tts_file`');
        }
    }
};
