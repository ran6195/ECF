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

    public function testThemeVariablesAndCustomCssAreApplied(): void
    {
        $form = Support::makeForm(
            [['key' => 'nome', 'label' => 'Nome', 'type' => 'text']],
            ['style' => [
                'theme' => ['primary' => '#ff0000', 'background' => '#0f172a'],
                'customCss' => '.ecf-submit { letter-spacing: 1px; }',
            ]]
        );

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString('--ecf-primary: #ff0000;', $html);
        $this->assertStringContainsString('--ecf-bg: #0f172a;', $html);
        $this->assertStringContainsString('letter-spacing: 1px', $html);
    }

    public function testFormIsFullWidthWithDefaultReadabilityCap(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text'],
        ]);

        $html = (new FormRenderer())->render($form);

        // Il form occupa il 100% del contenitore...
        $this->assertStringContainsString('width: 100%; max-width: var(--ecf-max-width);', $html);
        // ...con il cap di leggibilità di default a 720px.
        $this->assertStringContainsString('--ecf-max-width: 720px;', $html);
    }

    public function testMaxWidthCanBeOverriddenToFull(): void
    {
        $form = Support::makeForm(
            [['key' => 'nome', 'label' => 'Nome', 'type' => 'text']],
            ['style' => ['theme' => ['maxWidth' => '100%']]]
        );

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString('--ecf-max-width: 100%;', $html);
    }

    public function testFormIsCenteredByDefault(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text'],
        ]);

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString('--ecf-form-margin: 0 auto;', $html);
        $this->assertStringContainsString('margin: var(--ecf-form-margin);', $html);
    }

    public function testFormAlignmentCanBeOverridden(): void
    {
        $form = Support::makeForm(
            [['key' => 'nome', 'label' => 'Nome', 'type' => 'text']],
            ['style' => ['theme' => ['align' => 'right']]]
        );

        $html = (new FormRenderer())->render($form);

        $this->assertStringContainsString('--ecf-form-margin: 0 0 0 auto;', $html);
    }

    public function testCustomCssCannotBreakOutOfStyleTag(): void
    {
        $form = Support::makeForm(
            [['key' => 'nome', 'label' => 'Nome', 'type' => 'text']],
            ['style' => ['customCss' => '</style><script>alert(1)</script>']]
        );

        $html = (new FormRenderer())->render($form);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</style><', $html);
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
