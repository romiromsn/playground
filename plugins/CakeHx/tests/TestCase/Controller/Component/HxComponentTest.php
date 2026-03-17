<?php
declare(strict_types=1);

namespace CakeHx\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeHx\Controller\Component\HxComponent;

class HxComponentTest extends TestCase
{
    protected HxComponent $component;
    protected Controller $controller;

    protected function _buildComponent(array $headers = []): void
    {
        $request = new ServerRequest([
            'environment' => $headers,
        ]);
        $this->controller = new Controller($request, new Response());
        $registry = new ComponentRegistry($this->controller);
        $this->component = new HxComponent($registry);
    }

    public function testIsHxRequestTrue(): void
    {
        $this->_buildComponent(['HTTP_X_HX_REQUEST' => 'true']);
        $this->assertTrue($this->component->isHxRequest());
    }

    public function testIsHxRequestFalse(): void
    {
        $this->_buildComponent();
        $this->assertFalse($this->component->isHxRequest());
    }

    public function testGetTrigger(): void
    {
        $this->_buildComponent(['HTTP_X_HX_TRIGGER' => 'click']);
        $this->assertSame('click', $this->component->getTrigger());
    }

    public function testGetTriggerNull(): void
    {
        $this->_buildComponent();
        $this->assertNull($this->component->getTrigger());
    }

    public function testGetTarget(): void
    {
        $this->_buildComponent(['HTTP_X_HX_TARGET' => 'main-content']);
        $this->assertSame('main-content', $this->component->getTarget());
    }

    public function testGetCurrentUrl(): void
    {
        $this->_buildComponent(['HTTP_X_HX_CURRENT_URL' => 'http://localhost/posts']);
        $this->assertSame('http://localhost/posts', $this->component->getCurrentUrl());
    }
}
