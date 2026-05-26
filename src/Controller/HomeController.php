<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/travaux', name: 'app_public_projects')]
    public function publicProjects(\App\Repository\ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->findAll();
        return $this->render('public/projects.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/mentions-legales', name: 'app_legal')]
    public function legal(): Response
    {
        return $this->render('legal.html.twig');
    }

    #[Route('/travaux/{id}', name: 'app_public_project_detail', requirements: ['id' => '\d+'])]
    public function publicProjectDetail(int $id, \App\Repository\ProjectRepository $projectRepository, \App\Repository\MediaRepository $mediaRepository): Response
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }
        $medias = $mediaRepository->findBy(['project' => $project]);
        return $this->render('public/project_detail.html.twig', [
            'project' => $project,
            'medias' => $medias,
        ]);
    }
}
