<?php

declare(strict_types=1);

namespace App\Controller\EmployeeCollaboration\Create;

use App\Exception\InvalidCsv;
use App\Form\EmployeeCollaboration\Create\Csv\EmployeeCollaborationCsvUploadType;
use App\Service\EmployeesCollaboration\Create\CreateCollaborationServiceInterface;
use App\Service\EmployeesCollaboration\List\ListCollaborationServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateCollaborationController extends AbstractController
{
    // route also can be "employee-collaboration"
    #[Route('/', name: 'employee_collaboration_create', methods: ['POST'])]
    public function __invoke(Request $request, CreateCollaborationServiceInterface $importer, ListCollaborationServiceInterface $listCollaboration): Response
    {
        $form = $this->createForm(EmployeeCollaborationCsvUploadType::class, null, [
            'action' => $this->generateUrl('employee_collaboration_create'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        try {
            $data = $importer->validateFormData($form);
            $count = $importer->insertBatch($data);

            $this->addFlash('success', sprintf('%d assignments saved. Existing assignments were updated.', $count));

            return $this->redirectToRoute('employee_collaboration', status: Response::HTTP_SEE_OTHER);
        } catch (InvalidCsv $exception) {
            $form->get('file')->addError(new FormError($exception->getMessage()));
        }

        return $this->render('employee/collaboration.html.twig', [
            'form' => $form,
            'result' => $listCollaboration->getList(),
            'hasAssignments' => $listCollaboration->hasAssignments(),
            'showUpload' => true,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
