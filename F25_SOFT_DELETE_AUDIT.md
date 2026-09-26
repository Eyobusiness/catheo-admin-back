# CATHEO — REGISTRE D'AUDIT DES SUPPRESSIONS & CORBEILLE CENTRALE (F25)
## Architecture et fonctionnement de la Corbeille Multi-Modules

> **Document** : F25_SOFT_DELETE_AUDIT.md  
> **Service Moteur** : [SoftDeleteAuditService.php](file:///c:/xampp/htdocs/catheo/app/Services/SuperAdmin/SoftDeleteAuditService.php)  
> **Contrôleur API** : [SuperAdminTrashController.php](file:///c:/xampp/htdocs/catheo/app/Http/Controllers/Api/V1/SuperAdmin/SuperAdminTrashController.php)  

---

## 1. INVENTAIRE DES MODÈLES COUVERTS

La Corbeille centrale réunit en un point d'accès unifié l'intégralité des 20 entités auditables sous `SoftDeletes` dans l'application :

| Domaine | Module | Modèle Eloquent | Table SQL | Libellé / Élément affiché |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | Paroisse | `CatecheseConfiguration` | `paroisse_configurations` | Nom de la paroisse (`nom_paroisse`) |
| **Super Admin** | Organisation | `Organisation` | `organisations` | Nom + Type (`OPPE`, `OPPJ`, `OPPA`) |
| **Super Admin** | Produit SaaS | `Produit` | `produits` | Nom + Code (`CATHEO`, `OPPE`, etc.) |
| **Super Admin** | Formule Tarifaire | `Formule` | `formules` | Nom + Montant (`STANDARD (50 000 XOF)`) |
| **Super Admin** | Abonnement | `Abonnement` | `abonnements` | Référence contrat (`ABO-2026-XXXX`) |
| **Plateforme** | Utilisateur | `User` | `users` | Nom + Email de l'utilisateur |
| **CATHEO (Cœur)**| Catéchumène | `Catechumene` | `catechumenes` | Nom, Prénoms et Matricule |
| **CATHEO (Cœur)**| Inscription Catéchèse | `InscriptionAnnuelle`| `inscriptions_annuelles` | Code inscription (`INS-XXXX`) |
| **CATHEO (Cœur)**| Paiement Catéchèse | `Paiement` | `paiements` | Référence reçu et Montant |
| **CATHEO (Cœur)**| Année Pastorale | `AnneeCatechese` | `annee_catecheses` | Libellé de l'année (`2025-2026`) |
| **CATHEO (Cœur)**| Section | `Section` | `sections` | Nom et Code de section |
| **CATHEO (Cœur)**| Niveau | `Niveau` | `niveaux` | Nom du niveau de catéchèse |
| **CATHEO (Cœur)**| Classe | `Classe` | `classes` | Nom de la classe |
| **Organisations** | Membre | `Membre` | `membres` | Nom et Prénoms du membre |
| **Organisations** | Activité | `Activite` | `activites` | Titre de l'activité pastorale |
| **Organisations** | Campagne Pèlerinage | `CampagnePelerinage` | `campagne_pelerinages` | Titre du pèlerinage |
| **Organisations** | Participant Pèlerinage | `InscriptionPelerinage`| `inscription_pelerinages`| Nom et Prénoms du participant |
| **Organisations** | Tarif Pèlerinage | `TarifPelerinage` | `tarif_pelerinages` | Libellé et Montant du tarif |
| **Organisations** | Paiement Pèlerinage | `PaiementPelerinage` | `paiement_pelerinages` | Référence du paiement pèlerinage |
| **Organisations** | Caisse Organisation | `OperationOrganisation`| `operation_organisations`| Référence et Montant de l'opération |

---

## 2. FONCTIONNEMENT TECHNIQUE

### 2.1 Requête centrale consolidée
La méthode `SoftDeleteAuditService::list()` scanne le registre avec la clause `onlyTrashed()`.  
Pour chaque enregistrement supprimé :
- Son identifiant unique exposé est son **UUID v4** (`$item->uuid`).
- L'utilisateur ayant réalisé la suppression est résolu via `$item->deleted_by` (géré par le trait `Auditable`).
- Les éléments sont consolidés, triés chronologiquement par date de suppression décroissante (`deleted_at DESC`), puis paginés proprement pour le frontend.

### 2.2 Mécanisme de Restauration (`restore`)
1. L'appelant transmet l'UUID : `POST /api/v1/super-admin/trash/{uuid}/restore`.
2. Le service localise l'entité dans le registre multi-modèles via `onlyTrashed()->where('uuid', $uuid)`.
3. La méthode `$model->restore()` est invoquée, remettant `deleted_at = NULL` et nettoyant `deleted_by`.
4. L'action est immédiatement consignée dans le journal d'audit : `ActionAuditService::log('restore', 'Corbeille', ...)`.

### 2.3 Mécanisme de Suppression Définitive (`forceDelete`) & Garde-fous
Pour prévenir toute destruction accidentelle de la base de données, des **règles d'intégrité strictes** sont exécutées avant tout appel à `$model->forceDelete()` :
1. **Paroisse** : Le système vérifie si des organisations, utilisateurs, catéchumènes ou abonnements actifs pointent encore sur cette paroisse. Si oui, l'opération est avortée avec une exception HTTP 422 claire.
2. **Produit SaaS** : Le système vérifie si des organisations ou formules vivantes sont associées à ce produit.
3. **Organisation** : Le système vérifie si des utilisateurs ou membres vivants lui sont rattachés.
4. **Utilisateur** : Le système vérifie si l'utilisateur est le responsable officiel déclaré d'une organisation.
5. Une fois les contraintes validées, `$model->forceDelete()` est exécuté et tracé dans le journal d'audit (`force_delete`).
