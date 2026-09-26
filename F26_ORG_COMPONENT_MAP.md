# Cartographie des Composants — Espace Organisation F26
## Répertoire catheo-super-admin

Cette cartographie détaille l'organisation structurelle de l'Espace Organisation (OPPE / OPPJ / OPPA), les composants réutilisés, modernisés et nouvellement créés.

```
src/app/features/organisation/
├── components/
│   └── organisation-layout/
│       └── organisation-layout.component.ts       [Layout racine avec Sidebar dédiée et Navbar contextuelle]
│
├── services/
│   └── organisation-admin.service.ts              [Service central pour profil, logo, paroisses, utilisateurs & audit]
│
├── dashboard/
│   └── pages/
│       └── organisation-dashboard-page.component.ts [Dashboard enrichi : 5 KPIs + 4 Widgets + Bannière CATHEO]
│
├── membres/
│   ├── components/
│   │   └── membre-form-modal.component.ts         [Modal membre : Fonctions prédéfinies, fonction libre, mandat]
│   └── pages/
│       └── membres-list-page.component.ts         [Tableau des membres, badges de fonction et mandat]
│
├── activites/
│   ├── components/
│   │   └── activite-form-modal.component.ts       [Modal activité pastorale : Budget, dates, lieu, statut]
│   └── pages/
│       ├── activites-list-page.component.ts       [Liste et filtrage des activités pastorales]
│       └── activite-detail-page.component.ts      [Fiche détaillée de l'activité]
│
├── pelerinages/
│   ├── components/
│   │   └── campagne-form-modal.component.ts       [Modal campagne : Destination, capacité, dates]
│   └── pages/
│       ├── pelerinages-list-page.component.ts     [Gestion des campagnes de pèlerinage]
│       └── pelerinage-detail-page.component.ts    [Détail campagne : Statistiques, inscrits, caisse liée]
│
├── tarifs/                                        [NOUVEAU MODULE]
│   ├── components/
│   │   └── tarif-modal.component.ts               [Modal de création et d'édition d'une catégorie tarifaire]
│   └── pages/
│       └── tarifs-page.component.ts               [Tableau des tarifs, filtre par campagne, bascule actif/inactif]
│
├── inscriptions/                                  [NOUVEAU MODULE]
│   ├── components/
│   │   └── catheo-import-modal.component.ts       [Modal d'import direct de catéchumènes en mode=liee]
│   └── pages/
│       └── inscriptions-page.component.ts         [Console d'inscription directe avec calcul d'âge et acompte]
│
├── participants/                                  [NOUVEAU MODULE]
│   ├── components/
│   │   └── participant-detail-modal.component.ts  [Fiche pèlerin, pointage présence, reçus et historique]
│   └── pages/
│       └── participants-page.component.ts         [Registre complet des participants, filtres de paiement/présence]
│
├── caisse/
│   ├── components/
│   │   └── operation-modal.component.ts           [Saisie d'encaissements / décaissements]
│   └── pages/
│       └── caisse-page.component.ts               [Comptabilité : Solde, opérations, exports Excel et PDF]
│
├── paiements/                                     [NOUVEAU MODULE]
│   ├── components/
│   │   └── recu-modal.component.ts                [Affichage du reçu officiel et impression instantanée]
│   └── pages/
│       └── paiements-page.component.ts            [Journal des paiements, réédition, filtrage par mode de paiement]
│
├── catheo-population/
│   └── pages/
│       └── catheo-population-page.component.ts    [Passerelle et registre de la population catéchétique rattachée]
│
├── statistiques/
│   └── pages/
│       └── statistiques-page.component.ts         [Analyses, graphiques de répartition et indicateurs périodiques]
│
├── informations/                                  [NOUVEAU MODULE]
│   └── pages/
│       └── organisation-info-page.component.ts    [Formulaire d'identité, upload logo, bascule mode liee/independant]
│
├── utilisateurs/                                  [NOUVEAU MODULE]
│   ├── components/
│   │   └── organisation-user-modal.component.ts   [Modal d'invitation/création d'utilisateur organisationnel]
│   └── pages/
│       └── organisation-users-page.component.ts   [Gestion RBAC : Utilisateurs, profils, états et révocations]
│
└── historique/                                    [NOUVEAU MODULE]
    └── pages/
        └── organisation-historique-page.component.ts [Timeline chronologique d'audit des actions internes]
```
