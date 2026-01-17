<?php

namespace App\Services;

class DevDomainDetector
{
    /**
     * Suffixes de domaines considérés comme environnements de dev.
     */
    protected array $devSuffixes = [
        '.local',
        '.dev',
        '.test',
        '.localhost',
        '.preprod',
        '.staging',
        '.ddev.site',
    ];

    /**
     * Préfixes (sous-domaines) considérés comme environnements de dev.
     */
    protected array $devPrefixes = [
        'dev.',
        'local.',
        'preprod.',
        'staging.',
        'test.',
        'recette.',
    ];

    /**
     * Détermine si un domaine est un environnement de développement.
     */
    public function isDevDomain(string $domain): bool
    {
        $domain = $this->normalize($domain);

        // Vérifier les suffixes
        foreach ($this->devSuffixes as $suffix) {
            if (str_ends_with($domain, $suffix)) {
                return true;
            }
        }

        // Vérifier les préfixes
        foreach ($this->devPrefixes as $prefix) {
            if (str_starts_with($domain, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrait le domaine de production à partir d'un domaine de dev.
     * Retourne null si ce n'est pas un domaine dev.
     */
    public function extractProductionDomain(string $domain): ?string
    {
        $domain = $this->normalize($domain);

        if (!$this->isDevDomain($domain)) {
            return null;
        }

        // Retirer les suffixes dev et ajouter .com/.fr/etc
        foreach ($this->devSuffixes as $suffix) {
            if (str_ends_with($domain, $suffix)) {
                // monsite.local → monsite
                $baseName = substr($domain, 0, -strlen($suffix));
                return $baseName;
            }
        }

        // Retirer les préfixes dev
        foreach ($this->devPrefixes as $prefix) {
            if (str_starts_with($domain, $prefix)) {
                // dev.monsite.com → monsite.com
                return substr($domain, strlen($prefix));
            }
        }

        return null;
    }

    /**
     * Trouve les domaines de production possibles pour un domaine dev.
     * Retourne un tableau de patterns à chercher.
     */
    public function getPossibleProductionDomains(string $devDomain): array
    {
        $baseName = $this->extractProductionDomain($devDomain);

        if ($baseName === null) {
            return [];
        }

        // Si c'est déjà un domaine complet (dev.example.com → example.com)
        if (str_contains($baseName, '.')) {
            return [$baseName];
        }

        // Sinon c'est juste un nom (example.local → example)
        // On cherche tous les domaines qui commencent par ce nom
        return [
            $baseName . '.com',
            $baseName . '.fr',
            $baseName . '.net',
            $baseName . '.org',
            $baseName . '.io',
            $baseName . '.co',
        ];
    }

    /**
     * Vérifie si un domaine dev correspond à un domaine de production donné.
     */
    public function matchesProduction(string $devDomain, string $productionDomain): bool
    {
        $devDomain = $this->normalize($devDomain);
        $productionDomain = $this->normalize($productionDomain);

        if (!$this->isDevDomain($devDomain)) {
            return false;
        }

        $baseName = $this->extractProductionDomain($devDomain);

        if ($baseName === null) {
            return false;
        }

        // Cas 1: dev.example.com correspond à example.com
        if ($baseName === $productionDomain) {
            return true;
        }

        // Cas 2: example.local correspond à example.com, example.fr, etc.
        if (!str_contains($baseName, '.')) {
            // Extraire le nom de base du domaine de prod (example.com → example)
            $prodBaseName = explode('.', $productionDomain)[0];
            return $baseName === $prodBaseName;
        }

        return false;
    }

    /**
     * Normalise un domaine (minuscules, sans protocole, sans www, sans trailing slash).
     */
    protected function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');
        $domain = explode('/', $domain)[0]; // Retirer le path

        return $domain;
    }
}
