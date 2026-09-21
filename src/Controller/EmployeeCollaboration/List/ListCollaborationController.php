<?php

declare(strict_types=1);

namespace App\Controller\EmployeeCollaboration\List;

use App\Form\EmployeeCollaboration\Create\Csv\EmployeeCollaborationCsvUploadType;
use App\Service\EmployeesCollaboration\List\ListCollaborationServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListCollaborationController extends AbstractController
{
    // route also can be "employee-collaboration"
    #[Route('/', name: 'employee_collaboration', methods: ['GET'])]
    public function __invoke(ListCollaborationServiceInterface $listCollaboration): Response
    {
        $form = $this->createForm(EmployeeCollaborationCsvUploadType::class, null, [
            'action' => $this->generateUrl('employee_collaboration_create'),
            'method' => 'POST',
        ]);

        return $this->render('employee/collaboration.html.twig', [
            'form' => $form,
            'result' => $listCollaboration->getList(),
            'hasAssignments' => $listCollaboration->hasAssignments(),
            'showUpload' => false,
        ]);
    }
}
