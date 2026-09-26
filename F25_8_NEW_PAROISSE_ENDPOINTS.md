# F25.8 — Nouveaux Endpoints Paroisses & Spécifications API
=============================================================================

> **Version API :** v1 (Super Admin)  
> **Date :** 24 Septembre 2026  
> **Auteur :** Antigravity Engine  
> **Statut :** Validé & Testé (100% succès)

---

## 1. Création Autonome d'une Paroisse

Permet au Super Admin d'enregistrer une paroisse directement sans dépendre d'un abonnement préalable ni d'un compte de catéchèse.

- **Méthode :** `POST`
- **URI :** `/api/v1/super-admin/paroisses`
- **Middleware :** `auth:sanctum`, `role:super-admin`
- **Table cible :** `paroisse_configurations`

### Paramètres de la Requête (JSON ou `multipart/form-data`)

| Champ | Type | Requis | Description / Règle métier |
|---|---|:---:|---|
| `nom_paroisse` | `string` | **Oui** | Nom complet de la paroisse (max 255 car.) |
| `code_paroisse` | `string` | **Oui** | Code unique en majuscules (ex: `STMIC`, `STPAUL`), regex: `^[A-Z0-9]+$` |
| `diocese` | `string` | **Oui** | Diocèse de rattachement (max 150 car.) |
| `doyenne` | `string` | Non | Doyenné pastoral (max 150 car.) |
| `ville` | `string` | Non | Ville de la paroisse (max 100 car.) |
| `commune` | `string` | Non | Commune de la paroisse (max 100 car.) |
| `telephone` | `string` | Non | Numéro de téléphone principal (max 30 car.) |
| `email` | `string` | Non | Adresse email institutionnelle de la paroisse |
| `adresse` | `string` | Non | Adresse géographique détaillée |
| `cure_nom` | `string` | Non | Nom complet du Père Curé |
| `coordination_nom` | `string` | Non | Nom du coordinateur ou bureau pastoral |
| `site_web` | `string` | Non | URL du site web de la paroisse |
| `logo_paroisse` | `file` | Non | Image (png, jpg, webp), max 2 Mo |
| `logo_catechese` | `file` | Non | Image (png, jpg, webp), max 2 Mo |

### Valeurs générées automatiquement par le Backend

| Champ | Valeur générée |
|---|---|
| `uuid` | UUID v4 automatique via modèle |
| `prefixe_matricule` | Dérivé du `code_paroisse` (ex: `STMIC`) |
| `prefixe_recu` | Dérivé du `code_paroisse` (ex: `STMIC-R`) |
| `statut` | Initialisé à `"cree"` |
| `created_by` | ID de l'utilisateur Super Admin authentifié |

### Exemple de Requête
```json
{
  "nom_paroisse": "Paroisse Saint Michel Archange",
  "code_paroisse": "STMIC",
  "diocese": "Abidjan",
  "doyenne": "Cocody",
  "ville": "Abidjan",
  "commune": "Cocody",
  "telephone": "+2250102030405",
  "email": "saintmichel@catheo.ci",
  "adresse": "Angré 8ème Tranche"
}
```

### Exemple de Réponse (HTTP 201 Created)
```json
{
  "status": "success",
  "message": "Paroisse créée avec succès.",
  "data": {
    "uuid": "4c43ba4c-1e6a-464a-95b6-79ec9ad4eb68",
    "nom_paroisse": "Paroisse Saint Michel Archange",
    "code_paroisse": "STMIC",
    "diocese": "Abidjan",
    "doyenne": "Cocody",
    "ville": "Abidjan",
    "commune": "Cocody",
    "telephone": "+2250102030405",
    "email": "saintmichel@catheo.ci",
    "statut": "cree",
    "prefixe_matricule": "STMIC",
    "prefixe_recu": "STMIC-R",
    "created_at": "2026-09-24T14:20:00.000000Z"
  }
}
```

---

## 2. Mise à Jour d'une Paroisse

- **Méthode :** `PUT` / `POST` (avec `_method=PUT` pour les uploads)
- **URI :** `/api/v1/super-admin/paroisses/{id}`

Permet la modification complète des métadonnées paroissiales, contacts et logos avec journalisation d'audit automatique.

---

## 3. Liste et Supervision des Paroisses

- **Méthode :** `GET`
- **URI :** `/api/v1/super-admin/paroisses`

La nouvelle paroisse y apparaît immédiatement dès sa création avec le statut `"cree"` ou `"actif"`, le décompte de ses organisations rattachées (`organisations_count`), et les détails de l'abonnement CATHEO.

---

## 4. Suppression Sécurisée (SoftDelete)

- **Méthode :** `DELETE`
- **URI :** `/api/v1/super-admin/paroisses/{id}`

**Règle d'intégrité :** Le backend empêche la suppression d'une paroisse si des organisations actives lui sont rattachées (HTTP 422 avec message explicite).