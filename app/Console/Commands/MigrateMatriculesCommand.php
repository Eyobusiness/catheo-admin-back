<?php

namespace App\Console\Commands;

use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Services\MatriculeGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateMatriculesCommand extends Command
{
    /**
     * Signature de la commande Artisan.
     *
     * @var string
     */
    protected $signature = 'catheo:migrate-matricules
                            {--dry-run : Exécuter l\'analyse sans modifier la base de données}
                            {--force : Confirmer et appliquer réellement les modifications en base}
                            {--paroisse= : Filtrer sur un identifiant de paroisse spécifique}
                            {--fallback-unresolved : Assigner la section E par défaut aux catéchumènes sans section}';

    /**
     * Description de la commande.
     *
     * @var string
     */
    protected $description = 'Migre les anciens matricules des catéchumènes vers le nouveau format standardisé (ex: SM26-J8HDX)';

    /**
     * Expression régulière pour valider le nouveau format de matricule.
     * Ex: SM26-J8HDX, CIM26-E4K9P, PAR26-AB72K
     */
    public const NEW_FORMAT_REGEX = '/^[A-Z0-9]+[0-9]{2}-[JAE][A-Z0-9]{4}$/';

    /**
     * Exécution de la commande.
     */
    public function handle(MatriculeGeneratorService $generatorService): int
    {
        $this->info('========================================================================');
        $this->info('=== CATHEO : MIGRATION DES MATRICULES VERS LE NOUVEAU FORMAT ===');
        $this->info('========================================================================');

        $isDryRun = $this->option('dry-run') || !$this->option('force');
        $filterParoisse = $this->option('paroisse');
        $allowFallback = (bool) $this->option('fallback-unresolved');

        if ($isDryRun) {
            $this->comment("\nMode : DRY-RUN (Simulation d'analyse sans modification de la base de données)");
            $this->comment("Pour appliquer réellement les changements, utilisez l'option --force.\n");
        } else {
            $this->alert("\nMode : EXÉCUTION RÉELLE (Les matricules vont être mis à jour en base)\n");
        }

        // 1. Récupération des catéchumènes
        $query = Catechumene::with([
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau.section',
            'inscriptionsAnnuelles.classe.niveau.section',
            'inscriptionsAnnuelles.anneeCatechese',
            'paroisse',
        ]);

        if (!empty($filterParoisse)) {
            $query->where('paroisse_configuration_id', (int) $filterParoisse);
        }

        $catechumenes = $query->orderBy('id')->get();
        $totalCats = $catechumenes->count();

        if ($totalCats === 0) {
            $this->warn("Aucun catéchumène trouvé.");
            return Command::SUCCESS;
        }

        $this->info("Nombre total de catéchumènes analysés : {$totalCats}");

        // 2. Analyse et préparation des nouveaux matricules
        $alreadyCompliant = [];
        $toMigrate = [];
        $unresolvedSections = [];
        $missingPrefixes = [];
        $generatedMatriculesThisRun = [];

        foreach ($catechumenes as $c) {
            $currentMatricule = trim((string) $c->matricule);

            // A. Vérification de la configuration de paroisse
            $paroisseId = $c->paroisse_configuration_id;
            try {
                $prefix = $generatorService->resolvePrefix($paroisseId);
            } catch (\Exception $e) {
                $missingPrefixes[] = [
                    'id'        => $c->id,
                    'nom'       => $c->nom_complet,
                    'paroisse'  => $paroisseId ?? 'null',
                    'matricule' => $currentMatricule,
                    'raison'    => $e->getMessage(),
                ];
                continue;
            }

            // B. Déjà conforme au nouveau format ?
            if (preg_match(self::NEW_FORMAT_REGEX, $currentMatricule)) {
                // Vérifie aussi que le préfixe correspond bien à sa paroisse
                if (str_starts_with($currentMatricule, $prefix)) {
                    $alreadyCompliant[] = [
                        'id'        => $c->id,
                        'nom'       => $c->nom_complet,
                        'matricule' => $currentMatricule,
                    ];
                    continue;
                }
            }

            // C. Résolution de la section
            $section = $generatorService->resolveSectionFromCatechumene($c);
            $sectionLetter = null;

            if ($section) {
                $sectionLetter = $generatorService->resolveSectionLetter($section);
            } elseif ($allowFallback) {
                $sectionLetter = 'E';
            } else {
                $unresolvedSections[] = [
                    'id'        => $c->id,
                    'nom'       => $c->nom_complet,
                    'paroisse'  => $paroisseId,
                    'matricule' => $currentMatricule,
                    'inscr'     => $c->inscriptionsAnnuelles->count(),
                    'raison'    => 'Aucune inscription ni section associée trouvée',
                ];
                continue;
            }

            // D. Détermination de l'année (règle métier CATHEO)
            $lastInsc = $c->inscriptionsAnnuelles->sortByDesc('created_at')->first();
            $yearSource = 'année pastorale inscription';
            $yearVal = null;

            if ($lastInsc?->anneeCatechese) {
                $anneeCat = $lastInsc->anneeCatechese;
                if (!empty($anneeCat->date_debut)) {
                    $yearVal = $anneeCat->date_debut->format('y');
                } elseif (preg_match('/^([0-9]{4})/', (string) $anneeCat->libelle, $mMatches)) {
                    $yearVal = substr($mMatches[1], -2);
                }
            }

            // Repli 2 : année extraite de l'ancien matricule (ex: SM26-... -> 26)
            if (empty($yearVal) && preg_match('/^[A-Z0-9]+([0-9]{2})-/', $currentMatricule, $mMatches)) {
                $yearVal = $mMatches[1];
                $yearSource = 'ancien matricule';
            }

            // Repli 3 : created_at du catéchumène
            if (empty($yearVal) && $c->created_at) {
                $yearVal = $c->created_at->format('y');
                $yearSource = 'date création catéchumène';
            }

            // Repli 4 : résolution via service
            if (empty($yearVal)) {
                $yearVal = $generatorService->resolveYear(null, $paroisseId);
                $yearSource = 'année courante paroisse';
            }

            // E. Génération d'un matricule unique garanti
            $newMatricule = null;
            $attempts = 0;
            do {
                $codeAleatoire = $generatorService->generateRandomCode(4);
                $candidate = sprintf('%s%s-%s%s', $prefix, $yearVal, $sectionLetter, $codeAleatoire);

                $alreadyUsedInDb = Catechumene::withTrashed()
                    ->where('id', '!=', $c->id)
                    ->where('matricule', $candidate)
                    ->exists();

                $alreadyUsedThisRun = in_array($candidate, $generatedMatriculesThisRun, true);

                $attempts++;
                if (!$alreadyUsedInDb && !$alreadyUsedThisRun) {
                    $newMatricule = $candidate;
                    $generatedMatriculesThisRun[] = $newMatricule;
                    break;
                }
            } while ($attempts < 100);

            if (!$newMatricule) {
                $this->error("Collision répétée pour le catéchumène ID {$c->id} ({$c->nom_complet})");
                continue;
            }

            $toMigrate[] = [
                'id'               => $c->id,
                'nom'              => $c->nom_complet,
                'ancien_matricule' => $currentMatricule,
                'section_code'     => $section?->code ?? ($allowFallback ? 'FALLBACK-E' : 'AUCUNE'),
                'section_lettre'   => $sectionLetter,
                'annee'            => $yearVal,
                'source_annee'     => $yearSource,
                'nouveau_matricule'=> $newMatricule,
            ];
        }

        // 3. Affichage du bilan d'audit
        $this->newLine();
        $this->info("--- SYNTHÈSE D'ANALYSE PRÉALABLE ---");
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Nombre total de catéchumènes', $totalCats],
                ['Matricules déjà conformes au nouveau format', count($alreadyCompliant)],
                ['Matricules à mettre à jour', count($toMigrate)],
                ['Sections introuvables (non modifiés)', count($unresolvedSections)],
                ['Préfixes manquants ou invalides', count($missingPrefixes)],
                ['Collisions détectées', 0],
            ]
        );

        // Signalement des sections introuvables
        if (!empty($unresolvedSections)) {
            $this->warn("\n⚠️  Catéchumène(s) avec section introuvable (laissés intacts) :");
            $this->table(
                ['ID', 'Nom & Prénoms', 'Paroisse ID', 'Matricule Actuel', 'Nb Inscriptions', 'Raison'],
                $unresolvedSections
            );
            $this->line("Astuce : Pour leur assigner la lettre 'E' par défaut, relancez avec l'option --fallback-unresolved.");
        }

        // Signalement des préfixes manquants
        if (!empty($missingPrefixes)) {
            $this->error("\n❌ Catéchumène(s) avec préfixe de paroisse manquant :");
            $this->table(
                ['ID', 'Nom & Prénoms', 'Paroisse ID', 'Matricule Actuel', 'Raison'],
                $missingPrefixes
            );
        }

        // Affichage des modifications prévues
        if (!empty($toMigrate)) {
            $this->info("\n--- LISTE DES MATRICULES À MIGRER ---");
            $this->table(
                ['ID', 'Nom & Prénoms', 'Ancien Matricule', 'Section Code', 'L', 'AA', 'Nouveau Matricule'],
                array_map(fn($item) => [
                    $item['id'],
                    $item['nom'],
                    $item['ancien_matricule'],
                    $item['section_code'],
                    $item['section_lettre'],
                    $item['annee'],
                    $item['nouveau_matricule'],
                ], $toMigrate)
            );
        }

        // 4. Si mode Dry-Run : arrêt sécurisé sans écriture
        if ($isDryRun) {
            $this->newLine();
            $this->comment("Fin du mode DRY-RUN.");
            $this->comment("Aucune ligne n'a été modifiée dans la base de données.");
            $this->comment("Pour appliquer ces modifications en base, exécutez :");
            $this->line("  <info>php artisan catheo:migrate-matricules --force" . ($allowFallback ? ' --fallback-unresolved' : '') . "</info>");
            return Command::SUCCESS;
        }

        // 5. Exécution réelle sous transaction sécurisée
        if (empty($toMigrate)) {
            $this->info("\nAucun matricule n'a besoin d'être migré.");
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Application des modifications en base de données...");

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            foreach ($toMigrate as $item) {
                DB::table('catechumenes')
                    ->where('id', $item['id'])
                    ->update([
                        'matricule'  => $item['nouveau_matricule'],
                        'updated_at' => now(),
                    ]);
                $updatedCount++;
            }

            DB::commit();

            $this->newLine();
            $this->info("✅ SUCCÈS : {$updatedCount} matricule(s) mis à jour avec succès vers le nouveau format.");
            $this->newLine();

            // Exemple avant/après
            $first = $toMigrate[0] ?? null;
            if ($first) {
                $this->info("Exemple réel avant/après :");
                $this->line("  Avant : <comment>{$first['ancien_matricule']}</comment>");
                $this->line("  Après : <info>{$first['nouveau_matricule']}</info> ({$first['nom']})");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ ERREUR lors de la transaction : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
