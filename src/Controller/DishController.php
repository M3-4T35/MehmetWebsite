<?php

namespace App\Controller;

use App\Entity\Dish;
use App\Form\DishType;
use App\Repository\DishRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin management of the dishes shown on the public cooking page.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/cuisine')]
final class DishController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'app.dish_file_uploader')]
        private readonly FileUploader $fileUploader,
    ) {
    }

    #[Route(name: 'app_dish_index', methods: ['GET'])]
    public function index(DishRepository $dishRepository): Response
    {
        return $this->render('dish/index.html.twig', [
            'dishes' => $dishRepository->findForGallery(),
        ]);
    }

    #[Route('/new', name: 'app_dish_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dish = new Dish();
        $form = $this->createForm(DishType::class, $dish);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('imageFile')->getData();
            $dish->setFilename($this->fileUploader->upload($file));

            $entityManager->persist($dish);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le plat « %s » a été ajouté.', $dish->getTitle()));

            return $this->redirectToRoute('app_dish_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dish/new.html.twig', [
            'dish' => $dish,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dish_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Dish $dish, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DishType::class, $dish, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('imageFile')->getData();
            $oldFilename = null;
            if ($file instanceof UploadedFile) {
                $oldFilename = $dish->getFilename();
                $dish->setFilename($this->fileUploader->upload($file));
            }

            $entityManager->flush();

            // Only delete the previous file once the new one is safely saved
            $this->fileUploader->remove($oldFilename);

            $this->addFlash('success', sprintf('Le plat « %s » a été mis à jour.', $dish->getTitle()));

            return $this->redirectToRoute('app_dish_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dish/edit.html.twig', [
            'dish' => $dish,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dish_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Dish $dish, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dish->getId(), $request->getPayload()->getString('_token'))) {
            $filename = $dish->getFilename();

            $entityManager->remove($dish);
            $entityManager->flush();

            $this->fileUploader->remove($filename);

            $this->addFlash('success', 'Le plat a été supprimé.');
        }

        return $this->redirectToRoute('app_dish_index', [], Response::HTTP_SEE_OTHER);
    }
}
