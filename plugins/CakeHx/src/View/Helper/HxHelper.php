<?php
declare(strict_types=1);

namespace CakeHx\View\Helper;

use Cake\View\Helper;

/**
 * HxHelper — generates data-hx-* attributes for CakePHP views.
 *
 * Usage in templates:
 *   <?= $this->Hx->link('Load more', '/posts/page/2', ['target' => '#list', 'swap' => 'beforeend']) ?>
 *   <?= $this->Hx->get('/search', ['trigger' => 'keyup', 'target' => '#results']) ?>
 *   <div <?= $this->Hx->attrs(['get' => '/sidebar', 'trigger' => 'load']) ?>>Loading…</div>
 */
class HxHelper extends Helper
{
    /**
     * Helpers used by this helper.
     */
    protected array $helpers = ['Html'];

    /**
     * Default configuration.
     */
    protected array $_defaultConfig = [
        'scriptLoaded' => false,
    ];

    /**
     * Render the <script> tag for cakehx.js.
     * Call this once in your layout, typically before </body>.
     */
    public function script(): string
    {
        return $this->Html->script('CakeHx./js/cakehx', ['block' => false]);
    }

    /**
     * Build a data-hx-* attribute string from an options array.
     *
     * Keys map directly to htmx attributes:
     *   'get'       => data-hx-get
     *   'post'      => data-hx-post
     *   'put'       => data-hx-put
     *   'patch'     => data-hx-patch
     *   'delete'    => data-hx-delete
     *   'trigger'   => data-hx-trigger
     *   'target'    => data-hx-target
     *   'swap'      => data-hx-swap
     *   'select'    => data-hx-select
     *   'push-url'  => data-hx-push-url
     *   'confirm'   => data-hx-confirm
     *   'indicator' => data-hx-indicator
     *   'vals'      => data-hx-vals (array will be JSON encoded)
     *   'headers'   => data-hx-headers (array will be JSON encoded)
     *   'boost'     => data-hx-boost
     */
    public function attrs(array $options): string
    {
        $attrs = [];

        foreach ($options as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $attrs[] = sprintf('data-hx-%s="%s"', $key, htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
        }

        return implode(' ', $attrs);
    }

    /**
     * Create an anchor tag with HX attributes.
     *
     * @param string $title  Link text
     * @param string $url    The URL to request
     * @param array  $hxOptions HX attribute options (target, swap, trigger, etc.)
     * @param array  $htmlOptions Additional HTML attributes
     */
    public function link(string $title, string $url, array $hxOptions = [], array $htmlOptions = []): string
    {
        $hxOptions['get'] = $url;
        $hxAttrs = $this->attrs($hxOptions);

        $htmlOptions += ['escape' => true];
        $escapedTitle = $htmlOptions['escape'] ? h($title) : $title;
        unset($htmlOptions['escape']);

        $htmlAttrStr = $this->_formatAttributes($htmlOptions);

        return sprintf('<a href="%s" %s%s>%s</a>', h($url), $hxAttrs, $htmlAttrStr, $escapedTitle);
    }

    /**
     * Create a button with HX attributes.
     */
    public function button(string $title, string $url, string $verb = 'post', array $hxOptions = [], array $htmlOptions = []): string
    {
        $hxOptions[$verb] = $url;
        $hxAttrs = $this->attrs($hxOptions);

        $htmlOptions += ['type' => 'button'];
        $htmlAttrStr = $this->_formatAttributes($htmlOptions);

        return sprintf('<button %s%s>%s</button>', $hxAttrs, $htmlAttrStr, h($title));
    }

    /**
     * Shorthand: data-hx-get attribute set.
     */
    public function get(string $url, array $options = []): string
    {
        $options['get'] = $url;

        return $this->attrs($options);
    }

    /**
     * Shorthand: data-hx-post attribute set.
     */
    public function post(string $url, array $options = []): string
    {
        $options['post'] = $url;

        return $this->attrs($options);
    }

    /**
     * Create a boosted container.
     * All links and forms inside will be automatically AJAX-ified.
     */
    public function boostStart(array $htmlOptions = []): string
    {
        $htmlAttrStr = $this->_formatAttributes($htmlOptions);

        return sprintf('<div data-hx-boost="true"%s>', $htmlAttrStr);
    }

    /**
     * Close a boosted container.
     */
    public function boostEnd(): string
    {
        return '</div>';
    }

    /**
     * Render a loading indicator element.
     */
    public function indicator(string $id, string $content = 'Loading…', string $tag = 'span'): string
    {
        return sprintf(
            '<%s id="%s" class="hx-indicator" style="display:none;">%s</%s>',
            $tag,
            h($id),
            h($content),
            $tag
        );
    }

    /**
     * Format HTML attributes from an array.
     */
    private function _formatAttributes(array $attrs): string
    {
        if (empty($attrs)) {
            return '';
        }

        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = $key;
            } elseif ($value !== false && $value !== null) {
                $parts[] = sprintf('%s="%s"', $key, htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
            }
        }

        return ' ' . implode(' ', $parts);
    }
}
