<?php
declare(strict_types=1);

namespace CakeHx\Test\TestCase\View\Helper;

use Cake\TestSuite\TestCase;
use Cake\View\View;
use CakeHx\View\Helper\HxHelper;

class HxHelperTest extends TestCase
{
    protected HxHelper $helper;

    public function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->helper = new HxHelper($view);
    }

    public function testAttrsSimple(): void
    {
        $result = $this->helper->attrs(['get' => '/test', 'target' => '#main']);
        $this->assertStringContainsString('data-hx-get="/test"', $result);
        $this->assertStringContainsString('data-hx-target="#main"', $result);
    }

    public function testAttrsWithArray(): void
    {
        $result = $this->helper->attrs(['vals' => ['key' => 'value']]);
        $this->assertStringContainsString('data-hx-vals=', $result);
        $this->assertStringContainsString('{"key":"value"}', htmlspecialchars_decode($result));
    }

    public function testAttrsWithBooleans(): void
    {
        $result = $this->helper->attrs(['boost' => true]);
        $this->assertStringContainsString('data-hx-boost="true"', $result);
    }

    public function testAttrsSkipsNull(): void
    {
        $result = $this->helper->attrs(['get' => '/ok', 'target' => null]);
        $this->assertStringContainsString('data-hx-get="/ok"', $result);
        $this->assertStringNotContainsString('data-hx-target', $result);
    }

    public function testLinkGeneratesAnchor(): void
    {
        $result = $this->helper->link('Click me', '/load', ['target' => '#box', 'swap' => 'innerHTML']);
        $this->assertStringContainsString('<a href="/load"', $result);
        $this->assertStringContainsString('data-hx-get="/load"', $result);
        $this->assertStringContainsString('data-hx-target="#box"', $result);
        $this->assertStringContainsString('data-hx-swap="innerHTML"', $result);
        $this->assertStringContainsString('>Click me</a>', $result);
    }

    public function testLinkEscapesTitle(): void
    {
        $result = $this->helper->link('<b>XSS</b>', '/test');
        $this->assertStringNotContainsString('<b>XSS</b>', $result);
        $this->assertStringContainsString('&lt;b&gt;XSS&lt;/b&gt;', $result);
    }

    public function testButtonGeneratesButton(): void
    {
        $result = $this->helper->button('Delete', '/items/1', 'delete', ['confirm' => 'Sure?']);
        $this->assertStringContainsString('<button', $result);
        $this->assertStringContainsString('data-hx-delete="/items/1"', $result);
        $this->assertStringContainsString('data-hx-confirm="Sure?"', $result);
    }

    public function testGetShorthand(): void
    {
        $result = $this->helper->get('/search', ['trigger' => 'keyup', 'target' => '#results']);
        $this->assertStringContainsString('data-hx-get="/search"', $result);
        $this->assertStringContainsString('data-hx-trigger="keyup"', $result);
    }

    public function testPostShorthand(): void
    {
        $result = $this->helper->post('/submit', ['swap' => 'outerHTML']);
        $this->assertStringContainsString('data-hx-post="/submit"', $result);
        $this->assertStringContainsString('data-hx-swap="outerHTML"', $result);
    }

    public function testBoostContainer(): void
    {
        $start = $this->helper->boostStart();
        $end = $this->helper->boostEnd();
        $this->assertStringContainsString('data-hx-boost="true"', $start);
        $this->assertSame('</div>', $end);
    }

    public function testIndicator(): void
    {
        $result = $this->helper->indicator('loader', 'Bitte warten…');
        $this->assertStringContainsString('id="loader"', $result);
        $this->assertStringContainsString('class="hx-indicator"', $result);
        $this->assertStringContainsString('Bitte warten…', $result);
    }
}
