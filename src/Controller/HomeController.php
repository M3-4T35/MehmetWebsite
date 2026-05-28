<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/change-locale/{language}', name: 'app_switch_locale')]
    public function switchLocale(string $language, Request $request): Response
    {
        $request->getSession()->set('_locale', $language);
        $referer = $request->headers->get('referer');
        
        if ($referer) {
            // Try to replace the locale prefix in the referer URL
            $referer = preg_replace('/\/(fr|en)(\/|$)/', '/' . $language . '$2', $referer);
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home', ['_locale' => $language]);
    }

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
