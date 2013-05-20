<?php

/**
 * Rutinas de compresión y procesado de código HTML
 *
 * @package Tinyfier
 */
abstract class TinyfierHTML {

    private static $_settings;

    /**
     * Remove whitespaces from HTML code
     * @param string $html
     * @param boolean $compress_all Compress embedded css and js code
     * @return string
     */
    public static function process($html, array $settings = array()) {

        $settings = self::$_settings = $settings + array(
            'compress_all' => TRUE,
            'markers' => array(
                '<?'
            )
        );

        if ($settings['compress_all']) {
            require_once dirname(dirname(__FILE__)) . '/css/css.php';
            require_once dirname(dirname(__FILE__)) . '/js/js.php';

            return Minify_HTML::minify($html, array(
                'cssMinifier' => 'TinyfierHTML::_compress_inline_css',
                'jsMinifier' => 'TinyfierHTML::_compress_inline_js'
            ));
        } else {
            return Minify_HTML::minify($html);
        }
    }

    /**
     * Compress inline CSS code found in a HTML file.
     * Only por internal usage.
     * @access private
     */
    public static function _compress_inline_css($css) {
        if (self::_has_mark($css)) {
            return $css;
        } else {
            return TinyfierCSS::process($css, array(
                'use_less' => FALSE,
                'ie_compatible' => TRUE
            ));
        }
    }

    /**
     * Compress inline JS code found in a HTML file.
     * Only por internal usage.
     * @access private
     */
    public static function _compress_inline_js($js) {
        if (self::_has_mark($js)) {
            return $js;
        } else {
            return TinyfierJS::process($js);
        }
    }

    /**
     * Comprobar si el código tiene alguna de las marcas establecidas que evitan su compresión.
     * Se utiliza para evitar que fragmentos de código que lleven incustrado código PHP
     * se compriman y den lugar a pérdida de datos
     */
    private static function _has_mark($code) {
        foreach (self::$_settings['markers'] as $mark) {
            if (strpos($code, $mark) !== FALSE) {
                return TRUE;
            }
        }
        return FALSE;
    }

}

/**
 * Compress HTML
 *
 * This is a heavy regex-based removal of whitespace, unnecessary comments and
 * tokens. IE conditional comments are preserved. There are also options to have
 * STYLE and SCRIPT blocks compressed by callback functions.
 *
 * A test suite is available.
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class Minify_HTML {

    /**
     * "Minify" an HTML page
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     *
     * @return string
     */
    public static function minify($html, $options = array()) {
        $min = new Minify_HTML($html, $options);
        return $min->process();
    }

    /**
     * Create a minifier object
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     *
     * @return NULL
     */
    public function __construct($html, $options = array()) {
        $this->_html = str_replace("\r\n", "\n", trim($html));
        if (isset($options['xhtml'])) {
            $this->_isXhtml = (bool)$options['xhtml'];
        }
        if (isset($options['cssMinifier'])) {
            $this->_cssMinifier = $options['cssMinifier'];
        }
        if (isset($options['jsMinifier'])) {
            $this->_jsMinifier = $options['jsMinifier'];
        }
    }

    /**
     * Minify the markeup given in the constructor
     *
     * @return string
     */
    public function process() {
        if ($this->_isXhtml === NULL) {
            $this->_isXhtml = (FALSE !== strpos($this->_html, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML'));
        }

        $this->_replacementHash = 'MINIFYHTML' . md5($_SERVER['REQUEST_TIME']);
        $this->_placeholders = array();

        // replace SCRIPTs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/(\\s*)<script(\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/iu'
            , array($this, '_removeScriptCB')
            , $this->_html);

        // replace STYLEs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<style(\\b[^>]*>)([\\s\\S]*?)<\\/style>\\s*/iu'
            , array($this, '_removeStyleCB')
            , $this->_html);

        // remove HTML comments (not containing IE conditional comments).
        $this->_html = preg_replace_callback(
            '/<!--([\\s\\S]*?)-->/u'
            , array($this, '_commentCB')
            , $this->_html);

        // replace PREs with placeholders
        $this->_html = preg_replace_callback('/\\s*<pre(\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/iu'
            , array($this, '_removePreCB')
            , $this->_html);

        // replace TEXTAREAs with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<textarea(\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/iu'
            , array($this, '_removeTextareaCB')
            , $this->_html);

        // trim each line.
        // @todo take into account attribute values that span multiple lines.
        $this->_html = preg_replace('/^\\s+|\\s+$/mu', '', $this->_html);
        
        // remove ws around block/undisplayed elements
        $this->_html = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body'
            . '|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form'
            . '|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta'
            . '|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)'
            . '|ul)\\b[^>]*>)/iu', '$1', $this->_html);

        // remove ws outside of all elements
        $this->_html = preg_replace(
            '/>(\\s(?:\\s*))?([^<]+)(\\s(?:\s*))?</u'
            , '>$1$2$3<'
            , $this->_html);

        // use newlines before 1st attribute in open tags (to limit line lengths)
        $this->_html = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/iu', "$1\n$2", $this->_html);

        // fill placeholders
        $this->_html = str_replace(
            array_keys($this->_placeholders)
            , array_values($this->_placeholders)
            , $this->_html
        );
        // issue 229: multi-pass to catch scripts that didn't get replaced in textareas
        $this->_html = str_replace(
            array_keys($this->_placeholders)
            , array_values($this->_placeholders)
            , $this->_html
        );
        return $this->_html;
    }

    protected function _commentCB($m) {
        return (0 === strpos($m[1], '[') || FALSE !== strpos($m[1], '<![')) ? $m[0] : '';
    }

    protected function _reservePlace($content) {
        $placeholder = '%' . $this->_replacementHash . count($this->_placeholders) . '%';
        $this->_placeholders[$placeholder] = $content;
        return $placeholder;
    }

    protected $_isXhtml = NULL;
    protected $_replacementHash = NULL;
    protected $_placeholders = array();
    protected $_cssMinifier = NULL;
    protected $_jsMinifier = NULL;

    protected function _removePreCB($m) {
        return $this->_reservePlace("<pre{$m[1]}");
    }

    protected function _removeTextareaCB($m) {
        return $this->_reservePlace("<textarea{$m[1]}");
    }

    protected function _removeStyleCB($m) {
        $openStyle = "<style{$m[1]}";
        $css = $m[2];
        // remove HTML comments
        $css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);

        // remove CDATA section markers
        $css = $this->_removeCdata($css);

        // minify
        $minifier = $this->_cssMinifier ? $this->_cssMinifier : 'trim';
        $compressed = call_user_func($minifier, $css);
        if ($compressed !== FALSE)
            $css = $compressed;

        return $this->_reservePlace($this->_needsCdata($css) ? "{$openStyle}/*<![CDATA[*/{$css}/*]]>*/</style>" : "{$openStyle}{$css}</style>"
        );
    }

    protected function _removeScriptCB($m) {
        $openScript = "<script{$m[2]}";
        $js = $m[3];

        // whitespace surrounding? preserve at least one space
        $ws1 = ($m[1] === '') ? '' : ' ';
        $ws2 = ($m[4] === '') ? '' : ' ';

        // remove HTML comments (and ending "//" if present)
        $js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js);

        // remove CDATA section markers
        $js = $this->_removeCdata($js);

        // minify
        $minifier = $this->_jsMinifier ? $this->_jsMinifier : 'trim';
        $compressed = call_user_func($minifier, $js);
        if ($compressed !== FALSE)
            $js = $compressed;

        return $this->_reservePlace($this->_needsCdata($js) ? "{$ws1}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$ws2}" : "{$ws1}{$openScript}{$js}</script>{$ws2}"
        );
    }

    protected function _removeCdata($str) {
        return (FALSE !== strpos($str, '<![CDATA[')) ? str_replace(array('<![CDATA[', ']]>'), '', $str) : $str;
    }

    protected function _needsCdata($str) {
        return ($this->_isXhtml && preg_match('/(?:[<&]|\\-\\-|\\]\\]>)/', $str));
    }

}
