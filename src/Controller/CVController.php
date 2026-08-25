<?php

namespace App\Controller;

use App\Entity\CV;
use App\Form\CVType;
use App\Repository\CVRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cv')]
final class CVController extends AbstractController
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'app.cv_file_uploader')]
        private readonly FileUploader $fileUploader,
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(name: 'app_c_v_index', methods: ['GET'])]
    public function index(CVRepository $cVRepository): Response
    {
        return $this->render('cv/index.html.twig', [
            'c_vs' => $cVRepository->findAll(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/new', name: 'app_c_v_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $cV = new CV();
        $form = $this->createForm(CVType::class, $cV);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdfFile')->getData();
            if ($pdfFile) {
                $cV->setFilename($this->fileUploader->upload($pdfFile));
                $cV->setUploadedAt(new \DateTimeImmutable());
            }

            $entityManager->persist($cV);
            $entityManager->flush();

            return $this->redirectToRoute('app_c_v_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cv/new.html.twig', [
            'c_v' => $cV,
            'form' => $form,
        ]);
    }

    #[Route('/public', name: 'app_cv_public', methods: ['GET'])]
    public function publicList(CVRepository $cVRepository): Response
    {
        return $this->render('cv/public_list.html.twig', [
            'cvs' => $cVRepository->findAll(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'app_c_v_show', methods: ['GET'])]
    public function show(CV $cV): Response
    {
        return $this->render('cv/show.html.twig', [
            'c_v' => $cV,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/edit', name: 'app_c_v_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CV $cV, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CVType::class, $cV, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pdfFile = $form->get('pdfFile')->getData();
            if ($pdfFile) {
                $oldFilename = $cV->getFilename();
                $cV->setFilename($this->fileUploader->upload($pdfFile));
                $cV->setUploadedAt(new \DateTimeImmutable());
                $this->fileUploader->remove($oldFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_c_v_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('cv/edit.html.twig', [
            'c_v' => $cV,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'app_c_v_delete', methods: ['POST'])]
    public function delete(Request $request, CV $cV, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cV->getId(), $request->getPayload()->getString('_token'))) {
            $filename = $cV->getFilename();

            $entityManager->remove($cV);
            $entityManager->flush();

            $this->fileUploader->remove($filename);
        }

        return $this->redirectToRoute('app_c_v_index', [], Response::HTTP_SEE_OTHER);
    }
}
