# CATHEO — AUDIT PRÉALABLE À L'IMPLÉMENTATION (F25)
## Refonte Super Admin : Paroisses ↔ Organisations + Utilisateurs + Audit + Corbeille

> **Document** : F25_PRE_IMPLEMENTATION_AUDIT.md  
> **Date** : 24 septembre 2026  
> **Étape** : Phase A — Analyse technique d'impact préalable  

---

## 1. COMPOSANTS ANALYSÉS

### 1.1 `SuperAdminParoisseController` & `SuperAdminParoisseResource`
- **État actuel** :
  - `SuperAdminParoisseController` dispose uniquement de `index(Request $request)` et `show(string $id)`.
  - `show` charge : `['abonnements.formule.produit', 'abonnements.echeances.paiements']`.
  - `SuperAdminParoisseResource` expose : `id` (uuid), `id_interne` (id), `nom_paroisse`, `code_paroisse`, `diocese`, `doyenne`, `ville`, `commune`, `telephone`, `email`, `statut`, `total_abonnements`, `produits_souscrits`, `abonnements`.
  - **Manque pour F25** : Les organisations rattachées (`organisations`) ne sont ni chargées ni exposées dans le bloc du détail paroisse.
- **Évolution requise** :
  - Dans `SuperAdminParoisseController@show`, eager-loader `organisations.produit`.
  - Dans `SuperAdminParoisseResource`, exposer le tableau `organisations` avec leurs attributs complets (`id` uuid, `nom`, `type_organisation`, `produit_code`, `statut`, `responsable_nom`, `created_at`).
  - Assurer la résolution par UUID strict (`find($id)` ou `where('uuid', $id)`).

### 1.2 `SuperAdminOrganisationController`
- **État actuel** :
  - Méthodes actuelles : `index`, `show`, `createResponsable`.
  - **Manque pour F25** :
    - `store` (création simple et batch multi-select)
    - `update` (modification des champs et logo)
    - `changerStatut` (patch actif, suspendu, inactif)
    - `destroy` (soft delete)
- **Évolution requise** :
  - Implémenter `store` avec support création simple ou multi-sélection (`produits: ["OPPE", "OPPJ", "OPPA"]`).
  - Implémenter `update` pour nom, description, coordonnées, responsable, logo.
  - Implémenter `changerStatut` avec validation des statuts autorisés.
  - Implémenter `destroy` avec audit log et soft delete.
  - Résolution par UUID avec Route Model Binding.

### 1.3 Modèle `Organisation`
- **État actuel** :
  - Table : `organisations`.
  - Clés : `paroisse_configuration_id`, `produit_id`, `type_organisation` (`OPPE`, `OPPJ`, `OPPA`).
  - Traits : `Auditable`, `HasFactory`, `HasUuid`, `SoftDeletes`.
  - Verrou d'unicité `(paroisse_configuration_id, type_organisation)` actif dans `Organisation::booted()`.
- **Évolution requise** :
  - Ajouter l'accesseur `logo_url` retournant l'URL publique de `logo_path`.
  - Conserver strictement le verrou anti-doublon existant.

### 1.4 Modèle `CatecheseConfiguration`
- **État actuel** :
  - Table : `paroisse_configurations`.
  - Traits : `Auditable`, `HasFactory`, `HasUuid`, `SoftDeletes`.
  - Relation existante : `organisations(): HasMany`.
- **Évolution requise** :
  - Déjà prêt. Servira pour la résolution des paroisses par UUID et l'accès à ses organisations.

### 1.5 Modèle `Produit`
- **État actuel** :
  - Table : `produits`.
  - Traits : `Auditable`, `HasFactory`, `HasUuid`, `SoftDeletes`.
  - Codes : `CATHEO`, `OPPE`, `OPPJ`, `OPPA`.
- **Évolution requise** :
  - Déjà prêt pour la résolution par code ou UUID.

### 1.6 Modèle `User` & Module Utilisateurs Super Admin
- **État actuel** :
  - Table : `users`.
  - Traits : `Auditable`, `HasApiTokens`, `HasAuditFields`, `HasFactory`, `HasUuid`, `Notifiable`, `SoftDeletes`.
  - Relations : `paroisse()`, `organisation()`, `profil()`.
  - Pas de contrôleur dédié `SuperAdminUserController` dans `app/Http/Controllers/Api/V1/SuperAdmin/`.
- **Évolution requise** :
  - Créer `SuperAdminUserController` dans le namespace `SuperAdmin` avec les actions complètes :
    - `index` (avec filtres profil, paroisse, organisation, statut, recherche)
    - `store` (création utilisateur avec hash de mot de passe)
    - `show` (détail)
    - `update` (modification des informations)
    - `changerStatut` (patch actif, suspendu, inactif)
    - `destroy` (soft delete)
    - `resetPassword` (réinitialisation sécurisée du mot de passe)
  - Résolution stricte par UUID.

### 1.7 Trait `Auditable` & Système d'audit
- **État actuel** :
  - `Auditable.php` met à jour automatiquement `created_by`, `updated_by`, `deleted_by` avec l'UUID de l'utilisateur connecté.
  - La table `audit_logs` existante possède `paroisse_configuration_id` obligatoire (not null) et un enum d'actions restreint, orienté paroisse.
- **Évolution requise** :
  - Pour le Super Admin (qui gère la plateforme, les produits, les formules, et des actions transverses), créer une table dédiée `super_admin_audit_logs` (ou `action_audit_logs`) avec les colonnes requises :
    - `uuid`
    - `user_id` & `user_uuid`
    - `user_name` & `user_email`
    - `profil_nom`
    - `ip_address`
    - `user_agent`
    - `action` (login, logout, create, update, delete, restore, status_change, etc.)
    - `module` (Paroisse, Organisation, Utilisateur, Produit, Formule, Abonnement, etc.)
    - `anciennes_valeurs` (json)
    - `nouvelles_valeurs` (json)
    - `paroisse_configuration_id` (nullable)
    - `organisation_id` (nullable)
  - Créer un service central `AuditLogService` facilitant la consignation automatique ou explicite des événements clés.
  - Créer le contrôleur `SuperAdminAuditController` (`GET /api/v1/super-admin/audit-logs` avec filtres complets).

### 1.8 Modèles utilisant `SoftDeletes` (Corbeille centrale)
L'inventaire complet des modèles du projet utilisant `SoftDeletes` révèle :
- **CATHEO** :
  - `Catechumene`
  - `InscriptionAnnuelle`
  - `Paiement`
  - `AnneeCatechese`
  - `Section`
  - `Niveau`
  - `Classe`
  - `Ceb`
  - `Mouvement`
  - `Animateur`
  - `Evaluation`
  - `Seance`
  - `Presence`
- **Super Admin** :
  - `CatecheseConfiguration` (Paroisses)
  - `Organisation`
  - `Produit`
  - `Formule`
  - `Abonnement`
  - `User`
- **Organisations** :
  - `Membre`
  - `Activite`
  - `CampagnePelerinage`
  - `InscriptionPelerinage`
  - `TarifPelerinage`
  - `PaiementPelerinage`
  - `OperationOrganisation`

- **Évolution requise pour la Corbeille** :
  - Créer `SoftDeleteAuditService` qui registre un registre central des modèles auditables en soft-delete.
  - Méthode de scan multi-modèles avec pagination et filtres (`module`, `paroisse_id`, `organisation_id`, dates).
  - Deux opérations :
    - `restore(uuid)`
    - `forceDelete(uuid)` avec vérification des contraintes d'intégrité référentielle (ex: interdiction de force delete une paroisse avec des dépendances vivantes).
  - Créer le contrôleur `SuperAdminTrashController` (`GET /trash`, `GET /trash/{uuid}`, `POST /trash/{uuid}/restore`, `DELETE /trash/{uuid}/force`).

---

## 2. PLAN D'EXÉCUTION TECHNIQUE

| Phase | Description | Fichiers cibles |
| :--- | :--- | :--- |
| **Phase B** | Bloc Organisations dans Détail Paroisse | `SuperAdminParoisseController`, `SuperAdminParoisseResource` |
| **Phase C** | CRUD Super Admin Organisations (unitaire + batch) | `SuperAdminOrganisationController`, `StoreOrganisationRequest`, `UpdateOrganisationRequest` |
| **Phase D** | Logo Organisation (upload, storage, accesseur `logo_url`) | `Organisation`, `SuperAdminOrganisationController` |
| **Phase E** | Module Utilisateurs Super Admin (CRUD, statut, reset pwd) | `SuperAdminUserController`, FormRequests, routes |
| **Phase F** | Audit des actions Super Admin | Migration `super_admin_audit_logs`, modèle `ActionAuditLog`, service `AuditLogService`, contrôleur |
| **Phase G & H**| Service Corbeille & API Trash (scan, restore, forceDelete) | `SoftDeleteAuditService`, `SuperAdminTrashController`, routes |
| **Phase I** | RBAC : permissions requises | Seeders, `Profil`, contrôles de permissions |
| **Phase J** | Feature Tests complets et validation | `tests/Feature/SuperAdminF25Test.php` |

---

> **Validation Phase A** : Toutes les dépendances, contraintes d'intégrité et règles multi-tenants sont cartographiées. Le plan garantit zéro régression pour CATHEO et zéro altération des fonctionnalités existantes.
