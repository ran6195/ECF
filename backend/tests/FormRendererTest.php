<?php

declare(strict_types=1);

namespace Ecf\Tests;

use Ecf\Services\FormRenderer;
use PHPUnit\Framework\TestCase;

final class FormRendererTest extends TestCase
{
    public function testRendersFieldsAndStyle(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['key' => 'messaggio', 'label' => 'Messaggio', 'type' => 'textarea', 'required' => true],
        ]);

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('name="nome"', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('type="submit"', $html);
    }

    public function testHoneypotIsIncluded(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text'],
        ]);

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString(FormRenderer::HONEYPOT_KEY, $html);
        $this->assertStringContainsString('ecf-hp', $html);
    }

    public function testDynamicValuesAreEscaped(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => '<script>alert(1)</script>', 'type' => 'text'],
        ], ['name' => '"><img src=x>']);

        $html = (new FormRenderer())->render($form);

        // Niente tag <script> grezzo iniettato dalla label.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
