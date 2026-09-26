# Guide de Navigation — Espace Organisation F26
## Structure du Menu et Routage Complet

L'ensemble des modules est encapsulé sous le layout dédié `OrganisationLayoutComponent` avec le préfixe `/organisation/`.

### 1. Table de Correspondance Navigation / URL

| Menu Sidebar | URL Principale | Alias & Routes Secondaires | Description |
| :--- | :--- | :--- | :--- |
| **Dashboard** | `/organisation/dashboard` | `/organisation` | Vue d'ensemble, 5 KPIs et 4 widgets temps réel. |
| **Membres du bureau** | `/organisation/membres` | `/organisation/membres/:id` | Gestion de l'équipe pastorale, fonctions et mandats. |
| **Activités pastorales** | `/organisation/activites` | `/organisation/activites/:id` | Calendrier, budget, état et archivage d'activités. |
| **Campagnes pèlerinage** | `/organisation/pelerinages` | `/organisation/pelerinages/:id` | Programmation des pèlerinages, quotas et suivi. |
| **Tarifs** | `/organisation/tarifs` | — | Gestion des tarifs par campagne, activation/désactivation. |
| **Inscriptions** | `/organisation/inscriptions` | — | Enregistrement direct et import catéchumène CATHEO. |
| **Participants** | `/organisation/participants` | — | Registre général des pèlerins, statuts de paiement & présence. |
| **Caisse** | `/organisation/caisse` | — | Comptabilité, journal des écritures, export Excel & PDF. |
| **Paiements** | `/organisation/paiements` | — | Journal des règlements, réédition et impression de reçus. |
| **Population CATHEO** | `/organisation/catheo-population` | — | Registre de la population rattachée (si `mode=liee`). |
| **Statistiques** | `/organisation/statistiques` | — | Rapports analytiques et taux de remplissage. |
| **Informations** | `/organisation/informations` | `/organisation/parametres` | Édition des coordonnées, logo et bascule de mode. |
| **Utilisateurs & rôles** | `/organisation/utilisateurs` | — | Console RBAC, comptes d'accès et attribution des profils. |
| **Historique** | `/organisation/historique` | `/organisation/journal-audit` | Journal interne de traçabilité et timeline d'audit. |

---

### 2. Comportements d'Affichage selon le Mode

- **Organisation en mode `liee` (Rattachée à une paroisse) :**
  - La bannière d'en-tête affiche le nom de la paroisse affiliée.
  - L'onglet **Population CATHEO** est actif et synchronisé avec la base pastorale centrale.
  - Dans **Inscriptions**, le bouton *"Importer depuis CATHEO"* est affiché et opérationnel.
  - Dans **Informations**, le champ *"Paroisse rattachée"* est actif avec la liste dynamique des paroisses.

- **Organisation en mode `independant` (Autonome / Diocésaine) :**
  - L'en-tête affiche le badge *"Organisation Indépendante"*.
  - Dans **Inscriptions**, le bouton d'import CATHEO est masqué ou affiche une note d'autonomie.
  - Dans **Informations**, la sélection de paroisse affiche explicitement *"Aucune paroisse"* et le sélecteur est désactivé.
