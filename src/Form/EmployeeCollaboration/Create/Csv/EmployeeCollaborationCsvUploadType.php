<?php

declare(strict_types=1);

namespace App\Form\EmployeeCollaboration\Create\Csv;

use App\Form\EmployeeCollaboration\Create\FormCreateEmployeeCollaborationInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

final class EmployeeCollaborationCsvUploadType extends AbstractType implements FormCreateEmployeeCollaborationInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Employee assignments (CSV)',
            'mapped' => false,
            'attr' => ['accept' => '.csv,text/csv', 'aria-describedby' => 'csv-help'],
            'constraints' => [
                new NotNull(message: 'Choose a CSV file to upload.'),
                new File(extensions: ['csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel']], extensionsMessage: 'Upload a valid CSV file with a .csv extension.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'employee_upload']);
    }
}
