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

    public function testTimeSlotMustBelongToGeneratedRange(): void
    {
        $form = Support::makeForm([
            ['key' => 'orario', 'label' => 'Orario', 'type' => 'time_slot', 'required' => true, 'validation' => [
                'start_time' => '09:00', 'end_time' => '10:00', 'step' => 30,
            ]],
        ]);

        $validator = new FormValidator();
        $this->assertTrue($validator->validate($form, ['orario' => '09:30']));
        $this->assertFalse($validator->validate($form, ['orario' => '09:15'])); // non è un multiplo dello step
        $this->assertFalse($validator->validate($form, ['orario' => '11:00'])); // fuori range
    }

    public function testTimeSlotUsesDefaultRangeWhenUnconfigured(): void
    {
        $form = Support::makeForm([
            ['key' => 'orario', 'label' => 'Orario', 'type' => 'time_slot', 'required' => true],
        ]);

        $validator = new FormValidator();
        $this->assertTrue($validator->validate($form, ['orario' => '08:00'])); // default 08:00-17:00 step 30
        $this->assertFalse($validator->validate($form, ['orario' => '07:30']));
    }

    public function testDateExcludesPastWhenConfigured(): void
    {
        $form = Support::makeForm([
            ['key' => 'appuntamento', 'label' => 'Appuntamento', 'type' => 'date', 'validation' => ['exclude_past' => true]],
        ]);

        $validator = new FormValidator();
        $yesterday = (new \DateTime('yesterday'))->format('Y-m-d');
        $today = (new \DateTime('today'))->format('Y-m-d');

        $this->assertFalse($validator->validate($form, ['appuntamento' => $yesterday]));
        $this->assertTrue($validator->validate($form, ['appuntamento' => $today]));
    }

    public function testDateExcludesWeekendsWhenConfigured(): void
    {
        $form = Support::makeForm([
            ['key' => 'appuntamento', 'label' => 'Appuntamento', 'type' => 'date', 'validation' => ['exclude_weekends' => true]],
        ]);

        $validator = new FormValidator();
        $saturday = (new \DateTime('next saturday'))->format('Y-m-d');
        $monday = (new \DateTime('next monday'))->format('Y-m-d');

        $this->assertFalse($validator->validate($form, ['appuntamento' => $saturday]));
        $this->assertTrue($validator->validate($form, ['appuntamento' => $monday]));
    }

    public function testDateWithoutConstraintsAcceptsAnyValidDate(): void
    {
        $form = Support::makeForm([
            ['key' => 'appuntamento', 'label' => 'Appuntamento', 'type' => 'date'],
        ]);

        $validator = new FormValidator();
        $saturday = (new \DateTime('next saturday'))->format('Y-m-d');
        $this->assertTrue($validator->validate($form, ['appuntamento' => $saturday]));
    }
}
