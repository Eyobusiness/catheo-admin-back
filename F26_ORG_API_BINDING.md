# Liaisons API Backend — Espace Organisation F26
## Correspondance Composants Angular 21 ↔ Endpoints Laravel 12

Tous les appels API respectent l'isolation `OrganisationScopeMiddleware` et l'authentification `Bearer Sanctum`.

| Module Angular | Action | Méthode & Endpoint API | Modèle & Payloads |
| :--- | :--- | :--- | :--- |
| **Dashboard** | Obtenir KPIs & Widgets | `GET /api/v1/organisation/dashboard` | `DashboardOrganisation`, `indicateurs`, `widgets` |
| **Informations** | Obtenir le profil | `GET /api/v1/organisation/info` | `OrganisationProfileData` |
| **Informations** | Mettre à jour les données | `PUT /api/v1/organisation/info` | JSON : nom, description, email, mode, etc. |
| **Informations** | Upload Logo & mise à jour | `POST /api/v1/organisation/info` | FormData : `logo: File`, nom, mode, etc. |
| **Informations** | Liste des paroisses actives | `GET /api/v1/organisation/paroisses` | `ParoisseItem[]` |
| **Membres** | Liste des membres | `GET /api/v1/organisation/membres` | `Membre[]`, pagination, filtres fonction/statut |
| **Membres** | Créer un membre | `POST /api/v1/organisation/membres` | `nom`, `prenoms`, `fonction`, `mandat`, etc. |
| **Membres** | Mettre à jour | `PUT /api/v1/organisation/membres/{uuid}` | Mise à jour données & statut |
| **Membres** | Supprimer (Soft Delete) | `DELETE /api/v1/organisation/membres/{uuid}` | Soft delete avec traçabilité |
| **Activités** | Liste des activités | `GET /api/v1/organisation/activites` | `Activite[]`, filtres statut/type |
| **Activités** | Créer / Modifier / Archiver | `POST/PUT /api/v1/organisation/activites` | Titre, budget, dates, lieu, statut |
| **Campagnes** | Liste des campagnes | `GET /api/v1/organisation/pelerinages/campagnes` | `CampagnePelerinage[]` |
| **Campagnes** | Créer / Mettre à jour | `POST/PUT /api/v1/organisation/pelerinages/campagnes` | Destination, capacité, dates |
| **Tarifs** | Obtenir les tarifs | `GET /api/v1/organisation/pelerinages/campagnes/{id}/tarifs` | `TarifPelerinage[]` |
| **Tarifs** | Créer un tarif | `POST /api/v1/organisation/pelerinages/campagnes/{id}/tarifs` | `libelle`, `montant`, `statut` |
| **Tarifs** | Mettre à jour / Basculer | `PUT /api/v1/organisation/pelerinages/tarifs/{id}` | `libelle`, `montant`, `statut` |
| **Tarifs** | Supprimer | `DELETE /api/v1/organisation/pelerinages/tarifs/{id}` | Soft delete |
| **Inscriptions** | Créer inscription directe | `POST /api/v1/organisation/pelerinages/campagnes/{id}/inscriptions` | `StoreInscriptionPayload` |
| **Participants** | Liste des pèlerins | `GET /api/v1/organisation/pelerinages/inscriptions` | `InscriptionPelerinage[]`, filtres paiements/présence |
| **Participants** | Mettre à jour la présence | `PATCH /api/v1/organisation/pelerinages/inscriptions/{id}/participation` | `statut_participation: 'presente' \| 'absente'` |
| **Caisse** | État de caisse & opérations | `GET /api/v1/organisation/caisse` | `solde_actuel`, `operations: []` |
| **Caisse** | Enregistrer mouvement | `POST /api/v1/organisation/caisse/operations` | Encaissement / Décaissement |
| **Paiements** | Journal des paiements | `GET /api/v1/organisation/pelerinages/paiements` | `PaiementPelerinage[]` |
| **Paiements** | Enregistrer un paiement | `POST /api/v1/organisation/pelerinages/inscriptions/{id}/paiements` | `montant`, `mode_paiement`, `observation` |
| **Utilisateurs** | Liste des utilisateurs | `GET /api/v1/organisation/users` | `OrganisationUserItem[]` |
| **Utilisateurs** | Profils disponibles | `GET /api/v1/organisation/users/profils` | `ProfilItem[]` non-système |
| **Utilisateurs** | Créer / Inviter | `POST /api/v1/organisation/users` | `name`, `email`, `telephone`, `profil_id`, `password` |
| **Utilisateurs** | Basculer statut | `PATCH /api/v1/organisation/users/{id}/toggle-status` | Bascule actif / inactif |
| **Utilisateurs** | Supprimer utilisateur | `DELETE /api/v1/organisation/users/{id}` | Révocation du compte |
| **Historique** | Journal d'audit | `GET /api/v1/organisation/audit-logs` | `AuditLogItem[]`, logs filtrables par action |
