<?php
declare(strict_types=1);

namespace App\Core;

/** पेज नंबर वाली सूची */
final class Paginator
{
    public readonly int $pages;

    public function __construct(public readonly array $items, public readonly int $total, public readonly int $perPage, public readonly int $page)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
    }

    public static function offset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * $perPage;
    }

    public function from(): int
    {
        return $this->total ? ($this->page - 1) * $this->perPage + 1 : 0;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    /** मौजूदा क्वेरी स्ट्रिंग रखते हुए किसी पेज का लिंक */
    public function url(int $page): string
    {
        $q = $_GET;
        $q['page'] = $page;
        return '?' . http_build_query($q);
    }
}
