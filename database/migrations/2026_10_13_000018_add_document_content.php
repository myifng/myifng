<?php
/**
 * रिपोर्टर दस्तावेज़ (पत्र/प्रमाणपत्र): जारी होते समय का शीर्षक और सामग्री (टेम्पलेट से बना) सुरक्षित
 * ताकि बाद में टेम्पलेट बदलने से पुराना जारी पत्र अपने-आप न बदले।
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $cols = array_column($db->all('SHOW COLUMNS FROM {p}reporter_documents'), 'Field');
        if (!in_array('content', $cols, true)) {
            $db->query('ALTER TABLE {p}reporter_documents ADD `content` MEDIUMTEXT NULL AFTER `revoked_reason`');
        }
    }
};
