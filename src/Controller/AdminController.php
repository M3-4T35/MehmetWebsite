<?php

namespace App\Controller;

use App\Service\SiteDesign;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/admin/design', name: 'app_admin_design', methods: ['GET'])]
    public function design(SiteDesign $siteDesign): Response
    {
        return $this->render('admin/design.html.twig', [
            'designs' => $siteDesign->getAll(),
            'active' => $siteDesign->getActive(),
            'preview' => $siteDesign->getPreview(),
        ]);
    }

    /**
     * Publishes a design for every visitor.
     */
    #[Route('/admin/design/activate', name: 'app_admin_design_activate', methods: ['POST'])]
    public function activateDesign(Request $request, SiteDesign $siteDesign): Response
    {
        $design = $request->getPayload()->getString('design');
        if ($this->isCsrfTokenValid('design', $request->getPayload()->getString('_token')) && SiteDesign::exists($design)) {
            $siteDesign->setActive($design);
            $siteDesign->setPreview(null);
            $this->addFlash('success', sprintf('Le design « %s » est maintenant en ligne pour tous les visiteurs.', $siteDesign->getLabel($design)));
        }

        return $this->redirectToRoute('app_admin_design', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Starts (or stops, with an empty design) a preview visible only in the admin's own session.
     */
    #[Route('/admin/design/preview', name: 'app_admin_design_preview', methods: ['POST'])]
    public function previewDesign(Request $request, SiteDesign $siteDesign): Response
    {
        $design = $request->getPayload()->getString('design');
        if (!$this->isCsrfTokenValid('design', $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_design', [], Response::HTTP_SEE_OTHER);
        }

        if ('' === $design) {
            $siteDesign->setPreview(null);

            return $this->redirectToRoute('app_admin_design', [], Response::HTTP_SEE_OTHER);
        }

        if (SiteDesign::exists($design)) {
            $siteDesign->setPreview($design);
        }

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
}
