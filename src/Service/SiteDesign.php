<?php

namespace App\Service;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Resolves which visual design the site is rendered with.
 *
 * The active design is stored in the database and chosen from the admin dashboard.
 * An admin can also preview another design for their own session only.
 */
final class SiteDesign
{
    public const DEFAULT = 'classique';

    private const SETTING = 'design';
    private const CACHE_KEY = 'site_design.active';
    private const PREVIEW_SESSION_KEY = 'design_preview';

    /**
     * Each design other than the default is public/designs/common.css + public/designs/<key>.css,
     * unless it lists its own "stylesheets" (a variant layered on top of another design).
     * "swatches" are shown in the admin picker: background, text, accent, second accent.
     */
    public const DESIGNS = [
        'classique' => [
            'label' => 'Classique',
            'description' => 'Le design d\'origine : Bootstrap bleu, boutons arrondis et dégradés doux.',
            'fonts' => 'Police système',
            'swatches' => ['#ffffff', '#212529', '#0d6efd', '#cfe2ff'],
        ],
        'iznik' => [
            'label' => 'Iznik',
            'description' => 'Inspiré des faïences d\'Iznik : bleu cobalt, turquoise et rouge tomate sur blanc porcelaine, formes nettes et frise de carreaux.',
            'fonts' => 'Bricolage Grotesque',
            'swatches' => ['#f6f8fb', '#14213d', '#1b3fa0', '#c8372d'],
        ],
        'iznik-lite' => [
            'label' => 'Iznik épuré',
            'description' => 'Le design Iznik sans la frise de carreaux : mêmes couleurs et mêmes formes, en plus sobre.',
            'fonts' => 'Bricolage Grotesque',
            'swatches' => ['#f6f8fb', '#14213d', '#1b3fa0', '#12808c'],
            'stylesheets' => ['iznik', 'iznik-lite'],
        ],
        'kilim' => [
            'label' => 'Kilim',
            'description' => 'Inspiration turque : les teintes d\'un kilim anatolien (rouge garance, indigo, safran) sur laine écrue, avec une bande de rayures tissées.',
            'fonts' => 'Bricolage Grotesque',
            'swatches' => ['#f5eee1', '#2b1d18', '#9e2a22', '#d6951f'],
        ],
        'plaque' => [
            'label' => 'Plaque',
            'description' => 'Inspiration française : les plaques de rue parisiennes, bleu émaillé cerclé de vert sur fond pierre de taille.',
            'fonts' => 'IBM Plex Sans',
            'swatches' => ['#f4f0e8', '#1c2230', '#173880', '#1f6b4a'],
        ],
        'deux-rives' => [
            'label' => 'Deux rives',
            'description' => 'Franco-turc, en sobre : encre sur blanc chaud, rouge pour les actions, bleu pour les liens.',
            'fonts' => 'Newsreader & Bricolage Grotesque',
            'swatches' => ['#fbf9f5', '#161b2d', '#c9151e', '#1d3f94'],
        ],
        'epure' => [
            'label' => 'Épure',
            'description' => 'Une planche de dessin technique : papier quadrillé en clair, bleu de plan en sombre, cartouches et cotes.',
            'fonts' => 'IBM Plex Sans & Mono',
            'swatches' => ['#eef2f5', '#12263f', '#0b5cad', '#d9480f'],
        ],
        'lettre' => [
            'label' => 'Lettre',
            'description' => 'Mis en page comme une lettre soignée : texte à gauche, serif de lecture, encre noire et une seule couleur bordeaux.',
            'fonts' => 'Newsreader',
            'swatches' => ['#ffffff', '#000000', '#7a1e2c', '#e9e4e5'],
        ],
    ];

    public function __construct(
        private readonly SiteSettingRepository $settings,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%/public/designs')]
        private readonly string $designDirectory,
    ) {
    }

    public static function exists(string $design): bool
    {
        return isset(self::DESIGNS[$design]);
    }

    /**
     * The design every visitor sees.
     */
    public function getActive(): string
    {
        try {
            $design = $this->cache->get(self::CACHE_KEY, fn (): string => $this->settings->find(self::SETTING)?->getValue() ?? self::DEFAULT);
        } catch (\Doctrine\DBAL\Exception) {
            // Table missing (migration not run yet) or database unreachable: never break the page for a skin.
            return self::DEFAULT;
        }

        return self::exists($design) ? $design : self::DEFAULT;
    }

    public function setActive(string $design): void
    {
        if (!self::exists($design)) {
            throw new \InvalidArgumentException(sprintf('Unknown design "%s".', $design));
        }

        $setting = $this->settings->find(self::SETTING);
        if ($setting) {
            $setting->setValue($design);
        } else {
            $this->entityManager->persist(new SiteSetting(self::SETTING, $design));
        }
        $this->entityManager->flush();
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * The design previewed by the current admin session, if any.
     */
    public function getPreview(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasPreviousSession()) {
            return null;
        }
        $design = $request->getSession()->get(self::PREVIEW_SESSION_KEY);

        return \is_string($design) && self::exists($design) ? $design : null;
    }

    public function setPreview(?string $design): void
    {
        $session = $this->requestStack->getSession();
        if (null === $design) {
            $session->remove(self::PREVIEW_SESSION_KEY);

            return;
        }
        if (!self::exists($design)) {
            throw new \InvalidArgumentException(sprintf('Unknown design "%s".', $design));
        }
        $session->set(self::PREVIEW_SESSION_KEY, $design);
    }

    /**
     * The design to render the current request with.
     */
    public function getCurrent(): string
    {
        return $this->getPreview() ?? $this->getActive();
    }

    public function getLabel(string $design): string
    {
        return self::DESIGNS[$design]['label'] ?? $design;
    }

    /**
     * @return array<string, array{label: string, description: string, fonts: string, swatches: list<string>, stylesheets?: list<string>}>
     */
    public function getAll(): array
    {
        return self::DESIGNS;
    }

    /**
     * Stylesheet URL paths of the current design (none for the default one), cache-busted by file time.
     *
     * @return list<string>
     */
    public function getStylesheets(): array
    {
        $design = $this->getCurrent();
        if (self::DEFAULT === $design) {
            return [];
        }

        $urls = [];
        foreach (['common', ...(self::DESIGNS[$design]['stylesheets'] ?? [$design])] as $name) {
            $urls[] = 'designs/'.$name.'.css?v='.(@filemtime($this->designDirectory.'/'.$name.'.css') ?: 0);
        }

        return $urls;
    }
}
