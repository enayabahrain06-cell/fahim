<?php

namespace App\Services;

use ArPHP\I18N\Arabic;
use Illuminate\Support\HtmlString;

/**
 * dompdf neither joins Arabic letters nor lays them out right to left, so Arabic text is
 * converted to presentation forms in visual order and set in Amiri, which has Arabic glyphs.
 */
class PdfText
{
    private ?Arabic $arabic = null;

    public function hasArabic(?string $text): bool
    {
        return $text !== null && preg_match('/\p{Arabic}/u', $text) === 1;
    }

    public function html(?string $text): HtmlString
    {
        $text = (string) $text;

        if (! $this->hasArabic($text)) {
            return new HtmlString(e($text));
        }

        $this->arabic ??= new Arabic;

        return new HtmlString('<span class="ar">'.e($this->arabic->utf8Glyphs($text)).'</span>');
    }
}
