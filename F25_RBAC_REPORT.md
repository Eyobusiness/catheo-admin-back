# CATHEO — RAPPORT RBAC & MATRICE DES PERMISSIONS (F25)
## Gestion des Accès et Permissions Super Admin

> **Document** : F25_RBAC_REPORT.md  
> **Date** : 24 septembre 2026  

---

## 1. NOUVELLES PERMISSIONS INTÉGRÉES

Dans le cadre de l'étape **F25**, les permissions suivantes sont formalisées et contrôlées par le système :

| Clé de permission | Domaine | Description | Acteurs autorisés par défaut |
| :--- | :--- | :--- | :--- |
| `audit.read` | Journal d'audit | Consulter l'historique centralisé des actions et modifications | `SUPER_ADMIN` |
| `trash.read` | Corbeille centrale | Consulter la liste et le détail des éléments supprimés logiquement | `SUPER_ADMIN` |
| `trash.restore` | Corbeille centrale | Restaurer un élément supprimé logiquement (`restore()`) | `SUPER_ADMIN` |
| `trash.force_delete` | Corbeille centrale | Purger définitivement un élément sans dépendance (`forceDelete()`) | `SUPER_ADMIN` |
| `users.manage` | Utilisateurs | Créer, modifier, suspendre et réinitialiser les utilisateurs | `SUPER_ADMIN`, `ADMIN_PAROISSE` |
| `organisations.manage` | Organisations | Créer, modifier, suspendre et affecter les responsables d'organisations | `SUPER_ADMIN`, `ADMIN_PAROISSE` |

---

## 2. MATRICE DES PROFILS ET RÔLES

### 2.1 Profil `SUPER_ADMIN`
- **Code** : `SUPER_ADMIN`
- **Typologie** : `is_system = true`, `permissions = ['*']`
- **Périmètre d'action** :
  - Accès total et universel à la plateforme.
  - Gestion des Paroisses et des Produits SaaS.
  - Création unitaire et batch des Organisations (OPPE, OPPJ, OPPA).
  - Gestion de l'ensemble des Utilisateurs tous tenants confondus.
  - Consultation intégrale du Journal d'audit centralisé.
  - Accès complet à la Corbeille (scan, restauration, suppression définitive).

### 2.2 Profils Responsables d'Organisations (`RESPONSABLE_OPPE`, `RESPONSABLE_OPPJ`, `RESPONSABLE_OPPA`)
- **Code** : `RESPONSABLE_{TYPE}`
- **Périmètre d'action** :
  - Strictement cantonnés à leur `organisation_id` et leur `paroisse_configuration_id`.
  - Gestion des Membres de l'organisation.
  - Gestion des Activités et Campagnes de pèlerinage.
  - Consultation de la caisse et des rapports de leur organisation.
  - Consultation de la population catéchétique synchronisée avec CATHEO selon leurs sections canoniques strictes.
  - **Accès Super Admin interdit** (rejet 403 automatique par `EnsureSuperAdmin`).

### 2.3 Profil Administrateur Paroissial (`ADMIN_PAROISSE`)
- **Code** : `ADMIN_PAROISSE`
- **Périmètre d'action** :
  - Limité à sa propre paroisse (`paroisse_configuration_id`).
  - Gestion de la catéchèse (inscriptions, séances, évaluations, finances catéchèse).
  - Consultation des organisations appartenant exclusivement à sa paroisse.
  - **Accès Super Admin interdit**.

---

## 3. GARANTIES DE SÉCURITÉ MULTI-TENANT

1. **Barrière Middleware `EnsureSuperAdmin`** :
   - Toutes les routes `/api/v1/super-admin/*` sont scellées par le middleware `super_admin`.
   - Tout utilisateur qui n'est pas `user_type: 'super_admin'` ou qui n'a pas le profil `SUPER_ADMIN` reçoit une réponse HTTP 403 Forbidden immédiate.
2. **Contrôle d'Intégrité Utilisateur ↔ Organisation ↔ Paroisse** :
   - [SecurityContextService.php](file:///c:/xampp/htdocs/catheo/app/Services/SecurityContextService.php) applique la règle absolue : un utilisateur lié à l'organisation A ne peut jamais appartenir à la paroisse B.
3. **Protection contre l'Auto-Suppression** :
   - L'endpoint `DELETE /api/v1/super-admin/users/{uuid}` bloque formellement la suppression de son propre compte administrateur connecté (erreur 422).
