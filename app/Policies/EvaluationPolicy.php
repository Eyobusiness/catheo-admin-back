<?php

namespace App\Policies;

use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EvaluationPolicy
{
    use HandlesAuthorization;

    /**
     * Autorisation globale de visualisation des évaluations.
     */
    public function viewAny(mixed $user): bool
    {
        if ($user instanceof Animateur) {
            return true;
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.view') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Visualisation d'une évaluation spécifique.
     */
    public function view(mixed $user, Evaluation $evaluation): bool
    {
        if ($user->paroisse_configuration_id !== $evaluation->paroisse_configuration_id) {
            return false;
        }

        if ($user instanceof Animateur) {
            return $this->isAnimateurAssignedToClasse($user->id, $evaluation->classe_id);
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.view') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Création d'une évaluation pour une classe donnée.
     */
    public function create(mixed $user, ?int $classeId = null): bool
    {
        if ($user instanceof Animateur) {
            if (!$classeId) {
                return false;
            }
            return $this->isAnimateurAssignedToClasse($user->id, $classeId);
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.manage') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Modification d'une évaluation.
     */
    public function update(mixed $user, Evaluation $evaluation): bool
    {
        if ($user->paroisse_configuration_id !== $evaluation->paroisse_configuration_id) {
            return false;
        }

        if ($user instanceof Animateur) {
            return $this->isAnimateurAssignedToClasse($user->id, $evaluation->classe_id);
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.manage') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Suppression d'une évaluation.
     */
    public function delete(mixed $user, Evaluation $evaluation): bool
    {
        if ($user->paroisse_configuration_id !== $evaluation->paroisse_configuration_id) {
            return false;
        }

        if ($user instanceof Animateur) {
            return $this->isAnimateurAssignedToClasse($user->id, $evaluation->classe_id);
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.manage') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Saisie et modification des notes pour une évaluation.
     */
    public function manageNotes(mixed $user, Evaluation $evaluation): bool
    {
        return $this->update($user, $evaluation);
    }

    /**
     * Accès aux moyennes d'une classe.
     */
    public function viewClasseMoyennes(mixed $user, Classe $classe): bool
    {
        if ($user->paroisse_configuration_id !== $classe->paroisse_configuration_id) {
            return false;
        }

        if ($user instanceof Animateur) {
            return $this->isAnimateurAssignedToClasse($user->id, $classe->id);
        }

        if ($user instanceof User) {
            return $user->hasPermission('evaluations.view') || $user->profil?->code === 'SUPER_ADMIN';
        }

        return false;
    }

    /**
     * Vérifie si un animateur est affecté à une classe.
     */
    private function isAnimateurAssignedToClasse(int $animateurId, ?int $classeId): bool
    {
        if (!$classeId) {
            return false;
        }

        return AffectationAnimateur::where('animateur_id', $animateurId)
            ->where('classe_id', $classeId)
            ->exists();
    }
}
