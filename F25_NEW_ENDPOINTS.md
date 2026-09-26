# CATHEO — NOUVEAUX ENDPOINTS API (F25)
## Documentation technique des routes Super Admin créées & enrichies

> **Authentification requise** : `Authorization: Bearer <token_sanctum>`  
> **Acteur requis** : `user_type: 'super_admin'` ou profil possédant les permissions requises  
> **Format d'identifiant** : **Tous les identifiants `{uuid}` sont des UUIDs réels v4 (ex: `9d67d712-88f2-4e01-9c60-8f96c21e640b`).**

---

## 1. ORGANISATIONS (SUPER ADMIN)

### 1.1 Création d'organisation : Scénario A (Liée à une paroisse) et Scénario B (Indépendante)
- **Route** : `POST /api/v1/super-admin/organisations`

#### Scénario B — Organisation 100 % Indépendante (Sans paroisse, sans CATHEO) :
```json
{
  "type_organisation": "OPPJ",
  "nom": "OPPJ Communauté Saint Paul",
  "independant": true,
  "description": "Mouvement autonome de jeunesse diocésain",
  "telephone": "+225 0707070707",
  "email": "contact@oppj-stpaul.ci",
  "adresse": "Abidjan, Cocody",
  "responsable_nom": "KONAN Serge",
  "responsable_telephone": "+225 0505050505",
  "responsable_email": "serge@oppj.ci"
}
```

#### Scénario A — Organisation rattachée à une paroisse :
```json
{
  "paroisse_id": "8a72b834-0d72-466c-9418-2bf57f12e8b0",
  "type_organisation": "OPPE",
  "nom": "OPPE Saint Michel Archange",
  "telephone": "+225 0102030405",
  "email": "oppe.saintmichel@catheo.ci"
}
```
*(Supporte aussi le mode multi-select par lot : `{"paroisse_id": "...", "produits": ["OPPE", "OPPJ", "OPPA"]}`)*

- **Réponse Succès (201 Created)** :
```json
{
  "status": "success",
  "message": "Organisation indépendante créée avec succès.",
  "data": {
    "id": 12,
    "uuid": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
    "mode": "independant",
    "type_organisation": "OPPJ",
    "nom": "OPPJ Communauté Saint Paul",
    "statut": "actif"
  },
  "meta": {
    "mode": "independant",
    "created_count": 1,
    "skipped_count": 0
  }
}
```

---

### 1.2 Consultation d'une organisation (Espace d'administration complet — 9 modules)
- **Route** : `GET /api/v1/super-admin/organisations/{uuid}`
- **Réponse Succès (200 OK)** :
```json
{
  "status": "success",
  "message": "Détails de l'organisation récupérés avec succès.",
  "data": {
    "id": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
    "uuid": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
    "nom": "OPPJ Communauté Saint Paul",
    "type_organisation": "OPPJ",
    "mode": "independant",
    "informations": {
      "id": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
      "nom": "OPPJ Communauté Saint Paul",
      "code": "OPPJ-IND-COMMUNAUTE",
      "type_organisation": "OPPJ",
      "mode": "independant",
      "statut": "actif",
      "paroisse": null,
      "produit": {
        "code": "OPPJ",
        "nom": "Organisation Pastorale pour la Jeunesse"
      }
    },
    "responsable": {
      "nom": "KONAN Serge",
      "telephone": "+225 0505050505",
      "email": "serge@oppj.ci"
    },
    "utilisateurs": [],
    "statistiques": {
      "total_membres": 45,
      "total_activites": 8,
      "total_pelerinages": 2,
      "total_utilisateurs": 3,
      "total_operations": 12,
      "solde_caisse": 150000.00
    },
    "membres": [],
    "activites": [],
    "pelerinages": [],
    "caisse": {
      "operations": [],
      "solde_actuel": 150000.00
    },
    "abonnement": {
      "reference": "ABO-2026-0001",
      "produit_code": "OPPJ",
      "formule_nom": "Formule Annuelle Jeunesse",
      "statut": "actif",
      "montant": 25000.00
    }
  }
}
```

---

### 1.3 Formules tarifaires éligibles pour l'organisation
- **Route** : `GET /api/v1/super-admin/organisations/{uuid}/formules`
- **Description** : Renvoie exclusivement les formules tarifaires actives associées au produit SaaS de cette organisation (ex: formules OPPE pour OPPE, formules OPPJ pour OPPJ). Évite tout mélange avec les formules CATHEO.

---

### 1.4 Modification d'une organisation & Logo
- **Route** : `PUT /api/v1/super-admin/organisations/{uuid}` *(ou `POST` avec `_method=PUT` pour les formulaires multipart)*
- **Champs acceptés** : `nom`, `description`, `telephone`, `email`, `adresse`, `responsable_nom`, `responsable_telephone`, `responsable_email`, `logo` (fichier image max 2 Mo).

---

### 1.5 Changement de statut d'une organisation
- **Route** : `PATCH /api/v1/super-admin/organisations/{uuid}/statut`
- **Payload** : `{"statut": "suspendu"}` *(actif, suspendu, inactif)*

---

### 1.6 Suppression logique d'une organisation
- **Route** : `DELETE /api/v1/super-admin/organisations/{uuid}`
- **Action** : Soft Delete de l'organisation et transfert automatique vers la Corbeille Super Admin.

---

### 1.7 Provisionnement du Responsable d'Organisation
- **Route** : `POST /api/v1/super-admin/organisations/{uuid}/responsable`
- **Payload** :
```json
{
  "name": "KOUASSI Yves",
  "email": "yves.kouassi@catheo.ci",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "telephone": "+225 0707070707"
}
```

---

## 2. ABONNEMENTS SÉPARÉS (PAROISSES vs ORGANISATIONS)

### 2.1 Liste des abonnements Paroisses (CATHEO uniquement)
- **Route** : `GET /api/v1/super-admin/abonnements/paroisses`
- **Filtres optionnels** : `?statut=actif&paroisse_id={uuid}&per_page=15`
- **Contenu** : Uniquement les abonnements des paroisses CATHEO (`organisation_id IS NULL`).

---

### 2.2 Liste des abonnements Organisations (OPPE, OPPJ, OPPA)
- **Route** : `GET /api/v1/super-admin/abonnements/organisations`
- **Filtres optionnels** : `?statut=actif&organisation_id={uuid}&produit_id={uuid}&per_page=15`
- **Contenu** : Uniquement les abonnements des organisations (`organisation_id IS NOT NULL`).

---

### 2.3 Souscription d'une organisation à une formule
- **Route** : `POST /api/v1/super-admin/abonnements/organisations`
- **Description** : Souscrit une organisation à une formule tarifaire spécifique à son produit.
- **Règle métier stricte** : Rejette avec code 422 toute tentative d'attribuer une formule non compatible avec le produit de l'organisation.
- **Payload** :
```json
{
  "organisation_id": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
  "formule_id": "5f9e830b-3642-4f76-88a2-258043615456",
  "date_debut": "2026-10-01",
  "date_fin": "2027-09-30",
  "renouvellement_automatique": true,
  "observation": "Abonnement OPPJ annuel"
}
```

---

## 3. FORMULES TARIFAIRES FILTRÉES PAR PRODUIT

### 3.1 Liste filtrée par code produit
- **Route** : `GET /api/v1/super-admin/formules?produit=OPPE`
- **Paramètres supportés** : `produit=CATHEO|OPPE|OPPJ|OPPA` ou `produit_id={uuid}`
- **Garantie** : N'affiche jamais de formule CATHEO lorsqu'on consulte les formules OPPE/OPPJ/OPPA.

---

## 4. UTILISATEURS SUPER ADMIN

### 4.1 Liste des utilisateurs
- **Route** : `GET /api/v1/super-admin/users`
- **Filtres** : `?user_type=super_admin|paroisse_admin|organisation_user|user&paroisse_id={uuid}&organisation_id={uuid}&statut=actif|bloque`

### 4.2 Création d'un utilisateur
- **Route** : `POST /api/v1/super-admin/users`
- **Payload** :
```json
{
  "name": "Jean Dupont",
  "email": "jean.dupont@catheo.ci",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "user_type": "super_admin",
  "statut": "actif"
}
```

### 4.3 Fiche utilisateur
- **Route** : `GET /api/v1/super-admin/users/{uuid}`

### 4.4 Modification utilisateur
- **Route** : `PUT /api/v1/super-admin/users/{uuid}`

### 4.5 Activation / Suspension utilisateur
- **Route** : `PATCH /api/v1/super-admin/users/{uuid}/statut`
- **Payload** : `{"statut": "bloque"}`

### 4.6 Réinitialisation de mot de passe
- **Route** : `POST /api/v1/super-admin/users/{uuid}/reset-password`
- **Payload** : `{"password": "NewSecretPassword123!", "password_confirmation": "NewSecretPassword123!"}`

---

## 5. JOURNAL D'AUDIT CENTRALISÉ

### 5.1 Consultation des logs
- **Route** : `GET /api/v1/super-admin/audit-logs`
- **Filtres** : `?action=create|update|delete|restore|force_delete&module=Organisation|Paroisse|Corbeille&user_id={uuid}&search=SaintMichel`

### 5.2 Détail d'un log avec Ancien / Nouvel état
- **Route** : `GET /api/v1/super-admin/audit-logs/{id}`
- **Contenu** : Expose `anciennes_valeurs` et `nouvelles_valeurs` au format JSON structuré.

---

## 6. CORBEILLE CENTRALE (TRASH & SOFT DELETES)

### 6.1 Liste consolidée des éléments supprimés
- **Route** : `GET /api/v1/super-admin/trash`
- **Filtres** : `?module=Organisation|Paroisse|Membre|Activite&search=SaintMichel`

### 6.2 Aperçu d'un élément avant restauration (Recommandation C)
- **Route** : `GET /api/v1/super-admin/trash/{uuid}`
- **Réponse Succès (200 OK)** :
```json
{
  "status": "success",
  "data": {
    "uuid": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
    "nom": "OPPE Saint Michel Archange",
    "module": "Organisation",
    "date_suppression": "2026-09-24T10:15:00+00:00",
    "supprime_par": "Jean Dupont (jean@catheo.ci)",
    "dependances": {
      "membres": 15,
      "activites": 3,
      "utilisateurs": 2
    },
    "bouton_restaurer": true,
    "apercu_restauration": {
      "nom": "OPPE Saint Michel Archange",
      "module": "Organisation",
      "date": "2026-09-24T10:15:00+00:00",
      "supprime_par": "Jean Dupont (jean@catheo.ci)",
      "dependances": {
        "membres": 15,
        "activites": 3,
        "utilisateurs": 2
      },
      "bouton_restaurer": true
    }
  }
}
```

### 6.3 Restauration d'un élément (Recommandation B)
- **Route** : `POST /api/v1/super-admin/trash/{uuid}/restore`
- **Réponse Succès (200 OK)** :
```json
{
  "status": "success",
  "message": "L'élément [OPPE Saint Michel Archange] a été restauré avec succès.",
  "data": {
    "uuid": "e8df81cb-63fe-425b-ae0e-7d7211a76c8c",
    "element": "OPPE Saint Michel Archange",
    "module": "Organisation",
    "supprime_par": "Jean Dupont (jean@catheo.ci)",
    "restaure_par": "Paul Martin (paul@catheo.ci)"
  }
}
```

### 6.4 Suppression définitive protégée (Force Delete)
- **Route** : `DELETE /api/v1/super-admin/trash/{uuid}/force`
- **Règle de sécurité** : Rejette systématiquement avec code 422 si l'élément possède encore des entités actives dépendantes.
