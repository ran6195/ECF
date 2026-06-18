<?php

declare(strict_types=1);

namespace Ecf\Tests;

use Ecf\Services\FormValidator;
use PHPUnit\Framework\TestCase;

final class FormValidatorTest extends TestCase
{
    public function testRequiredFieldsAreEnforced(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ]);

        $validator = new FormValidator();
        $valid = $validator->validate($form, ['nome' => '', 'email' => '']);

        $this->assertFalse($valid);
        $this->assertArrayHasKey('nome', $validator->errors());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $form = Support::makeForm([
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ]);

        $validator = new FormValidator();
        $this->assertFalse($validator->validate($form, ['email' => 'non-una-email']));
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testValidPayloadPassesAndIsCleaned(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'extra', 'label' => 'Extra', 'type' => 'text', 'required' => false],
        ]);

        $validator = new FormValidator();
        $valid = $validator->validate($form, [
            'nome' => '  Mario  ',
            'email' => 'mario@test.it',
            'campo_non_definito' => 'ignorami', // non deve finire nel clean
        ]);

        $this->assertTrue($valid);
        $clean = $validator->clean();
        $this->assertSame('Mario', $clean['nome']); // trim applicato
        $this->assertArrayNotHasKey('campo_non_definito', $clean);
    }

    public function testMaxLengthConstraint(): void
    {
        $form = Support::makeForm([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'validation' => ['maxLength' => 3]],
        ]);

        $validator = new FormValidator();
        $this->assertFalse($validator->validate($form, ['nome' => 'troppolungo']));
    }

    public function testNumberRange(): void
    {
        $form = Support::makeForm([
            ['key' => 'eta', 'label' => 'Età', 'type' => 'number', 'required' => true, 'validation' => ['min' => 18, 'max' => 99]],
        ]);

        $validator = new FormValidator();
        $this->assertFalse($validator->validate($form, ['eta' => '10']));
        $this->assertTrue($validator->validate($form, ['eta' => '25']));
    }

    public function testSelectOptionMustBeAllowed(): void
    {
        $form = Support::makeForm([
            ['key' => 'colore', 'label' => 'Colore', 'type' => 'select', 'required' => true, 'options' => [
                ['value' => 'r', 'label' => 'Rosso'],
                ['value' => 'b', 'label' => 'Blu'],
            ]],
        ]);

        $validator = new FormValidator();
        $this->assertFalse($validator->validate($form, ['colore' => 'verde']));
        $this->assertTrue($validator->validate($form, ['colore' => 'r']));
    }
}
