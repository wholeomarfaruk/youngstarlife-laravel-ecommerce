<?php

namespace App\Support;

/**
 * HTML from the rich text editor (Omar Text Editor, Ops → Product → Full description).
 * The editor already sanitises in the browser; this is the server-side pass the editor's docs ask for —
 * run on save and again before the storefront prints it raw.
 *
 *   RichText::clean($html)   allow-listed HTML (HTMLPurifier)
 *   RichText::isRich($html)  has formatting beyond plain lines → the product page shows it as HTML instead of ✓ lines
 */
class RichText
{
    protected static ?\HTMLPurifier $purifier = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return $html;
        $out = self::purifier()->purify($html);
        $out = preg_replace('#<div[^>]*\bclass="[^"]*\bomar-text-editor-[\w-]*resize-handle\b[^"]*"[^>]*>\s*</div>#i', '', $out);   // the editor's table drag handles get saved with the HTML (fixed px width → page wider than the screen)
        return trim(preg_replace('#<iframe(?![^>]*\ssrc=)[^>]*>\s*</iframe>#i', '', $out));   // an embed from a host not on the list loses its src → drop the empty box
    }

    /** plain lines (<p>, <br>, simple lists) keep the ✓ feature list; bold, headings, images, tables, links, embeds… render as HTML */
    public static function isRich(?string $html): bool
    {
        return (bool) preg_match('#<(strong|b|em|i|u|s|sub|sup|h[1-6]|img|table|a|iframe|video|audio|blockquote|pre|code|hr|span[^>]+style)\b#i', (string) $html);
    }

    protected static function purifier(): \HTMLPurifier
    {
        if (self::$purifier) return self::$purifier;
        $dir = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($dir)) @mkdir($dir, 0755, true);

        $c = \HTMLPurifier_Config::createDefault();
        $c->set('Cache.SerializerPath', $dir);
        $c->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $c->set('HTML.DefinitionID', 'ys-richtext');   // + <video> / <audio> (editor's media plugin) below
        $c->set('HTML.DefinitionRev', 3);
        $c->set('Attr.EnableID', true);                // anchor plugin: <a id> jump targets
        $c->set('HTML.Allowed', implode(',', [
            'p[style]', 'br', 'hr', 'div[style|class]', 'span[style|class]', 'h1[style]', 'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
            'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'code[class]', 'pre[class|style]', 'blockquote[style]',
            'ul[style]', 'ol[style]', 'li[style]', 'a[href|target|title|id|name]', 'img[src|alt|width|height|style]',
            'table[style|width]', 'thead', 'tbody', 'tfoot', 'tr[style]', 'th[style|colspan|rowspan]', 'td[style|colspan|rowspan]', 'caption',
            'iframe[src|width|height|frameborder|style]', 'video[src|controls|width|height|poster|style]', 'audio[src|controls|style]', 'source[src|type]',
        ]));
        $c->set('CSS.AllowedProperties', ['color', 'background-color', 'text-align', 'font-weight', 'font-style', 'font-family', 'text-decoration', 'font-size', 'line-height',
            'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom', 'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
            'width', 'height', 'max-width', 'float', 'border', 'border-collapse', 'vertical-align']);
        $c->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $c->set('Attr.AllowedFrameTargets', ['_blank']);
        // embeds: only the same video / map hosts the editor's media plugin offers
        $c->set('HTML.SafeIframe', true);
        $c->set('URI.SafeIframeRegexp', '%^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed|docs\.google\.com/|open\.spotify\.com/embed/|codepen\.io/)%');
        // self-hosted <video> / <audio> (HTML5, unknown to HTMLPurifier) — must come last: it locks the config
        if ($def = $c->maybeGetRawHTMLDefinition()) {
            $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', ['src' => 'URI', 'controls' => 'Bool', 'width' => 'Length', 'height' => 'Length', 'poster' => 'URI']);
            $def->addElement('audio', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', ['src' => 'URI', 'controls' => 'Bool']);
            $def->addElement('source', 'Block', 'Empty', 'Common', ['src' => 'URI', 'type' => 'Text']);
        }
        return self::$purifier = new \HTMLPurifier($c);
    }
}
