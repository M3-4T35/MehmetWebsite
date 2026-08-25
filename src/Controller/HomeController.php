<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/change-locale/{language}', name: 'app_switch_locale', requirements: ['language' => 'fr|en'])]
    public function switchLocale(string $language, Request $request): Response
    {
        $request->getSession()->set('_locale', $language);

        $referer = $request->headers->get('referer');
        $refererHost = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        // Only follow the referer when it points back to this site (open-redirect guard)
        if ($refererHost !== null && strcasecmp($refererHost, $request->getHost()) === 0) {
            return $this->redirect(preg_replace('/\/(fr|en)(\/|$)/', '/'.$language.'$2', $referer));
        }

        return $this->redirectToRoute('app_home', ['_locale' => $language]);
    }

    #[Route('/', name: 'app_home')]
    public function index(\App\Repository\ProjectRepository $projectRepository, \App\Repository\MediaRepository $mediaRepository): Response
    {
        $latestProjects = $projectRepository->findBy([], ['createdAt' => 'DESC'], 3);
        $previews = $mediaRepository->findFirstPerProject();

        $featuredProjects = [];
        foreach ($latestProjects as $project) {
            $featuredProjects[] = [
                'entity' => $project,
                'preview' => $previews[$project->getId()] ?? null,
            ];
        }

        return $this->render('home.html.twig', [
            'featuredProjects' => $featuredProjects
        ]);
    }

    #[Route('/travaux', name: 'app_public_projects')]
    public function publicProjects(\App\Repository\ProjectRepository $projectRepository, \App\Repository\MediaRepository $mediaRepository): Response
    {
        $projects = $projectRepository->findBy([], ['createdAt' => 'DESC']);
        $previews = $mediaRepository->findFirstPerProject();

        $projectPreviews = [];
        foreach ($projects as $project) {
            $projectPreviews[] = [
                'entity' => $project,
                'preview' => $previews[$project->getId()] ?? null,
            ];
        }

        return $this->render('public/projects.html.twig', [
            'projectPreviews' => $projectPreviews,
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
        $medias = $mediaRepository->findBy(['project' => $project], ['position' => 'ASC']);
        return $this->render('public/project_detail.html.twig', [
            'project' => $project,
            'medias' => $medias,
        ]);
    }
}
