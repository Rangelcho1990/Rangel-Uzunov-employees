<?php

declare(strict_types=1);

namespace App\Form\EmployeeCollaboration\Create;

use Symfony\Component\Form\FormBuilderInterface;

interface FormCreateEmployeeCollaborationInterface
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void;
}
